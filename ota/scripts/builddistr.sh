#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# builddistr.sh — bouwt het distributiepakket van het ISMS
#
# Draait op de bouwhost, in de git-werkkopie. Levert één tarbal op die met
# scripts/deploy.sh op een doelhost uitgerold kan worden.
#
# LEESWIJZER
#   1. De bestandslijst komt uit git (`git archive`), niet uit de map. Alles wat
#      untracked of genegeerd is valt daarmee structureel af: .env, auth.json,
#      vendor/, node_modules/, public/build, controls.json en
#      maatregel-capaciteiten.json. De twee controlsets komen wél mee, maar in de
#      staat van versiebeheer: `git archive` leest uit de commit en niet uit de
#      werkkopie, dus een lokaal ingevulde normtekst reist niet mee. Een
#      handmatige uitsluitlijst zou je moeten onthouden bij te werken; git weet
#      het al. Eén ding komt van buiten ota/: de demofixtures van het
#      FruitBV-scenario, die apart geëxporteerd worden.
#   2. Daarna volgt een copyrightcontrole op wat er dan nog ligt. Sinds
#      11-08-2026 is dit de énige plek waar die regel nog afgedwongen wordt: de
#      pre-commit hook die de commit bewaakte is verwijderd. Deze controle staat
#      dus verder naar achteren dan prettig is — hij houdt tegen dat normtekst
#      wordt uitgeleverd, niet dat ze wordt vastgelegd.
#   3. Pas daarna wordt er gebouwd (composer, npm). Dat gebeurt hier omdat de
#      doelhost dan niets meer nodig heeft dan PHP: geen composer, geen npm,
#      geen node, en geen uitgaande verbinding naar packagist.org, github.com en
#      het npm-register. Eén boom die op de bouwhost is samengesteld en met
#      SHA256SUMS is vastgelegd, is bovendien de boom die overal draait.
#
#      Tot 14-08-2026 stond hier een tweede reden — livewire/flux zou
#      proprietary zijn en auth.json met credentials vragen — en daar hing een
#      harde controle aan. Die klopt niet: `composer.lock` haalt flux van
#      github.com, en de bouw komt zonder enige auth.json schoon door. De
#      controle is daarom vervallen. Zet er wél een neer (in de projectmap of in
#      COMPOSER_HOME) zodra er een betaald pakket bij komt, of om een
#      GitHub-token mee te geven; composer vindt hem dan zelf.
#
# Voorwaarden op de bouwhost: git, php, composer, npm, python3, tar, sha256sum.
# ─────────────────────────────────────────────────────────────────────────────

set -Eeuo pipefail

SCRIPTMAP=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=distr-gemeenschappelijk.sh
source "$SCRIPTMAP/distr-gemeenschappelijk.sh"

# ── Instellingen ─────────────────────────────────────────────────────────────
# Elk via ": ${NAAM:=…}", dus per aanroep te overschrijven zonder het script te
# wijzigen:  UIT=/tmp/dist scripts/builddistr.sh
: "${REF:=HEAD}"                  # wat er ingepakt wordt
: "${UIT:=}"                      # doelmap voor de tarbal (standaard: <repo>/dist)
: "${VERSIE:=$(date +%Y.%m.%d)}"  # versienummer in de bestandsnaam en het manifest
: "${BOUWEN:=ja}"                 # nee ⇒ zonder vendor/ en public/build
: "${PHP_BIN:=php}"               # de php die de bouwhost gebruikt
# ─────────────────────────────────────────────────────────────────────────────

gebruik() {
    cat <<'EOF'
Gebruik: builddistr.sh [opties]

  --ref=<git-ref>     wat er ingepakt wordt (standaard: HEAD)
  --uit=<map>         waar de tarbal komt (standaard: <repo>/dist)
  --versie=<naam>     versienummer (standaard: de datum van vandaag)
  --geen-bouw         zonder vendor/ en public/build; deploy.sh bouwt die dan op
                      de doelhost, die daarvoor composer, npm en node nodig heeft.
                      Niet te gebruiken voor de Docker-route: dat image bouwt niet.
  -h, --help          deze uitleg
EOF
}

# ── Meldingen ────────────────────────────────────────────────────────────────
stap()      { printf '\n\033[1m== %s\033[0m\n' "$*"; }
meld()      { printf '   %s\n' "$*"; }
waarschuw() { printf '\033[33m   let op: %s\033[0m\n' "$*" >&2; }
fout()      { printf '\033[31m\nFOUT: %s\033[0m\n' "$*" >&2; exit 1; }

