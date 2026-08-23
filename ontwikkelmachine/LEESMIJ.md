# Machineconfiguratie van de ontwikkelmachine

Wat hier staat hoort **niet bij de applicatie** maar bij de machine waarop de
ontwikkel-checkout draait: de twee configuratiebestanden hieronder, en de
pakketten die eronder moeten liggen. Het staat in versiebeheer om één reden: een
`git clone` draagt geen eigendom, geen rechten en geen ACL's mee, geen
webserverconfiguratie en al helemaal geen geïnstalleerde software. Zonder dit
document is dat na een verhuizing opnieuw uitzoekwerk.

De twee bestanden gaan **niet mee in de uitlevering**: `ota/scripts/builddistr.sh` pakt alleen
`ota/`, `docker/` en de demofixtures in de tarbal. Dat is met opzet — hieronder
staan een gebruikersnaam en een hostnaam die alleen op deze machine betekenis
hebben.

| Bestand | Hoort te staan in |
|---|---|
| `php-fpm-pool-ota-isms.conf` | `/etc/php/8.5/fpm/pool.d/ota-isms.conf` |
| `nginx-ota-isms.conf` | `/etc/nginx/sites-available/ota-isms`, met een symlink vanuit `sites-enabled/` |

Voor een **doelmachine** is er `ota/scripts/prephost.sh`; die legt een
productie-opstelling neer (vhost en pool als `www-data`, eigen database, een
doelpad voor `deploy.sh`). Dit document gaat over het andere geval: een
ontwikkelmachine die de werkboom zelf serveert. De twee kunnen naast elkaar op
één host staan — dan draaien er twee vhosts en twee pools met verschillende
namen.

## Pakketten op een kale machine

Deze stappen komen uit `ota/INSTALL.md`, dat op 13-08-2026 is verwijderd omdat
het verder alleen dingen herhaalde die inmiddels in `ota/.env.example` en
`ota/README.md` staan. Dit deel stond nergens anders en is daarom hierheen
gehaald. Uitgangspunt is Ubuntu 26.04 LTS.

```bash
sudo apt update && sudo apt -y upgrade

sudo apt install -y nginx                    # verwijder ::80 uit de default-site
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo mysql_secure_installation               # interactief

# 26.04 levert PHP 8.5 uit de distributie zelf; de ppa:ondrej/php die hier tot
# 24.04 voor nodig was, is dus verleden tijd. composer.json eist ^8.2 en
# vendor/composer/platform_check.php eist >= 8.4.1.
sudo apt install -y php8.5 php8.5-cli php8.5-common php8.5-fpm php8.5-mysql \
                    php8.5-xml php8.5-curl php8.5-mbstring php8.5-zip php8.5-gd \
                    php8.5-sqlite3 php8.5-intl

# Node is alleen op deze machine nodig: het Docker-image bevat geen nodejs en
# geen npm, dat krijgt de assets kant-en-klaar mee (docker/ezisms/Dockerfile).
# 26.04 levert node 22; een aparte NodeSource-bron hoeft niet meer.
sudo apt install -y nodejs npm

# Pandoc, voor de RTF-preview van beleidsdocumenten en de Word-versie van een
# schermkopie. Minimaal 3.1.7 — dat is de eerste versie met een RTF-lezer.
# Niet uit apt, en dat is niet alleen een kwestie van ouderdom: de pandoc van
# een distributie zet zijn datafiles in een apart pakket, en met --sandbox (wat
# de applicatie doet) leest pandoc alleen de in de binary ingebakken versie.
# Zo'n build schrijft dus helemaal geen .docx. Zie de pandoc-laag in
# docker/ezisms/Dockerfile, en PANDOC_BIN in .env.example.
wget https://github.com/jgm/pandoc/releases/download/3.10.2/pandoc-3.10.2-1-amd64.deb
sudo dpkg -i pandoc-3.10.2-1-amd64.deb

# Alleen voor scripts/pdf2md, waarmee de norm-.md-bestanden uit hun PDF zijn
# gehaald. Niet uit pip: 26.04 weigert installeren in de systeem-Python
# (PEP 668, "externally-managed-environment").
sudo apt install -y python3-pypdf
```

