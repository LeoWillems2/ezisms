#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# buildimage.sh — bouwt de image-uitlevering van het ISMS
#
# Draait op de bouwhost. Levert twee tarbals op die samen een ISMS op een
# vreemde host zetten zonder dat daar broncode, PHP, composer, npm of een
# uitgaande verbinding aan te pas komt:
#
#   ezisms-image-<tag>.tar.gz   het image (docker save)
#   ezisms-stack-<tag>.tar      compose.yml, .env.voorbeeld en LEESMIJ.md
#
# LEESWIJZER
#   1. De INVOER is een bestaande tarbal uit dist/, niet de werkkopie. Dat is een
#      bewuste keuze: het image draait dan gegarandeerd exact de boom die ook als
#      broncode is uitgeleverd — zelfde vendor/, zelfde public/build, zelfde
#      SHA256SUMS. Een tweede image op dezelfde release kost daardoor geen
#      composer- en npm-ronde, en de copyrightcontrole is bij het maken van die
#      tarbal al gedaan. Wilt u een verse boom, draai dan eerst builddistr.sh.
#   2. Er wordt daarom NIETS uit git gelezen en niets uit de werkkopie. Ook
#      compose.yml, het .env-voorbeeld en de LEESMIJ komen uit de uitgepakte
#      tarbal en niet uit deze repo: anders zou een uitlevering een compose
#      kunnen krijgen die sleutels doorgeeft die de entrypoint ín het image nog
#      niet kent. Wat hier verandert, verandert pas in de volgende dist-tarbal.
#   3. De tag komt uit het manifest van die tarbal — de git-tag als de boom er
#      een heeft, anders datum+commit. Hij wordt op drie plaatsen tegelijk gezet:
#      de image-tag, de bestandsnamen, en de ingevulde EZISMS_VERSIE in het
#      .env-voorbeeld dat de ontvanger krijgt. Eén bron, geen overtypwerk.
#
# Voorwaarden op de bouwhost: docker, tar, gzip, sha256sum, numfmt, python3.
# pigz wordt gebruikt als hij er is; anders gzip, en dan duurt het inpakken van
# een image van honderden megabytes een paar minuten langer.
# ─────────────────────────────────────────────────────────────────────────────

set -Eeuo pipefail

SCRIPTMAP=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=distr-gemeenschappelijk.sh
source "$SCRIPTMAP/distr-gemeenschappelijk.sh"

# ── Instellingen ─────────────────────────────────────────────────────────────
# Elk via ": ${NAAM:=…}", dus per aanroep te overschrijven zonder het script te
# wijzigen:  UIT=/var/tmp scripts/buildimage.sh
: "${TARBAL:=}"      # de dist-tarbal (standaard: de nieuwste in <repo>/dist)
: "${UIT:=}"         # doelmap voor de twee tarbals (standaard: naast de invoer)
: "${TAG:=}"         # de image-tag (standaard: uit het manifest van de tarbal)
: "${PLATFORM:=linux/amd64}"
# ─────────────────────────────────────────────────────────────────────────────

# ── Namen in de uitlevering ──────────────────────────────────────────────────
# In het image en in de stacktarbal heten ze anders dan in de boom: de ontvanger
# heeft maar één variant en hoeft niet te kiezen.
COMPOSE_BRON="docker/compose-image.yml"
COMPOSE_DOEL="compose.yml"
ENV_BRON="docker/env.voorbeeld-image"
ENV_DOEL=".env.voorbeeld"
LEESMIJ_BRON="docker/LEESMIJ-image.md"
LEESMIJ_DOEL="LEESMIJ.md"

gebruik() {
    cat <<'EOF'
Gebruik: buildimage.sh [opties]

  --tarbal=<pad>      de dist-tarbal die het image gaat dragen
                      (standaard: de nieuwste in <repo>/dist)
  --uit=<map>         waar de twee tarbals komen (standaard: naast de invoer)
  --tag=<naam>        de image-tag (standaard: uit het manifest van de tarbal)
  --platform=<naam>   standaard linux/amd64
  -h, --help          deze uitleg

Eerst een boom bouwen en dan pas een image:
  scripts/builddistr.sh
  scripts/buildimage.sh
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
        --tarbal=*)   TARBAL=${arg#*=} ;;
        --uit=*)      UIT=${arg#*=} ;;
        --tag=*)      TAG=${arg#*=} ;;
        --platform=*) PLATFORM=${arg#*=} ;;
        -h|--help)    gebruik; exit 0 ;;
        *)            gebruik >&2; fout "onbekend argument: $arg" ;;
    esac
done

# ── Voorwaarden ──────────────────────────────────────────────────────────────
for cmd in docker tar gzip sha256sum numfmt python3; do
    command -v "$cmd" >/dev/null || fout "$cmd ontbreekt op deze bouwhost"
