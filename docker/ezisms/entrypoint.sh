#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# entrypoint.sh — draait bij ELKE start van de app-container
#
# Kort en dom. De volgorde is wat telt:
#
#   0. blokkade      staat er een BLOKKADE, dan starten we niets (00n §9.7)
#   0b. demoprofiel  ISMS_DEMO=ja zet APP_ENV en 2FA in één keer goed (00n §0.2)
#   1. mappen        het skelet op de bind mount aanleggen
#   2. eigendom      chown, idempotent, ongeacht welke uid de host gebruikt
#   3. wachten op db PDO-lus (depends_on is niet genoeg)
#   4. APP_KEY       lezen uit installatie/app_key, of eenmalig genereren
#   5. config:cache  HIER, ná stap 4 — config/app.php leest APP_KEY
#   6. uitrol        scripts/deploy-docker.sh, met de pogingenteller eromheen
#   7. exec "$@"     supervisord wordt PID 1
#
# Zie implementatie/00l-docker-stack.md §8 en 00n-docker-deploy.md §9.7–§9.8.
# ─────────────────────────────────────────────────────────────────────────────

set -Eeuo pipefail

APP=/opt/ezisms
STATE=/var/lib/ezisms
EXPORT=/var/tmp/isms_export
PHP=/usr/bin/php

meld()      { printf '\033[1m[entrypoint]\033[0m %s\n' "$*"; }
goed()      { printf '\033[32m[entrypoint]\033[0m %s\n' "$*"; }
waarschuw() { printf '\033[33m[entrypoint] let op:\033[0m %s\n' "$*" >&2; }
fout()      { printf '\033[31m[entrypoint] FOUT:\033[0m %s\n' "$*" >&2; exit 1; }

# Stoppen zónder af te sluiten. `restart: unless-stopped` zou een exit meteen
# opnieuw proberen, en bij een fout die met opnieuw proberen niet weggaat — een
# verkeerde APP_KEY, een verkeerd normprofiel — levert dat een herstartlus op
# waarin de melding voorbijraast. Blijft het proces in leven, dan staat de
# melding stil, blijft `docker compose exec` werken en meldt de healthcheck op
# /up gewoon `unhealthy`: geen valse gezondheid (00n §9.7).
#
# `sleep infinity` en geen `read`: er is geen stdin in een container.
blokkeer_en_wacht() {
    printf '\n'
    printf '\033[31m[entrypoint] GEBLOKKEERD\033[0m — de applicatie is NIET gestart.\n' >&2
    printf '%s\n' "$*" >&2
    printf '\n             Onderzoeken kan met:  docker compose exec app bash\n\n' >&2
    exec sleep infinity
}

# Alles wat de applicatie zelf is — artisan, de scheduler, php-fpm — draait als
# www-data. Die gebruiker heeft geen bruikbare thuismap (/var/www is van root),
# en gereedschap dat in $HOME wil schrijven loopt daar stuk: psysh, de shell
# onder `artisan tinker`, weigert met "Writing to directory /var/www/.config/psysh
# is not allowed". Daarom een wegwerpthuismap. De XDG-variabelen staan er
# nadrukkelijk bij: psysh kijkt eerst daarnaar en pas daarna naar $HOME.
#
# GEDEELD MET deploy.sh :: als_app/artisan (:235–:258).
APPHOME=$(mktemp -d /tmp/ezisms-home.XXXXXXXX)
chown www-data:www-data "$APPHOME"
chmod 0700 "$APPHOME"
trap 'rm -rf "$APPHOME"' EXIT

als_app() {
    runuser -u www-data -- env \
        HOME="$APPHOME" \
        XDG_CONFIG_HOME="$APPHOME/.config" \
        XDG_DATA_HOME="$APPHOME/.local/share" \
        XDG_CACHE_HOME="$APPHOME/.cache" \
        "$@"
}

# artisan draait altijd als www-data en altijd in de applicatieboom. Zou root
# dit doen, dan staat er root-eigendom in storage/ en is de installatie stuk op
# precies de plek waar niemand kijkt.
artisan() { ( cd "$APP" && als_app "$PHP" artisan "$@" ); }