De extensielijst is op twee na dezelfde als die van het Docker-image, dus de
twee omgevingen blijven gelijk. `php8.5-gd` moet FreeType hebben, anders
verschijnt de tolerantiematrix als tabel zonder plaatje.

Die twee extra's zijn er **voor de testsuite**, en daarom staan ze niet in het
image en installeert `prephost.sh` ze ook niet: daar draait geen suite.

**`php8.5-sqlite3` is niet optioneel.** `phpunit.xml` draait de hele suite op
`DB_CONNECTION=sqlite` met `DB_DATABASE=:memory:`. Ontbreekt hij, dan zakt élke
test die de database raakt op "could not find driver" — ruim elfhonderd stuks,
terwijl de zestien tests die niets opslaan gewoon slagen. Dat leest als een
kapotte applicatie en niet als een ontbrekend pakket.

**`php8.5-intl` kost één assertie.** `KennisbankTest` schrijft met
`NumberFormatter` een aantal in woorden uit om het tegen de artikeltekst te
houden, en slaat die controle over als de extensie er niet is. De test slaagt
dan, alleen minder grondig — dus dit valt niet op tenzij u op het woord
"skipped" let. De applicatie zelf gebruikt `intl` nergens.

Composer installeert u erbij op de manier die u gewend bent; het image heeft hem
niet, want daar gaat `vendor/` al gebouwd in mee. `mysqldump` komt hier met
`mysql-server` mee — `deploy.sh` en de container eisen hem apart, want daar staat
de database elders.