done
docker info >/dev/null 2>&1 \
    || fout "docker draait niet, of deze gebruiker mag er niet bij"

# ── De invoertarbal ──────────────────────────────────────────────────────────
if [[ -z $TARBAL ]]; then
    command -v git >/dev/null \
        || fout "geen --tarbal= opgegeven en er is geen git om dist/ mee te vinden"
    REPO=$(git -C "$SCRIPTMAP" rev-parse --show-toplevel 2>/dev/null) \
        || fout "geen --tarbal= opgegeven en dit script staat niet in een git-werkkopie"
    # De nieuwste, op wijzigingstijd. Bewust niet op naam gesorteerd: die begint
    # met een datum, en een herbouw van gisteren zou dan winnen van vandaag.
    TARBAL=$(find "$REPO/dist" -maxdepth 1 -name "$PAKKETNAAM-*.tar.gz" -printf '%T@ %p\n' 2>/dev/null \
             | sort -rn | head -1 | cut -d' ' -f2-)
    [[ -n $TARBAL ]] || fout "geen tarbal in $REPO/dist — draai eerst scripts/builddistr.sh"
fi
[[ -f $TARBAL ]] || fout "tarbal bestaat niet: $TARBAL"
TARBAL=$(readlink -f "$TARBAL")
: "${UIT:=$(dirname "$TARBAL")}"

stap "Invoertarbal"
meld "$TARBAL ($(du -h "$TARBAL" | cut -f1))"

# Checksum controleren als hij er is. Goedkoop, en het is de hele reden om van
# een tarbal uit te gaan: het image hoort de uitgeleverde boom te dragen, niet
# een halve download ervan.
if [[ -f $TARBAL.sha256 ]]; then
    ( cd "$(dirname "$TARBAL")" && sha256sum --quiet -c "$(basename "$TARBAL").sha256" ) \
        || fout "de checksum van de tarbal klopt niet"
    meld "checksum akkoord"
else
    waarschuw "geen $(basename "$TARBAL").sha256 naast de tarbal; niet gecontroleerd"
fi

# ── Uitpakken ────────────────────────────────────────────────────────────────
WERKMAP=$(mktemp -d -t ezisms-image.XXXXXXXX)
trap 'rm -rf "$WERKMAP"' EXIT

stap "Uitpakken"
tar xzf "$TARBAL" -C "$WERKMAP"
# Eén maplaag, zoals builddistr.sh hem inpakt.
BOOM=$(find "$WERKMAP" -mindepth 1 -maxdepth 1 -type d)
[[ -d $BOOM ]] || fout "de tarbal heeft niet één maplaag; dit is geen dist-tarbal"
meld "$(basename "$BOOM")"

# ── Het manifest ─────────────────────────────────────────────────────────────
# De enige koppeling tussen builddistr.sh en wat er daarna met de boom gebeurt.
# Alles wat dit script moet weten staat erin; de boom zelf wordt niet afgetast.
[[ -f $BOOM/MANIFEST.json ]] || fout "geen MANIFEST.json in de boom; dit is geen dist-tarbal"

manifest() {
    MANIFESTPAD="$BOOM/MANIFEST.json" SLEUTEL="$1" python3 - <<'PY'
import json, os
m = json.load(open(os.environ["MANIFESTPAD"]))
w = m.get(os.environ["SLEUTEL"], "")
print(" ".join(map(str, w)) if isinstance(w, list) else w)
PY
}

stap "Manifest"
M_VERSIE=$(manifest manifest_versie)
M_TAG=$(manifest tag)
M_DATUM=$(manifest versie)
M_COMMIT=$(manifest commit)
M_GEBOUWD=$(manifest gebouwd)
M_DOCKER=$(manifest docker)
M_VARIANTEN=$(manifest db_varianten)

meld "commit:   $M_COMMIT"
meld "versie:   $M_DATUM${M_TAG:+  (tag $M_TAG)}"

(( M_VERSIE <= MANIFEST_VERSIE )) \
    || fout "manifestversie $M_VERSIE is nieuwer dan dit script kent ($MANIFEST_VERSIE)"

# Vier eisen aan de boom, elk met een eigen melding. Ze hier stellen kost een
# seconde; ze niet stellen kost een halfuur bouwen en een image dat niet start.
[[ -n $M_DOCKER ]] \
    || fout "deze tarbal draagt geen Docker-subboom en is alleen op bare metal uit te rollen"

[[ $M_GEBOUWD == *vendor* && $M_GEBOUWD == *public/build* ]] \
    || fout "deze tarbal is met --geen-bouw gemaakt: geen vendor/ en geen public/build.
