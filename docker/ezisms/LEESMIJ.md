# EzISMS als Docker-stack

Twee containers: een MySQL-server en een applicatiecontainer met nginx, php-fpm
en de takenplanner. U bouwt het image zelf uit de distributietarbal — er is geen
registry en geen Docker-login nodig.

Het bouwen zelf heeft wél internet nodig: de Ubuntu-archieven voor de pakketten
en `github.com` voor pandoc (op versie én hash vastgepind in de Dockerfile).
Staat er een proxy tussen, dan moet Docker die twee kunnen bereiken.

Bij elke start controleert de container de installatie, maakt hij op een
bestaande database eerst een dump, migreert en seedt hij, en toont hij een
slotscherm in het log. Klopt er iets niet — een normprofiel dat op twee plekken
iets anders zegt, een mislukte migratie — dan start de webserver niet. Zie §8.

---

## 1. Installeren

Er zijn twee varianten, en ze verschillen alleen in de database. Hieronder staat
de eenvoudigste: één container, met het ISMS in één bestand. Wilt u MySQL, sla
dan §1b erbij op.

```bash
mkdir -p ~/ezisms/mijn-isms && cd ~/ezisms/mijn-isms
tar xzf /pad/naar/ezisms-2026.08.09-abc123.tar.gz

cp ezisms-2026.08.09-abc123/docker/compose-sqlite.yml   compose.yml
cp ezisms-2026.08.09-abc123/docker/env.voorbeeld-sqlite .env
chmod 0600 .env
```

> Het bronbestand heet `compose-sqlite.yml` en komt hier als `compose.yml` te
> staan. Dat is met opzet: `compose.yml` in de boom is de MySQL-variant, en die
> naam is nooit van betekenis veranderd — anders zou een bestaande installatie
> bij een upgrade (§4) ongemerkt het verkeerde bestand overnemen.

Vul `.env` in. Minimaal nodig:

| Sleutel | |
|---|---|
| `ISMS_BOOM` | de mapnaam van de uitgepakte tarbal |
| `ISMS_NORM` | `iso27001`, `nen7510` of `bio2` — **onomkeerbaar**, zie §5 |
| `APP_URL` | de publieke URL, achter HAProxy dus `https://…` |
| `ISMS_CISO_EMAIL` | niet verplicht, maar dan staat het eerste account er meteen — zie §2 |

Dan:

```bash
docker compose up -d --build
docker compose logs -f app
```

De eerste start duurt enkele minuten: het image wordt gebouwd, de database
aangemaakt en de referentiedata geseed. Als het klaar is, staat er in het log
hoeveel maatregelen en gebruikers er zijn.

De applicatie luistert op **poort 81** van de host (`HTTP_POORT`). Zet uw HAProxy
daarnaartoe; die termineert de TLS en stuurt `X-Forwarded-Proto: https` mee.
Zonder die header bouwt de applicatie `http://`-links op.

`APP_URL` bepaalt daarnaast of het sessiecookie `secure` krijgt: bij `https://`
gaat het cookie alleen over een beveiligde verbinding, bij `http://` niet — en
dan zegt de container dat bij elke start in het log. Zet `APP_URL` dus op de URL
die de gebruiker in de browser heeft staan, niet op de poort erachter.
`SESSION_SECURE_COOKIE` in `.env` overrulet dit, maar hoort leeg te blijven.

> **Bij een upgrade van V2.8.0 of eerder.** Twee sleutels uit `env.voorbeeld`
> werden niet aan de container doorgegeven en deden daar dus niets.
> `SESSION_SECURE_COOKIE` was niet instelbaar — het cookie was achter HAProxy wél
> `secure`, want Laravel liet het bij een lege waarde de verbinding volgen — en
> `SESSION_ENCRYPT` stond ondanks de `true` in het voorbeeldbestand uit, waardoor
> de sessiegegevens onversleuteld in de database stonden. Kopieer `compose.yml`
> opnieuw uit de nieuwe boom, anders blijft het eerste zo. Het tweede gaat bij de
> volgende start vanzelf aan; iedereen wordt daardoor één keer uitgelogd.

Wilt u geen HAProxy en nginx zelf de TLS laten dragen, dan staat in `tls.md` in
de wortel van de boom wat daarvoor moet veranderen.

### 1b. Met een MySQL-database (twee containers)

