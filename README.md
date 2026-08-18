# ezisms #
<img width="400" height="300" alt="isms-logo" src="https://github.com/user-attachments/assets/39e7eaf8-0a71-4b81-999c-4453f7082e7c" />

# Introductie

- Kijk op [link](https://www.ezisms.nl). Op de sitemap: klik op de items om de functies te bekijken.


# VMHost of Bare metal requirements: #

- Ubuntu 26.04

- apt install nginx  composer php-cli php-zip unzip curl nodejs php php-cli php-common php-fpm php-mysql php-xml php-curl php-mbstring php-zip php-gd mysql-server ## pandoc

Lees ota/README.md voor details over de installatie van Pandoc.


# Nginx #

- Deze installatie gaat er van uit dat haproxy met SSL terminatie wordt gebruikt. Als dat niet zo is, lees dan ota/tls.md voor instructies.

- Mkdir /var/www/[sitedir]


# Mysql #

- Maak de database en de database-user.

```
CREATE DATABASE iso27001 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'User'@'localhost' IDENTIFIED BY 'Password';
GRANT ALL PRIVILEGES ON iso27001.* TO 'user'@'localhost';
```

# Env #

- Kopieer ota/.env.example naar /var/www/[sitedir]/.env en zet de gegevens.


# VMHost of Bare metal EZISMS installatie: #

- Korte versie: voer uit:

```
ota/scripts/builddistr.sh --uit=/var/tmp
```

Volg de instructies die aan het einde getoond worden.

- Lange versie: lees ota/README.md.


# Docker #

- Lees docker/LEESMIJ.md
