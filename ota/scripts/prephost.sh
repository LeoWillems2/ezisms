#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# prephost.sh — maakt een kale Ubuntu 26.04 klaar voor het ISMS (bare metal)
#
#   sudo prephost.sh [opties]
#
# LEESWIJZER VOOR DE BEHEERDER
#
#   Dit script komt vóór `deploy.sh`. Het legt neer wat een distributietarbal
#   nodig heeft en zelf niet meebrengt: webserver, database, PHP met de juiste
#   extensies, composer, node/npm, pandoc, een fpm-pool, een vhost, een lege
#   database met eigen gebruiker, en het doelpad met een ingevulde `.env`.
#
#   Daarna is de volgende stap:
#
#       sudo deploy.sh ezisms-<versie>.tar.gz <doelpad> --eerste
#
#   Wat dit script NIET doet, omdat deploy.sh het zelf doet:
#     * de code-eigenaar `ezisms` aanmaken (deploy.sh :: maak_gebruiker)
#     * releases/, shared/storage/ en de crontabregel aanleggen
#     * migreren, seeden, APP_KEY genereren, caches bouwen
#     * het eerste CISO-account aanmaken
#
#   Uitgangspunten, en waar ze vandaan komen:
#     * Ubuntu 26.04 levert PHP 8.5; de eis van de applicatie is >= 8.4.1
#       (vendor/composer/platform_check.php). De reeks staat op één plek en is
#       met --php= te overschrijven.
#     * De TLS wordt door HAProxy getermineerd (zie CLAUDE.md), dus de vhost
#       luistert alleen op poort 80 en vertaalt X-Forwarded-Proto zelf naar de
#       fastcgi-parameter HTTPS. Zonder die constructie genereert Laravel
#       achter de proxy http://-URL's.
#     * Eén schrijvend account: php-fpm, de scheduler en artisan draaien als
#       www-data, en dat account bezit storage/. De code is van `ezisms` en
#       draait niets (A.8.19). Zie README.md § Schrijfrechten op storage/.
#     * pandoc komt NIET uit apt. De pandoc van Ubuntu 26.04 (3.7.0.2) zet zijn
#       datafiles in een apart pakket, en met --sandbox — wat de applicatie
#       doet — leest pandoc uitsluitend de ingebakken versie. Zo'n build
#       schrijft dus geen .docx, en dat wordt zichtbaar als HTTP 503 op elke
#       Word-download. Vandaar de vastgepinde release van jgm/pandoc, met een
#       controle die precies het commando uit App\Support\Pandoc draait.
#
#   Het script is herhaalbaar: wat er al staat blijft staan. Het overschrijft
#   nooit een bestaande `.env`, en verandert nooit het wachtwoord van een
#   databasegebruiker die er al is.
#
#   Achtergrond: README.md § Eerste installatie, scripts/deploy.sh en
#   docker/Dockerfile. Paden zijn relatief aan de uitlevering; in het repo
#   staat deze boom onder ota/ en heet docker/ dan docker/ezisms/. Daar staat
#   ook ontwikkelmachine/LEESMIJ.md, met dezelfde stappen voor een
#   ontwikkelmachine — dat bestand reist niet mee in de tarbal.
# ─────────────────────────────────────────────────────────────────────────────

# -E is hier geen sierletter: zonder errtrace erven functies de ERR-trap niet,
# en dan breekt het script binnen een functie stil af — geen melding, geen
# regelnummer, alleen een exitcode.
set -Eeuo pipefail

PREPHOST_VERSIE="1.0"

# ── Instellingen ─────────────────────────────────────────────────────────────
# Alles via ": ${NAAM:=…}", dus per aanroep te overschrijven zonder het script
# te wijzigen:   PHP_REEKS=8.4 prephost.sh --hostnaam=isms.example.nl
#
: "${DOELPAD:=/var/www/isms}"        # hier komt de installatie; deploy.sh krijgt dit pad
: "${HOSTNAAM:=}"                    # server_name van de vhost; leeg ⇒ hostname -f
: "${HAPROXY_IP:=}"                  # alleen dit adres (en localhost) mag de vhost bereiken
: "${PHP_REEKS:=}"                   # leeg ⇒ de nieuwste reeks die apt hier kent
: "${NODE_BRON:=distributie}"        # distributie | nodesource
: "${NODESOURCE_REEKS:=22}"          # alleen bij NODE_BRON=nodesource
: "${PANDOC_VERSIE:=3.10.2}"         # vastgepind, mét hash — zie installeer_pandoc
: "${DB_NAAM:=ezisms}"
: "${DB_GEBRUIKER:=ezisms}"
: "${DB_WACHTWOORD:=}"               # leeg ⇒ het script genereert er een
: "${NORM:=iso27001}"                # iso27001 | nen7510 | bio2 — onomkeerbaar na de seed
: "${POOLNAAM:=ezisms}"              # naam van de fpm-pool en van de vhost
: "${WEBGEBRUIKER:=www-data}"        # draait fpm, cron en artisan; bezit storage/
# ─────────────────────────────────────────────────────────────────────────────

DRYRUN=nee
DOE_UPGRADE=nee
DOE_VHOST=ja
DOE_DATABASE=ja
DOE_PANDOC=ja
DOE_MYSQL_HARDING=ja
DOE_ENV=ja

gebruik() {
    cat <<'EOF'
Gebruik: sudo prephost.sh [opties]

  --doelpad=<pad>          waar de installatie komt (standaard /var/www/isms)
  --hostnaam=<naam>        server_name van de vhost (standaard: hostname -f)
  --haproxy=<ip>           alleen dit adres en localhost mogen de vhost bereiken.
                           Zonder deze optie staat poort 80 open voor iedereen
                           die de host kan bereiken.
  --norm=<profiel>         iso27001 (standaard), nen7510 of bio2. Dit is een
                           installatiekeuze: na de eerste seed ligt hij vast.
  --db-naam=<naam>         standaard ezisms
  --db-gebruiker=<naam>    standaard ezisms
  --db-wachtwoord=<ww>     standaard: het script genereert er een en toont hem
  --php=<reeks>            bijvoorbeeld 8.5; standaard de nieuwste die apt kent
  --nodesource[=<reeks>]   node uit NodeSource in plaats van uit de distributie
  --upgrade                eerst `apt upgrade` draaien
  --geen-vhost             geen serverblok en geen fpm-pool schrijven
  --geen-database          database en databasegebruiker overslaan
  --geen-env               het doelpad en de .env niet aanleggen
  --geen-pandoc            pandoc overslaan (dan geen Word-documenten)
  --geen-mysql-harding     de anonieme accounts en de testdatabase laten staan
  --dry-run                alleen tonen wat er zou gebeuren
  -h, --help               deze uitleg
EOF
}

