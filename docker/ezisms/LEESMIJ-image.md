# EzISMS — uitrollen uit een image

Deze uitlevering bestaat uit twee tarbals en geen broncode:

| bestand | wat erin zit |
|---|---|
| `ezisms-image-<versie>.tar.gz` | het image: Ubuntu, PHP, nginx en de applicatie |
| `ezisms-stack-<versie>.tar` | `compose.yml`, `.env.voorbeeld` en dit bestand |

De doelhost heeft alleen Docker met de compose-plugin nodig. Geen PHP, geen
composer, geen npm, en geen uitgaande verbinding: het image wordt geladen uit
het bestand, niet opgehaald bij een register.

## 1. Uitrollen

```sh
mkdir -p /opt/isms && cd /opt/isms
tar xf ~/ezisms-stack-<versie>.tar

cp .env.voorbeeld .env && chmod 600 .env
# invullen: EZISMS_VERSIE, ISMS_NORM, APP_URL, ORGANISATIE, HTTP_POORT
$EDITOR .env

docker load < ~/ezisms-image-<versie>.tar.gz
docker compose up -d
docker compose logs -f
```

`docker load` meldt aan het eind welke tag hij geladen heeft
(`Loaded image: ezisms:<versie>`). Diezelfde tag hoort in `.env` bij
`EZISMS_VERSIE` te staan; staat er iets anders, dan stopt `up -d` met de
mededeling dat het image niet bestaat — en dát is de bedoeling van
`pull_policy: never` in `compose.yml`.

`up -d`, zónder `--build`. Er valt niets te bouwen.

### Hoe lang de eerste start duurt

De eerste `up` migreert en seedt de database vóórdat nginx begint te luisteren.
Bij een NEN 7510-installatie duurde dat 6 minuten 42; met `ISMS_DEMO=ja` komt
het vullen van het scenario daar nog bij. De healthcheck heeft daarom een
`start_period` van 15 minuten: zolang staat de container op `starting` en niet
op `unhealthy`. Volg `docker compose logs -f` — de entrypoint vertelt in welke
stap hij zit en sluit af met een slotscherm.

### Het eerste account

Vulde u `ISMS_CISO_EMAIL` in, dan staat het gegenereerde wachtwoord na de eerste
start in `data/app/storage/app/private/eerste-ciso.txt` (rechten 0600). Het staat
niet in het log en niet in `.env`. Liet u de sleutel leeg, dan noemt het
slotscherm het commando waarmee u zelf een account maakt.

## 2. Waar uw ISMS staat

In één map: `data/` naast `compose.yml`, of waar `ISMS_DATA` heen wijst.
Daarin staan de database, de bewijsstukken, de audit trail, de logs en de
`APP_KEY`.

Het image is vervangbaar, deze map niet. Neem hem op in uw back-up, en zet de
container stil (`docker compose stop`) voordat u hem kopieert — een SQLite-
bestand kopiëren terwijl er geschreven wordt levert een onbruikbare kopie op.

Het is een hostmap en geen Docker-volume: `docker compose down -v` raakt hem
niet aan. Opnieuw beginnen betekent deze map met de hand verwijderen.

## 3. Upgraden

```sh
cd /opt/isms
docker compose stop                       # en maak nu een kopie van data/

docker load < ~/ezisms-image-<nieuw>.tar.gz
tar xf ~/ezisms-stack-<nieuw>.tar compose.yml   # NIEUW compose-bestand
sed -i 's/^EZISMS_VERSIE=.*/EZISMS_VERSIE=<nieuw>/' .env

docker compose up -d
docker compose logs -f
```

Twee dingen die vaak misgaan:

- **Pak `compose.yml` opnieuw uit.** Er kunnen sleutels bij zijn gekomen die de
  nieuwe entrypoint verwacht. Een oude `compose.yml` geeft die niet door, zonder
  foutmelding — dan valt bijvoorbeeld `ISMS_CISO_EMAIL` stil weg. `.env` houdt u
  wél: die is van u.
- **Maak eerst die kopie van `data/`.** Een migratie is niet terug te draaien.

### Terugrollen

Zolang het oude image er nog staat (`docker images ezisms`):

```sh
docker compose stop
# zet de kopie van data/ terug
sed -i 's/^EZISMS_VERSIE=.*/EZISMS_VERSIE=<oud>/' .env
docker compose up -d
```

Zonder die kopie is terugrollen niet compleet: het oude image draait niet op een
database die de nieuwe migraties al gehad heeft. Ruim oude images pas op als u
zeker bent — `docker image rm ezisms:<oud>`.

## 4. Wat deze uitlevering niet kan

- **Geen MySQL.** Deze stack draait op SQLite, in één container. De MySQL-variant
  vraagt een tweede image en zit in de broncode-uitlevering.
- **Van norm wisselen kan niet.** `ISMS_NORM` wordt één keer gelezen en daarna
  vastgelegd. Meer dan één norm betekent meer dan één stack, elk met een eigen
  map en een eigen `HTTP_POORT`.
- **Van databasevariant wisselen kan niet.** De installatie legt vast dat ze
  SQLite is en blokkeert een afwijking.

## 5. Als het misgaat

Loopt een migratie stuk, dan probeert de container het `ISMS_MIGRATIE_POGINGEN`
keer opnieuw — met vóór elke poging een volledige dump — en blijft daarna staan
met een `BLOKKADE`-bestand in `data/app/installatie/`. Daarin staat wat er aan de
hand is. De container start niet opnieuw voordat dat bestand weg is; herstel
eerst de oorzaak, verwijder het bestand en draai `docker compose up -d`.

Nuttige commando's:

```sh
docker compose ps                 # draait hij, en is hij healthy?
docker compose logs --tail=100    # het opstartlog en de entrypoint-meldingen
docker compose exec app bash      # in de container kijken
ls data/app/installatie/          # normprofiel, db-variant, app_key, BLOKKADE
```
