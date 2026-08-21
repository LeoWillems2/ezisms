<img width="200" height="150" alt="isms-logo" src="https://github.com/user-attachments/assets/39e7eaf8-0a71-4b81-999c-4453f7082e7c" />

# Introductie

- Kijk op [ezisms.nl](https://www.ezisms.nl). Op de sitemap: klik op de items om de functies te bekijken.

- Met deze repo rol je de Test, Acceptatie en Produktie sites uit voor een ISO 27001, NEN 7510 en BIO2 ISMS.

- Deze repo is de ontwikkelbasis, geheel ontwikkeld met Claude Code. De Architectuur, deelprojectplannen en bouwplannen staan hier niet. Wil je verder ontwikkelen, neem dan contact op: info @ ezisms.nl


# VMhost of bare metal requirements: #


Je zet altijd eerst een ontwikkelomgeving op. Dat staat hieronder.

- Ubuntu 26.04

Gebruik ota/scripts/prephost.sh om een ``kale Ubuntu'' in te richten ter voorbereiding.

Lees ontwikkelmachine/LEESMIJ.md voor details.
Lees ota/README.md voor details over de installatie van Pandoc.

# Nginx #

- Deze installatie gaat er van uit dat haproxy met SSL terminatie wordt gebruikt. Als dat niet zo is, lees dan ota/tls.md voor instructies.

### EZISMS produktie of test installatie ##

- Korte versie: voer uit:

```
ota/scripts/builddistr.sh --uit=/var/tmp
```

Volg de instructies die aan het einde getoond worden. Voor het iso27001 profiel met APP_ENV=local kun je een demonstratievulling maken, voeg dan de vlag --demo-vul toe achter --eerste.

- Lange versie: lees ota/README.md.


- Eerste gebruiker:
```
cd /var/www/[sitedir]/current
sudo bash
sudo -u www-data php artisan isms:eerste-ciso 'email-adres' 'wachtwoord' 'naam'
```

# Docker #

- Lees docker/ezisms/LEESMIJ.md

Dit werk is gelicenseerd onder <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/">CC BY-NC-SA 4.0</a><img src="https://mirrors.creativecommons.org/presskit/icons/cc.svg" alt="" style="max-width: 1em;max-height:1em;margin-left: .2em;"><img src="https://mirrors.creativecommons.org/presskit/icons/by.svg" alt="" style="max-width: 1em;max-height:1em;margin-left: .2em;"><img src="https://mirrors.creativecommons.org/presskit/icons/nc.svg" alt="" style="max-width: 1em;max-height:1em;margin-left: .2em;"><img src="https://mirrors.creativecommons.org/presskit/icons/sa.svg" alt="" style="max-width: 1em;max-height:1em;margin-left: .2em;">