# Eén waarde uit MANIFEST.json. PHP is de JSON-lezer; python3 staat niet in dit
# image en PHP per definitie wel.
# GEDEELD MET deploy.sh :: manifest_waarde (:194).
manifest_waarde() { # <sleutel>
    MANIFEST_PAD="$APP/MANIFEST.json" MANIFEST_SLEUTEL="$1" "$PHP" -r '
        $data = json_decode(file_get_contents(getenv("MANIFEST_PAD")), true);
        $waarde = $data[getenv("MANIFEST_SLEUTEL")] ?? "";
        echo is_array($waarde) ? implode("\n", $waarde) : $waarde;
    '
}

printf '\n'
meld "EzISMS $(manifest_waarde versie) ($(manifest_waarde commit | cut -c1-7))"

BLOKKADE="$STATE/installatie/BLOKKADE"
POGINGEN="$STATE/installatie/init-pogingen"

# ═════════════════════════════════════════════════════════════════════════════
#  0. Blokkade
# ═════════════════════════════════════════════════════════════════════════════
# Vóór alles, want een geblokkeerde installatie mag geen chown, geen migratie en
# geen dump meer krijgen. Het bestand wordt door stap 6 geschreven en gaat er
# alleen met de hand weer af (00n §9.7).

if [[ -f $BLOKKADE ]]; then
    printf '\n\033[31m[entrypoint] GEBLOKKEERD\033[0m — de vorige uitrol is herhaald mislukt.\n' >&2
    sed 's/^/             /' "$BLOKKADE" >&2
    cat >&2 <<EOF

             De applicatie is NIET gestart. Onderzoeken kan met:
                 docker compose exec app bash

             Opheffen na herstel — met sudo, want het bestand is van de
             gebruiker uit de container en niet van uw eigen account:
                 sudo rm ./data/app/installatie/BLOKKADE
                 docker compose restart app

EOF
    exec sleep infinity
fi

# ═════════════════════════════════════════════════════════════════════════════
#  0b. Het demoprofiel
# ═════════════════════════════════════════════════════════════════════════════
# `isms:demo-vul` stelt drie eisen tegelijk — APP_ENV is local of demo, het
# profiel is iso27001, en het commando wist eerst de hele database — en in een
# demo wil je bovendien geen 2FA-koppeling voor elk wegwerpaccount. Vier
# instellingen die alleen in combinatie kloppen, dus één schakelaar die ze alle
# vier zet (00n §0.2). Eén knop die niet half om kan staan.
#
# Hier en niet in deploy-docker.sh: APP_ENV moet in de omgeving staan vóór stap
# 5, anders bakt config:cache 'production' in en weigert het commando alsnog.
#
# De weigering op een verkeerd profiel zit hier ook, en dus vóór de migratie:
# ISMS_DEMO=ja met een ander profiel dan iso27001 stopt met een leesbare melding
# in plaats van straks halverwege een gevulde database (00n §9.4).

if [[ ${ISMS_DEMO:-nee} == ja ]]; then
    if [[ ${ISMS_NORM:-} != iso27001 ]]; then
        blokkeer_en_wacht "             ISMS_DEMO=ja gaat niet samen met ISMS_NORM=${ISMS_NORM:-<leeg>}.
             Het FruitBV-demoscenario is geschreven voor de controlset van
             ISO 27001; onder een ander normprofiel toont het de verkeerde.

             Kies er één: ISMS_DEMO=nee, of ISMS_NORM=iso27001.
             Daarna:  docker compose up -d"
    fi
    export APP_ENV=demo
    export ISMS_2FA_AFDWINGEN=false
    waarschuw "ISMS_DEMO=ja — APP_ENV=demo en 2FA uit. Geen opstelling voor echte gegevens."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  1. Mappen
# ═════════════════════════════════════════════════════════════════════════════
# Het storage-skelet komt uit het pakket, zodat het meebeweegt met de app. De
# mount is bij een eerste start leeg; hierna staat de indeling er die deploy.sh
# in shared/ aanlegt.
# GEDEELD MET deploy.sh :: maak_shared (:763).

mkdir -p "$STATE/seeddata" "$STATE/installatie"
while read -r map; do
    [[ -z $map ]] && continue
    mkdir -p "$STATE/$map"
done <<<"$(manifest_waarde storage_skelet)"