De variant hierboven draait op SQLite: het hele ISMS staat in één bestand op uw
eigen schijf, er is geen databasecontainer, en er zijn geen databasewachtwoorden
te verzinnen of te bewaren. Voor een organisatie van enkele tientallen mensen is
dat genoeg, en het is de eenvoudigste opstelling om te beheren en te back-uppen.

Kies MySQL als u daar een reden voor heeft: een bestaande back-up- of
monitoringroutine die op MySQL leunt, een beheerder die er thuis in is, of een
omvang waarbij u meerdere gelijktijdige schrijvers verwacht. Het kost een tweede
container, twee wachtwoorden en meer geheugen.

Het verschil zit in twee regels bij het installeren:

```bash
cp ezisms-2026.08.09-abc123/docker/compose.yml   .
cp ezisms-2026.08.09-abc123/docker/env.voorbeeld .env
chmod 0600 .env
```

Vul dan óók `MYSQL_ROOT_PASSWORD` en `MYSQL_WACHTWOORD` in: twee sterke
wachtwoorden. Ze hoeven niet onthouden te worden — poort 3306 wordt niet op de
host gepubliceerd — maar ze moeten wel sterk zijn en in `.env` blijven staan.

De rest van deze handleiding geldt onverkort; alleen komt de database dan in
`data/db/` te staan in plaats van in `data/app/database/` (zie §3).

**Kies één keer, in beide richtingen.** Na de eerste geslaagde start legt de
installatie de variant vast in `data/app/installatie/db-variant`. Richt u daarna
per ongeluk het andere compose-bestand op dezelfde `data/`-map, dan **start de
container niet** en zegt hij welke variant erbij hoort. Dat is met opzet: doorstarten zou naast uw ISMS
een tweede, lege database opbouwen.

Wisselen van variant is dan ook geen upgrade maar een verhuizing: er is geen
commando dat een MySQL-database naar SQLite omzet. Wat er wél is, is
`isms:exporteer` (§7) — en anders begint u opnieuw (§6).

## 2. Het eerste account

Zet `ISMS_CISO_EMAIL` (en eventueel `ISMS_CISO_NAAM`) in `.env` vóór de eerste
start. Dan maakt de container het CISO-account aan en zet het gegenereerde
wachtwoord in een bestand dat alleen de applicatie kan lezen:

```bash
docker compose exec app cat /var/lib/ezisms/storage/app/private/eerste-ciso.txt
```

**Het wachtwoord staat niet in `.env` en niet in het log.** Dat is met opzet: de
omgeving van een container is met `docker inspect` leesbaar, en een deploylog
wordt doorgestuurd. Wijzig het wachtwoord na de eerste inlog en verwijder dan
dat bestand.

Liever met de hand, of achteraf:

```bash
docker compose exec app runuser -u www-data -- \
    php /opt/ezisms/artisan isms:eerste-ciso ciso@voorbeeld.nl 'een-lang-wachtwoord' 'Naam Achternaam'
```

Tweefactorauthenticatie staat standaard aan; de eerste inlog vraagt om een
authenticator-app. Voor een snelle toets kunt u `ISMS_2FA_AFDWINGEN=false`
zetten in `.env` en `docker compose up -d` doen (zie §7 — géén `restart`).

### Het ISMS gevuld bekijken

`ISMS_DEMO=ja` vult een **verse** installatie met het FruitBV-demoscenario: 23
maanden aan gebeurtenissen, gebruikers, risico's en bewijsstukken. Die ene
schakelaar zet meteen `APP_ENV=demo` en `ISMS_2FA_AFDWINGEN=false`, en eist
`ISMS_NORM=iso27001` — het scenario hoort bij de 93 maatregelen van ISO 27001.
De inloggegevens komen in `demo-inloggegevens.txt` naast het bestand hierboven.

Het vullen **wist de hele database** en gebeurt daarom alleen bij een lege. Op
een bestaande installatie wordt `ISMS_DEMO=ja` genegeerd, met een waarschuwing in
het log. Zet er geen echte gegevens in.

## 3. Waar uw gegevens staan

Alles wat een herbouw van de container moet overleven staat in één map op de
host, standaard `./data` naast `compose.yml`:

```
data/
├── isms_export/         wat `isms:exporteer` oplevert
├── app/
│   ├── storage/         bewijsstukken, toetsbestanden, logs, audit trail
│   ├── database/        ezisms.sqlite — het ISMS zelf
│   ├── seeddata/        eigen, gelicentieerde normtekst
│   └── installatie/     de APP_KEY, de normstempel, de dumps, de opstartlogs
└── db/                  alleen bij de MySQL-variant (§1b): de datadirectory
                         van MySQL. Dan is app/database/ er niet.
```

