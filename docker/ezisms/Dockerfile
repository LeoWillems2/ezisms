# ─────────────────────────────────────────────────────────────────────────────
# EzISMS — de applicatiecontainer
#
# De build-context is een uitgepakte distributietarbal, niet de git-werkkopie:
# vendor/ en public/build zitten daar al gebouwd in. Bouwen hoort op de bouwhost
# — daar staan de gereedschappen en eventuele credentials, en de boom die daar
# is samengesteld en met SHA256SUMS is vastgelegd is de boom die hier draait.
#
# Gevolg: geen nodejs, geen npm, geen composer in dit image.
# Zie implementatie/00l-docker-stack.md §0.2 en §4.
# ─────────────────────────────────────────────────────────────────────────────

FROM ubuntu:26.04

ENV DEBIAN_FRONTEND=noninteractive

# De PHP-reeks van de distributie. Ubuntu 26.04 levert 8.5; de eis van de
# applicatie is >= 8.4.1 (vendor/composer/platform_check.php), dus dat voldoet.
# Dit is de ENIGE plek waar het versienummer staat: de pool, nginx en supervisord
# praten over vaste namen (zie onderaan), zodat een distributie-upgrade alleen
# deze regel raakt.
ARG PHP_REEKS=8.5

# ── Pakketten ────────────────────────────────────────────────────────────────
# Geen cron: de scheduler draait onder supervisord (§0.6).
# Geen pandoc: die komt niet uit apt maar uit de RUN hieronder — zie daar waarom.
#
# php-gd  — moet FreeType hebben, anders verschijnt de tolerantiematrix als
#           tabel zonder plaatje.
# mysql-client — mysqldump vóór elke migratie op een bestaande database. Dat is
#           verplicht en heeft geen ontsnapping (00n §9.5), dus zonder dit
#           pakket start een bestaande installatie niet meer.
# curl    — de healthcheck van compose.yml roept hem in de container aan, en de
#           pandoc-RUN hieronder haalt er zijn pakket mee op.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor \
        "php${PHP_REEKS}-fpm" "php${PHP_REEKS}-cli" "php${PHP_REEKS}-mysql" \
        "php${PHP_REEKS}-mbstring" "php${PHP_REEKS}-xml" "php${PHP_REEKS}-curl" \
        "php${PHP_REEKS}-zip" "php${PHP_REEKS}-gd" \
        mysql-client curl ca-certificates tzdata \
    && rm -rf /var/lib/apt/lists/*

# ── pandoc ───────────────────────────────────────────────────────────────────
# pandoc maakt de RTF/docx/odt-preview van bewijsstukken, de schermkopie voor de
# auditor en de Word-versie van een kennisartikel. Vereist >= 3.1.7, de eerste
# versie met een RTF-lezer.
#
# NIET `apt install pandoc`, en dat is de reden dat dit een eigen laag is. De
# pandoc van Ubuntu 26.04 (3.7.0.2+ds-1, met zijn datafiles in een apart pakket
# pandoc-data) kan met `--sandbox` géén .docx schrijven:
#
#     $ echo '# Hoi' | pandoc --sandbox --from=markdown --to=docx --output=-
#     Could not find data file data/data/docx/[Content_Types].xml
#
# In sandbox-modus leest pandoc zijn datafiles uitsluitend uit de binary zelf, en
# in die build zitten ze er niet in. `--data-dir` helpt niet — de sandbox negeert
# die map juist, dat is zijn hele punt. Gevolg: een HTTP 503 op élke Word-
# download, terwijl de HTML-preview bleef werken omdat de html-writer die
# datafiles niet nodig heeft.
#
# `--sandbox` laten vallen voor alleen de docx-conversie was het alternatief,
# maar dan leest de docx-writer plaatjespaden uit de markdown van schijf — en in
# de markdown van een register of een schermkopie staat gebruikersinvoer.
#
# Dit is dezelfde bron als op de ontwikkelmachine (ontwikkelmachine/LEESMIJ.md):
# de release van jgm/pandoc, één statische binary met de datafiles ingebakken.
# Vastgepind op versie én hash, want een build hoort niet stilletjes iets anders
# op te halen dan wat er getoetst is. Bij een nieuwe versie: het nummer hier en
# de twee sommen hieronder, alle drie tegelijk.
#
# LET OP: deze laag heeft github.com nodig tijdens de build.
ARG PANDOC_VERSIE=3.10.2
RUN set -eu; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
        amd64) som=6c06b69b49ae95087573631a6fcafb233ab7ab51e5cfa73f7539d6c964a2640d ;; \
        arm64) som=868c7675806237dd21711e3890e82f2844e011c8f542a1ddc6245df4324dd6b5 ;; \
        *) echo "Geen vastgepind pandoc-pakket voor architectuur '$arch'." >&2; exit 1 ;; \
    esac; \
    deb="pandoc-${PANDOC_VERSIE}-1-${arch}.deb"; \
    curl -fsSLo "/tmp/${deb}" \
        "https://github.com/jgm/pandoc/releases/download/${PANDOC_VERSIE}/${deb}"; \
    echo "${som}  /tmp/${deb}" | sha256sum -c -; \
    dpkg -i "/tmp/${deb}"; \
    rm -f "/tmp/${deb}"

# De controle waar het hierboven om begonnen is, en niet zomaar `pandoc
# --version`: dit is precies het commando uit App\Support\Pandoc::converteer().
# Levert een toekomstige pandoc dezelfde sandbox-fout op, dan faalt de build hier
# met een leesbare melding, in plaats van straks bij de klant als een 503 zonder
# regel in het log.
RUN printf '# Toets\n' | pandoc --sandbox --from=markdown --to=docx --output=/dev/null \
    && pandoc --version | head -1

# ── Twee accounts, zelfde taakverdeling als bij deploy.sh ────────────────────
#   ezisms    bezit de code en draait niets. Het webproces kan zijn eigen
#             applicatiecode dus niet herschrijven (A.8.19).
#   www-data  draait php-fpm, de scheduler en artisan, en bezit alles waar de
#             applicatie in schrijft.
# Hier bij de build en niet met useradd op runtime: een image hoort te weten wie
# het is voordat het start.
RUN groupadd --system ezisms && \
    useradd --system --gid ezisms --home-dir /opt/ezisms \
            --shell /usr/sbin/nologin --comment "ISMS-code" ezisms

# ── De applicatieboom ────────────────────────────────────────────────────────
# Eigenaar en rechten worden hier gezet en niet in een RUN erna. Dat scheelt
# minuten per build en 46 MB image, en de reden zit in overlayfs: een laag kan
# een bestand uit een onderliggende laag niet wijzigen zonder het eerst volledig
# naar zichzelf te kopiëren. Eén `chown -R` over deze boom betekende dus 9025
# copy-ups van samen 73 MB — de boom stond daarna twee keer in het image.
#
# --chmod is niet overbodig: `tar xzf` past bij een gewone gebruiker de umask
# toe. Pakt de klant de tarbal uit met umask 077, dan staan de bestanden op 0600
# en zou COPY die rechten overnemen — waarna www-data de code niet eens kan
# lezen en php-fpm elke pagina als 500 teruggeeft.
WORKDIR /opt/ezisms
COPY --chown=ezisms:ezisms --chmod=0644 . /opt/ezisms

# --chmod kent maar één waarde voor bestanden én mappen, dus de mappen komen
# binnen op 0644 en zijn niet doorloopbaar. Dat wordt hier rechtgezet, vóór
# alles wat de boom leest. Deze `find` is wél goedkoop: een map wijzigen kopieert
# alleen de map-inode en niet zijn inhoud.
RUN find /opt/ezisms -type d -exec chmod 0755 {} + && \
    chmod 0755 /opt/ezisms/artisan /opt/ezisms/scripts/*.sh

# De integriteitscontrole van deploy.sh, maar op bouwtijd. Een halve overdracht
# van de tarbal faalt hier, met een leesbare melding, in plaats van straks als
# een HTTP 500 bij de klant.
RUN sha256sum -c --quiet SHA256SUMS

# Een tarbal van `builddistr.sh --geen-bouw` draagt geen vendor/ en geen
# public/build. Voor de bare-metal route is dat opgelost — deploy.sh bouwt ze
# daar op de doelhost — maar dit image kan dat niet: er zit geen composer, geen
# npm en geen node in (zie de kop van dit bestand). Zonder deze regel struikelt
# de volgende over een ontbrekend bestand, en dat leest als een defect image in
# plaats van als een verkeerd gebouwde tarbal.
RUN [ -f vendor/autoload.php ] && [ -d public/build ] || { \
        echo "Deze tarbal is met --geen-bouw gebouwd: vendor/ en public/build ontbreken."; \
        echo "De Docker-route heeft een gebouwde tarbal nodig; bouw hem opnieuw"; \
        echo "met builddistr.sh zonder --geen-bouw."; \
        exit 1; \
    }

# Dezelfde controle als controleer_php_pakket: dit bestand ís de eis, composer
# schrijft er de platformvoorwaarden in waarmee vendor/ gebouwd is (§0.8).
# Faalt dit, dan levert het basisimage geen PHP 8.4.1+.
RUN php -r 'require "vendor/composer/platform_check.php";' \
    && php -r 'echo "PHP ", PHP_VERSION, " voldoet\n";'

# Het statische deel van de configuratie. Alles wat per installatie verschilt
# komt uit de omgeving en overschrijft dit — zie §0.4.
#
# Eigenaar en rechten expliciet, om dezelfde reden als bij de boom hierboven: dit
# bestand komt uit de build-context en draagt dus de umask van wie de tarbal
# uitpakte. Zonder deze twee vlaggen kan www-data het bij umask 077 niet lezen.
COPY --chown=ezisms:ezisms --chmod=0644 docker/env.statisch /opt/ezisms/.env

# Wat in de tarbal alleen een leeg skelet is, wordt een symlink naar de
# hostmap. Vaste paden, dus dit kan bij de build en hoeft niet elke start.
# Toetsbestanden hadden hier tot 01e een derde symlink: ze stonden in
# public/toetsen en werden door nginx uitgeserveerd. Ze staan nu in
# storage/app/private/toetsen — dus achter de eerste symlink hieronder — en
# gaan door de applicatie heen, die er een CSP-sandbox omheen zet.
RUN rm -rf /opt/ezisms/storage && \
    ln -sfn /var/lib/ezisms/storage            /opt/ezisms/storage && \
    ln -sfn /var/lib/ezisms/storage/app/public /opt/ezisms/public/storage

# De code is van ezisms — dat staat al op de COPY hierboven. Wat hier overblijft
# is wat de COPY niet kon weten.
#
# bootstrap/cache is de uitzondering op "niet schrijfbaar voor de applicatie":
# daar schrijft config:cache in, elke start (§0.4), en die map blijft bewust
# container-lokaal. public/ gaat mee omdat de twee symlinks erin naar de mount
# wijzen waar de applicatie wél schrijft.
#
# De drie symlinks zijn door de RUN hierboven gemaakt en dus van root; ze vallen
# buiten de COPY. -h op chown: ze wijzen naar een mount die tijdens de build nog
# niet bestaat, dus zonder -h volgt chown het doel en faalt hij.
RUN chown -h ezisms:ezisms /opt/ezisms/storage && \
    chown -Rh www-data:www-data /opt/ezisms/public /opt/ezisms/bootstrap/cache

# ── Webserver, pool, procesbewaking ──────────────────────────────────────────
# sites-enabled/default eruit: anders bedient nginx twee sites op poort 80 en
# wint de verkeerde. pool.d/www.conf eruit: anders draaien er twee pools en is
# niet vast te stellen welke de site bedient — precies waar controleer_koppeling
# op bare metal voor bestaat.
#
# /usr/local/sbin/ezisms-fpm is een symlink naar de php-fpm van deze reeks.
# Daarmee is supervisord.conf versievrij, net als nginx.conf en de pool: die
# praten over /run/php/ezisms-fpm.sock, een naam die wij zelf kiezen in
# php-fpm-pool.conf. Het versienummer staat alleen in de ARG hierboven.
COPY docker/nginx.conf        /etc/nginx/sites-available/ezisms
COPY docker/php-fpm-pool.conf /etc/php/${PHP_REEKS}/fpm/pool.d/ezisms.conf
COPY docker/supervisord.conf  /etc/supervisor/conf.d/ezisms.conf
COPY docker/entrypoint.sh     /entrypoint.sh

# De drie `sed`-regels hieronder halen het laatste logverkeer uit /var/log
# (implementatie/00p §2). Onze site en onze pool schrijven al naar de streams,
# maar de hóófdconfiguraties van de distributie niet — en juist die dekken wat
# je bij een startprobleem nodig hebt:
#
#   nginx.conf:5    de master-error_log. `error_log /dev/stderr` in ons
#                   serverblok geldt pas binnen dat blok, dus een fout in de
#                   configuratie ("unknown directive") landde in de container
#                   en was onvindbaar — precies op het moment dat nginx niet
#                   start en er dus ook geen serverblok is.
#   nginx.conf:41   de http-access_log. Ons serverblok overschrijft hem, dus er
#                   kwam nooit iets in — maar nginx opent het bestand wél bij
#                   het starten, en dat is waarom /var/log/nginx niet leeg was.
#   php-fpm.conf    de master van php-fpm ("ready to handle connections",
#                   "child N exited on signal"). Onze pool zet alleen de
#                   error_log van de pool.
#
# `sed` op de distributiebestanden en geen eigen kopie ernaast: een volledige
# nginx.conf meeleveren betekent hem bij elke Ubuntu-upgrade weer vergelijken.
# De `grep` erachter is het vangnet — hernoemt of verplaatst de distributie zo'n
# regel, dan mist de sed hem en faalt de build, in plaats van dat het log
# stilzwijgend weer in de container verdwijnt.
#
# php-fpm krijgt `/proc/self/fd/2` en niet `/dev/stderr`: de master heropent zijn
# error_log, en dat pad is in een container niet betrouwbaar. De pool mag
# /dev/stderr houden — die heropent niet en doet het aantoonbaar.
RUN rm -f /etc/nginx/sites-enabled/default \
          "/etc/php/${PHP_REEKS}/fpm/pool.d/www.conf" && \
    ln -s /etc/nginx/sites-available/ezisms /etc/nginx/sites-enabled/ezisms && \
    ln -s "/usr/sbin/php-fpm${PHP_REEKS}" /usr/local/sbin/ezisms-fpm && \
    mkdir -p /run/php && \
    chmod 0755 /entrypoint.sh && \
    sed -i 's#^error_log .*#error_log /dev/stderr;#' /etc/nginx/nginx.conf && \
    sed -i 's#^\([[:space:]]*\)access_log .*#\1access_log /dev/stdout;#' /etc/nginx/nginx.conf && \
    sed -i 's#^error_log = .*#error_log = /proc/self/fd/2#' \
           "/etc/php/${PHP_REEKS}/fpm/php-fpm.conf" && \
    ! grep -q '/var/log' /etc/nginx/nginx.conf \
                         "/etc/php/${PHP_REEKS}/fpm/php-fpm.conf" && \
    nginx -t && \
    ezisms-fpm --test

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