Het image bouwt die niet zelf. Draai builddistr.sh opnieuw zonder --geen-bouw."

[[ " $M_VARIANTEN " == *" sqlite "* ]] \
    || fout "deze boom kent de sqlite-variant niet (db_varianten: $M_VARIANTEN);
de image-uitlevering draait uitsluitend op SQLite."

for bestand in "$COMPOSE_BRON" "$ENV_BRON" "$LEESMIJ_BRON"; do
    [[ -f $BOOM/$bestand ]] || fout "$bestand zit niet in deze boom.
Dit is een tarbal van vóór de image-uitlevering; bouw hem opnieuw met builddistr.sh."
done
meld "compose, .env-voorbeeld en LEESMIJ aanwezig"

# ── De tag ───────────────────────────────────────────────────────────────────
# Volgorde: --tag= wint, anders de git-tag van de boom, anders datum+commit. Dat
# laatste is geen versienummer om te verspreiden, maar wel een naam die precies
# één boom aanwijst — en dus bruikbaar voor een tussentijdse bouw.
if [[ -z $TAG ]]; then
    if [[ -n $M_TAG ]]; then
        TAG=$M_TAG
    else
        TAG="$M_DATUM-${M_COMMIT:0:7}"
        waarschuw "deze boom staat niet op een git-tag; het image heet ezisms:$TAG"
    fi
fi
# Docker's eigen regel voor een tag. Hier controleren en niet aan `docker build`
# overlaten: die faalt pas na de bouw van alle lagen.
[[ $TAG =~ ^[a-zA-Z0-9_][a-zA-Z0-9._-]{0,127}$ ]] \
    || fout "'$TAG' is geen geldige image-tag (letters, cijfers, . _ - ; niet beginnen met . of -)"

IMAGE="$PAKKETNAAM:$TAG"
IMAGE_TARBAL="$UIT/$PAKKETNAAM-image-$TAG.tar.gz"
STACK_TARBAL="$UIT/$PAKKETNAAM-stack-$TAG.tar"
meld "image-tag: $IMAGE"

# ── Bouwen ───────────────────────────────────────────────────────────────────
# Context is de uitgepakte boom; de Dockerfile staat erin en kopieert eruit.
# `--pull` niet: de basislaag mag uit de lokale cache komen. Wie een verse
# ubuntu:26.04 wil, haalt hem zelf op — anders hangt de reproduceerbaarheid van
# een uitlevering aan het moment van bouwen.
#
# `--provenance=false` wél: BuildKit hangt er anders een attestatie aan en maakt
# er daarmee een manifestlijst van in plaats van één image. Dat is groter, en
# `docker load` van een manifestlijst gaat op oudere Docker-versies mis — precies
# wat een doelhost kan zijn. De herkomst van dit image staat in het manifest van
# de boom die erin zit, en dat is de vindplaats die hier telt.
stap "Image bouwen — $IMAGE ($PLATFORM)"
docker build \
    --platform "$PLATFORM" \
    --provenance=false \
    --file "$BOOM/docker/Dockerfile" \
    --tag "$IMAGE" \
    "$BOOM"

IMAGE_ID=$(docker image inspect "$IMAGE" --format '{{.Id}}')
meld "image-id: ${IMAGE_ID#sha256:}"
meld "omvang:   $(docker image inspect "$IMAGE" --format '{{.Size}}' | numfmt --to=iec)"

# ── Het image wegschrijven ───────────────────────────────────────────────────
# `docker save --platform` bestaat pas sinds Docker 27. Het image is hier
# eenplatforms, dus de vlag selecteert niets dat er niet al is — hij is een
# vangnet tegen een multi-arch cache op de bouwhost. Ontbreekt hij, dan slaan we
# hem over in plaats van te struikelen.
if docker save --help 2>&1 | grep -q -- '--platform'; then
    SAVE_PLATFORM=(--platform "$PLATFORM")
else
    SAVE_PLATFORM=()
    waarschuw "deze docker kent 'save --platform' niet; opgeslagen wat er lokaal staat"
fi

# pigz als hij er is: dit is honderden megabytes en gzip doet dat op één kern.
if command -v pigz >/dev/null; then
    COMPRESSOR=(pigz -9)
else
    COMPRESSOR=(gzip -9)
    meld "pigz niet gevonden; gzip op één kern — dit duurt even"
fi

stap "Image wegschrijven"
mkdir -p "$UIT"
docker save "${SAVE_PLATFORM[@]}" "$IMAGE" | "${COMPRESSOR[@]}" > "$IMAGE_TARBAL"
meld "$(basename "$IMAGE_TARBAL") ($(du -h "$IMAGE_TARBAL" | cut -f1))"

