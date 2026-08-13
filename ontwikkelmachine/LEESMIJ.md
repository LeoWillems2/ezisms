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
| `php-fpm-pool-ota-isms.conf` | `/etc/php/8.4/fpm/pool.d/ota-isms.conf` |
| `nginx-ota-isms.conf` | `/etc/nginx/sites-available/ota-isms`, met een symlink vanuit `sites-enabled/` |

## Pakketten op een kale machine

Deze stappen komen uit `ota/INSTALL.md`, dat op 13-08-2026 is verwijderd omdat
het verder alleen dingen herhaalde die inmiddels in `ota/.env.example` en
`ota/README.md` staan. Dit deel stond nergens anders en is daarom hierheen
gehaald. Uitgangspunt is Ubuntu 24.04 LTS.

```bash
sudo apt update && sudo apt -y upgrade

sudo apt install -y nginx                    # verwijder ::80 uit de default-site
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo mysql_secure_installation               # interactief

# PHP 8.4 komt niet uit 24.04 zelf; composer.json eist ^8.2, het Docker-image
# draait 8.4 en vendor/composer/platform_check.php eist >= 8.4.1.
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-common php8.4-fpm php8.4-mysql \
                    php8.4-xml php8.4-curl php8.4-mbstring php8.4-zip php8.4-gd
sudo update-alternatives --set php /usr/bin/php8.4

# Node is alleen op deze machine nodig: het Docker-image bevat geen nodejs en
# geen npm, dat krijgt de assets kant-en-klaar mee (docker/ezisms/Dockerfile).
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Pandoc, voor de RTF-preview van beleidsdocumenten en de Word-versie van een
# schermkopie. Minimaal 3.1.7 — dat is de eerste versie met een RTF-lezer.
# Niet uit apt: 24.04 levert een oudere. Zie PANDOC_BIN in .env.example.
wget https://github.com/jgm/pandoc/releases/download/3.10.1/pandoc-3.10.1-1-amd64.deb
sudo dpkg -i pandoc-3.10.1-1-amd64.deb
```

De extensielijst is dezelfde als die van het Docker-image, dus de twee
omgevingen blijven gelijk. `php8.4-gd` moet FreeType hebben, anders verschijnt
de tolerantiematrix als tabel zonder plaatje. Composer installeert u erbij op de
manier die u gewend bent; het image heeft hem niet, want daar gaat `vendor/` al
gebouwd in mee. `mysqldump` komt hier met `mysql-server` mee — `deploy.sh` en de
container eisen hem apart, want daar staat de database elders.

Het origineel installeerde vóór de PPA ook nog `php-mysql` en `php-fpm` uit
24.04 zelf, en daarna nog eens `php-curl`, `php-dom`, `php-gd`, `php-mbstring`,
`php-xml` en `php-zip`. Dat zijn de 8.3-pakketten; ze zijn overbodig naast de
lijst hierboven en hier weggelaten.

## De database

```sql
CREATE DATABASE isms27001 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'admin_isms'@'localhost' IDENTIFIED BY '……';
GRANT ALL PRIVILEGES ON isms27001.* TO 'admin_isms'@'localhost';
```

`utf8mb4_unicode_ci` is geen smaakkwestie: de norm- en maatregelteksten bevatten
diakrieten en aanhalingstekens die in `latin1` stilzwijgend sneuvelen.

Een tweede database naast de eerste is bruikbaar om beide normprofielen naast
elkaar te draaien, want `ISMS_NORM` wordt één keer gelezen en daarna in de
tabel `normprofiel` vastgelegd — omzetten in `.env` verandert daarna niets meer.
Dezelfde gebruiker mag erbij:

```sql
CREATE DATABASE isms7510 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON isms7510.* TO 'admin_isms'@'localhost';
```

Zet de naam en de aanmeldgegevens daarna in `DB_DATABASE`, `DB_USERNAME` en
`DB_PASSWORD` in `.env`. Vanaf dat punt neemt `ota/README.md` het over, onder
"Eerste installatie": `composer install`, `npm run build`, `migrate --seed` en
`isms:eerste-ciso`. Let daar op de instellingen die vóór de eerste seed goed
moeten staan — `ISMS_NORM` is er daar één van.

## Waarom een eigen fpm-pool

Op deze machine draaien acht nginx-sites op de gedeelde pool `www` als
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

## Installeren op een nieuwe machine

```bash
sudo install -o root -g root -m 0644 \
    ontwikkelmachine/php-fpm-pool-ota-isms.conf /etc/php/8.4/fpm/pool.d/ota-isms.conf
sudo install -o root -g root -m 0644 \
    ontwikkelmachine/nginx-ota-isms.conf /etc/nginx/sites-available/ota-isms
sudo ln -sfn ../sites-available/ota-isms /etc/nginx/sites-enabled/ota-isms

sudo php-fpm8.4 -t && sudo nginx -t          # eerst toetsen, dan pas herladen
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

Pas daarna `user`/`group` in het poolbestand en `root`/`server_name` in de vhost
aan als de nieuwe machine een ander account of een andere hostnaam gebruikt.

Zet een back-up van de vhost **nooit** in `sites-enabled/`: nginx laadt die map
met een glob, en een kopie levert een tweede serverblok voor dezelfde
`server_name` op.

## Controleren dat het klopt

```bash
ps -o user,args -C php-fpm8.4 | grep 'pool ota-isms'   # moet de juiste user zijn
find ota/storage ota/bootstrap/cache ! -user "$USER"   # moet leeg zijn
getfacl -Rsp ota/storage ota/bootstrap/cache           # moet leeg zijn
```
