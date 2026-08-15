#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy-docker.sh — de uitrollogica ín de applicatiecontainer
#
#   /opt/ezisms/scripts/deploy-docker.sh
#
# Wordt aangeroepen door docker/entrypoint.sh (stap 6), bij ELKE start van de
# container. Niet met de hand starten tenzij u onderzoekt wat er misging; het
# script neemt aan dat de entrypoint stap 1 t/m 5 al gedaan heeft (mappen,
# eigendom, database bereikbaar, APP_KEY, gecachede configuratie).
#
# LEESWIJZER VOOR DE BEHEERDER
#
#   Dit is de Docker-tegenhanger van scripts/deploy.sh. Wat daar de host doet,
#   doet hier het image; wat daar uit .env komt, komt hier uit de omgeving van
#   het containerproces. Wat blijft is de kern: één controlset per installatie,
#   een dump vóór elke migratie, en een normprofiel dat op drie plekken
#   hetzelfde moet zeggen.
#
#   Twee accounts, dezelfde taakverdeling als op bare metal:
#     ezisms    bezit de code en draait niets.
#     www-data  draait php-fpm, de scheduler en artisan, en bezit alles waar de
#               applicatie in schrijft.
#
#   Twee situaties, en het script leidt zelf af welke van de twee het is —
#   uit de database, niet uit een markerbestand (00n §0.1):
#     * 0 tabellen   ⇒ verse installatie
#     * >0 tabellen  ⇒ bestaande installatie, dit is een bijwerking
#
#   Wat dit script NIET doet: een container bouwen, de webserver instellen,
#   het normprofiel wijzigen, normtekst genereren, dumps opruimen of een
#   migratie terugdraaien. Terugrollen is ISMS_BOOM omzetten (00l §11.4).
#
#   Achtergrond en afwegingen: implementatie/00n-docker-deploy.md.
# ─────────────────────────────────────────────────────────────────────────────

# -E is hier geen sierletter: zonder errtrace erven functies de ERR-trap niet,
# en dan breekt het script binnen een functie stil af — geen melding, geen
# regelnummer, alleen een exitcode waar de entrypoint een blokkade op zet.
set -Eeuo pipefail

DEPLOY_DOCKER_VERSIE="1.0"

# ── Vaste paden ──────────────────────────────────────────────────────────────
# Geen instelbare varianten zoals in deploy.sh: het image bepaalt deze paden en
# de beheerder kan er niets aan veranderen. Dat is de winst van de container.
APP=/opt/ezisms
STATE=/var/lib/ezisms
PHP=/usr/bin/php

GEBRUIKER=ezisms
GROEP=ezisms
WEBGEBRUIKER=www-data
WEBGROEP=www-data

INSTALLATIE="$STATE/installatie"
SEEDBRON="$STATE/seeddata"
SEEDDOEL="$APP/database/seeders/data"

# ═════════════════════════════════════════════════════════════════════════════
#  Meldingen en logboek
# ═════════════════════════════════════════════════════════════════════════════

stap()      { printf '\n\033[1m== %s\033[0m\n' "$*"; }
meld()      { printf '   %s\n' "$*"; }
goed()      { printf '\033[32m   ✓ %s\033[0m\n' "$*"; }
waarschuw() { printf '\033[33m   ! %s\033[0m\n' "$*"; }

# Eén melding per fout. Breekt een commando af binnen een $(...), dan slaat de
# ERR-trap twee keer toe: eenmaal in de subshell en daarna nog eens op de
# mislukte toewijzing eromheen. Een variabele helpt niet — de subshell kan die
# van de ouder niet zetten — dus een bestand.
#
# GEEN BACKTICKS IN DE MELDING. Deze meldingen zijn meerregelige, dubbel
# aangehaalde teksten die vaak een commando noemen, en `zo aangehaald` voert de
# shell dat commando gewoon uit. Dat is één keer echt gebeurd: de melding van
# normcontrole noemde `docker compose up -d`, waarop de container "docker:
# command not found" toonde en de ERR-trap er "onverwachte fout op regel 379"
# van maakte — precies op het moment dat de gebruiker de uitleg nodig had.
# Schrijf het commando zonder aanhaling, of ontsnap de backticks met \`.
# GEDEELD MET deploy.sh :: fout().
FOUTVLAG=""
fout() {
    if [[ -n ${FOUTVLAG:-} ]]; then
        [[ -e $FOUTVLAG ]] && exit 1        # al gemeld, dieper in de aanroepketen
        : >"$FOUTVLAG"
    fi
    printf '\033[31m\nFOUT: %s\033[0m\n' "$*" >&2
    exit 1
}

# ═════════════════════════════════════════════════════════════════════════════
#  Kleine hulpmiddelen
# ═════════════════════════════════════════════════════════════════════════════

