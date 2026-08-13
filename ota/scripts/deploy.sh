#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy.sh — rolt het ISMS uit een distributietarbal uit op deze host
#
#   sudo deploy.sh <tarbal> <doelpad> [opties]
#
# LEESWIJZER VOOR DE BEHEERDER
#
#   Het script draait als root, maar voert elke applicatiestap uit als de
#   webservergebruiker (meestal www-data). Root doet alleen wat root moet doen:
#   eigendom zetten, de crontab vullen en php-fpm herladen.
#
#   Twee accounts, met een duidelijke taakverdeling:
#     ezisms    bezit de code en draait niets. Het webproces kan zijn eigen
#               applicatiecode dus niet herschrijven.
#     www-data  draait php-fpm, de scheduler en artisan, en bezit alles waar de
#               applicatie in schrijft (public/, storage/, bootstrap/cache, .env).
#
#   Eén schrijvend account betekent: geen ACL's nodig, en geen eigen fpm-pool.
#   De installatie gebruikt de pool die er al staat.
#
#   Layout die het aanlegt en verwacht:
#
#     <doelpad>/
#     ├── current -> releases/<tijdstempel>/   de symlink die de webserver volgt
#     ├── releases/                            elke uitrol een eigen map
#     └── shared/                              alles wat een release overleeft
#         ├── .env          normkeuze, APP_KEY, databasegegevens
#         ├── storage/      bewijsstukken, logs, audit trail
#         ├── seeddata/     eigen, gelicentieerde seedbestanden (--norm)
#         └── installatie/  normstempel, manifesten, deploylogs, dumps
#
#   Twee situaties, en het script leidt zelf af welke van de twee het is:
#     * <doelpad> bevat alleen .env   ⇒ nieuwe installatie (bevestig met --eerste)
#     * <doelpad> bevat releases/     ⇒ volgende release
#
#   Terugrollen is één symlink omzetten:  deploy.sh <tarbal> <doelpad> --terug
#   Een migratie draait dat NIET terug; daarvoor is de dump uit stap "Database".
#
#   Wat dit script NIET doet: een database aanmaken, een vhost schrijven,
#   systeempakketten installeren, het normprofiel wijzigen, normtekst genereren
#   of het eerste CISO-account aanmaken. Zie het slotscherm na --eerste.
#
#   Achtergrond en afwegingen staan in ../../voorstel-deploy.md.
# ─────────────────────────────────────────────────────────────────────────────

# -E is hier geen sierletter: zonder errtrace erven functies de ERR-trap niet,
# en dan breekt het script binnen een functie stil af — geen melding, geen
# regelnummer, alleen een exitcode. Precies wat het onderzoeken van een mislukte
# uitrol onnodig moeilijk maakt.
set -Eeuo pipefail

DEPLOY_VERSIE="1.0"

# ── Instellingen ─────────────────────────────────────────────────────────────
# Alles via ": ${NAAM:=…}", dus per aanroep te overschrijven zonder het script
# te wijzigen:   PHP_BIN=/usr/bin/php8.3 deploy.sh pakket.tar.gz /var/www/isms
#
: "${UITPAKMAP:=/var/tmp}"        # instructie: de tarbal wordt hier uitgepakt
: "${GEBRUIKER:=ezisms}"          # eigenaar van de code; schrijft niets tijdens bedrijf
: "${GROEP:=}"                    # leeg ⇒ gelijk aan GEBRUIKER
: "${WEBGEBRUIKER:=}"             # draait fpm, cron en artisan; leeg ⇒ van de webserver
: "${PHP_BIN:=}"                  # leeg ⇒ gedetecteerd, met expliciete versie
: "${RELEASES_BEWAREN:=5}"        # hoeveel oude releases blijven staan
: "${ROOKPROEF_PAD:=/login}"      # moet HTTP 200 geven na afloop
: "${LOCKMAP:=/var/lock}"
# ─────────────────────────────────────────────────────────────────────────────

gebruik() {
    cat <<'EOF'
Gebruik: deploy.sh <tarbal> <doelpad> [opties]

  <tarbal>              het distributiebestand van builddistr.sh
  <doelpad>             absoluut pad naar de installatie

  --eerste              bevestig dat dit een nieuwe installatie is
  --profiel=<naam>      alleen bij --eerste: iso27001 of nen7510
  --norm=<pad>          map met eigen, gelicentieerde seedbestanden
  --gebruiker=<naam>    eigenaar van de code (standaard: ezisms). De applicatie
                        zelf draait als de webservergebruiker; overschrijf die
                        zo nodig met WEBGEBRUIKER=… in de omgeving.
  --dry-run             alleen controleren en rapporteren, niets wijzigen
  --terug               vorige release terugzetten
  --geen-migraties      migraties overslaan (doe ze dan met de hand, ná een dump)
  --seeddata=release    seeddata uit de tarbal laten winnen van de installatie
  --herstel-cron        crontabregel herschrijven zoals dit script hem bedoelt
  --demo-vul            vul het ISMS met het FruitBV-demoscenario. WIST EERST DE
                        HELE DATABASE. Alleen als APP_ENV local of demo is; zonder
                        deze optie wordt er op zo'n installatie om bevestiging
                        gevraagd, en zonder terminal overgeslagen.
  --geen-demo           nooit vullen, ook niet vragen
  -h, --help            deze uitleg
EOF
}

# ═════════════════════════════════════════════════════════════════════════════
#  Meldingen en logboek
# ═════════════════════════════════════════════════════════════════════════════

stap()      { printf '\n\033[1m== %s\033[0m\n' "$*"; }
meld()      { printf '   %s\n' "$*"; }
goed()      { printf '\033[32m   ✓ %s\033[0m\n' "$*"; }
waarschuw() { printf '\033[33m   ! %s\033[0m\n' "$*"; }

# De vlag maakt van een fout één melding. Breekt een commando af binnen een
# $(...), dan slaat de ERR-trap twee keer toe: eenmaal in de subshell en daarna
# nog eens op de mislukte toewijzing eromheen. Dezelfde melding twee keer laat
# een beheerder zoeken naar een tweede fout die er niet is. Een variabele helpt
# niet — de subshell kan die van de ouder niet zetten — dus een bestand.
fout() {
    if [[ -n ${FOUTVLAG:-} ]]; then
        if [[ -e $FOUTVLAG ]]; then
            exit 1                        # al gemeld, dieper in de aanroepketen
        fi
        : >"$FOUTVLAG"
    fi
    printf '\033[31m\nFOUT: %s\033[0m\n' "$*" >&2
    exit 1
}

# ═════════════════════════════════════════════════════════════════════════════
#  Argumenten
# ═════════════════════════════════════════════════════════════════════════════

TARBAL=""; DOELPAD=""
EERSTE_BEVESTIGD=nee
PROFIEL_ARG=""
NORMMAP=""
DRYRUN=nee
TERUG=nee
MIGRATIES=ja
SEEDDATA=installatie
HERSTEL_CRON=nee
DEMO=vragen            # vragen | ja | nee