**`seeddata/` is van u.** Wat u daar neerzet wint bij elke start van wat het
pakket meelevert, en overleeft dus elke upgrade. Zo blijft uw eigen, gekochte
normtekst staan in plaats van vervangen te worden door de meegeleverde
mededeling. Het opstartlog toont per bestand wat er gebeurt.

Neerzetten vraagt wel `sudo cp`: de map is van de gebruiker uit de container.

**`isms_export/` is een uitgang.** Binnen de container heet die map
`/var/tmp/isms_export`; wat u daarheen exporteert staat dus meteen op de host.
Zie §7 voor het commando. Denk eraan bij een back-up van `data/`: een export kan
bewijsstukken bevatten en, met `--met-persoonsgegevens`, volledige namen. Wat u
niet meer nodig heeft, verwijdert u met `sudo rm -r`.

**Dit is een hostmap, geen Docker-volume.** `docker compose down -v` raakt hem
niet aan — met opzet: de `APP_KEY` in `installatie/app_key` versleutelt de
2FA-geheimen van al uw gebruikers, en die mag nooit per ongeluk verdwijnen.

De keerzijde: er is geen commando dat schoon schip maakt. Zie §6.

**Een back-up vraagt `sudo`.** De bestanden zijn eigendom van de gebruikers uit
de container (`www-data`, en bij de MySQL-variant ook `mysql`), niet van uw
eigen account. Het meeste is voor
u wel leesbaar, maar juist het onmisbare deel niet: de database en
`installatie/app_key`. Zonder `sudo` krijgt u dus een archief dat er compleet
uitziet en het niet is:

```bash
docker compose down            # of: op zijn minst de db bevriezen
sudo tar czf isms-backup-$(date +%F).tar.gz data/
docker compose up -d
```

**En een back-up vraagt een stilstaande database.** Dat geldt in beide
varianten, maar bij SQLite is de verleiding groter omdat het maar één bestand
is: een `cp` van `data/app/database/ezisms.sqlite` terwijl er in geschreven
wordt, levert een kopie op die er compleet uitziet en het niet is — precies
zoals hierboven. Stop de container, of gebruik een dump die de container zelf
maakte (§4): die wordt binnen één transactie geschreven en is wél consistent.

## 4. Bijwerken

Pak de nieuwe tarbal **naast** de bestaande uit en zet één regel om:

```bash
cd ~/ezisms/mijn-isms
tar xzf /pad/ezisms-2026.09.15-7d4e1a.tar.gz
cp ezisms-2026.09.15-7d4e1a/docker/compose-sqlite.yml compose.yml   # kan nieuwe sleutels bevatten
sed -i 's/^ISMS_BOOM=.*/ISMS_BOOM=ezisms-2026.09.15-7d4e1a/' .env
docker compose up -d --build
```

**Neem het compose-bestand van uw eigen variant.** Draait u op MySQL (§1b), dan
is die derde regel `cp …/docker/compose.yml .` — zonder `-sqlite`. Pakt u het
verkeerde, dan start de container niet en zegt hij welke variant bij deze
gegevens hoort; er gaat niets verloren, maar u staat wel stil. Weet u het niet
meer: `sudo cat data/app/installatie/db-variant`.

Die tweede regel niet overslaan: een nieuwe versie kan instellingen toevoegen
die de container uit `compose.yml` verwacht. Vergeet u hem, dan komen die niet
aan — zonder foutmelding. Vergelijk daarna zo nodig uw `.env` met de nieuwe
`env.voorbeeld`; het opstartlog waarschuwt zelf voor sleutels die u niet zet.

De oude map blijft staan. Terugrollen is dezelfde regel terugzetten en opnieuw
`up -d --build`.

De container maakt vóór elke migratie een volledige dump in
`data/app/installatie/`. Het opstartlog noemt het bestand. Staat er niets open —
bij een gewone herstart, of na een wijziging in `.env` — dan verandert het schema
niet en wordt er geen dump gemaakt; ook dat zegt het log.

