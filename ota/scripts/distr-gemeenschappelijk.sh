#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# distr-gemeenschappelijk.sh — wat builddistr.sh en deploy.sh over elkaar weten
#
# Dit bestand wordt ingelezen door builddistr.sh (op de bouwhost). deploy.sh
# leest het NIET: dat script draait op een vreemde host waar dit bestand pas
# bestaat nadat de tarbal is uitgepakt — kip en ei. Alles wat deploy.sh van de
# bouw moet weten, schrijft builddistr.sh daarom in MANIFEST.json, en dat is de
# enige koppeling tussen de twee. Wie hier iets wijzigt dat deploy.sh aangaat,
# wijzigt dus het manifest, niet het script aan de andere kant.
#
# Niet zelfstandig uitvoerbaar; alleen `source`n.
# ─────────────────────────────────────────────────────────────────────────────

# Versie van het manifestformaat. Verhoog dit zodra een sleutel van betekenis
# verandert; deploy.sh weigert een manifest dat het niet kent.
#
# 2: docker/docker_aantal erbij (de Docker-uitrolroute).
MANIFEST_VERSIE=2

# Minimale versie van deploy.sh die deze tarbal aankan. Verhoog dit wanneer
# builddistr.sh iets meegeeft dat een oudere deploy.sh niet begrijpt — dan
# krijgt de beheerder een duidelijke melding in plaats van een halve uitrol.
MINIMALE_DEPLOY_VERSIE="1.0"

# Naamprefix van archief en maplaag: ezisms-<versie>-<commit>.
PAKKETNAAM="ezisms"

# ── PHP-extensies die de doelhost moet hebben ────────────────────────────────
# Eén plek, want deploy.sh krijgt deze lijst via het manifest. De eerste groep
# volgt uit composer.lock (Laravel en zijn afhankelijkheden), pdo_mysql uit de
# databasekeuze, gd uit de tolerantiematrix (README §Documentgeneratie).
PHP_EXTENSIES=(
    ctype curl dom fileinfo filter gd hash mbstring
    openssl pdo pdo_mysql session tokenizer xml zip
)

# ── Seeddata: wat er in een uitlevering mag zitten ───────────────────────────
# Alles onder database/seeders/data/ dat hier NIET in staat, is gelicentieerd of
# installatiegebonden en hoort niet in een tarbal. De controle in builddistr.sh
# werkt met deze lijst als toegestane verzameling en niet als uitsluitlijst:
# een nieuw gegenereerd bestand valt dan vanzelf op in plaats van stilzwijgend
# mee te reizen.
# De tarbal draagt béide controlsets: de bouw weet niet welke norm de ontvanger
# volgt. `deploy.sh` gooit bij de uitrol de overbodige weg.
SEEDDATA_TOEGESTAAN=(
    maatregelen-iso27001.json
    maatregelen-nen7510.json
    maatregel-kenmerken.json
)

# Twee velden, twee toegestane verzamelingen, in beide controlsets hetzelfde.
# Staat er iets anders, dan is het bestand ingevuld uit een gekochte norm en mag
# het niet uitgeleverd worden. Gelijk aan Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD,
# ::ZORGAANVULLING_NIET_MEEGELEVERD en ::ZORGAANVULLING_GEEN.
#
# Sinds 11-08-2026 is dit de enige plek waar die regel nog afgedwongen wordt: de
# pre-commit hook die de commit bewaakte is verwijderd. Deze controle bewaakt de
# uitlevering en dus niet de historie — kijkt zij mee, dan ligt de tekst al vast.
OMSCHRIJVINGTEKST="Dit ISMS levert bij deze maatregel geen omschrijving mee."
ZORGTEKST="Dit ISMS levert bij deze maatregel geen zorgspecifieke maatregel mee."
ZORGLEEG="DO NOT TOUCH"

# ── Demofixtures ─────────────────────────────────────────────────────────────
# Het FruitBV-scenario waarmee `isms:demo-vul` een gevuld ISMS opbouwt. Deze
# bestanden staan búiten ota/ en komen dus niet mee met de export hierboven; ze
# worden apart geëxporteerd naar dezelfde plek in de boom.
#
# Ze gaan in elke tarbal mee, ook in een die naar een klant gaat. Het is eigen
# materiaal over een verzonnen bedrijf, het weegt niets, en de alternatieve
# opzet — een bouwvlag — levert een tarbal op waarvan je achteraf niet meer ziet
# of de demo erin zit. deploy.sh gebruikt ze alleen als APP_ENV local of demo is.
DEMOFIXTURES_BRON="saasdemo/data"
DEMOFIXTURES_DOEL="saasdemo/data"

# ── Docker-subboom ───────────────────────────────────────────────────────────
# De bouwstenen van de Docker-uitrol: Dockerfile, compose.yml, entrypoint,
# supervisord en de nginx/php-fpm-configuratie. Ze staan búiten ota/ en komen
# dus niet mee met de export; ze worden apart geëxporteerd naar dezelfde plek in
# de boom. De Dockerfile verwacht ze daar ook: hij wordt gebouwd met de
# uitgepakte boom als context en kopieert uit docker/.
#
# Ze gaan in elke tarbal mee, om dezelfde reden als de demofixtures: het weegt
# niets, en de alternatieve opzet — een bouwvlag — levert een tarbal op waarvan
# je achteraf niet meer ziet of de Docker-route erin zit.
DOCKER_BRON="docker/ezisms"
DOCKER_DOEL="docker"

# Bestanden en mappen die na `git archive` alsnog geleegd worden: ze staan wél
# in versiebeheer, maar horen niet bij een verse installatie.
#
# De lijst is sinds 01e leeg. `public/toetsen` stond er als enige in — materiaal
# van derden dat een verse installatie niet hoort te krijgen — en die map bestaat
# niet meer: toetsbestanden zijn inhoud van een installatie geworden, staan in
# storage/app/private/toetsen en komen daar via het beheerscherm terecht.
#
# De machinerie blijft staan, want dit is precies het soort geval dat terugkomt.
# De lus eromheen in builddistr.sh is bestand tegen een lege lijst.
LEEG_NA_EXPORT=()

# ── Sporen van versiebeheer ──────────────────────────────────────────────────
# Wat de export achterlaat maar op een doelhost niets doet — daar is geen git.
# Ze dragen wel interne informatie mee: .github zijn de CI-workflows, en de
# .gitignore in de wortel legt uit hoe de gelicentieerde seedbestanden uit de
# gekochte norm geregenereerd worden. Dat hoort niet op een klanthost.
#
# .gitattributes hoort in dit rijtje omdat git hem op archiveertijd uit de tree
# leest, niet uit de uitgepakte boom: zijn export-ignore houdt README.md nu al
# uit het pakket, en dat blijft zo. Wat er in de boom achterblijft is een dode
# kopie. Uit versiebeheer mag hij dus nooit weg.
#
# Namen, geen paden: `.gitignore` staat op elf plaatsen in de boom. Het opruimen
# gebeurt in builddistr.sh vóór composer draait, zodat de .gitignore-bestanden
# ín vendor/ — materiaal van derden — met rust gelaten worden.
VERSIEBEHEERSPOREN=(
    .github
    .gitignore
    .gitattributes
)