verwerk_argumenten() {
    local arg
    for arg in "$@"; do
        case "$arg" in
            --eerste)           EERSTE_BEVESTIGD=ja ;;
            --profiel=*)        PROFIEL_ARG=${arg#*=} ;;
            --norm=*)           NORMMAP=${arg#*=} ;;
            --gebruiker=*)      GEBRUIKER=${arg#*=} ;;
            --dry-run)          DRYRUN=ja ;;
            --terug)            TERUG=ja ;;
            --geen-migraties)   MIGRATIES=nee ;;
            --seeddata=*)       SEEDDATA=${arg#*=} ;;
            --herstel-cron)     HERSTEL_CRON=ja ;;
            --demo-vul)         DEMO=ja ;;
            --geen-demo)        DEMO=nee ;;
            -h|--help)          gebruik; exit 0 ;;
            -*)                 gebruik >&2; fout "onbekende optie: $arg" ;;
            *)
                if   [[ -z $TARBAL  ]]; then TARBAL=$arg
                elif [[ -z $DOELPAD ]]; then DOELPAD=$arg
                else gebruik >&2; fout "te veel argumenten: $arg"
                fi ;;
        esac
    done

    [[ -n $TARBAL && -n $DOELPAD ]] || { gebruik >&2; fout "tarbal en doelpad zijn beide verplicht"; }
    [[ $DOELPAD == /* ]] || fout "het doelpad moet absoluut zijn (dit script draait als root)"
    [[ $SEEDDATA == installatie || $SEEDDATA == release ]] \
        || fout "--seeddata= verwacht 'installatie' of 'release'"
    [[ $PROFIEL_ARG == "" || $PROFIEL_ARG == iso27001 || $PROFIEL_ARG == nen7510 ]] \
        || fout "--profiel= verwacht 'iso27001' of 'nen7510'"

    DOELPAD=${DOELPAD%/}
    : "${GROEP:=$GEBRUIKER}"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Kleine hulpmiddelen
# ═════════════════════════════════════════════════════════════════════════════

# ── GEDEELD MET deploy-docker.sh ─────────────────────────────────────────────
# Een deel van dit script is letterlijk overgenomen in scripts/deploy-docker.sh,
# de uitrollogica ín de applicatiecontainer. Die blokken dragen daar en hier
# dezelfde markering: "GEDEELD MET …". Wijzig je er hier een, wijzig hem dan ook
# daar.
#
# Reden om te dupliceren in plaats van te delen: dit script draait op een
# vreemde host waar een gedeeld bestand pas bestaat ná het uitpakken — kip en
# ei, zie de kop van distr-gemeenschappelijk.sh. Voor de Docker-route geldt dat
# niet, maar één script herschrijven om het andere te kunnen delen is nu niet de
# prijs waard. De volledige lijst staat in implementatie/00n-docker-deploy.md
# §12; die kan verjaren, dus ga op de markering af en niet op het regelnummer.
# ─────────────────────────────────────────────────────────────────────────────

# GEDEELD MET deploy-docker.sh :: env_waarde()
# Eén waarde uit een .env-bestand. Bewust geen `source`: een .env is data en
# geen shellscript, en `source` voert uit wat erin staat.
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

# Eén waarde uit MANIFEST.json. PHP is de JSON-lezer, want python3 staat niet op
# elke doelhost en PHP is er per definitie wel.
manifest_waarde() { # <sleutel>
    MANIFEST_PAD="$BOOM/MANIFEST.json" MANIFEST_SLEUTEL="$1" "$PHP" -r '
        $data = json_decode(file_get_contents(getenv("MANIFEST_PAD")), true);
        $waarde = $data[getenv("MANIFEST_SLEUTEL")] ?? "";
        echo is_array($waarde) ? implode("\n", $waarde) : $waarde;
    '
}

# GEDEELD MET deploy-docker.sh :: db_query()
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

# GEDEELD MET deploy-docker.sh :: maak_apphome/als_app/artisan
# Alles wat de applicatie zelf is — artisan, de scheduler, php-fpm — draait als
# de webservergebruiker. Eén account dat schrijft, dus geen ACL's nodig.
#
# Die gebruiker heeft alleen zelden een bruikbare thuismap: www-data krijgt
# /var/www (van root, niet schrijfbaar) en op andere distributies /nonexistent.
# Gereedschap dat in $HOME wil schrijven loopt daar stuk — psysh, de shell onder
# `artisan tinker`, weigert met "Writing to directory /var/www/.config/psysh is
# not allowed". Daarom krijgt elke aanroep een eigen wegwerpthuismap. De
# XDG-variabelen staan er nadrukkelijk bij: psysh kijkt eerst daarnaar en pas
# daarna naar $HOME, dus alleen HOME zetten lost het niet op.
APPHOME=""

maak_apphome() {
    APPHOME=$(mktemp -d "${UITPAKMAP%/}/ezisms-home.XXXXXXXX")
    chown "$WEBGEBRUIKER:$WEBGROEP" "$APPHOME"
    chmod 0700 "$APPHOME"
}

als_app() {
    # Niet hier aanmaken als hij ontbreekt: artisan() draait in een subshell, en
    # een map die dáár ontstaat kent de ouder niet, dus zou opruimen hem laten
    # staan — één achtergebleven map per artisan-aanroep. hoofd() maakt hem één
    # keer aan; komen we hier zonder, dan is dat een fout in dit script.
    [[ -n $APPHOME ]] || fout "interne fout: de wegwerpthuismap is niet aangemaakt"
    runuser -u "$WEBGEBRUIKER" -- env \
        HOME="$APPHOME" \
        XDG_CONFIG_HOME="$APPHOME/.config" \
        XDG_DATA_HOME="$APPHOME/.local/share" \
        XDG_CACHE_HOME="$APPHOME/.cache" \
        "$@"
}

# artisan draait altijd als de applicatiegebruiker en altijd in de release. Zou
# root dit doen, dan staat er root-eigendom in storage/ en is de installatie stuk
# op precies de plek waar niemand kijkt.
artisan() { ( cd "$RELEASE" && als_app "$PHP" artisan "$@" ); }

# ═════════════════════════════════════════════════════════════════════════════
#  Opruimen: dit loopt ook als het script halverwege afbreekt
# ═════════════════════════════════════════════════════════════════════════════

WERKMAP=""; LOGBESTAND=""
ONDERHOUD_AAN=nee
HARTSLAG=""
RELEASE=""; VORIGE_CURRENT=""; SYMLINK_GEWISSELD=nee

opruimen() {
    local code=$?
    trap - EXIT

    if [[ $ONDERHOUD_AAN == ja && -d "$DOELPAD/current" ]]; then
        meld "onderhoudsmodus uitzetten"
        ( cd "$DOELPAD/current" && als_app "$PHP" artisan up ) >/dev/null 2>&1 \
            || waarschuw "artisan up mislukte — zet de site met de hand weer open"
    fi

    if [[ $code -ne 0 && $SYMLINK_GEWISSELD == ja && -n $VORIGE_CURRENT ]]; then
        waarschuw "uitrol mislukt na de omschakeling — current gaat terug naar $(basename "$VORIGE_CURRENT")"
        ln -sfn "$VORIGE_CURRENT" "$DOELPAD/current.nieuw" && mv -T "$DOELPAD/current.nieuw" "$DOELPAD/current"
        herlaad_fpm || true
    fi

    if [[ -n $LOGBESTAND && -d ${DOELPAD:-}/shared/installatie ]]; then
        cp -p "$LOGBESTAND" "$DOELPAD/shared/installatie/" 2>/dev/null || true
    fi

    if [[ -n $WERKMAP && -d $WERKMAP ]]; then
        if [[ $code -eq 0 ]]; then
            rm -rf "$WERKMAP"
        else
            waarschuw "werkmap blijft staan voor onderzoek: $WERKMAP"
            [[ -n $LOGBESTAND ]] && waarschuw "log: $LOGBESTAND"
        fi
    fi

    # De wegwerpthuismap gaat altijd weg, ook na een afbreuk: er staat niets in
    # dat iets verklaart, alleen wat psysh voor zichzelf heeft neergezet.
    if [[ -n $APPHOME && -d $APPHOME ]]; then rm -rf "$APPHOME"; fi
    if [[ -n $FOUTVLAGMAP && -d $FOUTVLAGMAP ]]; then rm -rf "$FOUTVLAGMAP"; fi

    exit "$code"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Controles vooraf — hierna pas wordt er iets aangeraakt
# ═════════════════════════════════════════════════════════════════════════════

controleer_root() {
    [[ $EUID -eq 0 ]] || fout "dit script moet als root draaien (gebruiker aanmaken, eigendom, fpm, cron)"
}

controleer_gereedschap() {
    stap "Gereedschap op deze host"
    local cmd ontbreekt=()
    for cmd in tar rsync runuser useradd groupadd crontab systemctl sha256sum find flock; do
        command -v "$cmd" >/dev/null || ontbreekt+=("$cmd")
    done
    [[ ${#ontbreekt[@]} -eq 0 ]] || fout "ontbrekende commando's: ${ontbreekt[*]}"
    goed "alle benodigde commando's aanwezig"

    command -v curl >/dev/null || waarschuw "curl ontbreekt — de HTTP-rookproef wordt overgeslagen"
}

controleer_tarbal() {
    [[ -f $TARBAL ]] || fout "tarbal bestaat niet: $TARBAL"
    TARBAL=$(readlink -f "$TARBAL")

    # Naast de tarbal hoort een .sha256; is hij er, dan controleren we hem. Een
    # halve overdracht is anders pas zichtbaar als de site 500 geeft.
    if [[ -f "$TARBAL.sha256" ]]; then
        ( cd "$(dirname "$TARBAL")" && sha256sum -c --status "$(basename "$TARBAL").sha256" ) \
            || fout "de checksum van de tarbal klopt niet — overdracht opnieuw doen"
        goed "checksum van de tarbal klopt"
    else
        waarschuw "geen $TARBAL.sha256 gevonden; de tarbal zelf is niet geverifieerd"
    fi
}

# Nieuw of bestaand wordt afgeleid, niet aangenomen. Zo kan een typefout in het
# doelpad geen bestaande installatie overschrijven, en een vergeten --eerste
# geen halve nieuwe installatie opleveren.
bepaal_modus() {
    stap "Doelpad: $DOELPAD"
    [[ -d $DOELPAD ]] || fout "het doelpad bestaat niet; maak het aan en zet er de ingevulde .env in"

    local inhoud
    inhoud=$(ls -A "$DOELPAD")

    if [[ -d "$DOELPAD/releases" ]]; then
        MODUS=release
        [[ $EERSTE_BEVESTIGD == nee ]] \
            || fout "--eerste opgegeven, maar hier staat al een installatie ($DOELPAD/releases)"
        ENVBESTAND="$DOELPAD/shared/.env"
        [[ -f $ENVBESTAND ]] || fout "shared/.env ontbreekt terwijl er wel releases staan — dit is geen complete installatie"
        goed "bestaande installatie; dit wordt een nieuwe release"
    else
        MODUS=eerste
        [[ $inhoud == ".env" ]] \
            || fout "voor een nieuwe installatie moet $DOELPAD alleen een .env bevatten (gevonden: ${inhoud//$'\n'/, })"
        [[ $EERSTE_BEVESTIGD == ja ]] \
            || fout "dit lijkt een nieuwe installatie; bevestig dat met --eerste"
        ENVBESTAND="$DOELPAD/.env"
        goed "nieuwe installatie"
    fi
}

lees_env() {
    stap "Instellingen uit $(basename "$ENVBESTAND")"
    APP_ENV=$(env_waarde "$ENVBESTAND" APP_ENV production)
    APP_URL=$(env_waarde "$ENVBESTAND" APP_URL "")
    APP_KEY=$(env_waarde "$ENVBESTAND" APP_KEY "")
    NORM_ENV=$(env_waarde "$ENVBESTAND" ISMS_NORM iso27001)
    DB_HOST=$(env_waarde "$ENVBESTAND" DB_HOST 127.0.0.1)
    DB_PORT=$(env_waarde "$ENVBESTAND" DB_PORT 3306)
    DB_DATABASE=$(env_waarde "$ENVBESTAND" DB_DATABASE "")
    DB_USERNAME=$(env_waarde "$ENVBESTAND" DB_USERNAME "")
    DB_PASSWORD=$(env_waarde "$ENVBESTAND" DB_PASSWORD "")

    [[ -n $DB_DATABASE ]] || fout "DB_DATABASE staat niet in $ENVBESTAND"
    meld "omgeving:    $APP_ENV"
    meld "normprofiel: $NORM_ENV"
    meld "database:    $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE"
    [[ $APP_ENV == production ]] || waarschuw "APP_ENV is '$APP_ENV' en niet 'production'"
}

controleer_webserver() {
    stap "Webserver"
    local dienst
    WEBSERVER=""
    for dienst in nginx apache2 httpd; do
        if systemctl is-active --quiet "$dienst" 2>/dev/null; then WEBSERVER=$dienst; break; fi
    done
    [[ -n $WEBSERVER ]] || fout "geen draaiende nginx of apache gevonden; de applicatie zou onbereikbaar zijn"

    # De applicatie draait als deze gebruiker: php-fpm, de scheduler en elke
    # artisan-aanroep van dit script. mod_php mag hier ook, want dat draait PHP
    # in het webserverproces — dezelfde gebruiker, dus hetzelfde plaatje.
    case "$WEBSERVER" in
        nginx)  : "${WEBGEBRUIKER:=$(nginx -T 2>/dev/null | awk '$1=="user"{print $2}' | tr -d ';' | head -1)}" ;;
        apache2|httpd)
                : "${WEBGEBRUIKER:=$(apachectl -S 2>/dev/null | awk -F'"' '/User:/ {print $2}' | head -1)}" ;;
    esac
    : "${WEBGEBRUIKER:=www-data}"
    getent passwd "$WEBGEBRUIKER" >/dev/null \
        || fout "de webservergebruiker '$WEBGEBRUIKER' bestaat niet; zet hem met WEBGEBRUIKER=… in de omgeving"
    WEBGROEP=$(id -gn "$WEBGEBRUIKER")
    goed "$WEBSERVER draait; de applicatie draait als $WEBGEBRUIKER:$WEBGROEP"
}

controleer_php_basis() {
    stap "PHP"
    if [[ -z $PHP_BIN ]]; then
        command -v php >/dev/null || fout "geen php gevonden op deze host"
        PHP=$(command -v php)
    else
        PHP=$PHP_BIN
    fi
    [[ -x $PHP ]] || fout "php is niet uitvoerbaar: $PHP"

    PHP_MAJMIN=$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    # Liever /usr/bin/php8.4 dan de alternatives-symlink /usr/bin/php: een
    # systeemwijziging mag de cron en de uitrol niet ongemerkt naar een andere
    # runtime verplaatsen. Zelfde reden als de vastgepinde crontabregel.
    if [[ -z $PHP_BIN ]] && command -v "php$PHP_MAJMIN" >/dev/null; then
        PHP=$(command -v "php$PHP_MAJMIN")
    fi
    meld "php:  $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

    # php-fpm moet dezelfde versie draaien als de CLI; anders lopen web en cron
    # op verschillende runtimes.
    FPM_DIENST=""
    local kandidaat
    for kandidaat in "php$PHP_MAJMIN-fpm" "php-fpm$PHP_MAJMIN" php-fpm; do
        if systemctl list-unit-files "$kandidaat.service" >/dev/null 2>&1 \
           && systemctl cat "$kandidaat" >/dev/null 2>&1; then
            FPM_DIENST=$kandidaat; break
        fi
    done
    [[ -n $FPM_DIENST ]] || fout "geen php-fpm-dienst voor PHP $PHP_MAJMIN gevonden"
    systemctl is-active --quiet "$FPM_DIENST" || waarschuw "$FPM_DIENST draait niet"
    meld "fpm:  $FPM_DIENST"

    # De pools worden niet aangelegd of gewijzigd — alleen gelezen, om te kunnen
    # controleren als welke gebruiker de site straks bediend wordt (§5.4).
    POOLMAP=""
    for kandidaat in "/etc/php/$PHP_MAJMIN/fpm/pool.d" /etc/php-fpm.d; do
        [[ -d $kandidaat ]] && { POOLMAP=$kandidaat; break; }
    done
    if [[ -n $POOLMAP ]]; then
        meld "pools: $POOLMAP"
        FPM_SOCKET=$(standaardsocket)
        if [[ -n $FPM_SOCKET ]]; then
            meld "socket: $FPM_SOCKET (pool van $WEBGEBRUIKER)"
        else
            waarschuw "geen pool gevonden die als $WEBGEBRUIKER draait; controleer $POOLMAP"
        fi
    else
        waarschuw "geen pool.d-map gevonden; de koppelingscontrole doet het straks met minder zekerheid"
    fi
}

# De socket van de pool die als de applicatiegebruiker draait — voor het
# vhost-fragment in het slotscherm en voor de foutmelding als de vhost naar een
# andere pool wijst.
standaardsocket() {
    local bestand gebruiker luistert
    # Zonder deze regel matcht een lege $WEBGEBRUIKER op een pool zonder
    # `user =`, en dan meldt het script een socket die nergens op slaat.
    [[ -n $WEBGEBRUIKER ]] || return 0
    for bestand in "$POOLMAP"/*.conf; do
        [[ -f $bestand ]] || continue
        gebruiker=$(awk -F= '/^[[:space:]]*user[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$bestand")
        luistert=$(awk -F= '/^[[:space:]]*listen[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$bestand")
        if [[ $gebruiker == "$WEBGEBRUIKER" && $luistert == /* ]]; then
            printf '%s' "$luistert"; return 0
        fi
    done
    return 0
}

# Deze twee kunnen pas ná het uitpakken: de eisen staan in het pakket.
controleer_php_pakket() {
    stap "PHP-extensies en platformeisen"
    local ontbreekt=() ext
    local aanwezig; aanwezig=$("$PHP" -m | tr '[:upper:]' '[:lower:]')
    while read -r ext; do
        [[ -z $ext ]] && continue
        grep -qx "$ext" <<<"$aanwezig" || ontbreekt+=("$ext")
    done <<<"$(manifest_waarde php_extensies)"
    [[ ${#ontbreekt[@]} -eq 0 ]] || fout "ontbrekende php-extensies: ${ontbreekt[*]}"
    goed "alle vereiste extensies aanwezig"

    # Dit bestand ís de eis: composer schrijft er de platformvoorwaarden in
    # waarmee vendor/ gebouwd is. Beter dan een versienummer overschrijven.
    if [[ -f "$BOOM/vendor/composer/platform_check.php" ]]; then
        PC="$BOOM/vendor/composer/platform_check.php" "$PHP" -r 'require getenv("PC");' >/dev/null 2>&1 \
            || fout "deze PHP voldoet niet aan de platformeisen waarmee het pakket is gebouwd"
        goed "platform_check van composer komt door"
    fi
}

controleer_database() {
    stap "Database"
    if ! db_query "SELECT 1" >/dev/null; then
        fout "geen verbinding met de database met de gegevens uit $ENVBESTAND"
    fi
    local versie tabellen
    versie=$(db_query "SELECT VERSION()")
    tabellen=$(db_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")
    meld "server: $versie"
    meld "tabellen: $tabellen"

    if [[ $MODUS == eerste && $tabellen -ne 0 ]]; then
        fout "de database bevat al $tabellen tabellen; dat is geen nieuwe installatie"
    fi
    if [[ $MODUS == release && $tabellen -eq 0 ]]; then
        fout "de database is leeg terwijl er releases staan; controleer DB_DATABASE in shared/.env"
    fi
    goed "database bereikbaar en in de verwachte toestand"
}

controleer_optioneel() {
    stap "Optioneel — hier stopt het script niet op"
    command -v pandoc >/dev/null \
        && meld "pandoc: $(pandoc --version | head -1)" \
        || waarschuw "pandoc ontbreekt: documentgeneratie valt terug (README §Documentgeneratie)"

    "$PHP" -r 'exit(($i = @gd_info()) && ($i["FreeType Support"] ?? false) ? 0 : 1);' \
        && meld "gd met FreeType: aanwezig" \
        || waarschuw "gd zonder FreeType: de tolerantiematrix verschijnt als tabel zonder plaatje"

    if command -v getenforce >/dev/null && [[ $(getenforce) == Enforcing ]]; then
        waarschuw "SELinux staat op Enforcing; controleer de context van $DOELPAD en de fpm-socket"
    fi
    if command -v aa-status >/dev/null && aa-status --enabled 2>/dev/null; then
        meld "AppArmor is actief; let op profielen voor php-fpm en de webserver"
    fi

    # Een nieuwe release brengt soms nieuwe instellingen mee. config:cache bakt
    # een ontbrekende sleutel gewoon op zijn default in, en dat merk je pas als
    # iemand een verkeerde meldtermijn ziet.
    if [[ -f "$BOOM/.env.example" ]]; then
        local sleutel missend=()
        while read -r sleutel; do
            [[ -z $sleutel ]] && continue
            grep -qE "^[[:space:]]*$sleutel[[:space:]]*=" "$ENVBESTAND" || missend+=("$sleutel")
        done < <(grep -E '^[A-Z][A-Z0-9_]*=' "$BOOM/.env.example" | cut -d= -f1 | sort -u)
        if [[ ${#missend[@]} -gt 0 ]]; then
            waarschuw "sleutels uit .env.example die in uw .env ontbreken (de standaardwaarde wint dan stil):"
            printf '     %s\n' "${missend[@]}"
        fi
    fi
}

controleer_schijfruimte() { # <pad> <benodigd in kB>
    local vrij; vrij=$(df -Pk "$1" | awk 'NR==2 {print $4}')
    [[ $vrij -ge $2 ]] || fout "te weinig vrije ruimte op $1: $((vrij/1024)) MB vrij, $(($2/1024)) MB nodig"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Uitpakken en verifiëren
# ═════════════════════════════════════════════════════════════════════════════

pak_uit() {
    stap "Uitpakken in $UITPAKMAP"
    # Een verse mktemp-map: /var/tmp is world-writable en sticky, dus een vast
    # pad zou een symlink-aanval uitnodigen.
    WERKMAP=$(mktemp -d "${UITPAKMAP%/}/ezisms-deploy.XXXXXXXX")
    chmod 0700 "$WERKMAP"
    LOGBESTAND="$WERKMAP/deploy-$(date +%Y%m%dT%H%M%S).log"
    exec > >(tee -a "$LOGBESTAND") 2>&1
    meld "werkmap: $WERKMAP"

    controleer_schijfruimte "$WERKMAP" $(( $(stat -c%s "$TARBAL") / 1024 * 4 ))

    # Geen absolute paden en geen .. in het archief. tar weigert dat zelf ook,
    # maar niet elke tar en niet elke versie.
    if tar tzf "$TARBAL" | grep -qE '^/|(^|/)\.\./'; then
        fout "het archief bevat absolute paden of '..'; dat wordt niet uitgepakt"
    fi
    tar xzf "$TARBAL" -C "$WERKMAP"

    local mappen; mappen=$(find "$WERKMAP" -mindepth 1 -maxdepth 1 -type d)
    [[ $(wc -l <<<"$mappen") -eq 1 ]] || fout "het archief heeft niet precies één maplaag"
    BOOM=$mappen
    goed "uitgepakt: $(basename "$BOOM")"
}

controleer_manifest() {
    stap "Manifest"
    [[ -f "$BOOM/MANIFEST.json" ]] || fout "MANIFEST.json ontbreekt; dit is geen pakket van builddistr.sh"

    PAKKET_VERSIE=$(manifest_waarde versie)
    PAKKET_COMMIT=$(manifest_waarde commit)
    local minimaal; minimaal=$(manifest_waarde minimale_deploy_versie)

    # Een oude deploy.sh mag een nieuwer pakket niet half uitrollen.
    if [[ $(printf '%s\n%s\n' "$minimaal" "$DEPLOY_VERSIE" | sort -V | head -1) != "$minimaal" ]]; then
        fout "dit pakket vraagt deploy.sh $minimaal of hoger (dit is $DEPLOY_VERSIE). Haal de nieuwe eruit met:
    tar xzf $TARBAL -O '$(basename "$BOOM")/scripts/deploy.sh' > deploy.sh"
    fi

    meld "versie: $PAKKET_VERSIE   commit: ${PAKKET_COMMIT:0:12}"
    meld "gebouwd op: $(manifest_waarde gebouwd_op) met php $(manifest_waarde bouwhost_php)"
    local gebouwd; gebouwd=$(manifest_waarde gebouwd | tr '\n' ' ')
    if [[ -n ${gebouwd// } ]]; then
        meld "meegeleverd gebouwd: $gebouwd"
    else
        waarschuw "pakket zonder vendor/ en public/build; die moeten hier gebouwd worden"
        command -v composer >/dev/null || fout "composer ontbreekt en het pakket bevat geen vendor/"
        command -v npm >/dev/null      || fout "npm ontbreekt en het pakket bevat geen public/build"
    fi

    stap "Integriteit van de uitgepakte bestanden"
    ( cd "$BOOM" && sha256sum -c --quiet SHA256SUMS ) \
        || fout "een of meer bestanden komen niet overeen met SHA256SUMS"
    goed "$(wc -l <"$BOOM/SHA256SUMS") bestanden geverifieerd"
}

# ═════════════════════════════════════════════════════════════════════════════
#  De norm: stempel, .env en database moeten hetzelfde zeggen
# ═════════════════════════════════════════════════════════════════════════════

stempelbestand() { printf '%s' "$DOELPAD/shared/installatie/normprofiel"; }

# GEDEELD MET deploy-docker.sh :: profiel_uit_database()
# Het profiel zoals de database het laat zien. Onafhankelijk van .env en van de
# stempel, want beide zijn tekstregels die iemand kan wijzigen; de database is
# het resultaat van wat er werkelijk is geseed.
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

# GEDEELD MET deploy-docker.sh :: normcontrole()
normcontrole() {
    stap "Normcontrole"
    local stempel="" uit_db
    [[ -f $(stempelbestand) ]] && stempel=$(env_waarde "$(stempelbestand)" profiel "")
    uit_db=$(profiel_uit_database)

    meld ".env:     $NORM_ENV"
    meld "stempel:  ${stempel:-<geen>}"
    meld "database: $uit_db"

    if [[ -n $stempel && $stempel != "$NORM_ENV" ]]; then
        fout "ISMS_NORM in .env ($NORM_ENV) wijkt af van de normstempel ($stempel).
Een profielwissel is geen uitrol maar een herinstallatie: bestaande SoA-beoordelingen
gelden voor de oude controlset. Stel eerst vast welke van de twee klopt."
    fi
    if [[ $uit_db != leeg && $uit_db != "$NORM_ENV" ]]; then
        fout "de database vertelt een ander verhaal dan .env: $uit_db tegenover $NORM_ENV.
Ga niet verder; een uitrol maakt dit niet beter."
    fi
    goed "profiel eenduidig: $NORM_ENV"
}

# GEDEELD MET deploy-docker.sh :: controleer_geseed_profiel()
# Meteen na het seeden: heeft de installatie het profiel gekregen dat .env vraagt?
# De rookproef controleert dit ook, maar die draait pas ná het omschakelen en het
# zetten van de normstempel. Dan staat er een draaiende installatie met een
# controlset die niet bij haar eigen stempel past, en daar komt geen tweede uitrol
# meer langs: normcontrole() kapt die af, en NormprofielSeeder laat een eenmaal
# vastgelegd profiel staan. Hier is de schade nog een afgebroken uitrol.
controleer_geseed_profiel() {
    stap "Profielcontrole na het seeden"
    local uit_db; uit_db=$(profiel_uit_database)
    meld ".env:     $NORM_ENV"
    meld "database: $uit_db"

    [[ $uit_db == "$NORM_ENV" ]] || fout "de seeders leverden een ander profiel op dan .env vraagt: $uit_db tegenover $NORM_ENV.
Er is nog niets omgeschakeld; deze installatie is nog niet in gebruik.
Controleer ISMS_NORM in $ENVBESTAND en bouw de database opnieuw op — opnieuw uitrollen
herstelt dit niet, want een eenmaal vastgelegd profiel wordt nooit omgezet."
    goed "profiel geseed zoals gevraagd: $NORM_ENV"
}

# GEDEELD MET deploy-docker.sh :: zet_normstempel()
zet_normstempel() {
    local bestand; bestand=$(stempelbestand)
    cat >"$bestand" <<EOF
# Normstempel van deze installatie — gezet door deploy.sh, niet met de hand wijzigen.
# Van norm wisselen betekent de database opnieuw opbouwen; zie voorstel-deploy.md §9.1.
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
#  Verschilrapport — wat gaat deze release veranderen
# ═════════════════════════════════════════════════════════════════════════════

verschilrapport() {
    stap "Verschilrapport"
    local huidig="$DOELPAD/current"

    if [[ -L $huidig ]]; then
        local nu; nu=$(BOOM="$huidig" manifest_waarde versie 2>/dev/null || echo onbekend)
        meld "draait nu: $nu ($(basename "$(readlink -f "$huidig")"))"
    fi
    meld "wordt:     $PAKKET_VERSIE (${PAKKET_COMMIT:0:12})"

    if [[ -d $huidig ]]; then
        meld ""
        meld "Openstaande migraties:"
        ( cd "$huidig" && als_app "$PHP" artisan migrate:status --pending 2>/dev/null ) \
            | sed 's/^/     /' || meld "     (niet op te vragen)"
    fi

    meld ""
    meld "Seeddata (installatie versus pakket):"
    local bestand naam
    for bestand in "$BOOM"/database/seeders/data/*.json; do
        naam=$(basename "$bestand")
        # De controlset van het andere profiel wordt bij de uitrol weggegooid
        # (verwijder_andere_controlsets). Hem hier melden als "nieuw in deze
        # release" zet de beheerder op een spoor dat nergens heen loopt.
        if [[ $naam == maatregelen-*.json && $naam != "maatregelen-$NORM_ENV.json" ]]; then
            continue
        fi
        if [[ -f "$huidig/database/seeders/data/$naam" ]]; then
            if cmp -s "$bestand" "$huidig/database/seeders/data/$naam"; then
                meld "     $naam: gelijk"
            else
                meld "     $naam: VERSCHILT — de installatieversie blijft staan (--seeddata=release om te vervangen)"
            fi
        else
            meld "     $naam: nieuw in deze release, wordt geplaatst"
        fi
    done
    for bestand in "$huidig"/database/seeders/data/*.json; do
        [[ -e $bestand ]] || continue
        naam=$(basename "$bestand")
        [[ -f "$BOOM/database/seeders/data/$naam" ]] \
            || meld "     $naam: alleen op de installatie (eigen normtekst?), blijft staan"
    done
}

# ═════════════════════════════════════════════════════════════════════════════
#  Bouwstenen van de uitrol
# ═════════════════════════════════════════════════════════════════════════════

# De code-eigenaar. Draait niets en logt nergens in; hij bestaat alleen zodat de
# applicatie haar eigen bestanden niet kan overschrijven.
maak_gebruiker() {
    stap "Code-eigenaar $GEBRUIKER:$GROEP"
    if ! getent group "$GROEP" >/dev/null; then
        groupadd --system "$GROEP"
        goed "groep $GROEP aangemaakt"
    fi
    if ! getent passwd "$GEBRUIKER" >/dev/null; then
        useradd --system --gid "$GROEP" --home-dir "$DOELPAD" \
                --shell /usr/sbin/nologin --comment "ISMS-code ($DOELPAD)" "$GEBRUIKER"
        goed "gebruiker $GEBRUIKER aangemaakt (geen shell, geen login)"
    else
        meld "gebruiker $GEBRUIKER bestaat al"
    fi
}

maak_shared() {
    stap "shared/ aanleggen"
    local map
    mkdir -p "$DOELPAD/releases" "$DOELPAD/shared/installatie" \
             "$DOELPAD/shared/seeddata"
    # Het storage-skelet komt uit het pakket, zodat het meebeweegt met de app.
    # Alleen de mappen: het pakket draagt geen .gitignore meer (builddistr.sh
    # ruimt de sporen van versiebeheer op), en shared/storage is geen werkkopie.
    while read -r map; do
        [[ -z $map ]] && continue
        mkdir -p "$DOELPAD/shared/$map"
    done <<<"$(manifest_waarde storage_skelet)"
    goed "lege storage aangelegd"

    mv "$DOELPAD/.env" "$DOELPAD/shared/.env"
    goed ".env verplaatst naar shared/ (niet gekopieerd — anders staan er twee)"
    ENVBESTAND="$DOELPAD/shared/.env"
}

schrijf_release() {
    RELEASE="$DOELPAD/releases/$(date +%Y-%m-%dT%H-%M-%S)"
    stap "Release wegschrijven: $(basename "$RELEASE")"

    # /var/tmp en het doelpad staan zelden op hetzelfde filesystem, dus een `mv`
    # zou een kopieerslag zijn en geen atomaire hernoeming. Atomair is alleen de
    # symlinkomzetting straks; die blijft binnen één filesystem.
    controleer_schijfruimte "$DOELPAD" $(( $(du -sk "$BOOM" | cut -f1) * 2 ))
    mkdir -p "$RELEASE"
    rsync -a "$BOOM/" "$RELEASE/"
    goed "$(du -sh "$RELEASE" | cut -f1) geschreven"
}

# GEDEELD MET deploy-docker.sh :: neem_seeddata_over() — daar vereenvoudigd
# Wat er op de installatie ligt, blijft liggen. De reden is dwingend: de
# installatie voert haar eigen normteksten in `maatregelen-<profiel>.json` in,
# ónder dezelfde naam als het meegeleverde bestand. Een release die dat bestand
# uit het pakket plaatst, vervangt die teksten door de meegeleverde mededeling —
# en de eerstvolgende seed schrijft dat door naar de database.
neem_seeddata_over() {
    stap "Seeddata"
    local doel="$RELEASE/database/seeders/data"
    local huidig="$DOELPAD/current/database/seeders/data"
    local bestand naam

    if [[ $MODUS == release && -d $huidig && $SEEDDATA == installatie ]]; then
        for bestand in "$huidig"/*; do
            [[ -f $bestand ]] || continue
            naam=$(basename "$bestand")
            if [[ -f "$doel/$naam" ]] && ! cmp -s "$bestand" "$doel/$naam"; then
                meld "$naam: installatieversie behouden (het pakket brengt een andere mee)"
            elif [[ ! -f "$doel/$naam" ]]; then
                meld "$naam: alleen op de installatie, blijft"
            fi
            cp -p "$bestand" "$doel/$naam"
        done
    elif [[ $SEEDDATA == release ]]; then
        waarschuw "--seeddata=release: het pakket wint van wat er op de installatie staat"
    fi

    # Eerder meegegeven eigen normtekst leeft in shared/seeddata en overleeft zo
    # elke release.
    if compgen -G "$DOELPAD/shared/seeddata/*" >/dev/null; then
        cp -p "$DOELPAD/shared/seeddata/"* "$doel/"
        meld "eigen seedbestanden uit shared/seeddata geplaatst"
    fi

    # --norm wint altijd, en wordt bewaard voor de volgende keer.
    if [[ -n $NORMMAP ]]; then
        [[ -d $NORMMAP ]] || fout "--norm=$NORMMAP is geen map"
        for bestand in "$NORMMAP"/*.json; do
            [[ -f $bestand ]] || continue
            naam=$(basename "$bestand")
            cp -p "$bestand" "$DOELPAD/shared/seeddata/$naam"
            cp -p "$bestand" "$doel/$naam"
            goed "eigen normbestand geplaatst: $naam"
        done
    fi

    verwijder_andere_controlsets
}

# GEDEELD MET deploy-docker.sh :: verwijder_andere_controlsets()
# Eén controlset per installatie. Het pakket brengt ze allebei mee, want het
# weet niet welke norm de ontvanger volgt; deze installatie weet dat wel.
#
# Het andere bestand is niet alleen overbodig, het is verwarrend: wie het
# openslaat bewerkt een bestand dat nooit geseed wordt, en typt dus normtekst
# over die nergens aankomt. Daarom onvoorwaardelijk en bij élke uitrol —
# weggooien bij alleen `--eerste` zou het bestand bij de volgende release
# gewoon terugbrengen, want de lus hierboven kopieert alleen wat er óp de
# installatie staat.
#
# Geen `case` op het profiel: het te behouden bestand heet altijd
# `maatregelen-$NORM_ENV.json`, dus het overbodige is "alle andere". Zo hoeft
# deze functie bij een derde profiel niet mee te veranderen.
verwijder_andere_controlsets() {
    local bestand
    for bestand in "$RELEASE"/database/seeders/data/maatregelen-*.json; do
        [[ -f $bestand ]] || continue
        [[ $(basename "$bestand") == "maatregelen-$NORM_ENV.json" ]] && continue
        rm -f "$bestand"
        meld "andere controlset verwijderd: $(basename "$bestand")"
    done
    meld "deze installatie houdt: maatregelen-$NORM_ENV.json"
}

zet_symlinks() {
    stap "Symlinks naar shared/"
    ln -sfn "$DOELPAD/shared/.env"     "$RELEASE/.env"
    rm -rf  "$RELEASE/storage"        && ln -sfn "$DOELPAD/shared/storage" "$RELEASE/storage"
    # Geen symlink meer voor public/toetsen: toetsbestanden staan sinds 01e in
    # storage/app/private/toetsen — dus binnen shared/storage — en worden door
    # de applicatie uitgeserveerd in plaats van door nginx.

    # Wat `artisan storage:link` zou doen, maar dan door root en met een pad
    # naar shared/ in plaats van naar de release. Anders wijst de symlink na de
    # volgende uitrol naar een opgeruimde release.
    ln -sfn "$DOELPAD/shared/storage/app/public" "$RELEASE/public/storage"

    goed ".env, storage/ en public/storage wijzen naar shared/"
}

# Twee rollen, en dat is met opzet:
#
#   $GEBRUIKER    (ezisms)   bezit de code. Draait niets. Het webproces kan zijn
#                            eigen applicatiecode dus niet herschrijven — dat is
#                            de winst die dit account oplevert (A.8.19).
#   $WEBGEBRUIKER (www-data) draait php-fpm, de scheduler en artisan, en bezit
#                            alles waar de applicatie in schrijft.
#
# Eén schrijvend account, dus geen ACL's. Dat geldt sinds 13-08-2026 ook op de
# ontwikkelmachine — daar draaide php-fpm als www-data naast een artisan als
# leo, met ACL's ertussen die op de mask stilvielen; sindsdien heeft die site
# een eigen fpm-pool die als leo draait. Zelfde principe, andere identiteit.
zet_rechten() {
    stap "Eigendom en rechten"
    chown -Rh "$GEBRUIKER:$GROEP" "$RELEASE"
    find "$RELEASE" -type d -exec chmod 0755 {} +
    find "$RELEASE" -type f -exec chmod 0644 {} +
    chmod 0755 "$RELEASE/artisan"
    [[ -d "$RELEASE/scripts" ]] && chmod 0755 "$RELEASE"/scripts/*.sh 2>/dev/null || true

    # De webserver serveert hieruit.
    chown -Rh "$WEBGEBRUIKER:$WEBGROEP" "$RELEASE/public"

    # Alles waar de applicatie in schrijft, is van de applicatiegebruiker.
    chown -Rh "$WEBGEBRUIKER:$WEBGROEP" "$DOELPAD/shared/storage" "$RELEASE/bootstrap/cache"
    find "$DOELPAD/shared/storage" "$RELEASE/bootstrap/cache" -type d -exec chmod 0750 {} +
    find "$DOELPAD/shared/storage" "$RELEASE/bootstrap/cache" -type f -exec chmod 0640 {} +

    # .env draagt APP_KEY en het databasewachtwoord: alleen de applicatie leest
    # hem. 0640 en niet 0600, omdat `artisan key:generate` bij --eerste schrijft.
    chown -h "$WEBGEBRUIKER:$WEBGROEP" "$DOELPAD/shared/.env"; chmod 0640 "$DOELPAD/shared/.env"

    chown -Rh "$GEBRUIKER:$GROEP" "$DOELPAD/shared/seeddata" "$DOELPAD/shared/installatie"
    chown -h  "$GEBRUIKER:$GROEP" "$DOELPAD/releases" "$DOELPAD"

    goed "code $GEBRUIKER:$GROEP (alleen lezen voor de applicatie)"
    goed "public/, storage/ en .env $WEBGEBRUIKER:$WEBGROEP (0750/0640)"
}

# De fpm-service wordt herladen om de opcache leeg te maken. Dat is een graceful
# reload van de hele dienst: draaien er meer sites op deze host, dan merken die
# er niets van behalve dat hun opcache ook opnieuw opbouwt.
herlaad_fpm() { systemctl reload "$FPM_DIENST" 2>/dev/null || systemctl restart "$FPM_DIENST"; }

# ── Crontab ──────────────────────────────────────────────────────────────────
# Eén regel die elke minuut de scheduler wakker maakt. De planning zelf staat in
# routes/console.php; zou hier een regel per commando staan, dan lopen die twee
# uit elkaar. De grep filtert alléén het "niets te doen"-bericht, anders groeit
# het log met 1440 regels per dag.
#
# De regel staat in de crontab van $WEBGEBRUIKER en niet van $GEBRUIKER: de
# scheduler schrijft in storage/ (taken, logs, KPI-metingen) en dat moet dezelfde
# gebruiker zijn als die van php-fpm. Twee accounts die er met verschillende
# umasks in schrijven is precies wat je hier niet wilt — dat vraagt om ACL's, en
# die vallen stil zodra één van beide een map op 0700 aanmaakt (de mask gaat dan
# op ---). Op de ontwikkelmachine is die constructie 13-08-2026 juist daarom
# opgeruimd; zie ota/README.md, "Schrijfrechten op storage/".
cron_markering() { printf '# ezisms-scheduler %s\n' "$DOELPAD"; }
cron_regel() {
    printf "* * * * * cd %s/current && %s artisan schedule:run 2>&1 | grep -vE '^[[:space:]]*\$|No scheduled commands are ready to run' >> %s/shared/storage/logs/scheduler.log\n" \
        "$DOELPAD" "$PHP" "$DOELPAD"
}

# De huidige crontab, of niets. `crontab -l` geeft exitcode 1 als de gebruiker
# er nog geen heeft — bij een eerste installatie dus altijd — en met `set -e` en
# `pipefail` breekt het script daarop af. Vandaar de `|| true`.
cron_nu() { crontab -u "$WEBGEBRUIKER" -l 2>/dev/null || true; }

# De regel die nú in de crontab staat, direct onder onze markering.
huidige_cron_regel() {
    cron_nu | awk -v m="$(cron_markering)" 'gevonden {print; exit} $0 == m {gevonden=1}'
}

# De crontab zonder ons blok: de markering én de regel eronder eruit, de rest
# ongemoeid. Er kan meer in staan dan alleen dit ISMS.
cron_zonder_blok() {
    cron_nu | awk -v m="$(cron_markering)" '$0 == m {overslaan=2} overslaan {overslaan--; next} {print}'
}

zet_cron() {
    stap "Crontab van $WEBGEBRUIKER"
    local huidig; huidig=$(huidige_cron_regel)

    if [[ -z $huidig ]]; then
        { cron_zonder_blok; cron_markering; cron_regel; } | crontab -u "$WEBGEBRUIKER" -
        goed "crontabregel toegevoegd"
    elif [[ $huidig == "$(cron_regel | head -1)" ]]; then
        goed "crontabregel staat er al en klopt"
    elif [[ $HERSTEL_CRON == ja ]]; then
        { cron_zonder_blok; cron_markering; cron_regel; } | crontab -u "$WEBGEBRUIKER" -
        goed "crontabregel herschreven (--herstel-cron)"
    else
        # Iemand kan een goede reden hebben gehad; stil overschrijven van een
        # regel die elke minuut draait is geen deploy-taak.
        waarschuw "de crontabregel wijkt af van wat dit script bedoelt:"
        meld "     nu:      $huidig"
        meld "     bedoeld: $(cron_regel | head -1)"
        meld "     laat staan, of herschrijf met --herstel-cron"
    fi
}

# ── Database ─────────────────────────────────────────────────────────────────

# GEDEELD MET deploy-docker.sh :: maak_dump()
maak_dump() {
    command -v mysqldump >/dev/null || fout "mysqldump ontbreekt; zonder dump geen migratie (of gebruik --geen-migraties)"
    local doel="$DOELPAD/shared/installatie/dump-$(date +%Y%m%dT%H%M%S).sql.gz"
    stap "Databasedump vóór de migratie"
    # --no-tablespaces: vanaf MySQL 5.7.31/8.0.21 leest mysqldump standaard
    # INFORMATION_SCHEMA.FILES, en dat vraagt het serverbrede PROCESS-recht. Dat
    # recht laat een account álle draaiende queries van álle gebruikers zien;
    # dat geef je de applicatiegebruiker van een ISMS niet om een dump te kunnen
    # maken. De optie slaat het over. Er valt niets weg: deze database gebruikt
    # geen eigen tablespaces, en CREATE TABLESPACE hoort sowieso niet thuis in
    # een dump die in een andere schema teruggezet kan worden. De optie bestaat
    # al sinds MySQL 5.1.14 en dus ook in MariaDB.
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
Zonder bruikbare dump wordt er niet gemigreerd. Controleer de schijfruimte in
$DOELPAD/shared/installatie en de rechten van $DB_USERNAME op $DB_DATABASE."
    fi

    chown "$GEBRUIKER:$GROEP" "$doel"; chmod 0600 "$doel"
    goed "dump: $doel ($(du -h "$doel" | cut -f1))"
    MIGRATIE_GEDRAAID=ja
}

# GEDEELD MET deploy-docker.sh :: migreer_en_seed()
migreer_en_seed() {
    if [[ $MIGRATIES == nee ]]; then
        waarschuw "--geen-migraties: het schema wordt niet bijgewerkt"
        waarschuw "doe dit met de hand, ná een dump:  cd $DOELPAD/current && $PHP artisan migrate --force"
        return
    fi
    [[ $MODUS == release ]] && maak_dump

    stap "Migreren"
    artisan migrate --force

    # De referentiedata-seeders zijn idempotent, maar niet betekenisloos: een
    # gewijzigde RolPermissieSeeder verandert de rechten van bestaande
    # gebruikers. Daarom draaien ze mee én staat het in het log.
    stap "Referentiedata seeden"
    artisan db:seed --force
}

# ── Demo-inhoud ──────────────────────────────────────────────────────────────
#
# GEDEELD MET deploy-docker.sh :: demo_vullen() — daar zonder de vraag
# `isms:demo-vul` bouwt het FruitBV-scenario op: 23 maanden aan gebeurtenissen,
# gebruikers, risico's, bewijsstukken. Het commando begint met het legen van
# alle tabellen en zet daarna de referentiedata terug met db:seed — dus ook de
# maatregelen, inclusief eventuele eigen normtekst uit shared/seeddata. Draai
# het dáárom pas ná neem_seeddata_over en migreer_en_seed.
#
# Er wordt om bevestiging gevraagd in plaats van gewoon gevuld. De applicatie
# bewaakt zelf al de grens (alleen local en demo), maar APP_ENV=local op een
# uitgerolde host is net zo vaak een vergissing als een keuze, en dan scheelt
# het één uitrol tussen "de demo verversen" en "alles kwijt". Met --demo-vul is
# het antwoord al gegeven; zonder terminal en zonder die optie gebeurt er niets.
demo_vullen() {
    if [[ $DEMO == nee ]]; then
        return 0
    fi

    # Buiten local en demo is dit geen keuze van dit script: het commando zelf
    # weigert daar. Alleen wie er expliciet om vroeg hoort dat te merken.
    if [[ $APP_ENV != local && $APP_ENV != demo ]]; then
        if [[ $DEMO == ja ]]; then
            fout "--demo-vul kan niet: APP_ENV is '$APP_ENV'.
isms:demo-vul draait alleen in 'local' of 'demo', want het wist de hele database."
        fi
        return 0
    fi

    stap "Demo-inhoud"

    local map fixtures
    map=$(manifest_waarde demofixtures)
    fixtures="$RELEASE/$map"
    if [[ -z $map || ! -d $fixtures ]]; then
        if [[ $DEMO == ja ]]; then
            fout "--demo-vul kan niet: deze tarbal bevat geen demofixtures.
Bouw hem opnieuw met een builddistr.sh die saasdemo/data meelevert."
        fi
        waarschuw "deze tarbal bevat geen demofixtures; vullen wordt overgeslagen"
        return 0
    fi

    # Het scenario is geschreven voor de 93 maatregelen van ISO 27001. Onder
    # NEN 7510 weigert het commando; dat hoeft niet de hele uitrol te kosten.
    if [[ $NORM_ENV != iso27001 ]]; then
        if [[ $DEMO == ja ]]; then
            fout "--demo-vul kan niet op een $NORM_ENV-installatie.
Het FruitBV-scenario hoort bij de controlset van ISO 27001."
        fi
        waarschuw "profiel is $NORM_ENV; het demoscenario is ISO-only en wordt overgeslagen"
        return 0
    fi

    if [[ $DEMO == vragen ]]; then
        # De vraag eerst kunnen stellen, dan pas stellen: anders leest een
        # beheerder in het log een waarschuwing over een wissing die nooit
        # aan de orde was.
        if [[ ! -t 0 ]]; then
            meld "geen terminal om te bevestigen; niet gevuld (gebruik --demo-vul of --geen-demo)"
            return 0
        fi
        cat <<EOF

   APP_ENV is '$APP_ENV'. Dit script kan het ISMS vullen met het
   FruitBV-demoscenario.

   $(printf '\033[1m')Daarbij wordt eerst de hele database geleegd.$(printf '\033[0m') Alles wat er nu in
   staat — gebruikers, beoordelingen, bewijs — is daarna weg. De
   referentiedata wordt opnieuw geseed; de rest niet.

EOF
        local antwoord
        read -r -p "   Typ 'vullen' om door te gaan, iets anders om over te slaan: " antwoord
        if [[ $antwoord != vullen ]]; then
            meld "overgeslagen; de database blijft zoals hij is"
            return 0
        fi
    fi

    artisan isms:demo-vul --fixtures="$fixtures"
    DEMO_GEVULD=ja
    goed "demo gevuld uit $map"
    meld "inloggegevens: $DOELPAD/shared/storage/app/private/demo-inloggegevens.txt"
    meld "die staan bewust niet in dit log; het bestand is 0600 en van $WEBGEBRUIKER"
}

# ── Bedient de webserver deze installatie, en met welke gebruiker? ───────────
#
# nginx kan de draaigebruiker van PHP niet zetten: `user` in nginx.conf gaat over
# de nginx-workers zelf. Die gebruiker staat in de fpm-pool (user = / group =),
# en de vhost kiest met fastcgi_pass alleen wélke pool. Omdat deze installatie
# de standaardpool gebruikt, klopt dat in de regel vanzelf — maar niet als er op
# deze host een tweede pool onder een ander account draait.
#
# Twee dingen worden daarom nagelopen, en geen van beide wijzigt iets:
#   1. wijst er überhaupt een vhost naar <doelpad>/current/public? Zo niet, dan
#      komt een uitrol niet aan: de symlink wisselt wel, de site niet.
#   2. draait de pool achter die vhost als de gebruiker die storage/ bezit?

# Het server-blok uit de nginx-config waarvan de root onze release-symlink is.
nginx_serverblok() { # <wortelpad>
    nginx -T 2>/dev/null | awk -v wortel="$1" '
        /^[[:space:]]*server[[:space:]]*\{/ && !in_blok { in_blok = 1; buf = ""; diepte = 0 }
        in_blok {
            buf = buf $0 "\n"
            diepte += gsub(/\{/, "{"); diepte -= gsub(/\}/, "}")
            if (diepte == 0) { if (index(buf, wortel) > 0) printf "%s", buf; in_blok = 0 }
        }'
}

# De socket waar de webserver PHP heen stuurt voor deze installatie.
#
# "Niets gevonden" is hier een geldige uitkomst — bij --eerste bestaat de vhost
# nog niet — en geen fout. Vandaar de `|| true` op elke pipeline: `grep` geeft
# exitcode 1 als er niets matcht, en met `set -e` en `pipefail` zou dit script
# dan afbreken op precies het geval waarvoor de aanroeper een waarschuwing heeft
# klaarstaan.
socket_uit_webserverconfig() {
    local wortel="$DOELPAD/current/public" bestand
    case "$WEBSERVER" in
        nginx)
            nginx_serverblok "$wortel" \
                | grep -oE 'fastcgi_pass[[:space:]]+unix:[^;]+' \
                | head -1 | sed -E 's/.*unix://' || true ;;
        apache2|httpd)
            bestand=$(grep -rl -- "$wortel" /etc/apache2 /etc/httpd 2>/dev/null | head -1) || true
            if [[ -n $bestand ]]; then
                grep -oE 'proxy:unix:[^|]+' "$bestand" | head -1 | sed -E 's/^proxy:unix://' || true
            fi ;;
    esac
    return 0
}

# Welke gebruiker draait er in de pool die op deze socket luistert?
#
# De paden worden eerst genormaliseerd: nginx schrijft doorgaans
# /var/run/php/…sock en de pool /run/php/…sock. Dat is dezelfde socket — /var/run
# is een symlink naar /run — maar als tekst verschillen ze, en dan zou deze
# controle stilletjes niets vinden op precies het moment dat hij moet aanslaan.
gebruiker_van_pool() { # <socketpad>
    local doel bestand luistert
    doel=$(readlink -f "$1")
    for bestand in "$POOLMAP"/*.conf; do
        [[ -f $bestand ]] || continue
        luistert=$(awk -F= '/^[[:space:]]*listen[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$bestand")
        [[ -n $luistert && $luistert == /* ]] || continue
        if [[ $(readlink -f "$luistert") == "$doel" ]]; then
            awk -F= '/^[[:space:]]*user[[:space:]]*=/ {gsub(/[[:space:]]/, "", $2); print $2; exit}' "$bestand"
            return 0
        fi
    done
    return 1
}

controleer_koppeling() {
    stap "Webserver → applicatie → gebruiker"
    local socket pool_gebruiker

    socket=$(socket_uit_webserverconfig)
    if [[ -z $socket ]]; then
        if [[ $MODUS == eerste ]]; then
            waarschuw "nog geen vhost gevonden die naar $DOELPAD/current/public wijst —"
            waarschuw "dat is verwacht bij een eerste installatie; zie het slotscherm hieronder"
        else
            waarschuw "geen vhost gevonden die naar $DOELPAD/current/public wijst."
            waarschuw "Draait deze installatie via een ander pad, dan werkt het releasemodel niet:"
            waarschuw "een uitrol wisselt dan wel de symlink, maar de site blijft op de oude boom."
        fi
        return
    fi
    meld "vhost stuurt PHP naar: $socket"

    if ! pool_gebruiker=$(gebruiker_van_pool "$socket"); then
        waarschuw "geen poolbestand in $POOLMAP dat op $socket luistert; kan de gebruiker niet vaststellen"
        return
    fi
    meld "die pool draait als: $pool_gebruiker"

    if [[ $pool_gebruiker != "$WEBGEBRUIKER" ]]; then
        fout "de webserver stuurt PHP naar een pool die als '$pool_gebruiker' draait,
terwijl storage/ van '$WEBGEBRUIKER' is. De applicatie kan dan niet schrijven.
Kies één van beide:
  - zet fastcgi_pass in de vhost naar de pool van $WEBGEBRUIKER${FPM_SOCKET:+  (unix:$FPM_SOCKET)}, of
  - rol opnieuw uit met WEBGEBRUIKER=$pool_gebruiker, dan volgt het eigendom die pool."
    fi
    goed "de site draait als $WEBGEBRUIKER, net als storage/"

    # De empirische tegencontrole: wie heeft er werkelijk geschreven? Dit vangt
    # ook een artisan die ooit als root of als een ander account is gedraaid.
    local vreemd
    vreemd=$(find "$DOELPAD/shared/storage" ! -user "$WEBGEBRUIKER" -print -quit 2>/dev/null) || true
    if [[ -n $vreemd ]]; then
        fout "in shared/storage staat werk van een andere gebruiker: $vreemd (van $(stat -c%U "$vreemd")).
Herstel met:  chown -Rh $WEBGEBRUIKER:$WEBGROEP $DOELPAD/shared/storage"
    fi
    goed "alles in shared/storage is van $WEBGEBRUIKER"
}

# ── Het versienummer ─────────────────────────────────────────────────────────

# ── GEDEELD MET docker/ezisms/entrypoint.sh :: stap 4b ───────────────────────
# APP_VERSION komt achter de productnaam in de zijbalk te staan. Hij mag in .env
# gezet worden, maar hoeft niet — en dat is met opzet: `shared/.env` overleeft
# elke release, dus die sleutel gaat bij een upgrade niet vanzelf mee. Een scherm
# dat de vórige versie noemt is misleidender dan een scherm dat er geen noemt.
#
# Laat .env hem leeg, dan komt hij uit het manifest van de release die uitgerold
# wordt. Dat is per definitie de waarheid over deze installatie.
#
# Móet vóór `config:cache` draaien, anders bakt die de lege waarde in. `export`
# volstaat: als_app geeft de omgeving door aan `runuser`, en die laat hem staan.
bepaal_versie() {
    local uit_env; uit_env=$(env_waarde "$ENVBESTAND" APP_VERSION "")
    local uit_manifest; uit_manifest=$(manifest_waarde tag)

    # Geen tag (een tussentijdse bouw): dan de releaseversie uit de tarbalnaam.
    [[ -n $uit_manifest ]] || uit_manifest=$(manifest_waarde versie)

    if [[ -n $uit_env ]]; then
        # Wel gezet, maar niet wat er draait. Niet stilzwijgend overschrijven —
        # het is een bewuste invoer van de beheerder — maar wél zeggen, want dit
        # is precies het geval waarin het scherm gaat liegen.
        if [[ -n $(manifest_waarde tag) && $uit_env != $(manifest_waarde tag) ]]; then
            waarschuw "APP_VERSION in .env ($uit_env) is niet de tag van deze release ($(manifest_waarde tag)); de zijbalk toont uw eigen waarde."
        fi

        return 0
    fi

    if [[ -n $uit_manifest ]]; then
        export APP_VERSION="$uit_manifest"
        meld "versie uit het manifest: $APP_VERSION"
    fi
}

# ── Bewaking ─────────────────────────────────────────────────────────────────

# ── GEDEELD MET deploy-docker.sh :: controleer_hartslag() ────────────────────
# Heeft de geplande bewaking gedraaid terwijl deze machine weg was
# (implementatie/00m §8)? Dit is de opstartkant; de andere kant is het dagelijkse
# `isms:controleer-hartslag` uit routes/console.php. Allebei nodig: dit vangt
# "de machine was er niet", dat vangt "één commando faalde".
#
# Ná migreer_en_seed, want `db:seed` zet het nulpunt van een verse installatie —
# zonder dat nulpunt meldt de eerste uitrol een gat sinds 1970 (00m §3).
controleer_hartslag() {
    stap "Bewaking"

    local uitvoer
    if ! uitvoer=$(artisan isms:controleer-hartslag --stil 2>&1); then
        # Niet blokkerend, en dat is een bewuste grens: dit is een controle óp de
        # bewaking, geen voorwaarde voor de uitrol. Een uitrol afbreken omdat de
        # gatendetectie zelf struikelt maakt een klein probleem groot.
        waarschuw "de hartslagcontrole liep vast; de uitrol gaat door"
        HARTSLAG="controle mislukt — zie het deploylog"
        return 0
    fi

    printf '%s\n' "$uitvoer" | sed 's/^/   /'

    # De eerste regel is de samenvatting; die gaat mee naar het slotscherm.
    HARTSLAG=$(printf '%s\n' "$uitvoer" | head -1)
}

# ── Rookproef ────────────────────────────────────────────────────────────────

rookproef() {
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

    # Vóór de HTTP-proef, zodat een 500 al verklaard is voordat hij valt.
    controleer_koppeling

    if command -v curl >/dev/null && [[ -n $APP_URL ]]; then
        local code
        code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${APP_URL%/}$ROOKPROEF_PAD" 2>/dev/null || echo "geen")
        case "$code" in
            200) goed "HTTP 200 op ${APP_URL%/}$ROOKPROEF_PAD" ;;
            geen) waarschuw "$APP_URL niet bereikbaar vanaf deze host (SSL termineert elders?) — HTTP-proef overgeslagen" ;;
            *)   waarschuw "HTTP $code op ${APP_URL%/}$ROOKPROEF_PAD
Kijk eerst in $DOELPAD/shared/storage/logs/laravel.log en php-fpm.log." ;;
        esac
    else
        waarschuw "geen APP_URL of geen curl: de HTTP-proef is overgeslagen"
    fi

    stap "Audit trail"
    # Een deploy hoort de ketenhashing niet te raken, en dat hoor je aan te tonen
    # in plaats van aan te nemen.
    artisan isms:controleer-audittrail || fout "de ketenhashing van de audit trail is niet ongebroken"
}

# ── Omschakelen en opruimen ──────────────────────────────────────────────────

wissel_current() {
    stap "Omschakelen"
    [[ -L "$DOELPAD/current" ]] && VORIGE_CURRENT=$(readlink -f "$DOELPAD/current")
    ln -sfn "$RELEASE" "$DOELPAD/current.nieuw"
    mv -T "$DOELPAD/current.nieuw" "$DOELPAD/current"   # atomair binnen dit filesystem
    chown -h "$GEBRUIKER:$GROEP" "$DOELPAD/current"
    SYMLINK_GEWISSELD=ja
    goed "current → $(basename "$RELEASE")"
    herlaad_fpm
    goed "$FPM_DIENST herladen (opcache leeg)"
}

ruim_releases_op() {
    local oud
    mapfile -t oud < <(find "$DOELPAD/releases" -mindepth 1 -maxdepth 1 -type d | sort -r | tail -n +$((RELEASES_BEWAREN + 1)))
    [[ ${#oud[@]} -eq 0 ]] && return
    stap "Oude releases opruimen"
    local pad
    for pad in "${oud[@]}"; do
        [[ $pad == "$(readlink -f "$DOELPAD/current")" ]] && continue
        rm -rf "$pad"
        meld "verwijderd: $(basename "$pad")"
    done
}

# GEDEELD MET deploy-docker.sh :: bewaar_verantwoording()
bewaar_verantwoording() {
    local map="$DOELPAD/shared/installatie"
    cp -p "$BOOM/MANIFEST.json" "$map/manifest-$PAKKET_VERSIE.json"
    printf '%s\n' "$PAKKET_VERSIE $PAKKET_COMMIT $(date -Iseconds) $(basename "$RELEASE")" \
        >>"$map/uitrolhistorie"
    chown -R "$GEBRUIKER:$GROEP" "$map"

    if [[ -f "$RELEASE/scripts/genereer-sbom.php" ]]; then
        ( cd "$RELEASE" && als_app "$PHP" scripts/genereer-sbom.php >/dev/null 2>&1 ) \
            && meld "SBOM bijgewerkt (informatief; gezaghebbend is de versie in de git-werkkopie)" \
            || waarschuw "SBOM-generatie mislukte; niet blokkerend"
    fi
}

# ═════════════════════════════════════════════════════════════════════════════
#  Terugrollen
# ═════════════════════════════════════════════════════════════════════════════

terugrollen() {
    stap "Terugrollen"
    [[ -L "$DOELPAD/current" ]] || fout "er is geen current-symlink om terug te rollen"
    local huidig vorige
    huidig=$(readlink -f "$DOELPAD/current")
    vorige=$(find "$DOELPAD/releases" -mindepth 1 -maxdepth 1 -type d | sort -r \
             | grep -v "^$huidig$" | head -1)
    [[ -n $vorige ]] || fout "er is geen vorige release om naar terug te gaan"

    meld "van: $(basename "$huidig")"
    meld "naar: $(basename "$vorige")"
    waarschuw "een migratie wordt hiermee NIET teruggedraaid: down()-methodes zijn niet"
    waarschuw "betrouwbaar genoeg voor een productie-ISMS. Is er gemigreerd, dan is de"
    waarschuw "dump in shared/installatie/ de weg terug."

    RELEASE=$vorige
    ln -sfn "$vorige" "$DOELPAD/current.nieuw"
    mv -T "$DOELPAD/current.nieuw" "$DOELPAD/current"
    chown -h "$GEBRUIKER:$GROEP" "$DOELPAD/current"
    herlaad_fpm
    rookproef
    goed "teruggerold naar $(basename "$vorige")"
}

# ═════════════════════════════════════════════════════════════════════════════
#  De twee uitrollen
# ═════════════════════════════════════════════════════════════════════════════

bevestig_profiel() {
    stap "Normprofiel vastleggen"
    [[ -n $PROFIEL_ARG ]] && NORM_ENV=$PROFIEL_ARG

    cat <<EOF

   Deze installatie wordt ingericht op:  $NORM_ENV

   Dat is een keuze voor de levensduur van de installatie. MaatregelSeeder
   vertakt erop — 93 maatregelen onder ISO 27001, 101 onder NEN 7510 — en
   achteraf omzetten betekent de database opnieuw opbouwen, want bestaande
   SoA-beoordelingen gelden voor de controlset waarop ze zijn gemaakt.

EOF
    if [[ -t 0 ]]; then
        local antwoord
        read -r -p "   Typ het profiel over om te bevestigen: " antwoord
        [[ $antwoord == "$NORM_ENV" ]] || fout "niet bevestigd; er is niets gewijzigd"
    else
        [[ -n $PROFIEL_ARG ]] \
            || fout "geen terminal om te bevestigen; geef het profiel expliciet mee met --profiel="
        meld "bevestigd via --profiel=$PROFIEL_ARG"
    fi

    # De .env is leidend voor de applicatie, dus die moet het profiel dragen.
    if [[ $(env_waarde "$ENVBESTAND" ISMS_NORM "") != "$NORM_ENV" ]]; then
        fout "ISMS_NORM in .env staat op '$(env_waarde "$ENVBESTAND" ISMS_NORM "<leeg>")' en niet op '$NORM_ENV'.
Pas de .env aan; dit script bewerkt uw .env niet."
    fi
}

installeer_nieuw() {
    bevestig_profiel
    maak_gebruiker
    maak_shared
    schrijf_release
    neem_seeddata_over
    zet_symlinks
    zet_rechten
    herlaad_fpm

    stap "Applicatiesleutel en caches"
    if [[ -z $APP_KEY ]]; then
        artisan key:generate --force
        goed "APP_KEY gegenereerd"
    else
        meld "APP_KEY stond al in .env"
    fi
    # Let op de volgorde: hierna is .env voor de applicatie onzichtbaar. Laravel
    # slaat het inlezen ervan over zodra de configuratie gecached is, dus alles
    # wat de seeders hieronder uit .env nodig hebben — ISMS_NORM — moet via een
    # configuratiebestand lopen. Zie config/norm.php; dit is één keer misgegaan
    # en leverde een ISO-installatie op waar NEN 7510 was gevraagd.
    bepaal_versie
    artisan config:cache
    artisan route:cache
    artisan view:cache

    migreer_en_seed

    # Zijn er eigen normbestanden meegegeven, dan moeten de maatregelteksten
    # daaruit alsnog de database in. Via het commando en niet via db:seed: dat
    # controleert het bestand eerst en meldt hoeveel maatregelen een eigen
    # normtekst hebben — bij een meegegeven bestand is dat precies de vraag.
    if [[ -n $NORMMAP ]] || compgen -G "$DOELPAD/shared/seeddata/*" >/dev/null; then
        stap "Maatregelen opnieuw seeden met de eigen normtekst"
        artisan isms:maatregelen
    fi

    controleer_geseed_profiel
    demo_vullen

    zet_cron
    wissel_current
    zet_normstempel
    controleer_hartslag
    rookproef
    bewaar_verantwoording
    slotscherm_eerste
}

rol_release_uit() {
    normcontrole
    verschilrapport
    [[ $DRYRUN == ja ]] && { stap "--dry-run: hier stopt het"; return; }

    schrijf_release
    neem_seeddata_over
    zet_symlinks
    zet_rechten

    stap "Caches bouwen tegen de .env van deze installatie"
    bepaal_versie
    artisan config:cache
    artisan route:cache
    artisan view:cache

    stap "Onderhoudsmodus"
    # Dit parkeert meteen de scheduler: Laravel slaat geplande commando's over
    # zolang de applicatie down is.
    ( cd "$DOELPAD/current" && als_app "$PHP" artisan down --render=errors::503 ) \
        && ONDERHOUD_AAN=ja
    goed "site staat in onderhoud"

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
    wissel_current

    ( cd "$DOELPAD/current" && als_app "$PHP" artisan up ); ONDERHOUD_AAN=nee
    goed "site is weer open"

    zet_cron
    controleer_hartslag
    rookproef
    bewaar_verantwoording
    ruim_releases_op
    slotscherm_release
}

# ═════════════════════════════════════════════════════════════════════════════
#  Slotschermen
# ═════════════════════════════════════════════════════════════════════════════

slotscherm_eerste() {
    cat <<EOF

$(printf '\033[1m')Klaar — de installatie draait, maar is nog niet bereikbaar.$(printf '\033[0m')

Twee dingen doet dit script bewust niet:

1. De vhost. Laat de webserver naar de release-symlink wijzen — niet naar een
   vaste releasemap, want dan komt een volgende uitrol niet aan. Voor nginx:

     root $DOELPAD/current/public;
     location ~ \.php\$ {
         fastcgi_pass unix:${FPM_SOCKET:-/run/php/php$PHP_MAJMIN-fpm.sock};
         fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
         include fastcgi_params;
     }

   Dat is de standaardpool, die als $WEBGEBRUIKER draait — dezelfde gebruiker
   die storage/ bezit. Wijst u naar een andere pool, dan meldt de volgende
   uitrol dat en stopt hij.

   \$realpath_root is hier essentieel: PHP ziet dan het echte releasepad, dus er
   blijft na een uitrol geen oude opcache op het symlinkpad hangen.
   Voor apache is het equivalent %{REALPATH} in de SetHandler-constructie.

2. Het eerste CISO-account. Daar hoort een wachtwoord bij dat niet in een
   deploylog thuishoort:

     cd $DOELPAD/current
     sudo -u $WEBGEBRUIKER $PHP artisan isms:eerste-ciso <e-mail> '<wachtwoord>' '<naam>'
${DEMO_GEVULD:+
   De demo is gevuld, dus er zijn al accounts — met de wachtwoorden die
   hierboven in het log staan. Een eigen CISO-account aanmaken kan alsnog,
   maar hoeft niet om in te loggen.
}

Verder:
  toetsbestanden  → $DOELPAD/shared/storage/app/private/toetsen/
  logs            → $DOELPAD/shared/storage/logs/
  deploylog       → $DOELPAD/shared/installatie/$(basename "$LOGBESTAND")
  bewaking        → $HARTSLAG

EOF
}

slotscherm_release() {
    cat <<EOF

$(printf '\033[1m')Klaar — release $PAKKET_VERSIE draait.$(printf '\033[0m')

  release     $(basename "$RELEASE")
  commit      $PAKKET_COMMIT
  terugrollen deploy.sh $TARBAL $DOELPAD --terug
  deploylog   $DOELPAD/shared/installatie/$(basename "$LOGBESTAND")
  bewaking    $HARTSLAG
${DEMO_GEVULD:+  demo        opnieuw gevuld; de vorige inhoud van de database is weg
}
${MIGRATIE_GEDRAAID:+  dump        $DOELPAD/shared/installatie/ (bewaren tot de release zich bewezen heeft)
}
EOF
}

# ═════════════════════════════════════════════════════════════════════════════
#  Hoofdstroom
# ═════════════════════════════════════════════════════════════════════════════

MODUS=""; ENVBESTAND=""; BOOM=""; PHP=""; MIGRATIE_GEDRAAID=""
PAKKET_VERSIE=""; PAKKET_COMMIT=""
# Deze worden pas in de controles gevuld, maar het opruimen kan er eerder bij
# komen als het script vroeg afbreekt; met `set -u` moeten ze dus bestaan.
FPM_DIENST=""; FPM_SOCKET=""; POOLMAP=""; POOLNAAM=""
WEBSERVER=""; WEBGROEP=""; PHP_MAJMIN=""
FOUTVLAG=""; FOUTVLAGMAP=""
DEMO_GEVULD=""        # niet-leeg zodra isms:demo-vul is gedraaid

hoofd() {
    controleer_root
    verwerk_argumenten "$@"

    # Eén uitrol tegelijk per doelpad. De grendel staat in /var/lock en niet in
    # het doelpad zelf: bij een nieuwe installatie moet dat pad leeg zijn op de
    # .env na.
    mkdir -p "$LOCKMAP"
    exec 9>"$LOCKMAP/ezisms-deploy$(printf '%s' "$DOELPAD" | tr '/' '-').lock"
    flock -n 9 || fout "er draait al een uitrol voor $DOELPAD"

    # Een eigen map, want het gaat om het bestaan van het bestand erin: mktemp
    # zou het meteen aanmaken en dan zou de eerste fout al als "gemeld" gelden.
    FOUTVLAGMAP=$(mktemp -d "${UITPAKMAP%/}/ezisms-vlag.XXXXXXXX")
    FOUTVLAG="$FOUTVLAGMAP/gemeld"

    trap opruimen EXIT
    trap 'fout "onverwachte fout op regel $LINENO"' ERR

    printf '\033[1mISMS-uitrol\033[0m — %s\n' "$(date -Iseconds)"
    meld "deploy.sh $DEPLOY_VERSIE, draait als $(id -un), doel $DOELPAD"

    controleer_gereedschap
    controleer_tarbal
    bepaal_modus
    lees_env
    # Webserver eerst: die stelt WEBGEBRUIKER vast, en de poolcontrole in
    # controleer_php_basis zoekt juist de pool die als díe gebruiker draait.
    controleer_webserver
    maak_apphome          # kan pas als WEBGEBRUIKER bekend is, moet vóór artisan
    controleer_php_basis

    if [[ $TERUG == ja ]]; then
        terugrollen
        return
    fi

    controleer_database
    pak_uit
    controleer_manifest
    controleer_php_pakket
    controleer_optioneel

    if [[ $MODUS == eerste ]]; then
        if [[ $DRYRUN == ja ]]; then
            stap "--dry-run: alle controles zijn doorlopen, er is niets gewijzigd"
            return
        fi
        installeer_nieuw
    else
        rol_release_uit
    fi
}

hoofd "$@"