> Terugrollen draait een **migratie niet terug**. Is de nieuwe versie
> gemigreerd, dan is die dump de weg terug: `docker compose down`, database
> terugzetten, `ISMS_BOOM` terug, `up -d --build`.
>
> Terugzetten verschilt per variant. Bij SQLite is het een bestand:
> `sudo gunzip -c data/app/installatie/dump-….sqlite.gz > data/app/database/ezisms.sqlite`
> met de container gestopt, en daarna het eigendom herstellen (`sudo chown 33:33`
> — de container zet het bij de volgende start zelf goed). Bij MySQL gaat de
> `.sql.gz` via `mysql` de draaiende db-container in.

Oude mappen en oude dumps opruimen doet u zelf; het systeem gooit uw weg terug
niet weg. Vanaf tien dumps zegt het opstartlog hoeveel er staan en hoeveel ruimte
ze innemen.

## 5. Eén norm per stack

`ISMS_NORM` wordt één keer gelezen, bij het opzetten, en daarna vastgelegd in de
database én in `data/app/installatie/normprofiel`. Hem later omzetten verandert
niets meer: de SoA-beoordelingen gelden voor de norm waarop ze gemaakt zijn.

Zet u hem tóch om, dan **start de container niet**: bij elke start worden `.env`,
die normstempel en de database met elkaar vergeleken, en die drie moeten
hetzelfde zeggen. Stilzwijgend doormigreren zou een ISMS opleveren dat een norm
claimt die het niet volgt.

Wilt u meer dan één norm naast elkaar, dan is dat een stack per norm in een eigen map, elk
met een eigen `.env` en een eigen `HTTP_POORT`. Ze botsen niet: de containernamen
worden uit de mapnaam afgeleid.

## 6. Opnieuw beginnen

```bash
docker compose down
sudo rm -rf ./data          # database ÉN state — deze twee horen bij elkaar
docker compose up -d --build
```

**Dit wist alles**: bewijsstukken, audit trail, gebruikers, en de `APP_KEY`
waarmee de 2FA-geheimen zijn versleuteld. Verwijder de twee nooit los van
elkaar — dan start de container op een lege database met een bestaande sleutel,
of andersom. In de standaardvariant staan ze allebei onder `data/app/`; draait u
op MySQL (§1b), dan hoort `data/db/` er ook bij.

## 7. Beheer

```bash
docker compose ps                     # draait alles, en is app healthy?
docker compose logs -f app            # het opstartlog en nginx
docker compose exec app supervisorctl status
docker compose up -d                  # pikt een gewijzigde .env op
docker compose exec app bash          # rondkijken in de container
```

**Artisan draait u als `www-data`, niet als root:**

```bash
docker compose exec app runuser -u www-data -- php /opt/ezisms/artisan <commando>
```

`docker compose exec app php artisan …` werkt ook, maar u komt binnen als root,
en alles wat zo'n commando níéuw aanmaakt in `storage/` is daarna van root. Het
logboek van vandaag bestond meestal al en gaat dan goed; het logboek van morgen
wordt door root aangemaakt en is voor de applicatie niet meer schrijfbaar. De
eerstvolgende start zet het eigendom terug — maar tot dat moment schrijft de
applicatie stilletjes niets meer weg.

**Het ISMS uitleveren als leesbare Markdown:**

```bash
docker compose exec app runuser -u www-data -- \
    php /opt/ezisms/artisan isms:exporteer --doel=/var/tmp/isms_export
```

`--doel` is hier niet optioneel: zonder die vlag landt de export in `storage/` en
komt hij tussen uw bedrijfsgegevens te staan. `/var/tmp/isms_export` in de
container is `data/isms_export` op de host (§3). Elke run krijgt een eigen map
met datumstempel; er wordt nooit iets overschreven. De bestanden zijn van
`www-data` uit de container, dus lezen of verplaatsen vanaf de host vraagt
`sudo` — om dezelfde reden als bij de back-up in §3.

**Na een wijziging in `.env`: `docker compose up -d`, niet `restart`.**
`docker compose restart` start hetzelfde containerproces opnieuw en houdt de
omgeving die het bij aanmaak meekreeg — uw wijziging doet dan niets, zonder
foutmelding. `up -d` merkt dat de omgeving veranderd is en vervangt de container;
daarbij wordt de configuratie opnieuw gecached en is de nieuwe waarde actief.

De eerste start duurt lang: alle migraties en de referentiedata. Bij NEN 7510 is
bijna zeven minuten gemeten. Zolang dat loopt is er nog geen webserver en meldt
`docker compose ps` de container als `starting`; dat is normaal.