`python3-pypdf` staat los van de applicatie: het is de motor onder
`scripts/pdf2md`, de eerste schakel van de keten die van een norm-PDF een
seedbestand maakt:

    norm.pdf  --pdf2md-->  ../<norm>-normen/*.md
              --scripts/genereer_*_seed.py-->  ota/database/seeders/data/*.json
              --MaatregelSeeder-->  de database

De seeders lezen dus JSON en raken die `.md` nooit aan; die is alleen invoer
voor de generatoren. Beide tussenproducten liggen er al, dus u hebt pypdf pas
nodig als er een nieuwe uitgave van een norm binnenkomt. Om diezelfde reden zit
het niet in de uitlevering en niet in het image.

Twee dingen die het script niet doet: het leest geen argumenten en het heeft
geen shebang. De in- en uitvoerpaden staan onderin in `__main__`, en dat is
`iso27001.pdf` → `iso27001.md`. Wie het onveranderd aanroept krijgt dus "Error:
The file 'iso27001.pdf' does not exist" — pas de paden aan, of importeer
`pdf_to_markdown()` en roep hem zelf aan.

## De database

```sql
CREATE DATABASE isms27001 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'admin_isms'@'localhost' IDENTIFIED BY '……';
GRANT ALL PRIVILEGES ON isms27001.* TO 'admin_isms'@'localhost';
```

`utf8mb4_unicode_ci` is geen smaakkwestie: de norm- en maatregelteksten bevatten
diakrieten en aanhalingstekens die in `latin1` stilzwijgend sneuvelen.

Staat `DB_HOST` op `127.0.0.1` en niet op `localhost`, dan verbindt de
mysql-client over TCP en niet over de socket. MySQL ziet dat als een ándere
host, dus dan is er ook een `'admin_isms'@'127.0.0.1'` nodig met dezelfde
rechten — anders is het "Access denied" terwijl het wachtwoord klopt.

Een database per normprofiel is bruikbaar om ze naast elkaar te draaien, want
`ISMS_NORM` wordt één keer gelezen en daarna in de tabel `normprofiel`
vastgelegd — omzetten in `.env` verandert daarna niets meer. Dezelfde gebruiker
mag erbij:

```sql
CREATE DATABASE isms7510 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE bio2      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON isms7510.* TO 'admin_isms'@'localhost';
GRANT ALL PRIVILEGES ON bio2.*     TO 'admin_isms'@'localhost';
```

Zet de naam en de aanmeldgegevens daarna in `DB_DATABASE`, `DB_USERNAME` en
`DB_PASSWORD` in `.env`. Vanaf dat punt neemt `ota/README.md` het over, onder
"Eerste installatie": `composer install`, `npm run build`, `migrate --seed` en
`isms:eerste-ciso`. Let daar op de instellingen die vóór de eerste seed goed
moeten staan — `ISMS_NORM` is er daar één van.

## Waarom een eigen fpm-pool

De overige sites op deze machine draaien op de gedeelde pool `www` als
`www-data`. Dit project is de enige waarin óók artisan en de testsuite in
`storage/` schrijven, en wel als `leo`.

Twee accounts met verschillende umasks in dezelfde boom vroeg om ACL's, en die
vielen stil op de mask: een map die één van beide op 0700 aanmaakt zet
`mask::---`, waarna élke benoemde ACL-entry `#effective:---` wordt — ook die van
het account dat de map zelf maakte. Livewire's map voor tijdelijke uploads
(`storage/app/private/livewire-tmp`) deed precies dat.

De oplossing is dezelfde als die productie en de container al gebruikten: **één
schrijvend account**. Daar is dat `www-data`, hier `leo`. Zie
`ota/README.md`, sectie "Schrijfrechten op storage/".

## De doorloopbit op de thuismap

De vhost serveert rechtstreeks uit de werkboom, en die ligt onder `/home/leo`.
Sinds Ubuntu 21.04 maakt `adduser` een thuismap op **0750**, en op een
cloud-image is dat ook zo. Nginx draait als `www-data` en zit niet in de groep
`leo`, dus die komt de map niet ín — ook niet als alles eronder wereldleesbaar
is. Het resultaat is een HTTP 404 met dit in `error.log`:

```
realpath() "/home/leo/claude/isms/ota/public" failed (13: Permission denied)
```

Let op dat de fout in nginx zit en niet in php-fpm: de pool draait immers wél
als `leo`. Wie alleen naar de fpm-kant kijkt, zoekt eindeloos.

```bash
chmod 0751 /home/leo        # x voor 'other' = doorlopen, r blijft dicht
```

`ls /home/leo` blijft daarmee geweigerd voor anderen; alleen doorlopen naar een
pad dat je al kent, is toegestaan. Een ACL (`setfacl -m u:www-data:x`) zou nog
krapper zijn, maar dan staat er weer een ACL op de machine terwijl de vorige
paragraaf er juist vanaf is gestapt.

## Installeren op een nieuwe machine

```bash
sudo install -o root -g root -m 0644 \
    ontwikkelmachine/php-fpm-pool-ota-isms.conf /etc/php/8.5/fpm/pool.d/ota-isms.conf
sudo install -o root -g root -m 0644 \
    ontwikkelmachine/nginx-ota-isms.conf /etc/nginx/sites-available/ota-isms
sudo ln -sfn ../sites-available/ota-isms /etc/nginx/sites-enabled/ota-isms

sudo php-fpm8.5 -t && sudo nginx -t          # eerst toetsen, dan pas herladen
sudo systemctl reload php8.5-fpm
sudo systemctl reload nginx
```

Pas daarna `user`/`group` in het poolbestand en `root`/`server_name` in de vhost
aan als de nieuwe machine een ander account of een andere hostnaam gebruikt. De
vhost laat alleen HAProxy en localhost toe; staat de TLS-terminatie op een ander
adres, dan moet het `allow`-regeltje mee.

Zet een back-up van de vhost **nooit** in `sites-enabled/`: nginx laadt die map
met een glob, en een kopie levert een tweede serverblok voor dezelfde
`server_name` op.

## Controleren dat het klopt

```bash
ps -o user,args -C php-fpm8.5 | grep 'pool ota-isms'   # moet de juiste user zijn
find ota/storage ota/bootstrap/cache ! -user "$USER"   # moet leeg zijn
getfacl -Rsp ota/storage ota/bootstrap/cache           # moet leeg zijn

# De vhost, zonder browser en zonder DNS. Verwacht: 302 naar /dashboard op https.
curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n' \
     -H 'Host: ismsota.lewi.nl' -H 'X-Forwarded-Proto: https' http://127.0.0.1/

# Pandoc: dit is precies wat App\Support\Pandoc draait. Een build zonder
# ingebakken datafiles zakt hier, en nergens anders.
printf '{\\rtf1 test}' > /tmp/t.rtf && pandoc --sandbox -f rtf -t docx -o /tmp/t.docx /tmp/t.rtf
```