# De uitvoermap van isms:exporteer. Aanmaken kan geen kwaad als de mount er niet
# is — het wordt dan een gewone map in de container — maar de gebruiker moet dat
# wél weten, want dan verdwijnt zijn export bij de eerstvolgende `up -d` zonder
# dat er iets misgaat. Dat is precies de stille terugval die de HTTP_POORT-fout
# ook opleverde, dus staat hier een waarschuwing in plaats van een aanname.
#
# mountinfo en niet `mountpoint`: dat commando zit in util-linux en dat is in
# dit image niet gegarandeerd aanwezig. Het veld met het pad is het vijfde.
mkdir -p "$EXPORT"
if ! awk -v p="$EXPORT" '$5 == p { gevonden = 1 } END { exit !gevonden }' /proc/self/mountinfo; then
    waarschuw "$EXPORT is geen mount — een export blijft binnen de container en verdwijnt bij de eerstvolgende 'up -d'.
             Kopieer compose.yml opnieuw uit de nieuwe boom; daar staat de regel
             ISMS_DATA/isms_export bij de volumes van app."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  2. Eigendom
# ═════════════════════════════════════════════════════════════════════════════
# Idempotent, elke start. Op een bind mount draagt de host zijn eigen uid's; dit
# zet ze goed ongeacht welke dat zijn.
#
# storage is van www-data — daar schrijft de applicatie in, inclusief de
# toetsbestanden die sinds 01e in storage/app/private/toetsen staan.
# seeddata en installatie zijn van ezisms: de beheerder zet daar iets neer en
# het webproces heeft er niets te schrijven.

chown -R www-data:www-data "$STATE/storage"
chown -R ezisms:ezisms     "$STATE/seeddata" "$STATE/installatie"
chmod 0755 "$STATE/storage" "$STATE/seeddata" "$STATE/installatie"

# De exportmap: wél chown, géén -R. Docker maakt een ontbrekend aanhechtpunt aan
# als root, en dan faalt de eerste export op ensureDirectoryExists() — alleen dit
# aanhechtpunt telt voor het schrijven. De boom eronder blijft met rust: een
# export met --met-bewijs draagt alle bewijsstukken en die elke start opnieuw
# doorlopen kost tijd zonder iets op te lossen.
chown www-data:www-data "$EXPORT"
# 0750 en niet 0755: hier komt de volledige inhoud van het ISMS te staan. De
# beheerder op de host leest hem met sudo, zoals bij de rest van data/.
chmod 0750 "$EXPORT"

# ═════════════════════════════════════════════════════════════════════════════
#  3. Wachten op de database
# ═════════════════════════════════════════════════════════════════════════════
# depends_on: service_healthy is niet genoeg. "healthy" betekent dat mysqld
# antwoordt, niet dat de applicatiegebruiker kan inloggen op de juiste database —
# die wordt door het mysql-image pas ná de eerste start aangemaakt.

wacht_op_db() {
    local poging=0
    until DB_H="${DB_HOST:-db}" DB_P="${DB_PORT:-3306}" DB_D="${DB_DATABASE:-ezisms}" \
          DB_U="${DB_USERNAME:-ezisms}" DB_W="${DB_PASSWORD:-}" "$PHP" -r '
              $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s",
                  getenv("DB_H"), getenv("DB_P"), getenv("DB_D"));
              new PDO($dsn, getenv("DB_U"), getenv("DB_W"),
                  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
          ' 2>/dev/null
    do
        (( ++poging <= 60 )) || fout "de database antwoordt na 60 seconden nog niet"
        (( poging % 10 )) || meld "wachten op de database ($poging s)"
        sleep 1
    done
    goed "database bereikbaar: ${DB_DATABASE:-ezisms} op ${DB_HOST:-db}"
}
wacht_op_db

# ═════════════════════════════════════════════════════════════════════════════
#  4. APP_KEY
# ═════════════════════════════════════════════════════════════════════════════
# Fortify slaat two_factor_secret en de recovery codes versleuteld op met deze
# sleutel. Een container die er bij elke herstart een nieuwe genereert sluit
# IEDEREEN permanent buiten. Dus: één keer genereren, bewaren op de bind mount,
# en daarna uitsluitend lezen.
#
# De mount overleeft `docker compose down -v` en een volledige rebuild van het
# image, dus de sleutel ook.

SLEUTELPAD="$STATE/installatie/app_key"