# ── De stacktarbal ───────────────────────────────────────────────────────────
# Drie bestanden, uit de boom en niet uit deze repo (zie de leeswijzer). Ze
# krijgen hier hun uitleveringsnaam: de ontvanger heeft één variant en hoeft
# niet te kiezen tussen compose.yml en compose-sqlite.yml.
stap "Stacktarbal"
STACK="$WERKMAP/stack"
mkdir -p "$STACK"
cp "$BOOM/$COMPOSE_BRON"  "$STACK/$COMPOSE_DOEL"
cp "$BOOM/$ENV_BRON"      "$STACK/$ENV_DOEL"
cp "$BOOM/$LEESMIJ_BRON"  "$STACK/$LEESMIJ_DOEL"

# De tag invullen die hier net gezet is. Zonder dit staat er een voorbeeldwaarde
# in die de ontvanger moet overtypen uit de uitvoer van `docker load` — precies
# het soort overschrijffout dat `pull_policy: never` wel netjes meldt maar niet
# voorkomt.
python3 - "$STACK/$ENV_DOEL" "$TAG" <<'PY'
import re, sys
pad, tag = sys.argv[1], sys.argv[2]
tekst = open(pad, encoding="utf-8").read()
tekst, n = re.subn(r"(?m)^EZISMS_VERSIE=.*$", f"EZISMS_VERSIE={tag}", tekst)
if n != 1:
    sys.exit(f"EZISMS_VERSIE komt {n}× voor in {pad}; verwacht precies 1")
open(pad, "w", encoding="utf-8").write(tekst)
PY
meld "EZISMS_VERSIE=$TAG ingevuld in $ENV_DOEL"

# Geen maplaag in dit archief: de ontvanger pakt hem uit in de map waar de stack
# komt te staan, naast zijn eigen .env en data/. Een maplaag zou daar een
# overbodige tussenmap opleveren — en bij een upgrade, waar alleen compose.yml
# opnieuw uitgepakt wordt, zelfs op de verkeerde plek.
#
# --force-local: de tag mag punten bevatten en een pad met een dubbele punt zou
# GNU tar naar een host doen zoeken. Die zit niet in de naamgeving hierboven,
# maar --uit= kan er wel een aandragen.
tar --force-local -cf "$STACK_TARBAL" -C "$STACK" \
    "$COMPOSE_DOEL" "$ENV_DOEL" "$LEESMIJ_DOEL"
meld "$(basename "$STACK_TARBAL") ($COMPOSE_DOEL, $ENV_DOEL, $LEESMIJ_DOEL)"

# ── Checksums ────────────────────────────────────────────────────────────────
stap "Checksums"
( cd "$UIT" \
  && sha256sum "$(basename "$IMAGE_TARBAL")" > "$(basename "$IMAGE_TARBAL").sha256" \
  && sha256sum "$(basename "$STACK_TARBAL")" > "$(basename "$STACK_TARBAL").sha256" )
meld "$(basename "$IMAGE_TARBAL").sha256"
meld "$(basename "$STACK_TARBAL").sha256"

# ── Slotscherm ───────────────────────────────────────────────────────────────
printf '\n\033[1mKlaar.\033[0m\n'
meld "image:    $IMAGE_TARBAL ($(du -h "$IMAGE_TARBAL" | cut -f1))"
meld "stack:    $STACK_TARBAL"
meld "uit:      $(basename "$TARBAL")"
meld "commit:   $M_COMMIT"

printf '\nOverzetten naar de doelhost:\n'
printf '   scp %s %s %s <host>:\n' \
       "$(basename "$IMAGE_TARBAL")" "$(basename "$STACK_TARBAL")" "*.sha256"

printf '\nUitrollen daar:\n'
printf '   sha256sum -c %s.sha256\n' "$(basename "$IMAGE_TARBAL")"
printf '   mkdir -p /opt/isms && cd /opt/isms\n'
printf '   tar xf ~/%s\n' "$(basename "$STACK_TARBAL")"
printf '   cp .env.voorbeeld .env && chmod 600 .env    # invullen: ISMS_NORM, APP_URL\n'
printf '   docker load < ~/%s\n' "$(basename "$IMAGE_TARBAL")"
printf '   docker compose up -d && docker compose logs -f\n'
printf '   (zie LEESMIJ.md in de stacktarbal; de eerste start seedt en duurt minuten)\n'

printf '\n\033[33mEZISMS_VERSIE staat al op %s in .env.voorbeeld — de ontvanger hoeft\n' "$TAG"
printf 'alleen ISMS_NORM en APP_URL in te vullen. Er wordt niets gebouwd op de\n'
printf 'doelhost: geen --build, en pull_policy: never houdt Docker bij het register weg.\033[0m\n'
printf '\n'