# ── Argumenten ───────────────────────────────────────────────────────────────
for arg in "$@"; do
    case "$arg" in
        --ref=*)      REF=${arg#*=} ;;
        --uit=*)      UIT=${arg#*=} ;;
        --versie=*)   VERSIE=${arg#*=} ;;
        --geen-bouw)  BOUWEN=nee ;;
        -h|--help)    gebruik; exit 0 ;;
        *)            gebruik >&2; fout "onbekend argument: $arg" ;;
    esac
done

# ── Voorwaarden ──────────────────────────────────────────────────────────────
for cmd in git tar sha256sum python3 "$PHP_BIN"; do
    command -v "$cmd" >/dev/null || fout "$cmd ontbreekt op deze bouwhost"
done

REPO=$(git -C "$SCRIPTMAP" rev-parse --show-toplevel) \
    || fout "dit script staat niet in een git-werkkopie"
: "${UIT:=$REPO/dist}"

git -C "$REPO" rev-parse --verify --quiet "$REF" >/dev/null \
    || fout "git-ref bestaat niet: $REF"

COMMIT=$(git -C "$REPO" rev-parse "$REF")
KORT=$(git -C "$REPO" rev-parse --short "$REF")
# De tag waar deze boom precies op staat, of leeg. `--exact-match` en niet het
# kale `git describe`: die verzint bij een commit ná een tag iets als
# "V2.5.0-3-gab0b683", en dat is geen versienummer om in een zijbalk te zetten.
# Leeg is hier een geldige uitkomst — een tussentijdse bouw hoort geen
# versienummer te claimen dat niet bestaat.
TAG=$(git -C "$REPO" describe --tags --exact-match "$REF" 2>/dev/null || true)
MAPNAAM="$PAKKETNAAM-$VERSIE-$KORT"
TARBAL="$UIT/$MAPNAAM.tar.gz"

# Een vuile werkkopie is geen bezwaar — er wordt niets van meegenomen — maar
# "ik had het toch uitgerold?" is een voorspelbare vergissing, dus het wordt
# gemeld.
if [[ -n $(git -C "$REPO" status --porcelain) ]]; then
    waarschuw "de werkkopie heeft ongecommitte wijzigingen; die gaan NIET mee"
    waarschuw "ingepakt wordt uitsluitend $REF ($KORT)"
fi

if [[ $BOUWEN == ja ]]; then
    command -v composer >/dev/null || fout "composer ontbreekt (of gebruik --geen-bouw)"
    command -v npm >/dev/null      || fout "npm ontbreekt (of gebruik --geen-bouw)"
    # Hier stond tot 14-08-2026 een controle op auth.json. Die is vervallen; zie
    # de leeswijzer bovenaan. Heeft composer credentials nodig, dan zegt hij dat
    # zelf, met de naam van het pakket erbij — dat is een betere melding dan een
    # aanname vooraf over welk pakket die nodig zou hebben.
fi

BOUWMAP=$(mktemp -d -t ezisms-bouw.XXXXXXXX)
trap 'rm -rf "$BOUWMAP"' EXIT
BOOM="$BOUWMAP/$MAPNAAM"
mkdir -p "$BOOM"

# ── 1. Exporteren uit git ────────────────────────────────────────────────────
stap "Exporteren uit git — $REF ($KORT)"
git -C "$REPO" archive --format=tar "$REF:ota" | tar -x -C "$BOOM"
meld "$(find "$BOOM" -type f | wc -l) bestanden uit versiebeheer"

# De demofixtures staan buiten ota/ en vallen dus buiten de export hierboven.
# Een tweede archive haalt ze op; ze belanden op dezelfde plek in de boom, zodat
# deploy.sh ze met --fixtures= kan aanwijzen.
if git -C "$REPO" rev-parse --verify --quiet "$REF:$DEMOFIXTURES_BRON" >/dev/null; then
    mkdir -p "$BOOM/$DEMOFIXTURES_DOEL"
    git -C "$REPO" archive --format=tar "$REF:$DEMOFIXTURES_BRON" \
        | tar -x -C "$BOOM/$DEMOFIXTURES_DOEL"
    DEMOFIXTURES=$(find "$BOOM/$DEMOFIXTURES_DOEL" -type f | wc -l)
    meld "$DEMOFIXTURES demofixtures uit $DEMOFIXTURES_BRON"
else
    DEMOFIXTURES=0
    waarschuw "$DEMOFIXTURES_BRON zit niet in $REF; deze tarbal kan geen demo vullen"
fi