if [[ -s $SLEUTELPAD ]]; then
    BEWAARDE=$(<"$SLEUTELPAD")
    if [[ -n ${APP_KEY:-} && $APP_KEY != "$BEWAARDE" ]]; then
        # Wachten en niet afsluiten: opnieuw proberen kan dit nooit oplossen, en
        # met `restart: unless-stopped` zou een exit hier een herstartlus geven
        # waarin juist deze melding voorbijraast. Geen BLOKKADE-bestand: de
        # oorzaak staat in .env, niet in de installatie, dus zodra .env klopt en
        # `docker compose up -d` de container vervangt is het over — er valt
        # niets met de hand op te ruimen.
        blokkeer_en_wacht "             De APP_KEY in uw .env wijkt af van de bewaarde sleutel in
             $SLEUTELPAD

             Doorstarten met de verkeerde sleutel maakt elk versleuteld gegeven
             onleesbaar — onder meer de 2FA-geheimen van alle gebruikers.

             Haal APP_KEY uit .env weg, of herstel de bewaarde sleutel.
             Daarna:  docker compose up -d"
    fi
    export APP_KEY="$BEWAARDE"
    meld "APP_KEY gelezen uit installatie/app_key"
else
    if [[ -n ${APP_KEY:-} ]]; then
        # Een meegegeven sleutel bij een verse installatie mag: dat is het geval
        # "ik herstel een back-up en heb de oude sleutel nog".
        printf '%s' "$APP_KEY" >"$SLEUTELPAD"
        meld "APP_KEY uit de omgeving overgenomen en bewaard"
    else
        NIEUW="base64:$(head -c 32 /dev/urandom | base64)"
        printf '%s' "$NIEUW" >"$SLEUTELPAD"
        export APP_KEY="$NIEUW"
        goed "APP_KEY gegenereerd en bewaard in installatie/app_key"
    fi
    chown ezisms:ezisms "$SLEUTELPAD"
    chmod 0600 "$SLEUTELPAD"
fi

# ═════════════════════════════════════════════════════════════════════════════
#  4b. Het versienummer
# ═════════════════════════════════════════════════════════════════════════════
# APP_VERSION komt achter de productnaam in de zijbalk te staan. Hij mag in .env
# gezet worden, maar hoeft niet — en dat is met opzet: die sleutel gaat bij een
# upgrade niet vanzelf mee met ISMS_BOOM, en een scherm dat de vórige versie
# noemt is misleidender dan een scherm dat er geen noemt.
#
# Laat .env hem leeg, dan komt hij uit het manifest van de boom die draait. Dat
# is per definitie de waarheid over deze installatie.
#
# Hier en niet in deploy-docker.sh: de waarde moet in de omgeving staan vóór
# stap 5, anders bakt config:cache de lege waarde in — precies de fout die
# APP_ENV bij het demoprofiel ook al maakte (00n §0.2).
#
# `export` volstaat: als_app geeft de omgeving door aan `runuser`, en die laat
# hem staan. Getoetst, want zoiets hoor je niet aan te nemen.

if [[ -z ${APP_VERSION:-} ]]; then
    VERSIE_UIT_MANIFEST=$(manifest_waarde tag)
    # Geen tag (een tussentijdse bouw): dan de releaseversie uit de tarbalnaam.
    # Minder mooi dan `V2.5.0`, maar nog altijd een nummer dat bij déze boom
    # hoort.
    [[ -n $VERSIE_UIT_MANIFEST ]] || VERSIE_UIT_MANIFEST=$(manifest_waarde versie)

    if [[ -n $VERSIE_UIT_MANIFEST ]]; then
        export APP_VERSION="$VERSIE_UIT_MANIFEST"
        meld "versie uit het manifest: $APP_VERSION"
    fi
elif [[ -n $(manifest_waarde tag) && $APP_VERSION != $(manifest_waarde tag) ]]; then
    # Wel gezet, maar niet wat er draait. Niet stilzwijgend overschrijven — het
    # is een bewuste invoer van de beheerder — maar wél zeggen, want dit is
    # precies het geval waarin het scherm gaat liegen.
    waarschuw "APP_VERSION in .env ($APP_VERSION) is niet de tag van deze boom ($(manifest_waarde tag)); de zijbalk toont uw eigen waarde."
fi

# ═════════════════════════════════════════════════════════════════════════════
#  5. Configuratie cachen
# ═════════════════════════════════════════════════════════════════════════════
# Hier, ná stap 4, want config/app.php leest APP_KEY. En bij ELKE start, niet
# alleen de eerste: Laravel slaat het lezen van .env over zodra de configuratie
# gecached is, dus een gewijzigde .env in de compose-map wordt alleen zo
# opgepikt. Daarom staat bootstrap/cache container-lokaal en nooit op de mount.