# ═════════════════════════════════════════════════════════════════════════════
#  Meldingen
# ═════════════════════════════════════════════════════════════════════════════

stap()      { printf '\n\033[1m== %s\033[0m\n' "$*"; }
meld()      { printf '   %s\n' "$*"; }
goed()      { printf '\033[32m   ✓ %s\033[0m\n' "$*"; }
waarschuw() { printf '\033[33m   ! %s\033[0m\n' "$*"; WAARSCHUWINGEN=$((WAARSCHUWINGEN + 1)); }
fout()      { printf '\033[31m\nFOUT: %s\033[0m\n' "$*" >&2; exit 1; }

WAARSCHUWINGEN=0

# Elke wijziging aan deze host loopt hier langs, zodat --dry-run werkelijk niets
# doet en het log laat zien wat er zou gebeuren.
doe() {
    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] %s\033[0m\n' "$*"
        return 0
    fi
    "$@"
}

# Een bestand schrijven met inhoud van stdin. Ook dit langs --dry-run, en met
# een back-up als er al iets anders stond: op een host die dit script voor de
# tweede keer ziet, is een stille overschrijving het laatste wat je wilt.
schrijf() {   # <pad> <modus>
    local pad=$1 modus=$2 inhoud
    inhoud=$(cat)

    if [[ -f $pad ]] && [[ $inhoud == "$(cat "$pad")" ]]; then
        meld "$pad staat er al en is ongewijzigd"
        return 0
    fi
    if [[ -f $pad ]]; then
        doe cp -a "$pad" "$pad.vorig-$(date +%Y%m%d%H%M%S)"
        waarschuw "$pad bestond al; de vorige versie staat ernaast met tijdstempel"
    fi
    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] schrijf %s (%s), %d regels\033[0m\n' \
               "$pad" "$modus" "$(wc -l <<<"$inhoud")"
        return 0
    fi
    printf '%s\n' "$inhoud" >"$pad"
    chmod "$modus" "$pad"
    goed "$pad geschreven"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Argumenten
# ═════════════════════════════════════════════════════════════════════════════

verwerk_argumenten() {
    local arg
    for arg in "$@"; do
        case "$arg" in
            --doelpad=*)        DOELPAD=${arg#*=} ;;
            --hostnaam=*)       HOSTNAAM=${arg#*=} ;;
            --haproxy=*)        HAPROXY_IP=${arg#*=} ;;
            --norm=*)           NORM=${arg#*=} ;;
            --db-naam=*)        DB_NAAM=${arg#*=} ;;
            --db-gebruiker=*)   DB_GEBRUIKER=${arg#*=} ;;
            --db-wachtwoord=*)  DB_WACHTWOORD=${arg#*=} ;;
            --php=*)            PHP_REEKS=${arg#*=} ;;
            --nodesource)       NODE_BRON=nodesource ;;
            --nodesource=*)     NODE_BRON=nodesource; NODESOURCE_REEKS=${arg#*=} ;;
            --upgrade)          DOE_UPGRADE=ja ;;
            --geen-vhost)       DOE_VHOST=nee ;;
            --geen-database)    DOE_DATABASE=nee ;;
            --geen-env)         DOE_ENV=nee ;;
            --geen-pandoc)      DOE_PANDOC=nee ;;
            --geen-mysql-harding) DOE_MYSQL_HARDING=nee ;;
            --dry-run)          DRYRUN=ja ;;
            -h|--help)          gebruik; exit 0 ;;
            *)                  gebruik; fout "onbekende optie: $arg" ;;
        esac
    done

    [[ $DOELPAD == /* ]] || fout "--doelpad moet een absoluut pad zijn"
    DOELPAD=${DOELPAD%/}

    case "$NORM" in
        iso27001|nen7510|bio2) ;;
        *) fout "onbekend normprofiel '$NORM'; kies iso27001, nen7510 of bio2" ;;
    esac

    # Een naam die in SQL of in een .env-regel geciteerd wordt: beperk hem tot
    # wat daar zonder ontsnapping veilig is, in plaats van te gaan quoten.
    [[ $DB_NAAM      =~ ^[A-Za-z0-9_]+$ ]] || fout "--db-naam mag alleen letters, cijfers en _ bevatten"
    [[ $DB_GEBRUIKER =~ ^[A-Za-z0-9_]+$ ]] || fout "--db-gebruiker mag alleen letters, cijfers en _ bevatten"
    if [[ -n $DB_WACHTWOORD && ! $DB_WACHTWOORD =~ ^[A-Za-z0-9._~!@%^*+=-]+$ ]]; then
        fout "dit wachtwoord bevat tekens die in .env of in SQL geciteerd moeten worden.
Gebruik letters, cijfers en . _ ~ ! @ % ^ * + = - , of laat het script er een genereren."
    fi
}

# ═════════════════════════════════════════════════════════════════════════════
#  Controles vooraf
# ═════════════════════════════════════════════════════════════════════════════

controleer_root() {
    [[ $EUID -eq 0 ]] || fout "dit script moet als root draaien (pakketten, database, webserver)"
}

controleer_distributie() {
    stap "Deze host"
    [[ -r /etc/os-release ]] || fout "geen /etc/os-release; dit script is voor Ubuntu"
    # shellcheck disable=SC1091
    . /etc/os-release
    meld "${PRETTY_NAME:-onbekend}, $(uname -m), kernel $(uname -r)"

    [[ ${ID:-} == ubuntu ]] || fout "dit script is voor Ubuntu; gevonden: ${ID:-onbekend}"
    if [[ ${VERSION_ID:-} != "26.04" ]]; then
        waarschuw "geschreven voor Ubuntu 26.04, deze host draait ${VERSION_ID:-onbekend}"
        waarschuw "  ⇒ de pakketnamen kloppen mogelijk niet; controleer vooral de PHP-reeks"
    fi

    case "$(dpkg --print-architecture)" in
        amd64|arm64) ;;
        *) waarschuw "architectuur $(dpkg --print-architecture): voor pandoc is hier geen
           vastgepind pakket, die stap slaat straks over" ;;
    esac

    if [[ -z $HOSTNAAM ]]; then HOSTNAAM=$(hostname -f 2>/dev/null || hostname); fi
    meld "vhost server_name: $HOSTNAAM"
    meld "doelpad: $DOELPAD"
    meld "normprofiel: $NORM"
    if [[ $DRYRUN == ja ]]; then meld "--dry-run: er wordt niets gewijzigd"; fi
    return 0
}

# ═════════════════════════════════════════════════════════════════════════════
#  Pakketten
# ═════════════════════════════════════════════════════════════════════════════

export DEBIAN_FRONTEND=noninteractive

APT_BIJGEWERKT=nee

apt_bijwerken() {
    [[ $APT_BIJGEWERKT == ja ]] && return 0
    doe apt-get update -qq
    APT_BIJGEWERKT=ja
}

# Alleen installeren wat er nog niet is, en in één aanroep: dat scheelt tijd bij
# een tweede run en houdt het log leesbaar.
apt_installeer() {   # <pakket>...
    apt_bijwerken
    local pkg ontbreekt=()
    for pkg in "$@"; do
        dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q '^install ok installed$' \
            || ontbreekt+=("$pkg")
    done
    if [[ ${#ontbreekt[@]} -eq 0 ]]; then
        meld "al aanwezig: $*"
        return 0
    fi
    meld "installeren: ${ontbreekt[*]}"
    doe apt-get install -y --no-install-recommends "${ontbreekt[@]}"
}

apt_kent() {   # <pakket> — bestaat dit pakket in de bronnen van deze host?
    apt_bijwerken
    apt-cache show "$1" >/dev/null 2>&1
}

installeer_basis() {
    stap "Basisgereedschap"
    if [[ $DOE_UPGRADE == ja ]]; then apt_bijwerken; doe apt-get upgrade -y; fi

    # tar, rsync, runuser, crontab, systemctl, sha256sum, find en flock zijn wat
    # deploy.sh :: controleer_gereedschap eist; runuser en flock zitten in
    # util-linux, sha256sum en find in coreutils/findutils. cron ontbreekt op een
    # minimale server-installatie, en zonder cron draait de scheduler niet — dan
    # staat het hele ISMS stil op herinneringen, meldtermijnen en de hartslag.
    apt_installeer ca-certificates curl wget openssl unzip tar rsync cron \
                   util-linux coreutils findutils tzdata fonts-dejavu-core
    doe systemctl enable --now cron
    goed "gereedschap voor deploy.sh staat klaar"
}

installeer_nginx() {
    stap "Webserver"
    apt_installeer nginx
    doe systemctl enable --now nginx

    # De meegeleverde default-site luistert op 80 én [::]:80 met default_server.
    # Laat je hem staan, dan bedient nginx twee sites op dezelfde poort en wint
    # bij een verzoek zonder passende Host-header de verkeerde.
    if [[ -e /etc/nginx/sites-enabled/default ]]; then
        doe rm -f /etc/nginx/sites-enabled/default
        goed "default-site uitgeschakeld (het bestand in sites-available blijft staan)"
    fi
}

installeer_mysql() {
    stap "Database-server"
    # mysql-client apart: deploy.sh maakt vóór elke migratie een dump, en dat is
    # geen optionele stap. mysql-server trekt de client meestal mee, maar dat
    # expliciet maken kost niets.
    apt_installeer mysql-server mysql-client
    doe systemctl enable --now mysql

    if [[ $DOE_MYSQL_HARDING == ja ]]; then
        # Het niet-interactieve deel van `mysql_secure_installation`. Het root-
        # account van Ubuntu draait op auth_socket en heeft dus geen wachtwoord
        # nodig; wat overblijft zijn de anonieme accounts en de testdatabase.
        doe mysql --protocol=socket -uroot <<'SQL'
DELETE FROM mysql.user WHERE User = '';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db = 'test' OR Db LIKE 'test\\_%';
FLUSH PRIVILEGES;
SQL
        goed "anonieme accounts en de testdatabase zijn weg"
    else
        waarschuw '--geen-mysql-harding: draai zelf nog mysql_secure_installation'
    fi
}

bepaal_php_reeks() {
    if [[ -n $PHP_REEKS ]]; then
        apt_kent "php$PHP_REEKS-fpm" || fout "php$PHP_REEKS-fpm zit niet in de pakketbronnen van deze host"
        return 0
    fi
    local kandidaat
    for kandidaat in 8.5 8.4; do
        if apt_kent "php$kandidaat-fpm"; then PHP_REEKS=$kandidaat; return 0; fi
    done
    fout "geen php8.5 of php8.4 in de pakketbronnen. De applicatie eist >= 8.4.1
(vendor/composer/platform_check.php). Voeg anders ppa:ondrej/php toe en draai
dit script opnieuw met --php=8.4."
}

installeer_php() {
    stap "PHP"
    bepaal_php_reeks
    meld "reeks: $PHP_REEKS"

    # Dezelfde lijst als het Docker-image (docker/Dockerfile), zodat de
    # twee omgevingen niet uit elkaar lopen. Wat er níét in staat — ctype,
    # fileinfo, filter, hash, openssl, pdo, session, tokenizer — zit in de kern
    # of in php-common en komt met php-cli mee.
    #
    # php-gd moet FreeType hebben, anders verschijnt de tolerantiematrix als
    # tabel zonder plaatje (README § Optioneel: GD met FreeType).
    apt_installeer "php$PHP_REEKS-fpm"  "php$PHP_REEKS-cli"  "php$PHP_REEKS-mysql" \
                   "php$PHP_REEKS-mbstring" "php$PHP_REEKS-xml" "php$PHP_REEKS-curl" \
                   "php$PHP_REEKS-zip"  "php$PHP_REEKS-gd"

    # Op een host met meer dan één reeks moet `php` zonder versienummer dezelfde
    # zijn als de fpm-pool: web en cron horen niet op verschillende PHP's te
    # draaien. deploy.sh kiest zelf de versie-expliciete binary, maar alles wat
    # een beheerder met de hand doet volgt dit alternatief.
    if update-alternatives --list php >/dev/null 2>&1; then
        doe update-alternatives --set php "/usr/bin/php$PHP_REEKS"
    fi

    doe systemctl enable --now "php$PHP_REEKS-fpm"
    goed "php$PHP_REEKS-fpm draait"
}

# De pool. Op bare metal draait de applicatie als www-data — hetzelfde account
# dat storage/ bezit — dus deze pool draait als www-data, precies als die in de
# container. deploy.sh :: controleer_koppeling breekt de uitrol af als de vhost
# naar een pool wijst die als iemand anders draait.
#
# Een eigen pool naast de meegeleverde `www` en niet in plaats daarvan: op een
# host met meer sites hangen die aan www.conf, en die mag dit script niet
# omgooien. De socketnaam draagt bewust geen PHP-versie, zodat een reeksupgrade
# alleen dit bestand raakt en niet ook de vhost.
schrijf_pool() {
    stap "php-fpm-pool $POOLNAAM"
    local poolmap="/etc/php/$PHP_REEKS/fpm/pool.d"
    [[ -d $poolmap ]] || fout "$poolmap bestaat niet; is php$PHP_REEKS-fpm wel geïnstalleerd?"

    schrijf "$poolmap/$POOLNAAM.conf" 0644 <<POOL
; EzISMS — de php-fpm-pool op deze host. Geschreven door prephost.sh $PREPHOST_VERSIE.
;
; Draait als $WEBGEBRUIKER: hetzelfde account dat storage/ bezit en dat de
; scheduler en artisan draaien. Eén schrijvend account betekent geen ACL's.
; De socketnaam draagt geen PHP-versie; het versienummer staat alleen in de
; padnaam van dit bestand en in de vhost hoeft dus niets te veranderen.

[$POOLNAAM]
user = $WEBGEBRUIKER
group = $WEBGEBRUIKER

listen = /run/php/$POOLNAAM-fpm.sock
listen.owner = $WEBGEBRUIKER
listen.group = $WEBGEBRUIKER
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6

; Gelijk aan de container (docker/php-fpm-pool.conf). De standaard van
; de distributie is 2M, en dan zakt het uploaden van een bewijsstuk zonder dat
; er iets in het applicatielog komt.
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M

; 512M vanwege de exports en \`isms:demo-vul\`.
php_admin_value[memory_limit] = 512M
POOL

    doe "php-fpm$PHP_REEKS" --test
    doe systemctl reload "php$PHP_REEKS-fpm"
    goed "pool $POOLNAAM luistert op /run/php/$POOLNAAM-fpm.sock"
}

installeer_composer() {
    stap "Composer"

    # Zonder dit blijft het script hier staan wachten. Composer waarschuwt bij
    # élk commando dat als root draait — ook bij `--version` — en stelt daarna
    # de vraag "Continue as root/super user [yes]?". Op een terminal is stdin
    # een tty, dus die vraag komt er, en in een $(…) is hij niet te beantwoorden
    # zonder te zien wat er gevraagd wordt.
    #
    # Die waarschuwing gaat niet over composer neerzetten maar over composer
    # gebruiken: `install` en `update` voeren plugins en post-install-haken van
    # derden uit, en dat wil je niet als root. Hier gebeurt dat niet — root haalt
    # alleen de phar op en vraagt zijn versienummer. Het echte `composer install`
    # draait als de webservergebruiker, via deploy.sh :: bouw_afhankelijkheden.
    #
    # Composer slaat dezelfde controle uit zichzelf over binnen Docker, en wijst
    # deze variabele aan voor containers en CI. Alleen deze schakelaar en niet
    # --no-interaction: die onderdrukt de vraag wel maar niet de gele
    # waarschuwing eronder, en die leest als een probleem terwijl het er geen is.
    export COMPOSER_ALLOW_SUPERUSER=1

    if command -v composer >/dev/null; then
        meld "composer staat er al: $(composer --version | head -1)"
        return 0
    fi
    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] composer installeren via getcomposer.org\033[0m\n'
        return 0
    fi

    # De officiële installer, met de handtekening die de makers publiceren. Niet
    # `apt install composer`: die is aan de distributie gekoppeld en loopt achter
    # op de versie waarmee de tarbal gebouwd is.
    local verwacht werkelijk
    verwacht=$(curl -fsSL https://composer.github.io/installer.sig) \
        || fout "kan de handtekening van de composer-installer niet ophalen"
    curl -fsSLo /tmp/composer-setup.php https://getcomposer.org/installer \
        || fout "kan de composer-installer niet ophalen"
    werkelijk=$(sha384sum /tmp/composer-setup.php | cut -d' ' -f1)
    [[ $verwacht == "$werkelijk" ]] \
        || { rm -f /tmp/composer-setup.php; fout "de composer-installer komt niet overeen met zijn handtekening"; }

    "php$PHP_REEKS" /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    goed "composer: $(composer --version | head -1)"
}

installeer_node() {
    stap "Node en npm"
    # Node is op deze host nodig omdat een tarbal van `builddistr.sh --geen-bouw`
    # zonder public/build aankomt; deploy.sh draait dan `npm run build` hier.
    # Een gebouwde tarbal heeft node niet nodig, maar hem toch neerzetten kost
    # weinig en scheelt een mislukte uitrol op een zaterdagavond.
    if [[ $NODE_BRON == nodesource ]]; then
        if [[ $DRYRUN == ja ]]; then
            printf '   \033[2m[dry-run] NodeSource %s.x toevoegen en nodejs installeren\033[0m\n' "$NODESOURCE_REEKS"
        else
            curl -fsSL "https://deb.nodesource.com/setup_${NODESOURCE_REEKS}.x" | bash -
            APT_BIJGEWERKT=nee
            apt_installeer nodejs
        fi
    else
        apt_installeer nodejs npm
    fi

    if command -v node >/dev/null; then
        local major; major=$(node -v | sed 's/^v//; s/\..*//')
        meld "node $(node -v), npm $(npm -v 2>/dev/null || echo '?')"
        # Vite 6 (ota/package.json) draait op ^18.18, ^20.9 of >= 22.
        if [[ $major -lt 20 ]]; then
            waarschuw "node $major is te oud voor vite 6; draai dit script met --nodesource=22"
        fi
    elif [[ $DRYRUN == nee ]]; then
        waarschuw "node ontbreekt nog; een tarbal van --geen-bouw is hier niet uit te rollen"
    fi
}

installeer_pandoc() {
    stap "pandoc"

    # Eerst de vraag die telt: kán de pandoc die er staat doen wat de applicatie
    # hem vraagt? Dit ís het commando uit App\Support\Pandoc::converteer(). De
    # pandoc van Ubuntu 26.04 bestaat wél maar zakt hier, en dat werd zichtbaar
    # als een 503 op de Word-download zonder één regel in storage/logs.
    if command -v pandoc >/dev/null \
       && printf '# Toets\n' | pandoc --sandbox --from=markdown --to=docx --output=/dev/null 2>/dev/null; then
        goed "pandoc voldoet al: $(pandoc --version | head -1)"
        return 0
    fi
    if command -v pandoc >/dev/null; then
        waarschuw "de aanwezige pandoc ($(pandoc --version | head -1 | cut -d' ' -f2)) kan met --sandbox"
        waarschuw "  geen .docx schrijven; hij wordt vervangen door de release van jgm/pandoc"
    fi

    # Versie én hash vastgepind, want een uitrol hoort niet stilletjes iets
    # anders op te halen dan wat er getoetst is. Bij een nieuwe versie: het
    # nummer en beide sommen tegelijk. Gelijk aan docker/Dockerfile.
    local arch som deb
    arch=$(dpkg --print-architecture)
    case "$arch" in
        amd64) som=6c06b69b49ae95087573631a6fcafb233ab7ab51e5cfa73f7539d6c964a2640d ;;
        arm64) som=868c7675806237dd21711e3890e82f2844e011c8f542a1ddc6245df4324dd6b5 ;;
        *) waarschuw "geen vastgepind pandoc-pakket voor architectuur '$arch'; overgeslagen"
           waarschuw "  ⇒ zonder pandoc geven de Word-downloads HTTP 503 en valt de preview terug"
           return 0 ;;
    esac
    deb="pandoc-${PANDOC_VERSIE}-1-${arch}.deb"

    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] %s ophalen bij github.com/jgm/pandoc en installeren\033[0m\n' "$deb"
        return 0
    fi

    curl -fsSLo "/tmp/$deb" \
        "https://github.com/jgm/pandoc/releases/download/${PANDOC_VERSIE}/${deb}" \
        || fout "kan $deb niet ophalen; deze stap heeft github.com nodig"
    echo "${som}  /tmp/${deb}" | sha256sum -c - \
        || { rm -f "/tmp/$deb"; fout "de checksum van $deb klopt niet"; }
    dpkg -i "/tmp/$deb" >/dev/null
    rm -f "/tmp/$deb"

    printf '# Toets\n' | pandoc --sandbox --from=markdown --to=docx --output=/dev/null \
        || fout "ook deze pandoc kan met --sandbox geen .docx schrijven"
    goed "pandoc: $(pandoc --version | head -1)"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Database
# ═════════════════════════════════════════════════════════════════════════════

genereer_wachtwoord() {
    # base64 en daarna de tekens eruit die in .env of in SQL geciteerd zouden
    # moeten worden. Wat overblijft is nog altijd ruim 150 bit.
    openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | cut -c1-32
}

maak_database() {
    stap "Database $DB_NAAM"

    # Geen pijp naar grep: met pipefail kan een vroeg afsluitende grep de
    # mysql-kant een SIGPIPE bezorgen, en dat leest hier als "bestaat niet".
    local bestaat_al=nee gebruiker_bestaat=nee antwoord
    if [[ $DRYRUN == nee ]]; then
        antwoord=$(mysql --protocol=socket -uroot -N -B -e \
            "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$DB_NAAM'" \
            2>/dev/null) || fout "geen verbinding met mysql als root over de socket"
        [[ $antwoord == 0 ]] || bestaat_al=ja

        antwoord=$(mysql --protocol=socket -uroot -N -B -e \
            "SELECT COUNT(*) FROM mysql.user WHERE user = '$DB_GEBRUIKER'" 2>/dev/null) \
            || fout "geen verbinding met mysql als root over de socket"
        [[ $antwoord == 0 ]] || gebruiker_bestaat=ja
    fi

    if [[ $gebruiker_bestaat == ja && -z $DB_WACHTWOORD ]]; then
        # Het wachtwoord van een bestaande gebruiker omzetten breekt elke andere
        # installatie die hem gebruikt. Dan liever niets doen en het zeggen.
        waarschuw "gebruiker '$DB_GEBRUIKER' bestaat al; zijn wachtwoord blijft ongemoeid"
        waarschuw "  ⇒ zet het met de hand in $DOELPAD/.env, of geef --db-wachtwoord=…"
    fi
    [[ -n $DB_WACHTWOORD ]] || DB_WACHTWOORD=$(genereer_wachtwoord)

    # utf8mb4_unicode_ci is geen smaakkwestie: de norm- en maatregelteksten
    # bevatten diakrieten en aanhalingstekens die in latin1 stilzwijgend
    # sneuvelen.
    #
    # Twee hosts voor dezelfde gebruiker, en dat is geen slordigheid: .env zet
    # DB_HOST=127.0.0.1, dus de applicatie verbindt over TCP. MySQL matcht dat
    # adres alleen op 'localhost' zolang naamresolutie aanstaat — met
    # skip-name-resolve is dat ineens niet meer zo, en dan valt de applicatie
    # uit met "Access denied" terwijl er aan .env niets veranderd is.
    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] database %s en gebruiker %s@localhost/127.0.0.1 aanmaken\033[0m\n' \
               "$DB_NAAM" "$DB_GEBRUIKER"
        return 0
    fi

    mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAAM\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_GEBRUIKER'@'localhost' IDENTIFIED BY '$DB_WACHTWOORD';
CREATE USER IF NOT EXISTS '$DB_GEBRUIKER'@'127.0.0.1' IDENTIFIED BY '$DB_WACHTWOORD';
GRANT ALL PRIVILEGES ON \`$DB_NAAM\`.* TO '$DB_GEBRUIKER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAAM\`.* TO '$DB_GEBRUIKER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

    if [[ $bestaat_al == ja ]]; then
        local tabellen
        tabellen=$(mysql --protocol=socket -uroot -N -B -e \
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAAM'")
        if [[ $tabellen -gt 0 ]]; then
            waarschuw "database $DB_NAAM bestond al en bevat $tabellen tabellen —"
            waarschuw "  ⇒ deploy.sh --eerste weigert dat; dit is dan geen nieuwe installatie"
        else
            meld "database $DB_NAAM bestond al en is leeg"
        fi
    fi
    goed "database $DB_NAAM en gebruiker $DB_GEBRUIKER staan klaar"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Doelpad en .env
# ═════════════════════════════════════════════════════════════════════════════

# deploy.sh leidt aan de inhoud van het doelpad af wat voor uitrol dit is: staat
# er alléén een .env, dan is het een nieuwe installatie. Dus precies dat, en
# niets meer — geen releases/, geen shared/, dat legt deploy.sh zelf aan.
maak_doelpad() {
    stap "Doelpad $DOELPAD"

    if [[ -d "$DOELPAD/releases" ]]; then
        meld "hier staat al een installatie ($DOELPAD/releases); .env blijft ongemoeid"
        return 0
    fi

    doe mkdir -p "$DOELPAD"
    doe chmod 0755 "$DOELPAD"

    if [[ -f "$DOELPAD/.env" ]]; then
        meld ".env staat er al; die wordt niet overschreven"
        meld "controleer zelf ISMS_NORM, DB_DATABASE, DB_USERNAME en DB_PASSWORD"
        return 0
    fi

    local app_url="https://$HOSTNAAM"

    if [[ $DRYRUN == ja ]]; then
        printf '   \033[2m[dry-run] %s/.env schrijven (norm %s, database %s)\033[0m\n' \
               "$DOELPAD" "$NORM" "$DB_NAAM"
        return 0
    fi

    # Dit is een startpunt en niet de volledige .env.example uit de tarbal: die
    # kent meer sleutels, en deploy.sh noemt bij elke uitrol de sleutels die hier
    # ontbreken. De sleutels die er wél in staan, staan er omdat ze vóór de
    # eerste seed goed moeten zijn (ISMS_*) of omdat de applicatie zonder hen
    # niet start (DB_*, APP_*).
    cat >"$DOELPAD/.env" <<ENVBESTAND
# Geschreven door prephost.sh $PREPHOST_VERSIE op $(date -Iseconds).
#
# deploy.sh verplaatst dit bestand bij de eerste uitrol naar shared/.env; daar
# overleeft het elke volgende release. Vergelijk het na de eerste uitrol met
# .env.example uit de tarbal — die kent meer sleutels, met uitleg per sleutel.

APP_NAME=EzISMS
ORGANISATIE=""
APP_ENV=production
APP_DEBUG=false

# Leeg laten: deploy.sh draait bij --eerste \`artisan key:generate\`.
APP_KEY=
# Leeg laten: dan komt het versienummer uit het manifest van de release die
# werkelijk draait, en niet uit een sleutel die bij een upgrade blijft staan.
APP_VERSION=

APP_URL=$app_url

# ── Installatiekeuzes: goed zetten VÓÓR de eerste seed ───────────────────────
# ISMS_NORM is onomkeerbaar. NormprofielSeeder schrijft hem één keer naar de
# tabel \`normprofiel\`; daarna wint de database en is deze regel betekenisloos.
# Van norm wisselen betekent de database opnieuw opbouwen.
ISMS_NORM=$NORM

ISMS_2FA_AFDWINGEN=true
ISMS_2FA_RESPIJT_DAGEN=14

# Valt de organisatie onder de Cyberbeveiligingswet? Stuurt de meldtermijnen.
ISMS_CBW_PLICHTIG=false

# Niet met de hand op true zetten — dan is de dimensie actief maar leeg.
# Gebruik \`php artisan isms:capaciteiten aan\`.
ISMS_CAPACITEITEN=false

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAAM
DB_USERNAME=$DB_GEBRUIKER
DB_PASSWORD="$DB_WACHTWOORD"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
# De TLS termineert op HAProxy; de vhost vertaalt X-Forwarded-Proto naar de
# fastcgi-parameter HTTPS, dus een secure cookie komt aan.
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=

# Zonder mailserver blijven uitnodigingen en herinneringen in het log staan.
# Vul dit in vóór u gebruikers uitnodigt.
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS="isms@$HOSTNAAM"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
ENVBESTAND

    # 0640 en niet 0600: deploy.sh laat `artisan key:generate` er straks in
    # schrijven, en zet het eigendom daarna zelf op de webservergebruiker.
    chmod 0640 "$DOELPAD/.env"
    goed "$DOELPAD/.env geschreven (norm $NORM, database $DB_NAAM)"
}

# ═════════════════════════════════════════════════════════════════════════════
#  Serverblok
# ═════════════════════════════════════════════════════════════════════════════

schrijf_vhost() {
    stap "Serverblok $POOLNAAM"

    # De toegangsbeperking is er alleen als er een HAProxy-adres is opgegeven.
    # Een `deny all` zonder `allow` zou de site voor iedereen sluiten, en dat is
    # een storing die je pas na de uitrol ontdekt.
    local toegang=""
    if [[ -n $HAPROXY_IP ]]; then
        toegang=$(cat <<TOEGANG

    # Alleen HAProxy (TLS-terminatie) en localhost mogen deze vhost bereiken.
    # Zonder deze blokkade is het ISMS met een Host-header ook kaal over HTTP
    # te bereiken, en is X-Forwarded-Proto door de bezoeker zelf te zetten.
    allow $HAPROXY_IP;
    allow 127.0.0.1;
    deny all;
TOEGANG
)
    fi

    schrijf "/etc/nginx/sites-available/$POOLNAAM" 0644 <<VHOST
# EzISMS — het serverblok op deze host. Geschreven door prephost.sh $PREPHOST_VERSIE.
#
# De TLS wordt door HAProxy getermineerd; hier komt uitsluitend HTTP binnen.
#
# LET OP: root wijst naar de release-symlink en niet naar een releasemap. Wijs
# hem naar een vaste map, dan wisselt een volgende uitrol wel de symlink maar
# blijft de site op de oude boom staan.

server {
    listen 80;
    listen [::]:80;
    server_name $HOSTNAAM;
    root $DOELPAD/current/public;
$toegang
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Gelijk aan upload_max_filesize in de pool; anders zakt een groot
    # bewijsstuk op nginx in plaats van op PHP, met een 413 en niets in het
    # applicatielog.
    client_max_body_size 64m;

    # Toegang tot gevoelige bestandsextensies blokkeren
    location ~* \.(log|json|lock|sql|bak|ini|env)\$ { deny all; return 404; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        # De pool die als $WEBGEBRUIKER draait — dezelfde gebruiker die storage/
        # bezit. Wijst dit naar een andere pool, dan breekt deploy.sh de uitrol
        # af (controleer_koppeling).
        fastcgi_pass unix:/run/php/$POOLNAAM-fpm.sock;

        # \$realpath_root is hier essentieel: PHP ziet dan het echte releasepad,
        # dus er blijft na een uitrol geen oude opcache op het symlinkpad hangen.
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;

        # HAProxy termineert de TLS. De applicatie kent geen TrustProxies-
        # configuratie, dus dít is het HTTPS-besef: zonder deze constructie
        # genereert Laravel achter de proxy http://-URL's.
        set \$my_https "";
        if (\$http_x_forwarded_proto = "https") { set \$my_https "on"; }
        fastcgi_param HTTPS \$my_https;

        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/$POOLNAAM-access.log;
    error_log  /var/log/nginx/$POOLNAAM-error.log;
}
VHOST

    # Een back-up van een vhost hoort NOOIT in sites-enabled: nginx laadt die map
    # met een glob, en een kopie levert een tweede serverblok voor dezelfde
    # server_name op. Vandaar de symlink, en niet nog een bestand.
    doe ln -sfn "../sites-available/$POOLNAAM" "/etc/nginx/sites-enabled/$POOLNAAM"

    doe nginx -t
    doe systemctl reload nginx
    goed "http://$HOSTNAAM/ wijst naar $DOELPAD/current/public"

    if [[ -z $HAPROXY_IP ]]; then
        waarschuw "geen --haproxy=<ip> opgegeven: poort 80 staat open voor elke host die"
        waarschuw "  hier bij kan. Zet de allow/deny-regels erin zodra het adres bekend is."
    fi
}

# ═════════════════════════════════════════════════════════════════════════════
#  Controleren dat het klopt
# ═════════════════════════════════════════════════════════════════════════════

controleer_resultaat() {
    stap "Controle"
    if [[ $DRYRUN == ja ]]; then meld "--dry-run: overgeslagen"; return 0; fi

    local php="/usr/bin/php$PHP_REEKS"

    # Dezelfde lijst die builddistr.sh in het manifest zet en die deploy.sh
    # afdwingt (scripts/distr-gemeenschappelijk.sh :: PHP_EXTENSIES).
    local vereist=(ctype curl dom fileinfo filter gd hash mbstring
                   openssl pdo pdo_mysql session tokenizer xml zip)
    local aanwezig ontbreekt=() ext
    aanwezig=$("$php" -m | tr '[:upper:]' '[:lower:]')
    for ext in "${vereist[@]}"; do
        grep -qx "$ext" <<<"$aanwezig" || ontbreekt+=("$ext")
    done
    if [[ ${#ontbreekt[@]} -gt 0 ]]; then
        fout "ontbrekende php-extensies: ${ontbreekt[*]} — deploy.sh weigert de uitrol"
    fi
    goed "alle vereiste php-extensies aanwezig"

    # De eis van vendor/composer/platform_check.php in de tarbal.
    "$php" -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);' \
        && goed "PHP $("$php" -r 'echo PHP_VERSION;') voldoet aan >= 8.4.1" \
        || fout "PHP $("$php" -r 'echo PHP_VERSION;') is te oud; de applicatie eist >= 8.4.1"

    "$php" -r 'exit(($i = @gd_info()) && ($i["FreeType Support"] ?? false) ? 0 : 1);' \
        && goed "gd met FreeType aanwezig" \
        || waarschuw "gd zonder FreeType: de tolerantiematrix verschijnt als tabel zonder plaatje"

    ls /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf >/dev/null 2>&1 \
        && goed "DejaVu-lettertype aanwezig (voor de matrixafbeelding)" \
        || waarschuw "geen DejaVu-lettertype: de matrixafbeelding blijft leeg"

    # Wat deploy.sh :: controleer_gereedschap eist, hier alvast nagelopen.
    local cmd mist=()
    for cmd in tar rsync runuser useradd groupadd crontab systemctl sha256sum find flock \
               curl mysql mysqldump composer node npm; do
        command -v "$cmd" >/dev/null || mist+=("$cmd")
    done
    [[ ${#mist[@]} -eq 0 ]] \
        && goed "alle commando's voor deploy.sh aanwezig" \
        || waarschuw "ontbrekende commando's: ${mist[*]}"

    systemctl is-active --quiet nginx            && goed "nginx draait"            || waarschuw "nginx draait niet"
    systemctl is-active --quiet mysql            && goed "mysql draait"            || waarschuw "mysql draait niet"
    systemctl is-active --quiet "php$PHP_REEKS-fpm" && goed "php$PHP_REEKS-fpm draait" || waarschuw "php$PHP_REEKS-fpm draait niet"
    systemctl is-active --quiet cron             && goed "cron draait"             || waarschuw "cron draait niet — de scheduler zou stilstaan"

    if [[ $DOE_VHOST == ja ]]; then
        # De socket bestaat pas als de pool werkelijk is opgestart; dat is de
        # empirische tegencontrole op het poolbestand.
        if [[ -S "/run/php/$POOLNAAM-fpm.sock" ]]; then
            goed "de pool luistert, als $(stat -c%U "/run/php/$POOLNAAM-fpm.sock")"
        else
            waarschuw "/run/php/$POOLNAAM-fpm.sock bestaat niet; kijk in journalctl -u php$PHP_REEKS-fpm"
        fi
    fi

    if [[ $DOE_DATABASE == ja ]]; then
        if mysql -h 127.0.0.1 -u"$DB_GEBRUIKER" -p"$DB_WACHTWOORD" -e "USE \`$DB_NAAM\`" 2>/dev/null; then
            goed "de databasegegevens uit .env werken over TCP"
        else
            waarschuw "kan niet als $DB_GEBRUIKER op 127.0.0.1 in $DB_NAAM komen —"
            waarschuw "  ⇒ controleer DB_USERNAME en DB_PASSWORD in $DOELPAD/.env"
        fi
    fi
}

# ═════════════════════════════════════════════════════════════════════════════
#  Slotscherm
# ═════════════════════════════════════════════════════════════════════════════

slotscherm() {
    local wachtwoordregel="  databasewachtwoord  $DB_WACHTWOORD"
    if [[ $DOE_DATABASE == nee ]]; then
        wachtwoordregel="  database            overgeslagen (--geen-database)"
    fi

    cat <<EOF

$(printf '\033[1m')Klaar — deze host kan het ISMS draaien.$(printf '\033[0m')

  doelpad             $DOELPAD
  normprofiel         $NORM   (na de eerste seed onomkeerbaar)
  database            $DB_NAAM, gebruiker $DB_GEBRUIKER
$wachtwoordregel
  php                 $PHP_REEKS, pool $POOLNAAM op /run/php/$POOLNAAM-fpm.sock
  vhost               $([[ $DOE_VHOST == ja ]] && echo "/etc/nginx/sites-available/$POOLNAAM (server_name $HOSTNAAM)" || echo "overgeslagen (--geen-vhost)")
  waarschuwingen      $WAARSCHUWINGEN

Het wachtwoord staat in $DOELPAD/.env en hierboven in uw scrollback. Wist die,
of bewaar het meteen in de wachtwoordkluis van de organisatie.

$(printf '\033[1m')Nu de uitrol:$(printf '\033[0m')

  1. Loop $DOELPAD/.env na — vooral ISMS_NORM, ISMS_CBW_PLICHTIG en de
     MAIL_-sleutels. Wat vóór de eerste seed goed moet staan, staat daarna vast.

  2. sudo deploy.sh ezisms-<versie>.tar.gz $DOELPAD --eerste

     deploy.sh maakt zelf de code-eigenaar \`ezisms\`, releases/, shared/, de
     crontabregel en de rechten. Hij verwacht dat $DOELPAD nu alléén een .env
     bevat — precies wat hier staat.

  3. Daarna het eerste CISO-account, met een wachtwoord dat niet in een
     deploylog thuishoort:

       cd $DOELPAD/current
       sudo -u $WEBGEBRUIKER php artisan isms:eerste-ciso <e-mail> '<wachtwoord>' '<naam>'

Wat dit script bewust NIET heeft gedaan:
  * TLS. Die termineert op HAProxy; wijs hem naar http://$HOSTNAAM:80 van deze host.
  * De firewall. Poort 80 hoort alleen vanaf HAProxy bereikbaar te zijn$([[ -n $HAPROXY_IP ]] && echo " (de vhost dwingt dat af)" || echo "; zonder --haproxy=<ip> staat hij open").
  * Back-ups van de database en van $DOELPAD/shared/storage.
  * mysql_secure_installation$([[ $DOE_MYSQL_HARDING == ja ]] && echo " — het niet-interactieve deel is wel gedraaid" || echo " (overgeslagen)").

EOF
}

# ═════════════════════════════════════════════════════════════════════════════
#  Hoofdstroom
# ═════════════════════════════════════════════════════════════════════════════

hoofd() {
    controleer_root
    verwerk_argumenten "$@"

    trap 'fout "onverwachte fout op regel $LINENO"' ERR

    printf '\033[1mISMS — host voorbereiden\033[0m — %s\n' "$(date -Iseconds)"
    meld "prephost.sh $PREPHOST_VERSIE, draait als $(id -un)"

    controleer_distributie
    installeer_basis
    installeer_nginx
    installeer_mysql
    installeer_php
    if [[ $DOE_VHOST    == ja ]]; then schrijf_pool;      fi
    installeer_composer
    installeer_node
    if [[ $DOE_PANDOC   == ja ]]; then installeer_pandoc; fi
    if [[ $DOE_DATABASE == ja ]]; then maak_database;     fi
    if [[ $DOE_ENV      == ja ]]; then maak_doelpad;      fi
    if [[ $DOE_VHOST    == ja ]]; then schrijf_vhost;     fi

    controleer_resultaat
    slotscherm
}

hoofd "$@"