# Hetzelfde voor de Docker-subboom. Die moet vóór stap 3 in de boom staan: het
# opruimen van de versiebeheersporen hoort ook de .gitignore hierin te pakken.
if git -C "$REPO" rev-parse --verify --quiet "$REF:$DOCKER_BRON" >/dev/null; then
    mkdir -p "$BOOM/$DOCKER_DOEL"
    git -C "$REPO" archive --format=tar "$REF:$DOCKER_BRON" \
        | tar -x -C "$BOOM/$DOCKER_DOEL"
    DOCKERBESTANDEN=$(find "$BOOM/$DOCKER_DOEL" -type f | wc -l)
    meld "$DOCKERBESTANDEN bestanden voor de Docker-uitrol uit $DOCKER_BRON"
else
    DOCKERBESTANDEN=0
    waarschuw "$DOCKER_BRON zit niet in $REF; deze tarbal kan niet als Docker-stack draaien"
fi

# ── 2. Copyrightcontrole ─────────────────────────────────────────────────────
# Toegepast op wat er daadwerkelijk uitgeleverd gaat worden. Faalt er iets, dan
# komt er geen tarbal. Dit is de laatste grendel; er zit er geen meer vóór de
# commit (zie de leeswijzer bovenaan).
stap "Copyrightcontrole op de seeddata"
DATAMAP="$BOOM/database/seeders/data"
TOEGESTAAN=$(printf '%s\n' "${SEEDDATA_TOEGESTAAN[@]}")

TOEGESTAAN="$TOEGESTAAN" OMSCHRIJVINGTEKST="$OMSCHRIJVINGTEKST" ZORGTEKST="$ZORGTEKST" \
ZORGLEEG="$ZORGLEEG" DATAMAP="$DATAMAP" python3 - <<'PY' || fout "de seeddata is niet uitleverbaar; zie hierboven"
import json, os, sys
from pathlib import Path

datamap    = Path(os.environ["DATAMAP"])
toegestaan = set(os.environ["TOEGESTAAN"].split("\n"))

# Per veld de toegestane verzameling. Zelfde waarden als in
# distr-gemeenschappelijk.sh en als de constanten op Maatregel.
velden = {
    "omschrijving":   {os.environ["OMSCHRIJVINGTEKST"]},
    "zorgaanvulling": {os.environ["ZORGLEEG"], os.environ["ZORGTEKST"]},
}

fouten = []

# a. Niets in de map dat er niet hoort. Een nieuw gegenereerd bestand valt zo op
#    in plaats van mee te reizen, en een achtergebleven maatregelen-basis.json
#    laat de bouw struikelen — precies wat je wilt tijdens een verbouwing.
aanwezig = {p.name for p in datamap.iterdir() if p.is_file()}
for naam in sorted(aanwezig - toegestaan):
    fouten.append(f"{naam}: staat niet op de lijst van uitleverbare seedbestanden")

# b. Beide controlsets: één lus, twee velden, dezelfde regel. De titels worden
#    hier niet gecontroleerd — die zijn openbaar en statisch, en de testsuite
#    vergelijkt ze tussen de twee bestanden.
for profiel in ("iso27001", "nen7510"):
    naam = f"maatregelen-{profiel}.json"
    pad = datamap / naam
    if not pad.is_file():
        fouten.append(f"{naam}: ontbreekt; elke tarbal draagt beide controlsets")
        continue

    data = json.loads(pad.read_text(encoding="utf-8"))
    for veld, toegestane_waarden in velden.items():
        echt = [r["annex_a_referentie"] for r in data.get("maatregelen", [])
                if r.get(veld) not in toegestane_waarden]
        if echt:
            fouten.append(
                f"{naam} draagt normtekst in het veld '{veld}' bij {len(echt)} "
                f"maatregelen (o.a. {', '.join(echt[:3])}) — herstel met: "
                f"git checkout HEAD -- ota/database/seeders/data/{naam}"
            )

for f in fouten:
    print(f"   \033[31m{f}\033[0m", file=sys.stderr)
sys.exit(1 if fouten else 0)
PY
meld "geen gelicentieerde normtekst aangetroffen"

# ── 3. Opschonen ─────────────────────────────────────────────────────────────
stap "Opschonen: leeg wat leeg begint, weg wat van versiebeheer is"
# De ${…+…}-vorm is nodig: onder `set -u` klapt "${leeg[@]}" eruit op bash < 4.4,
# en sinds 01e is deze lijst leeg.
for rel in ${LEEG_NA_EXPORT[@]+"${LEEG_NA_EXPORT[@]}"}; do
    if [[ -d "$BOOM/$rel" ]]; then
        aantal=$(find "$BOOM/$rel" -type f | wc -l)
        find "$BOOM/$rel" -type f -delete
        meld "$rel geleegd ($aantal bestanden — materiaal van derden)"
    fi