De logs van de applicatie zelf staan in `data/app/storage/logs/` en niet in
`docker compose logs` — ze overleven de container, wat voor een audit trail het
punt is.

**Wat er wél in `docker compose logs` staat, wordt geroteerd en verdwijnt.** Dat
is het opstartlog, het toegangs- en foutlog van nginx, php-fpm en de foutmeldingen
van de database. Er wordt per container ongeveer 50 MB bewaard (vijf delen van
10 MB, de oude gecomprimeerd); daarboven valt het oudste weg. Bij een kleine
organisatie is dat ruwweg tien dagen aan verkeer.

Dat is met opzet begrensd: zonder die grens groeit het toegangslog door tot de
schijf vol is. Het betekent wel dat dit geen bewaarplek is. Wilt u een
webtoegangslog langer houden, dan hoort dat op de HAProxy ervóór — die ziet
dezelfde verzoeken, staat buiten deze stack en heeft de logrotatie van de host.
Het logboek dat een auditor vraagt is de audit trail, en die staat in de
database.

Bij het opnieuw aanmaken van een container (`up -d` na een wijziging, of een
upgrade) begint dit log leeg. `data/` blijft ongemoeid.

**Bekende beperking.** `php artisan isms:capaciteiten aan` schrijft zijn
instelling naar het `.env` *in de container*, en die is vluchtig: bij de
eerstvolgende `up --build` is de wijziging weg. Zet de vijfde attribuutdimensie
in deze versie dus nog niet aan.

## 8. Als het misgaat

De container start de webserver pas als de uitrol geslaagd is. Er zijn twee
manieren waarop hij blijft staan, en beide zijn te zien aan `docker compose ps`:
de container draait, maar is `unhealthy`. Het log zegt waarom.

**Een instelling die niet kan.** Een `APP_KEY` die afwijkt van de bewaarde, een
`ISMS_NORM` die de normstempel tegenspreekt, `ISMS_DEMO=ja` op een
`nen7510`-installatie, of een compose-bestand van de andere databasevariant
(§1): de container meldt het en blijft wachten in plaats van eindeloos te
herstarten. Bij deze fouten wordt niet drie keer opnieuw geprobeerd — opnieuw
proberen kan er niets aan veranderen. Herstel `.env` en doe
`docker compose up -d` — géén `restart`, zie §7.

**Een mislukte uitrol.** Na drie mislukte pogingen (`ISMS_MIGRATIE_POGINGEN`)
schrijft de container `data/app/installatie/BLOKKADE` met de reden, de laatste
dump en het laatste opstartlog erin, en probeert het niet meer. Dat is met
opzet: elke poging maakt eerst een volledige dump, en een lus daarvan vult de
schijf terwijl de melding voorbijraast.

```bash
docker compose logs app | tail -40
sudo cat data/app/installatie/BLOKKADE
docker compose exec app bash          # de container blijft bereikbaar
```

Opheffen na herstel:

```bash
sudo rm data/app/installatie/BLOKKADE
docker compose up -d --force-recreate
```

Slaagt de uitrol, dan gaat de teller vanzelf op nul.

**Waarom `--force-recreate` en niet gewoon `restart` of `up -d`.** Lag de oorzaak
in `.env`, dan helpt `restart` niet: die start hetzelfde containerproces opnieuw
met de omgeving van toen (zie §7). Lag de oorzaak *binnen* de installatie, dan
helpt `up -d` niet: er is aan `.env` en `compose.yml` niets veranderd, dus laat
Compose de container met rust en gebeurt er niets — zonder foutmelding.
`--force-recreate` vervangt hem altijd en pakt de actuele instellingen mee.

Die `sudo`'s zijn geen slordigheid: alles onder `data/` is eigendom van de
gebruikers uit de container, en op de host valt hun uid bijna altijd op een
andere naam. Zonder `sudo` krijgt u "Permission denied" — ook op `installatie/`
en `seeddata/`. Zie §3.

Eén ding om niet in te trappen: `docker compose ps` noemt de container binnen de
eerste vijftien minuten `starting` en pas daarna `unhealthy`. Die tijd is de
`start_period` uit `compose.yml`, ruim genomen voor een eerste installatie. Een
geblokkeerde container is dus meteen te herkennen aan het log, maar aan zijn
gezondheid pas een kwartier later.