# ── GEDEELD MET deploy.sh ────────────────────────────────────────────────────
# Wijzig je hier iets, wijzig het dan ook in scripts/deploy.sh :: env_waarde().
# Reden om te dupliceren in plaats van te delen: deploy.sh draait op een vreemde
# host waar een gedeeld bestand pas bestaat ná het uitpakken — kip en ei, zie de
# kop van distr-gemeenschappelijk.sh. Voor de Docker-route geldt dat niet, maar
# één script herschrijven om het andere te kunnen delen is nu niet de prijs waard.
# Zie 00n §12 voor de volledige lijst van gedupliceerde blokken.
#
# Eén waarde uit een key=value-bestand. Bewust geen `source`: zo'n bestand is
# data en geen shellscript, en `source` voert uit wat erin staat. Hier alleen
# nog gebruikt voor de normstempel — de rest komt uit de omgeving (00n §9.1).
env_waarde() { # <bestand> <sleutel> [standaard]
    local regel waarde
    regel=$(grep -E "^[[:space:]]*$2[[:space:]]*=" "$1" 2>/dev/null | tail -n1) || true
    if [[ -z ${regel:-} ]]; then printf '%s' "${3-}"; return; fi
    waarde=${regel#*=}
    waarde=${waarde%$'\r'}
    waarde=$(printf '%s' "$waarde" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
    if [[ ${#waarde} -ge 2 && ( $waarde == \"*\" || $waarde == \'*\' ) ]]; then
        waarde=${waarde:1:${#waarde}-2}
    fi
    printf '%s' "$waarde"
}

# Eén waarde uit MANIFEST.json. PHP is de JSON-lezer; python3 staat niet in dit
# image en PHP per definitie wel.
# GEDEELD MET deploy.sh :: manifest_waarde(), en met docker/entrypoint.sh.
manifest_waarde() { # <sleutel>
    MANIFEST_PAD="$APP/MANIFEST.json" MANIFEST_SLEUTEL="$1" "$PHP" -r '
        $data = json_decode(file_get_contents(getenv("MANIFEST_PAD")), true);
        $waarde = $data[getenv("MANIFEST_SLEUTEL")] ?? "";
        echo is_array($waarde) ? implode("\n", $waarde) : $waarde;
    '
}

# ── GEDEELD MET deploy.sh :: db_query() ──────────────────────────────────────
# Een query op de applicatiedatabase, via PDO. De gegevens gaan via de omgeving
# en niet via de commandoregel: argumenten zijn met `ps` voor iedereen leesbaar,
# de omgeving van een proces alleen voor de eigenaar en root.
db_query() { # <sql> → rijen, kolommen gescheiden door een tab
    SQL="$1" DB_H="$DB_HOST" DB_P="$DB_PORT" DB_D="$DB_DATABASE" \
    DB_U="$DB_USERNAME" DB_W="$DB_PASSWORD" "$PHP" -r '
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s",
            getenv("DB_H"), getenv("DB_P"), getenv("DB_D"));
        try {
            $pdo = new PDO($dsn, getenv("DB_U"), getenv("DB_W"),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach ($pdo->query(getenv("SQL"))->fetchAll(PDO::FETCH_NUM) as $rij) {
                echo implode("\t", array_map(fn ($v) => $v ?? "", $rij)), "\n";
            }
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(2);
        }
    '
}

# ── GEDEELD MET deploy.sh :: maak_apphome/als_app/artisan ────────────────────
# www-data heeft geen bruikbare thuismap (/var/www is van root), en gereedschap
# dat in $HOME wil schrijven loopt daar stuk: psysh, de shell onder
# `artisan tinker`, weigert met "Writing to directory /var/www/.config/psysh is
# not allowed". Daarom een wegwerpthuismap. De XDG-variabelen staan er
# nadrukkelijk bij: psysh kijkt eerst daarnaar en pas daarna naar $HOME.
#
# Een eigen exemplaar en niet die van de entrypoint: dit script moet ook met de
# hand te starten zijn om een mislukte uitrol te onderzoeken.
APPHOME=""

maak_apphome() {
    APPHOME=$(mktemp -d /tmp/ezisms-deploy-home.XXXXXXXX)
    chown "$WEBGEBRUIKER:$WEBGROEP" "$APPHOME"
    chmod 0700 "$APPHOME"
}

als_app() {
    [[ -n $APPHOME ]] || fout "interne fout: de wegwerpthuismap is niet aangemaakt"
    runuser -u "$WEBGEBRUIKER" -- env \
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

# ═════════════════════════════════════════════════════════════════════════════
#  Opruimen
# ═════════════════════════════════════════════════════════════════════════════
# Geen onderhoudsmodus, geen terugdraaiende symlink: er is niets omgeschakeld
# zolang dit script draait, want nginx start pas ná ons (00l §8.1 stap 7).

FOUTVLAGMAP=""
LOGBESTAND=""

opruimen() {
    local code=$?
    trap - EXIT
    if [[ -n $LOGBESTAND && -f $LOGBESTAND ]]; then
        chown "$GEBRUIKER:$GROEP" "$LOGBESTAND" 2>/dev/null || true
        chmod 0640 "$LOGBESTAND" 2>/dev/null || true
    fi
    # In de wegwerpthuismap staat niets dat iets verklaart, alleen wat psysh
    # voor zichzelf heeft neergezet.
    [[ -n $APPHOME     && -d $APPHOME     ]] && rm -rf "$APPHOME"
    [[ -n $FOUTVLAGMAP && -d $FOUTVLAGMAP ]] && rm -rf "$FOUTVLAGMAP"
    exit "$code"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Controles vooraf
# ═════════════════════════════════════════════════════════════════════════════

controleer_root() {
    [[ $EUID -eq 0 ]] || fout "dit script moet als root draaien (eigendom zetten, als www-data draaien)"
}

controleer_gereedschap() {
    stap "Gereedschap in dit image"
    local cmd ontbreekt=()
    # Uitgedund ten opzichte van deploy.sh: geen systemctl, crontab, useradd of
    # rsync. Systemd bestaat hier niet, de accounts komen uit de build en er
    # wordt niets gekopieerd — de code zit in het image (00n §9.1).
    for cmd in runuser find mysqldump gzip zcat; do
        command -v "$cmd" >/dev/null || ontbreekt+=("$cmd")
    done
    [[ ${#ontbreekt[@]} -eq 0 ]] || fout "ontbrekende commando's in het image: ${ontbreekt[*]}
Dit is een bouwfout, geen instelling: vul docker/Dockerfile aan."
    goed "alle benodigde commando's aanwezig"
}

# Configuratie komt uit de procesomgeving, niet uit een .env-bestand (00l §0.4).
# Deze functie leest hem één keer uit en controleert wat niet fout mag zijn.
lees_omgeving() {
    stap "Instellingen uit de omgeving van deze container"

    NORM_ENV=${ISMS_NORM:-}
    APP_URL=${APP_URL:-}
    APP_ENV_NU=${APP_ENV:-production}
    DEMO=${ISMS_DEMO:-nee}

    DB_HOST=${DB_HOST:-db}
    DB_PORT=${DB_PORT:-3306}
    DB_DATABASE=${DB_DATABASE:-}
    DB_USERNAME=${DB_USERNAME:-}
    DB_PASSWORD=${DB_PASSWORD:-}

    [[ -n $DB_DATABASE ]] || fout "DB_DATABASE staat niet in de omgeving van deze container"
    [[ -n $NORM_ENV ]] \
        || fout "ISMS_NORM is leeg. Er is geen standaardwaarde: een stilzwijgende terugval
op ISO 27001 zou een zorginstelling de verkeerde controlset geven. Zet
ISMS_NORM=iso27001 of ISMS_NORM=nen7510 in .env en doe: docker compose up -d"
    [[ $NORM_ENV == iso27001 || $NORM_ENV == nen7510 ]] \
        || fout "ISMS_NORM='$NORM_ENV' is geen bekend profiel (iso27001 of nen7510)"
    [[ $DEMO == ja || $DEMO == nee ]] \
        || fout "ISMS_DEMO='$DEMO' wordt niet begrepen; gebruik 'ja' of 'nee'"

    meld "omgeving:    $APP_ENV_NU"
    meld "normprofiel: $NORM_ENV"
    meld "database:    $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
    meld "publieke URL: ${APP_URL:-<niet gezet>}"
    [[ $DEMO == ja ]] && waarschuw "ISMS_DEMO=ja — dit is een demo-opstelling, geen productie"
    return 0
}

controleer_php_basis() {
    stap "PHP"
    [[ -x $PHP ]] || fout "php is niet uitvoerbaar: $PHP"
    meld "php: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"
    # Geen extensiecontrole en geen platform_check: die zijn bij de build al
    # gedraaid en falen daar, met een leesbare melding (00l §4). Wat in het image
    # zit kan hier niet meer veranderen.
    goed "runtime uit het image, bij de build al getoetst"
}

controleer_manifest() {
    stap "Manifest"
    [[ -f "$APP/MANIFEST.json" ]] || fout "MANIFEST.json ontbreekt; dit image is niet uit een tarbal van builddistr.sh gebouwd"

    PAKKET_VERSIE=$(manifest_waarde versie)
    PAKKET_COMMIT=$(manifest_waarde commit)
    local minimaal; minimaal=$(manifest_waarde minimale_deploy_versie)

    # Een oude deploy-docker.sh mag een nieuwer pakket niet half uitrollen. In
    # Docker kan dat alleen als iemand het script met de hand vervangen heeft:
    # normaal reizen script en pakket samen in hetzelfde image.
    if [[ $(printf '%s\n%s\n' "$minimaal" "$DEPLOY_DOCKER_VERSIE" | sort -V | head -1) != "$minimaal" ]]; then
        fout "dit pakket vraagt deploy-docker.sh $minimaal of hoger (dit is $DEPLOY_DOCKER_VERSIE)"
    fi

    meld "versie: $PAKKET_VERSIE   commit: ${PAKKET_COMMIT:0:12}"
    meld "gebouwd op: $(manifest_waarde gebouwd_op) met php $(manifest_waarde bouwhost_php)"
    # SHA256SUMS is bij de build gecontroleerd (00l §4) en hoeft hier niet
    # opnieuw: de laag met de applicatieboom is sindsdien niet gewijzigd.
    goed "manifest gelezen"
}

# Vers of bestaand wordt afgeleid uit de database, niet uit een markerbestand.
# Een marker zou op de state-mount staan terwijl de toestand die hij beschrijft
# in de database zit; verwijdert iemand het ene en niet het andere, dan draait
# de initialisatie opnieuw over een gevulde database (00n §0.1).
controleer_database() {
    stap "Database"
    db_query "SELECT 1" >/dev/null \
        || fout "geen verbinding met $DB_DATABASE op $DB_HOST — controleer MYSQL_* in .env"

    local versie tabellen
    versie=$(db_query "SELECT VERSION()")
    tabellen=$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")
    meld "server: $versie"
    meld "tabellen: $tabellen"

    if [[ $tabellen -eq 0 ]]; then
        MODUS=eerste
        goed "lege database; dit wordt een verse installatie"
    else
        MODUS=bij
        goed "bestaande installatie; dit wordt een bijwerking"
    fi
}

controleer_optioneel() {
    stap "Optioneel — hier stopt het script niet op"
    # Zelfde controle als op bare metal: niet "staat pandoc erop" maar "kan deze
    # pandoc een .docx schrijven mét --sandbox". Het image pint pandoc vast en
    # toetst dit al bij de build, dus hier hoort dit altijd te slagen — behalve
    # als iemand in de container met de hand een andere pandoc heeft gezet.
    if ! command -v pandoc >/dev/null; then
        waarschuw "pandoc ontbreekt: documentgeneratie valt terug (README §Documentgeneratie)"
    elif printf '# Toets\n' | pandoc --sandbox --from=markdown --to=docx \
                                     --output=/dev/null 2>/dev/null; then
        meld "pandoc: $(pandoc --version | head -1)"
    else
        waarschuw "pandoc $(pandoc --version | head -1 | cut -d' ' -f2) kan met --sandbox geen .docx schrijven"
        waarschuw "  ⇒ elke Word-download geeft HTTP 503. Bouw het image opnieuw: docker compose up -d --build"
    fi

    "$PHP" -r 'exit(($i = @gd_info()) && ($i["FreeType Support"] ?? false) ? 0 : 1);' \
        && meld "gd met FreeType: aanwezig" \
        || waarschuw "gd zonder FreeType: de tolerantiematrix verschijnt als tabel zonder plaatje"

    # Een nieuwe versie brengt soms nieuwe instellingen mee. config:cache bakt
    # een ontbrekende sleutel gewoon op zijn default in, en dat merk je pas als
    # iemand een verkeerde meldtermijn ziet.
    #
    # Anders dan op bare metal zijn er hier twee bronnen: het statische .env in
    # het image en de omgeving van dit proces. Een sleutel telt als aanwezig
    # zodra hij in één van beide staat.
    if [[ -f "$APP/.env.example" ]]; then
        local sleutel missend=()
        while read -r sleutel; do
            [[ -z $sleutel ]] && continue
            [[ -v $sleutel ]] && continue
            grep -qE "^[[:space:]]*$sleutel[[:space:]]*=" "$APP/.env" && continue
            missend+=("$sleutel")
        done < <(grep -E '^[A-Z][A-Z0-9_]*=' "$APP/.env.example" | cut -d= -f1 | sort -u)
        if [[ ${#missend[@]} -gt 0 ]]; then
            waarschuw "sleutels uit .env.example die deze installatie niet zet (de standaardwaarde wint dan stil):"
            printf '     %s\n' "${missend[@]}"
        fi
    fi
    return 0
}

# ═════════════════════════════════════════════════════════════════════════════
#  De norm: stempel, omgeving en database moeten hetzelfde zeggen
# ═════════════════════════════════════════════════════════════════════════════

stempelbestand() { printf '%s' "$INSTALLATIE/normprofiel"; }

# ── GEDEELD MET deploy.sh :: profiel_uit_database() ──────────────────────────
# Het profiel zoals de database het laat zien. Onafhankelijk van de omgeving en
# van de stempel, want beide zijn tekstregels die iemand kan wijzigen; de
# database is het resultaat van wat er werkelijk is geseed.
profiel_uit_database() {
    local aantal zorg
    aantal=$(db_query "SELECT COUNT(*) FROM maatregelen" 2>/dev/null) || { printf 'onbekend'; return; }
    zorg=$(db_query "SELECT COUNT(*) FROM maatregelen WHERE annex_a_referentie IN
        ('5.38','5.39','5.40','5.41','5.42','5.43','6.9','8.35')" 2>/dev/null)
    if   [[ $aantal -eq 101 && $zorg -eq 8 ]]; then printf 'nen7510'
    elif [[ $aantal -eq 93  && $zorg -eq 0 ]]; then printf 'iso27001'
    elif [[ $aantal -eq 0 ]];                  then printf 'leeg'
    else printf 'afwijkend(%s maatregelen, %s zorgmaatregelen)' "$aantal" "$zorg"
    fi
}

# ── GEDEELD MET deploy.sh :: normcontrole() ──────────────────────────────────
normcontrole() {
    stap "Normcontrole"
    local stempel="" uit_db
    [[ -f $(stempelbestand) ]] && stempel=$(env_waarde "$(stempelbestand)" profiel "")
    uit_db=$(profiel_uit_database)

    meld "omgeving: $NORM_ENV"
    meld "stempel:  ${stempel:-<geen>}"
    meld "database: $uit_db"

    if [[ -n $stempel && $stempel != "$NORM_ENV" ]]; then
        fout "ISMS_NORM in uw .env ($NORM_ENV) wijkt af van de normstempel ($stempel).
Een profielwissel is geen bijwerking maar een herinstallatie: bestaande SoA-beoordelingen
gelden voor de oude controlset. Stel eerst vast welke van de twee klopt.
Zet ISMS_NORM terug op '$stempel' en doe: docker compose up -d"
    fi
    if [[ $uit_db != leeg && $uit_db != "$NORM_ENV" ]]; then
        fout "de database vertelt een ander verhaal dan uw .env: $uit_db tegenover $NORM_ENV.
Ga niet verder; een bijwerking maakt dit niet beter."
    fi
    goed "profiel eenduidig: $NORM_ENV"
}

# ── GEDEELD MET deploy.sh :: controleer_geseed_profiel() ─────────────────────
# Meteen na het seeden: heeft de installatie het profiel gekregen dat de
# omgeving vraagt? De rookproef controleert dit ook, maar die draait pas ná het
# zetten van de normstempel. Dan staat er een installatie met een controlset die
# niet bij haar eigen stempel past, en daar komt geen tweede uitrol meer langs:
# normcontrole() kapt die af, en NormprofielSeeder laat een eenmaal vastgelegd
# profiel staan. Hier is de schade nog een afgebroken start.
controleer_geseed_profiel() {
    stap "Profielcontrole na het seeden"
    local uit_db; uit_db=$(profiel_uit_database)
    meld "omgeving: $NORM_ENV"
    meld "database: $uit_db"

    [[ $uit_db == "$NORM_ENV" ]] || fout "de seeders leverden een ander profiel op dan gevraagd: $uit_db tegenover $NORM_ENV.
Deze installatie is nog niet in gebruik; de webserver is niet gestart.
Controleer ISMS_NORM in .env en bouw de database opnieuw op — opnieuw starten
herstelt dit niet, want een eenmaal vastgelegd profiel wordt nooit omgezet.
Opnieuw beginnen: docker compose down && sudo rm -rf ./data && docker compose up -d --build"
    goed "profiel geseed zoals gevraagd: $NORM_ENV"
}

# ── GEDEELD MET deploy.sh :: zet_normstempel() ───────────────────────────────
zet_normstempel() {
    local bestand; bestand=$(stempelbestand)
    cat >"$bestand" <<EOF
# Normstempel van deze installatie — gezet door deploy-docker.sh, niet met de hand wijzigen.
# Van norm wisselen betekent de database opnieuw opbouwen; zie docker/LEESMIJ.md §5.
profiel=$NORM_ENV
gezet_op=$(date -Iseconds)
release=$PAKKET_VERSIE
commit=$PAKKET_COMMIT
EOF
    chown "$GEBRUIKER:$GROEP" "$bestand"
    chmod 0640 "$bestand"
    goed "normstempel gezet: $NORM_ENV"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Verschilrapport — wat gaat deze start veranderen
# ═════════════════════════════════════════════════════════════════════════════
# Zonder het releasegedeelte van deploy.sh: er zijn hier geen releasemappen om
# tegen af te zetten. Wat er wél is: de uitrolhistorie op de mount, die de vorige
# versie kent, en de eigen seeddata die deze installatie meedraagt.

verschilrapport() {
    stap "Verschilrapport"
    local vorige=""
    [[ -f "$INSTALLATIE/uitrolhistorie" ]] && vorige=$(tail -n1 "$INSTALLATIE/uitrolhistorie" | awk '{print $1" ("substr($2,1,12)")"}')
    meld "draaide:  ${vorige:-onbekend}"
    meld "wordt:    $PAKKET_VERSIE (${PAKKET_COMMIT:0:12})"

    meld ""
    meld "Openstaande migraties:"
    artisan migrate:status --pending 2>/dev/null | sed 's/^/     /' \
        || meld "     (niet op te vragen)"

    meld ""
    meld "Eigen seeddata (data/app/seeddata versus dit pakket):"
    local bestand naam gevonden=nee
    for bestand in "$SEEDBRON"/*; do
        [[ -f $bestand ]] || continue
        gevonden=ja
        naam=$(basename "$bestand")
        if [[ -f "$SEEDDOEL/$naam" ]] && cmp -s "$bestand" "$SEEDDOEL/$naam"; then
            meld "     $naam: gelijk aan het pakket"
        elif [[ -f "$SEEDDOEL/$naam" ]]; then
            meld "     $naam: VERSCHILT — uw eigen versie wint"
        else
            meld "     $naam: alleen van u, wordt geplaatst"
        fi
    done
    [[ $gevonden == nee ]] && meld "     (geen; de installatie draait op de meegeleverde teksten)"
    return 0
}

# ═════════════════════════════════════════════════════════════════════════════
#  Seeddata
# ═════════════════════════════════════════════════════════════════════════════
# Vereenvoudigd ten opzichte van deploy.sh: er is geen vorige release om
# bestanden uit over te nemen. Wat de installatie zelf meebrengt staat op de
# mount in seeddata/ en overleeft elke herbouw van het image; dat wint van wat
# het pakket meelevert. De reden is dwingend: de installatie voert haar eigen
# normteksten in `maatregelen-<profiel>.json` in, ónder dezelfde naam als het
# meegeleverde bestand. Wint het pakket, dan vervangt het die teksten door de
# meegeleverde mededeling — en de eerstvolgende seed schrijft dat door naar de
# database.
#
# Dit schrijft in de containerlaag, niet op de mount: bij de volgende start
# staat de onaangeroerde boom uit het image er weer, en gebeurt dit opnieuw.
neem_seeddata_over() {
    stap "Seeddata"
    if compgen -G "$SEEDBRON/*" >/dev/null; then
        local bestand naam
        for bestand in "$SEEDBRON"/*; do
            [[ -f $bestand ]] || continue
            naam=$(basename "$bestand")
            cp -p "$bestand" "$SEEDDOEL/$naam"
            meld "$naam: eigen versie geplaatst"
        done
        EIGEN_SEEDDATA=ja
        chown -R "$GEBRUIKER:$GROEP" "$SEEDDOEL"
    else
        meld "geen eigen seedbestanden in data/app/seeddata"
    fi

    verwijder_andere_controlsets
}

# ── GEDEELD MET deploy.sh :: verwijder_andere_controlsets() ──────────────────
# Eén controlset per installatie. Het pakket brengt ze allebei mee, want het
# weet niet welke norm de ontvanger volgt; deze installatie weet dat wel.
#
# Het andere bestand is niet alleen overbodig, het is verwarrend: wie het
# openslaat bewerkt een bestand dat nooit geseed wordt, en typt dus normtekst
# over die nergens aankomt.
#
# Geen `case` op het profiel: het te behouden bestand heet altijd
# `maatregelen-$NORM_ENV.json`, dus het overbodige is "alle andere". Zo hoeft
# deze functie bij een derde profiel niet mee te veranderen.
verwijder_andere_controlsets() {
    local bestand
    for bestand in "$SEEDDOEL"/maatregelen-*.json; do
        [[ -f $bestand ]] || continue
        [[ $(basename "$bestand") == "maatregelen-$NORM_ENV.json" ]] && continue
        rm -f "$bestand"
        meld "andere controlset verwijderd: $(basename "$bestand")"
    done
    meld "deze installatie houdt: maatregelen-$NORM_ENV.json"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Database
# ═════════════════════════════════════════════════════════════════════════════

# Staat er een migratie klaar?
#
# `migrate:status --pending=1` geeft exitcode 1 als er iets openstaat en 0 als
# er niets openstaat. De `=1` is nodig en geen slordigheid: zonder waarde zet
# Symfony deze VALUE_OPTIONAL-optie op null, en dan valt de teruggave in
# StatusCommand weg en is de exitcode áltijd 0 — de controle zou dan stil
# "niets te doen" zeggen op precies het moment dat er wél iets te doen is.
#
# "Migration table not found" geeft óók 1. Dat is precies goed: dan weten we het
# niet, en onwetendheid hoort hier naar dumpen te leiden.
openstaande_migraties() { ! artisan migrate:status --pending=1 >/dev/null 2>&1; }

# ── GEDEELD MET deploy.sh :: maak_dump() ─────────────────────────────────────
# Verplicht zodra er werkelijk gemigreerd wordt, en dan zonder ontsnapping: er
# is geen --geen-migraties in de Docker-route. In Docker is een upgrade één
# regel in .env, dus de drempel is lager dan op bare metal — reden te meer om de
# dump daar niet optioneel te maken (00n §9.5). Dit is de enige stap in de keten
# die onomkeerbaar data kan kosten.
maak_dump() {
    local doel="$INSTALLATIE/dump-$(date +%Y%m%dT%H%M%S).sql.gz"
    stap "Databasedump vóór de migratie"
    # --no-tablespaces: vanaf MySQL 5.7.31/8.0.21 leest mysqldump standaard
    # INFORMATION_SCHEMA.FILES, en dat vraagt het serverbrede PROCESS-recht. Dat
    # recht laat een account álle draaiende queries van álle gebruikers zien;
    # dat geef je de applicatiegebruiker van een ISMS niet om een dump te kunnen
    # maken. De optie slaat het over. Er valt niets weg: deze database gebruikt
    # geen eigen tablespaces, en CREATE TABLESPACE hoort sowieso niet thuis in
    # een dump die in een ander schema teruggezet kan worden.
    MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick --routines --events \
        --no-tablespaces \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" | gzip -c >"$doel"

    # mysqldump meldde de tablespace-fout hierboven op stderr maar sloot af met
    # exitcode 0 — `pipefail` ving dat dus niet. Op de exitcode alleen kun je bij
    # dit commando niet varen, en dit bestand is de enige weg terug als de
    # migratie hierna misgaat. `-- Dump completed` staat als laatste regel in
    # elke voltooide dump; ontbreekt hij, dan is het bestand afgebroken.
    if ! zcat "$doel" | tail -1 | grep -q 'Dump completed'; then
        rm -f "$doel"
        fout "de databasedump is niet compleet afgesloten en is weggegooid.
Zonder bruikbare dump wordt er niet gemigreerd. Controleer de vrije ruimte op de
hostmap achter data/app/installatie en de rechten van $DB_USERNAME op $DB_DATABASE."
    fi

    chown "$GEBRUIKER:$GROEP" "$doel"; chmod 0600 "$doel"
    DUMP="$doel"
    goed "dump: ${doel#"$STATE"/} ($(du -h "$doel" | cut -f1))"

    # Ze worden bewust niet opgeruimd — het zijn de wegen terug (00n §13). Maar
    # er wordt er één per start gemaakt, en een herstart is in Docker goedkoop.
    # Dus wel tellen, zodat het niet ongemerkt de schijf vult.
    local aantal; aantal=$(find "$INSTALLATIE" -maxdepth 1 -name 'dump-*.sql.gz' | wc -l)
    if [[ $aantal -ge 10 ]]; then
        waarschuw "er staan $aantal dumps in data/app/installatie/ ($(du -sh "$INSTALLATIE" | cut -f1))."
        waarschuw "Ze worden nooit automatisch weggegooid; ruim zelf op wat u niet meer nodig heeft."
    fi
}

# ── GEDEELD MET deploy.sh :: migreer_en_seed() ───────────────────────────────
# Wijkt op één punt af van deploy.sh, en met opzet: daar is een uitrol een
# handeling van een beheerder en dumpt hij onvoorwaardelijk, hier draait dit bij
# élke containerstart. Een herstart na een gewijzigde .env, een herstart van de
# hostmachine, `up -d` om een instelling op te pikken — dat zijn er veel, en een
# volledige dump per keer kost minuten en vult ongemerkt de schijf.
#
# Dus: dumpen zodra er een migratie klaarstaat, en anders niet. De grens ligt
# waar het onomkeerbare zit. `db:seed` draait wel altijd mee, maar dat is
# idempotente referentiedata — geen stap waar een dump je van terugbrengt.
migreer_en_seed() {
    if [[ $MODUS == bij ]]; then
        if openstaande_migraties; then
            maak_dump
        else
            stap "Databasedump"
            meld "geen openstaande migraties; het schema verandert niet, dus geen dump"
        fi
    fi

    stap "Migreren"
    artisan migrate --force
    MIGRATIE_GEDRAAID=ja

    # De referentiedata-seeders zijn idempotent, maar niet betekenisloos: een
    # gewijzigde RolPermissieSeeder verandert de rechten van bestaande
    # gebruikers. Daarom draaien ze mee én staat het in het log.
    stap "Referentiedata seeden"
    artisan db:seed --force
}

# ═════════════════════════════════════════════════════════════════════════════
#  Het eerste account en de demo
# ═════════════════════════════════════════════════════════════════════════════

# Het wachtwoord komt NIET uit de omgeving: daar staat het leesbaar in
# `docker inspect`, in compose.yml en in de shell-historie van de klant, en voor
# een ISMS is dat de verkeerde eerste indruk. Het wordt hier gegenereerd en in
# een bestand gezet dat alleen www-data kan lezen (00n §9.4).
eerste_ciso() {
    [[ $MODUS == eerste ]] || return 0

    if [[ $DEMO_GEVULD == ja ]]; then
        meld "demo gevuld: er zijn al accounts, er wordt geen CISO aangemaakt"
        return 0
    fi
    if [[ -z ${ISMS_CISO_EMAIL:-} ]]; then
        meld "ISMS_CISO_EMAIL is niet gezet; het slotscherm toont het commando"
        return 0
    fi

    stap "Eerste CISO-account"
    local wachtwoord bestand="$STATE/storage/app/private/eerste-ciso.txt"
    # 18 bytes → 24 tekens base64. Ruim boven de ondergrens van 12 uit
    # Password::defaults() (AppServiceProvider), en het is niet aan dit script
    # om die ondergrens te kennen.
    wachtwoord=$(head -c 18 /dev/urandom | base64 | tr -d '\n')

    artisan isms:eerste-ciso "$ISMS_CISO_EMAIL" "$wachtwoord" "${ISMS_CISO_NAAM:-}" \
        || fout "het eerste CISO-account kon niet worden aangemaakt (zie de melding hierboven).
De installatie is verder in orde; maak het account met de hand aan:
    docker compose exec app runuser -u www-data -- \\
        php /opt/ezisms/artisan isms:eerste-ciso <email> '<wachtwoord>' '<naam>'"

    mkdir -p "$(dirname "$bestand")"
    cat >"$bestand" <<EOF
Eerste CISO-account van dit ISMS, aangemaakt op $(date -Iseconds).

  e-mail:      $ISMS_CISO_EMAIL
  wachtwoord:  $wachtwoord

Wijzig dit wachtwoord na de eerste inlog en verwijder dan dit bestand.
Het staat bewust niet in het opstartlog van de container.
EOF
    chown "$WEBGEBRUIKER:$WEBGROEP" "$bestand"
    chmod 0600 "$bestand"
    CISO_BESTAND="$bestand"
    goed "CISO-account aangemaakt voor $ISMS_CISO_EMAIL"
    meld "wachtwoord: data/app/storage/app/private/eerste-ciso.txt"
    meld "die staat bewust niet in dit log; het bestand is 0600 en van $WEBGEBRUIKER"
}

# ── GEDEELD MET deploy.sh :: demo_vullen(), zonder de interactieve vraag ─────
#
# `isms:demo-vul` bouwt het FruitBV-scenario op: 23 maanden aan gebeurtenissen,
# gebruikers, risico's, bewijsstukken. Het commando begint met het legen van
# alle tabellen en zet daarna de referentiedata terug met db:seed — dus ook de
# maatregelen, inclusief eventuele eigen normtekst uit seeddata/. Draai het
# dáárom pas ná neem_seeddata_over en migreer_en_seed.
#
# Geen bevestigingsvraag zoals in deploy.sh: een container heeft geen terminal
# om hem in te stellen. In plaats daarvan de harde grens uit 00n §0.2 — dit
# draait uitsluitend bij een verse database, want het commando wist alles.
demo_vullen() {
    [[ $DEMO == ja ]] || return 0

    if [[ $MODUS != eerste ]]; then
        stap "Demo-inhoud"
        waarschuw "ISMS_DEMO=ja, maar deze database is niet leeg."
        waarschuw "isms:demo-vul wist eerst álles; dat gebeurt hier niet ongevraagd."
        waarschuw "Wilt u werkelijk opnieuw vullen, begin dan met een lege stack:"
        waarschuw "    docker compose down && sudo rm -rf ./data && docker compose up -d"
        return 0
    fi

    stap "Demo-inhoud"

    # Het scenario is geschreven voor de 93 maatregelen van ISO 27001. Onder
    # NEN 7510 weigert het commando. De entrypoint kapt dit al vóór de migratie
    # af (00n §9.4); dit is de tweede grendel voor wie het script met de hand
    # start.
    [[ $NORM_ENV == iso27001 ]] \
        || fout "ISMS_DEMO=ja kan niet op een $NORM_ENV-installatie.
Het FruitBV-scenario hoort bij de controlset van ISO 27001."

    local map fixtures
    map=$(manifest_waarde demofixtures)
    fixtures="$APP/$map"
    [[ -n $map && -d $fixtures ]] \
        || fout "ISMS_DEMO=ja kan niet: dit pakket bevat geen demofixtures.
Bouw de tarbal opnieuw met een builddistr.sh die saasdemo/data meelevert."

    artisan isms:demo-vul --fixtures="$fixtures"
    DEMO_GEVULD=ja
    goed "demo gevuld uit $map"
    meld "inloggegevens: data/app/storage/app/private/demo-inloggegevens.txt"
    meld "die staan bewust niet in dit log; het bestand is 0600 en van $WEBGEBRUIKER"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Schedulerhartslag
# ═════════════════════════════════════════════════════════════════════════════
# implementatie/00m-schedulerhartslag.md is nog niet gebouwd. Zodra
# `isms:controleer-hartslag` bestaat hoort de aanroep hier te staan — ná
# migreer_en_seed, want het nulpunt uit 00m §3 wordt door db:seed gezet en zonder
# dat nulpunt meldt de eerste installatie een gat sinds 1970 (00n §9.2). De
# uitkomst hoort daarna in het slotscherm (00n §9.6).
#
# Deze functie doet bewust niets in plaats van een commando aan te roepen dat er
# nog niet is: de plek ligt vast, het gedrag komt met 00m. Hetzelfde geldt voor
# deploy.sh, dat dezelfde aanroep krijgt (00n §12).
controleer_hartslag() {
    stap "Bewaking"

    local uitvoer
    if ! uitvoer=$(artisan isms:controleer-hartslag --stil 2>&1); then
        # Niet blokkerend, en dat is een bewuste grens: dit is een controle óp de
        # bewaking, geen voorwaarde voor de uitrol. Een uitrol afbreken omdat de
        # gatendetectie zelf struikelt maakt een klein probleem groot.
        waarschuw "de hartslagcontrole liep vast; de uitrol gaat door"
        HARTSLAG="controle mislukt — zie het opstartlog"
        return 0
    fi

    printf '%s\n' "$uitvoer" | sed 's/^/   /'

    # De eerste regel is de samenvatting; die gaat mee naar het slotscherm, naast
    # de audittrail-controle. De rest staat in het opstartlog (00m §14).
    HARTSLAG=$(printf '%s\n' "$uitvoer" | head -1)
}

# ═════════════════════════════════════════════════════════════════════════════
#  Eigendom
# ═════════════════════════════════════════════════════════════════════════════
# Wat er van controleer_koppeling overblijft (00n §9.3). De webserver hoeft niet
# meer nagelopen te worden — nginx en de fpm-pool zijn van ons en draaien vast
# als www-data — maar een bind mount kan wél bestanden met een vreemde uid
# dragen. De chown in de entrypoint lost dat normaal al op; deze controle vangt
# wat daarna nog misgaat, bijvoorbeeld een beheerder die met `docker cp` of
# vanaf de host een bewijsstuk neerzet.
controleer_eigendom() {
    stap "Eigendom van de gegevens"
    local vreemd
    vreemd=$(find "$STATE/storage" ! -user "$WEBGEBRUIKER" -print -quit 2>/dev/null) || true
    if [[ -n $vreemd ]]; then
        fout "in storage staat werk van een andere gebruiker: ${vreemd#"$STATE"/} (van $(stat -c%U "$vreemd")).
De applicatie kan daar niet in schrijven. Herstel met:
    docker compose exec app chown -Rh $WEBGEBRUIKER:$WEBGROEP $STATE/storage"
    fi
    goed "alles in storage is van $WEBGEBRUIKER"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Rookproef op artisan-niveau
# ═════════════════════════════════════════════════════════════════════════════
# Het HTTP-deel bestaat al: de healthcheck van compose.yml haalt /up op zodra
# nginx draait (00l §5). Hier kan dat nog niet — nginx start pas na ons.

rookproef_artisan() {
    stap "Rookproef"
    artisan about --only=environment | sed 's/^/   /'

    local label
    label=$(artisan tinker --execute='echo App\Support\Normprofiel::label("bijlage");' 2>/dev/null | tr -d '\r\n ')
    [[ -n $label ]] || fout "de applicatie kan niet opstarten (het normprofiel is niet op te vragen)"
    meld "profiel toont: $label"

    local uit_db; uit_db=$(profiel_uit_database)
    [[ $uit_db == "$NORM_ENV" ]] \
        || fout "na de uitrol wijkt het profiel in de database af: $uit_db tegenover $NORM_ENV"
    goed "normprofiel klopt: $NORM_ENV ($uit_db)"

    controleer_eigendom

    stap "Audit trail"
    # Een uitrol hoort de ketenhashing niet te raken, en dat hoor je aan te tonen
    # in plaats van aan te nemen.
    artisan isms:controleer-audittrail || fout "de ketenhashing van de audit trail is niet ongebroken"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Verantwoording
# ═════════════════════════════════════════════════════════════════════════════

# ── GEDEELD MET deploy.sh :: bewaar_verantwoording() ─────────────────────────
bewaar_verantwoording() {
    cp -p "$APP/MANIFEST.json" "$INSTALLATIE/manifest-$PAKKET_VERSIE.json"
    # Zelfde vier velden als deploy.sh, zodat beide historieën hetzelfde lezen.
    # Het vierde is daar de releasemap; hier is dat de commit van het image.
    printf '%s\n' "$PAKKET_VERSIE $PAKKET_COMMIT $(date -Iseconds) docker" \
        >>"$INSTALLATIE/uitrolhistorie"
    chown -R "$GEBRUIKER:$GROEP" "$INSTALLATIE"

    if [[ -f "$APP/scripts/genereer-sbom.php" ]]; then
        ( cd "$APP" && als_app "$PHP" scripts/genereer-sbom.php >/dev/null 2>&1 ) \
            && meld "SBOM bijgewerkt (informatief; gezaghebbend is de versie in de git-werkkopie)" \
            || waarschuw "SBOM-generatie mislukte; niet blokkerend"
    fi
    return 0
}

# ═════════════════════════════════════════════════════════════════════════════
#  Slotschermen
# ═════════════════════════════════════════════════════════════════════════════

slotscherm_eerste() {
    cat <<EOF

$(printf '\033[1m')Klaar — het ISMS is ingericht op $NORM_ENV.$(printf '\033[0m')

  versie      $PAKKET_VERSIE (${PAKKET_COMMIT:0:12})
  maatregelen $(db_query "SELECT COUNT(*) FROM maatregelen")
  gebruikers  $(db_query "SELECT COUNT(*) FROM gebruikers")
  opstartlog  data/app/installatie/$(basename "$LOGBESTAND")
  bewaking    $HARTSLAG
EOF

    if [[ -n $CISO_BESTAND ]]; then
        cat <<EOF

  Het eerste CISO-account is aangemaakt voor $ISMS_CISO_EMAIL.
  Het wachtwoord staat in:
      data/app/storage/app/private/eerste-ciso.txt
  Lees het uit met:
      docker compose exec app cat $CISO_BESTAND
  Wijzig het na de eerste inlog en verwijder dan dat bestand.
EOF
    elif [[ $DEMO_GEVULD == ja ]]; then
        cat <<EOF

  De demo is gevuld, dus er zijn al accounts. De inloggegevens staan in:
      data/app/storage/app/private/demo-inloggegevens.txt
  Lees ze uit met:
      docker compose exec app cat $STATE/storage/app/private/demo-inloggegevens.txt
EOF
    else
        cat <<EOF

  Er is nog geen account. Zet ISMS_CISO_EMAIL in .env en doe
  \`docker compose up -d\`, of maak het met de hand aan:

      docker compose exec app runuser -u www-data -- \\
          php /opt/ezisms/artisan isms:eerste-ciso <e-mail> '<wachtwoord>' '<naam>'
EOF
    fi

    cat <<EOF

Verder:
  de applicatie luistert op poort ${HTTP_POORT:-81} van de host; zet uw HAProxy daarnaartoe
  toetsbestanden  → data/app/storage/app/private/toetsen/
  eigen normtekst → data/app/seeddata/
  logs            → data/app/storage/logs/

EOF
}

slotscherm_bij() {
    cat <<EOF

$(printf '\033[1m')Klaar — versie $PAKKET_VERSIE draait.$(printf '\033[0m')

  versie      $PAKKET_VERSIE (${PAKKET_COMMIT:0:12})
  maatregelen $(db_query "SELECT COUNT(*) FROM maatregelen")
  gebruikers  $(db_query "SELECT COUNT(*) FROM gebruikers")
  opstartlog  data/app/installatie/$(basename "$LOGBESTAND")
  bewaking    $HARTSLAG
${DUMP:+  dump        data/app/installatie/$(basename "${DUMP:-}") (bewaren tot deze versie zich bewezen heeft)
}  terugrollen ISMS_BOOM in .env terugzetten en \`docker compose up -d --build\`
              — een migratie draait dat NIET terug; de dump hierboven is de weg terug

EOF
}

# ═════════════════════════════════════════════════════════════════════════════
#  De twee stromen
# ═════════════════════════════════════════════════════════════════════════════

installeer_nieuw() {
    neem_seeddata_over
    migreer_en_seed

    # Zijn er eigen normbestanden meegegeven, dan moeten de maatregelteksten
    # daaruit alsnog de database in. Via het commando en niet via db:seed: dat
    # controleert het bestand eerst en meldt hoeveel maatregelen een eigen
    # normtekst hebben — bij een meegegeven bestand is dat precies de vraag.
    if [[ $EIGEN_SEEDDATA == ja ]]; then
        stap "Maatregelen opnieuw seeden met de eigen normtekst"
        artisan isms:maatregelen
    fi

    controleer_geseed_profiel
    zet_normstempel
    demo_vullen
    eerste_ciso
    controleer_hartslag
    rookproef_artisan
    bewaar_verantwoording
    slotscherm_eerste
}

werk_bij() {
    normcontrole
    verschilrapport
    neem_seeddata_over
    migreer_en_seed

    # De uitgangsclassificatie opnieuw inlezen én signaleren wat er níét
    # aankomt. `kenmerken_eigen` op een SoA-regel is alles-of-niets, dus een
    # correctie op een meegeleverde waarde bereikt juist de regels niet waar
    # iemand zelf naar gekeken heeft. Dit commando maakt daar taken voor aan.
    # Alleen hier en niet in installeer_nieuw: op een verse installatie bestaat
    # er geen enkele eigen classificatie, dus valt er niets te signaleren.
    stap "Uitgangsclassificatie"
    artisan isms:kenmerken

    demo_vullen
    controleer_hartslag
    rookproef_artisan
    bewaar_verantwoording
    slotscherm_bij
}

# ═════════════════════════════════════════════════════════════════════════════
#  Hoofdstroom
# ═════════════════════════════════════════════════════════════════════════════

# Alleen namen die dit script zelf verzint. DB_HOST, DB_PORT, DB_DATABASE,
# DB_USERNAME en DB_PASSWORD staan hier NIET bij, en dat is geen vergetelheid:
# dat zijn de namen waaronder compose ze aan de container meegeeft. Ze hier op
# leeg zetten wist precies de waarden die lees_omgeving even later moet lezen —
# de installatie stopte dan op "DB_DATABASE staat niet in de omgeving van deze
# container" terwijl `docker inspect` hem gewoon toonde. `set -u` is er ook
# zonder die regel niet mee: lees_omgeving leest ze alle vijf met een
# `${…:-}`-vangnet, en niets raakt ze aan vóór dat moment.
MODUS=""; PAKKET_VERSIE=""; PAKKET_COMMIT=""
NORM_ENV=""; APP_ENV_NU=""; DEMO=nee
EIGEN_SEEDDATA=nee
DEMO_GEVULD=nee
MIGRATIE_GEDRAAID=nee
DUMP=""
CISO_BESTAND=""
HARTSLAG=""

hoofd() {
    controleer_root
    [[ $# -eq 0 ]] || fout "dit script kent geen opties; alles komt uit de omgeving van de container"

    # Geen grendel zoals in deploy.sh: er draait per container precies één
    # entrypoint, en die roept dit script één keer aan.
    FOUTVLAGMAP=$(mktemp -d /tmp/ezisms-vlag.XXXXXXXX)
    FOUTVLAG="$FOUTVLAGMAP/gemeld"

    trap opruimen EXIT
    trap 'fout "onverwachte fout op regel $LINENO"' ERR

    # Het log gaat rechtstreeks naar de mount en niet naar een werkmap: het moet
    # de container overleven, juist als deze uitrol mislukt. tee houdt het
    # tegelijk in `docker compose logs`.
    LOGBESTAND="$INSTALLATIE/deploy-$(date +%Y%m%dT%H%M%S).log"
    exec > >(tee -a "$LOGBESTAND") 2>&1

    printf '\033[1mISMS-uitrol (Docker)\033[0m — %s\n' "$(date -Iseconds)"
    meld "deploy-docker.sh $DEPLOY_DOCKER_VERSIE, draait als $(id -un) in $(hostname)"

    controleer_gereedschap
    lees_omgeving
    maak_apphome
    controleer_php_basis
    controleer_manifest
    controleer_database
    controleer_optioneel

    if [[ $MODUS == eerste ]]; then
        installeer_nieuw
    else
        werk_bij
    fi
}

hoofd "$@"