done

# Hier en niet later: ná stap 4 zou dit ook de .gitignore-bestanden ín vendor/
# opruimen, en ná stap 5 zou SHA256SUMS naar bestanden verwijzen die er niet
# meer zijn — dan faalt de uitrol op zijn eigen controle.
for naam in "${VERSIEBEHEERSPOREN[@]}"; do
    aantal=$(find "$BOOM" -name "$naam" -prune | wc -l)
    (( aantal )) || continue
    find "$BOOM" -name "$naam" -prune -exec rm -rf {} +
    meld "$naam verwijderd ($aantal×)"
done

# Het storage-skelet is nu een boom van lege mappen: de .gitignore-bestanden die
# git eerder liet meekomen zijn hierboven verdwenen. Dat mag, want tar bewaart
# lege mappen en het manifest legt de mappen zelf vast — niet hun inhoud.
# deploy.sh maakt ze daarmee aan in shared/storage.
STORAGE_SKELET=$(cd "$BOOM" && find storage -type d | sort)
meld "storage-skelet: $(wc -l <<<"$STORAGE_SKELET") mappen"

# ── 4. Bouwen ────────────────────────────────────────────────────────────────
if [[ $BOUWEN == ja ]]; then
    stap "PHP-afhankelijkheden (zonder dev)"
    # --no-interaction is hier geen overbodige beleefdheid: vindt composer om
    # wat voor reden dan ook geen composer.json waar hij hem verwacht, dan
    # vráágt hij of hij een andere mag gebruiken. Die prompt gaat naar stderr en
    # stdin blijft aan de terminal hangen — het script lijkt dan zonder reden te
    # blijven staan tot je Enter geeft. Zelfde reden waarom composer met
    # ontbrekende credentials hier hoorbaar faalt in plaats van te wachten.
    ( cd "$BOOM" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress )

    stap "Frontend bouwen"
    ( cd "$BOOM" && npm ci --no-audit --no-fund && npm run build )
    # node_modules is build-time: de doelhost heeft alleen public/build nodig.
    rm -rf "$BOOM/node_modules"
    meld "node_modules verwijderd; public/build blijft"
    GEBOUWD='["vendor","public/build"]'
else
    waarschuw "--geen-bouw: deze tarbal draagt geen vendor/ en geen public/build"
    waarschuw "deploy.sh bouwt ze op de doelhost; die heeft daarvoor composer, npm"
    waarschuw "en node nodig, plus toegang tot packagist.org en github.com"
    waarschuw "de Docker-route werkt hier niet mee: dat image bouwt zelf niets"
    GEBOUWD='[]'
    MINIMALE_DEPLOY_VERSIE=$MINIMALE_DEPLOY_VERSIE_ONGEBOUWD
fi

# auth.json kan nooit uit git komen, maar composer schrijft hem soms weg in de
# projectmap. Dubbele bodem, want dit is een credentialbestand.
rm -f "$BOOM/auth.json"

# ── 5. Manifest en checksums ─────────────────────────────────────────────────
stap "Manifest en checksums"
( cd "$BOOM" && find . -type f ! -name SHA256SUMS -print0 \
    | sort -z | xargs -0 sha256sum > SHA256SUMS )
meld "$(wc -l <"$BOOM/SHA256SUMS") bestanden in SHA256SUMS"

PHP_VERSIE=$("$PHP_BIN" -r 'echo PHP_VERSION;')
NODE_VERSIE=$(command -v node >/dev/null && node -v || echo "n.v.t.")