meld "configuratie cachen"
artisan config:clear >/dev/null
artisan config:cache >/dev/null
artisan route:cache  >/dev/null
artisan view:cache   >/dev/null
goed "config, routes en views gecached"

# ═════════════════════════════════════════════════════════════════════════════
#  6. De uitrol
# ═════════════════════════════════════════════════════════════════════════════
# Alles wat er te beslissen valt — verse of bestaande database, normcontrole,
# dump, seeddata, eerste CISO, demo — staat in scripts/deploy-docker.sh. Dit
# blok doet er niets anders omheen dan tellen hoe vaak het misging.
#
# Waarom tellen: met `restart: unless-stopped` zou een falende migratie elke paar
# seconden opnieuw worden geprobeerd, en omdat élke poging op een bestaande
# database eerst een volledige dump maakt, stapelen die zich dan op terwijl de
# logs vollopen. Na ISMS_MIGRATIE_POGINGEN mislukkingen valt de installatie
# daarom stil in plaats van door te blijven proberen (00n §9.7).
#
# De teller staat op de mount en niet in de container: hij moet juist de
# herstart overleven die hij wil begrenzen.

MAXPOGINGEN=${ISMS_MIGRATIE_POGINGEN:-3}
[[ $MAXPOGINGEN =~ ^[0-9]+$ && $MAXPOGINGEN -ge 1 ]] \
    || fout "ISMS_MIGRATIE_POGINGEN moet een geheel getal van 1 of hoger zijn (nu: $MAXPOGINGEN)"

if "$APP/scripts/deploy-docker.sh"; then
    # 6a. Geslaagd — de teller mag weg. Anders zou een installatie die ooit
    #     tweemaal struikelde bij de eerstvolgende hapering meteen blokkeren.
    rm -f "$POGINGEN"
else
    UITROLCODE=$?
    POGING=$(( $(cat "$POGINGEN" 2>/dev/null || echo 0) + 1 ))
    printf '%s' "$POGING" >"$POGINGEN"
    chown ezisms:ezisms "$POGINGEN"; chmod 0640 "$POGINGEN"

    LAATSTE_LOG=$(ls -1t "$STATE/installatie"/deploy-*.log 2>/dev/null | head -1 || true)
    LAATSTE_DUMP=$(ls -1t "$STATE/installatie"/dump-*.sql.gz 2>/dev/null | head -1 || true)

    if (( POGING < MAXPOGINGEN )); then
        waarschuw "de uitrol mislukte (poging $POGING van $MAXPOGINGEN, exitcode $UITROLCODE)"
        waarschuw "de container sluit af en wordt door Docker opnieuw gestart"
        exit "$UITROLCODE"
    fi

    cat >"$BLOKKADE" <<EOF
De uitrol is ${POGING}× mislukt en wordt niet opnieuw geprobeerd.
geblokkeerd op: $(date -Iseconds)
reden:  scripts/deploy-docker.sh gaf exitcode $UITROLCODE
dump:   ${LAATSTE_DUMP:-<geen dump gemaakt>}
log:    ${LAATSTE_LOG:-<geen log>}
EOF
    chown ezisms:ezisms "$BLOKKADE"; chmod 0640 "$BLOKKADE"

    printf '\n\033[31m[entrypoint] GEBLOKKEERD\033[0m\n' >&2
    sed 's/^/             /' "$BLOKKADE" >&2
    cat >&2 <<EOF

             De applicatie is NIET gestart. Onderzoeken kan met:
                 docker compose exec app bash

             Opheffen na herstel — met sudo, want het bestand is van de
             gebruiker uit de container en niet van uw eigen account:
                 sudo rm ./data/app/installatie/BLOKKADE
                 docker compose restart app

EOF
    exec sleep infinity
fi

# ═════════════════════════════════════════════════════════════════════════════
#  7. Overdragen aan supervisord
# ═════════════════════════════════════════════════════════════════════════════
# exec, geen achtergrondproces: supervisord wordt PID 1, ruimt zombies op, en de
# signaalafhandeling van Docker werkt zoals bedoeld.

meld "starten: nginx, php-fpm, scheduler"
exec "$@"