MANIFEST_VERSIE="$MANIFEST_VERSIE" PAKKETNAAM="$PAKKETNAAM" VERSIE="$VERSIE" \
COMMIT="$COMMIT" REF="$REF" MAPNAAM="$MAPNAAM" TAG="$TAG" \
MIN_DEPLOY="$MINIMALE_DEPLOY_VERSIE" PHP_VERSIE="$PHP_VERSIE" \
NODE_VERSIE="$NODE_VERSIE" GEBOUWD="$GEBOUWD" \
EXTENSIES="$(printf '%s\n' "${PHP_EXTENSIES[@]}")" \
SKELET="$STORAGE_SKELET" \
DEMOMAP="$DEMOFIXTURES_DOEL" DEMOAANTAL="$DEMOFIXTURES" \
DOCKERMAP="$DOCKER_DOEL" DOCKERAANTAL="$DOCKERBESTANDEN" \
python3 - >"$BOOM/MANIFEST.json" <<'PY'
import json, os, subprocess
print(json.dumps({
    "manifest_versie":        int(os.environ["MANIFEST_VERSIE"]),
    "naam":                   os.environ["PAKKETNAAM"],
    "versie":                 os.environ["VERSIE"],
    "mapnaam":                os.environ["MAPNAAM"],
    "commit":                 os.environ["COMMIT"],
    "ref":                    os.environ["REF"],
    # De git-tag van deze boom, of "" bij een tussentijdse bouw. Hieruit vullen
    # de uitrolscripts APP_VERSION als de .env van de installatie hem leeg laat,
    # zodat de zijbalk het nummer toont van de code die er werkelijk draait.
    "tag":                    os.environ["TAG"],
    "gebouwd_op":             subprocess.run(["date", "-Iseconds"],
                                             capture_output=True, text=True).stdout.strip(),
    "bouwhost_php":           os.environ["PHP_VERSIE"],
    "bouwhost_node":          os.environ["NODE_VERSIE"],
    "minimale_deploy_versie": os.environ["MIN_DEPLOY"],
    "php_extensies":          os.environ["EXTENSIES"].split(),
    "storage_skelet":         os.environ["SKELET"].split(),
    "gebouwd":                json.loads(os.environ["GEBOUWD"]),
    # Waar de demofixtures in de boom staan, en hoeveel het er zijn. Nul betekent
    # dat deze tarbal geen demo kan vullen; deploy.sh meldt dat dan zo.
    "demofixtures":           os.environ["DEMOMAP"] if int(os.environ["DEMOAANTAL"]) else "",
    "demofixtures_aantal":    int(os.environ["DEMOAANTAL"]),
    # Waar de bouwstenen van de Docker-uitrol in de boom staan. Nul betekent dat
    # deze tarbal alleen op bare metal uitgerold kan worden.
    "docker":                 os.environ["DOCKERMAP"] if int(os.environ["DOCKERAANTAL"]) else "",
    "docker_aantal":          int(os.environ["DOCKERAANTAL"]),
}, indent=2, ensure_ascii=False))
PY

# ── 6. Inpakken ──────────────────────────────────────────────────────────────
stap "Inpakken"
mkdir -p "$UIT"
tar czf "$TARBAL" -C "$BOUWMAP" "$MAPNAAM"
( cd "$UIT" && sha256sum "$(basename "$TARBAL")" > "$(basename "$TARBAL").sha256" )

printf '\n\033[1mKlaar.\033[0m\n'
meld "pakket:   $TARBAL ($(du -h "$TARBAL" | cut -f1))"
meld "checksum: $TARBAL.sha256"
meld "commit:   $COMMIT"
printf '\nUitrollen op de doelhost:\n'
printf '   tar xzf %s -O %s/scripts/deploy.sh > deploy.sh\n' "$(basename "$TARBAL")" "$MAPNAAM"
printf '   sudo bash deploy.sh %s /var/www/isms --eerste\n' "$(basename "$TARBAL")"

if (( DOCKERBESTANDEN )) && [[ $BOUWEN == nee ]]; then
    printf '\n\033[33mDeze tarbal is niet als Docker-stack uit te rollen: het image bouwt geen\n'
    printf 'vendor/ en public/build. Bouw daarvoor opnieuw zonder --geen-bouw.\033[0m\n'
elif (( DOCKERBESTANDEN )); then
    printf '\nUitrollen als Docker-stack:\n'
    printf '   mkdir -p ~/ezisms/<naam> && tar xzf %s -C ~/ezisms/<naam>\n' "$(basename "$TARBAL")"
    printf '   cd ~/ezisms/<naam>\n'
    printf '   cp %s/docker/compose.yml   .\n' "$MAPNAAM"
    printf '   cp %s/docker/env.voorbeeld .env    # invullen: ISMS_BOOM, ISMS_NORM, APP_URL\n' "$MAPNAAM"
    printf '   docker compose up -d --build\n'
    printf '   (zie %s/docker/LEESMIJ.md)\n' "$MAPNAAM"
    printf '\nBijwerken van een bestaande stack: kopieer compose.yml OPNIEUW uit deze boom.\n'
    printf '   Er kunnen sleutels bij zijn gekomen die de container verwacht; een oude\n'
    printf '   compose.yml geeft die niet door, zonder foutmelding.\n'
fi
printf '\n'
