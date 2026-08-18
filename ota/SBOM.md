# Software Bill of Materials (SBOM)

Overzicht van alle software waaruit dit ISMS-platform is opgebouwd en waarvan het
op productie afhankelijk is. Bedoeld voor kwetsbaarheidsbeheer en
licentie-verantwoording (ISO 27001 A.5.19–5.21 leveranciers-/ketenrisico,
A.8.8 technische kwetsbaarheden).

- **Peildatum:** 2026-08-18
- **Applicatie:** ISMS-ondersteuningsplatform (Laravel, map `ota/`)
- **Bron van de versies:** `composer.lock` en `package-lock.json` (exacte,
  vastgezette versies), aangevuld met de op de server aangetroffen systeemsoftware.
- **Regenereren:** `php scripts/genereer-sbom.php` — leest de lockfiles en
  detecteert de systeemversies. Draai het na elke `composer update` /
  `npm update` of een wijziging aan de runtime-omgeving, en commit het resultaat.

> **Reikwijdte.** PHP-pakketten hieronder worden mét de applicatie uitgerold
> (`composer install --no-dev` levert alle *productie*-pakketten). De
> npm-pakketten zijn **build-time**: Vite compileert daaruit de CSS/JS-bundle;
> de pakketten zelf staan niet op productie. Dev-pakketten (test/lint/build)
> draaien alleen op de ontwikkel-/CI-machine.

---

## 1. Runtime & infrastructuur

Software buiten de package-managers om, nodig om het platform te draaien. TLS
wordt op een aparte HAProxy getermineerd; nginx praat onversleuteld daarachter.

| Component | Versie | Rol | Licentie |
|---|---|---|---|
| Besturingssysteem | Ubuntu 24.04.3 LTS | Basis onder alle systeempakketten hieronder | Distributie — diverse licenties |
| Linux-kernel | 7.0.0-28-generic | Kernel | GPL-2.0 |
| PHP | 8.4.22 (vereist `^8.2`) | Applicatie-runtime | PHP License 3.01 |
| nginx | 1.24.0 (Ubuntu) | Webserver / reverse proxy | BSD-2-Clause |
| HAProxy | *(aparte server)* | TLS-terminatie vóór nginx | GPL-2.0-or-later |
| MySQL | 8.0.46 | Relationele database | GPL-2.0 (+ commercieel) |
| pandoc | 3.10.1 | Schermkopie voor de auditor naar Word (12h); RTF→HTML-preview (blok 5/6) | GPL-2.0-or-later |
| fonts-dejavu-core | 2.37-8 | Lettertype voor de matrixafbeelding in de auditorkopie (12h §7a) | Bitstream Vera / Arev |
| cron | systeem | Draait `schedule:run` (taken, KPI's, archivering) | systeempakket |
| Node.js | 20.20.2 | Build-toolchain (alleen build-time) | MIT (+ overige) |
| npm | 10.8.2 | Package-manager frontend (build-time) | Artistic-2.0 |

Versies van systeempakketten volgen de distributie; houd ze via de
OS-updatecyclus actueel — ze staan los van de lockfiles. De eerste regel is
daarbij de sleutel: een pakketversie is pas aan een kwetsbaarhedenfeed te
koppelen als vaststaat op welke distributie hij gebouwd is (A.8.8).

---

## 2. PHP — directe afhankelijkheden (bewust gekozen)

De pakketten die in `composer.json` staan; de rest in §3 is transitief.

| Component | Versie (constraint) | Rol |
|---|---|---|
| `laravel/fortify` | `^1.37` | Tweefactorauthenticatie — alleen die feature (implementatie/01d §2) |
| `laravel/framework` | `^12.0` | Web-applicatieframework |
| `laravel/tinker` | `^2.10.1` | Artisan REPL / console |
| `livewire/flux` | `^2.0` | UI-componentenbibliotheek (Flux 2, free tier) — **proprietary** |
| `livewire/volt` | `^1.6.7` | Single-file Livewire-componenten |

Dev: `phpunit/phpunit`, `laravel/pint`, `mockery/mockery`, `fakerphp/faker`,
`nunomaduro/collision`, `laravel/pail`, `laravel/sail`.

> **Licentie-attentie.** `livewire/flux` is **proprietary** (commercieel; hier de
> gratis tier). Alle overige afhankelijkheden zijn permissief open source
> (MIT / BSD / Apache-2.0). Zie de licentie-samenvatting in §6.

---

## 3. PHP — alle productiepakketten (100)

Volledig uitgerold op productie (`composer install --no-dev`).

| Pakket | Versie | Licentie |
|---|---|---|
| `bacon/bacon-qr-code` | 3.1.1 | BSD-2-Clause |
| `brick/math` | 0.14.8 | MIT |
| `carbonphp/carbon-doctrine-types` | 3.2.0 | MIT |
| `dasprid/enum` | 1.0.7 | BSD-2-Clause |
| `dflydev/dot-access-data` | 3.0.3 | MIT |
| `doctrine/deprecations` | 1.1.6 | MIT |
| `doctrine/inflector` | 2.1.0 | MIT |
| `doctrine/lexer` | 3.0.1 | MIT |
| `dragonmantank/cron-expression` | 3.6.0 | MIT |
| `egulias/email-validator` | 4.0.4 | MIT |
| `fruitcake/php-cors` | 1.4.0 | MIT |
| `graham-campbell/result-type` | 1.1.4 | MIT |
| `guzzlehttp/guzzle` | 7.15.1 | MIT |
| `guzzlehttp/promises` | 2.5.1 | MIT |
| `guzzlehttp/psr7` | 2.13.0 | MIT |
| `guzzlehttp/uri-template` | 1.0.10 | MIT |
| `laravel/fortify` | 1.37.3 | MIT |
| `laravel/framework` | 12.64.0 | MIT |
| `laravel/passkeys` | 0.2.1 | MIT |
| `laravel/prompts` | 0.3.21 | MIT |
| `laravel/serializable-closure` | 2.0.14 | MIT |
| `laravel/tinker` | 2.11.1 | MIT |
| `league/commonmark` | 2.8.3 | BSD-3-Clause |
| `league/config` | 1.2.0 | BSD-3-Clause |
| `league/flysystem-local` | 3.31.0 | MIT |
| `league/flysystem` | 3.35.2 | MIT |
| `league/mime-type-detection` | 1.17.0 | MIT |
| `league/uri-interfaces` | 7.8.1 | MIT |
| `league/uri` | 7.8.1 | MIT |
| `livewire/blaze` | 1.0.15 | MIT |
| `livewire/flux` | 2.15.0 | proprietary |
| `livewire/livewire` | 4.3.3 | MIT |
| `livewire/volt` | 1.10.5 | MIT |
| `monolog/monolog` | 3.10.0 | MIT |
| `nesbot/carbon` | 3.13.1 | MIT |
| `nette/schema` | 1.3.5 | BSD-3-Clause / GPL-2.0-only / GPL-3.0-only |
| `nette/utils` | 4.1.5 | BSD-3-Clause / GPL-2.0-only / GPL-3.0-only |
| `nikic/php-parser` | 5.8.0 | BSD-3-Clause |
| `nunomaduro/termwind` | 2.4.0 | MIT |
| `paragonie/constant_time_encoding` | 3.1.3 | MIT |
| `phpdocumentor/reflection-common` | 2.2.0 | MIT |
| `phpdocumentor/reflection-docblock` | 6.0.3 | MIT |
| `phpdocumentor/type-resolver` | 2.0.0 | MIT |
| `phpoption/phpoption` | 1.9.5 | Apache-2.0 |
| `phpstan/phpdoc-parser` | 2.3.3 | MIT |
| `pragmarx/google2fa` | 9.0.0 | MIT |
| `psr/clock` | 1.0.0 | MIT |
| `psr/container` | 2.0.2 | MIT |
| `psr/event-dispatcher` | 1.0.0 | MIT |
| `psr/http-client` | 1.0.3 | MIT |
| `psr/http-factory` | 1.1.0 | MIT |
| `psr/http-message` | 2.0 | MIT |
| `psr/log` | 3.0.2 | MIT |
| `psr/simple-cache` | 3.0.0 | MIT |
| `psy/psysh` | 0.12.24 | MIT |
| `ralouphie/getallheaders` | 3.0.3 | MIT |
| `ramsey/collection` | 2.1.1 | MIT |
| `ramsey/uuid` | 4.9.3 | MIT |
| `spomky-labs/cbor-php` | 3.3.0 | MIT |
| `spomky-labs/pki-framework` | 1.5.0 | MIT |
| `symfony/clock` | 8.1.0 | MIT |
| `symfony/console` | 7.4.14 | MIT |
| `symfony/css-selector` | 8.1.0 | MIT |
| `symfony/deprecation-contracts` | 3.7.1 | MIT |
| `symfony/error-handler` | 7.4.14 | MIT |
| `symfony/event-dispatcher-contracts` | 3.7.1 | MIT |
| `symfony/event-dispatcher` | 8.1.1 | MIT |
| `symfony/finder` | 7.4.14 | MIT |
| `symfony/http-foundation` | 7.4.14 | MIT |
| `symfony/http-kernel` | 7.4.14 | MIT |
| `symfony/mailer` | 7.4.14 | MIT |
| `symfony/mime` | 7.4.13 | MIT |
| `symfony/polyfill-ctype` | 1.37.0 | MIT |
| `symfony/polyfill-intl-grapheme` | 1.38.1 | MIT |
| `symfony/polyfill-intl-idn` | 1.38.1 | MIT |
| `symfony/polyfill-intl-normalizer` | 1.38.0 | MIT |
| `symfony/polyfill-mbstring` | 1.38.2 | MIT |
| `symfony/polyfill-php80` | 1.37.0 | MIT |
| `symfony/polyfill-php83` | 1.38.2 | MIT |
| `symfony/polyfill-php84` | 1.38.1 | MIT |
| `symfony/polyfill-php85` | 1.38.1 | MIT |
| `symfony/polyfill-uuid` | 1.37.0 | MIT |
| `symfony/process` | 7.4.13 | MIT |
| `symfony/property-access` | 8.1.0 | MIT |
| `symfony/property-info` | 8.1.2 | MIT |
| `symfony/routing` | 7.4.13 | MIT |
| `symfony/serializer` | 8.1.3 | MIT |
| `symfony/service-contracts` | 3.7.1 | MIT |
| `symfony/string` | 8.1.0 | MIT |
| `symfony/translation-contracts` | 3.7.1 | MIT |
| `symfony/translation` | 8.1.1 | MIT |
| `symfony/type-info` | 8.1.0 | MIT |
| `symfony/uid` | 7.4.9 | MIT |
| `symfony/var-dumper` | 7.4.14 | MIT |
| `tijsverkoyen/css-to-inline-styles` | 2.4.0 | BSD-3-Clause |
| `vlucas/phpdotenv` | 5.6.4 | BSD-3-Clause |
| `voku/portable-ascii` | 2.1.1 | MIT |
| `web-auth/cose-lib` | 4.6.0 | MIT |
| `web-auth/webauthn-lib` | 5.3.5 | MIT |
| `webmozart/assert` | 2.4.1 | MIT |

---

## 4. PHP — dev-pakketten (35)

Alleen op de ontwikkel-/CI-machine (test, lint, build). **Niet** op productie.

| Pakket | Versie | Licentie |
|---|---|---|
| `fakerphp/faker` | 1.24.1 | MIT |
| `filp/whoops` | 2.18.4 | MIT |
| `hamcrest/hamcrest-php` | 2.1.1 | BSD-3-Clause |
| `laravel/pail` | 1.2.7 | MIT |
| `laravel/pint` | 1.29.3 | MIT |
| `laravel/sail` | 1.64.0 | MIT |
| `mockery/mockery` | 1.6.12 | BSD-3-Clause |
| `myclabs/deep-copy` | 1.13.4 | MIT |
| `nunomaduro/collision` | 8.9.5 | MIT |
| `phar-io/manifest` | 2.0.4 | BSD-3-Clause |
| `phar-io/version` | 3.2.1 | BSD-3-Clause |
| `phpunit/php-code-coverage` | 11.0.12 | BSD-3-Clause |
| `phpunit/php-file-iterator` | 5.1.1 | BSD-3-Clause |
| `phpunit/php-invoker` | 5.0.1 | BSD-3-Clause |
| `phpunit/php-text-template` | 4.0.1 | BSD-3-Clause |
| `phpunit/php-timer` | 7.0.1 | BSD-3-Clause |
| `phpunit/phpunit` | 11.5.56 | BSD-3-Clause |
| `sebastian/cli-parser` | 3.0.2 | BSD-3-Clause |
| `sebastian/code-unit-reverse-lookup` | 4.0.1 | BSD-3-Clause |
| `sebastian/code-unit` | 3.0.3 | BSD-3-Clause |
| `sebastian/comparator` | 6.3.3 | BSD-3-Clause |
| `sebastian/complexity` | 4.0.1 | BSD-3-Clause |
| `sebastian/diff` | 6.0.2 | BSD-3-Clause |
| `sebastian/environment` | 7.2.1 | BSD-3-Clause |
| `sebastian/exporter` | 6.3.2 | BSD-3-Clause |
| `sebastian/global-state` | 7.0.2 | BSD-3-Clause |
| `sebastian/lines-of-code` | 3.0.1 | BSD-3-Clause |
| `sebastian/object-enumerator` | 6.0.1 | BSD-3-Clause |
| `sebastian/object-reflector` | 4.0.1 | BSD-3-Clause |
| `sebastian/recursion-context` | 6.0.3 | BSD-3-Clause |
| `sebastian/type` | 5.1.3 | BSD-3-Clause |
| `sebastian/version` | 5.0.2 | BSD-3-Clause |
| `staabm/side-effects-detector` | 1.0.5 | MIT |
| `symfony/yaml` | 8.1.1 | MIT |
| `theseer/tokenizer` | 1.3.1 | BSD-3-Clause |

---

## 5. JavaScript / frontend (build-time)

Directe npm-afhankelijkheden uit `package.json` (resolved versies uit
`package-lock.json`). De volledige `node_modules`-boom bevat 145 pakketten;
die zijn build-time en worden **niet** uitgerold — alleen de door Vite
gecompileerde bundle (`public/build/`) gaat mee.

| Pakket | Versie | Licentie |
|---|---|---|
| `@tailwindcss/vite` | 4.0.8 | MIT |
| `autoprefixer` | 10.4.20 | MIT |
| `axios` | 1.7.9 | MIT |
| `concurrently` | 9.1.2 | MIT |
| `laravel-vite-plugin` | 1.2.0 | MIT |
| `tailwindcss` | 4.0.8 | MIT |
| `vite` | 6.1.1 | MIT |

---

## 6. Meegeleverde statische bestanden (geen package manager)

Bestanden van derden die rechtstreeks in `public/` staan en met de applicatie
worden uitgerold. Ze komen niet uit een lockfile, dus deze tabel is handwerk:
werk hem bij zodra je zo'n bestand toevoegt of vervangt. Ze staan er bewust
lokaal — de applicatie laadt niets van een extern domein, zodat een installatie
zonder uitgaand verkeer identiek oogt.

| Bestand(en) | Herkomst | Rol | Licentie |
|---|---|---|---|
| `public/fonts/instrument-sans/*.woff2` (6×) | fonts.bunny.net, opgehaald 2026-08-18 | Schermlettertype (400/500/600, latin + latin-ext); `public/fonts/instrument-sans.css` draagt de `@font-face`-regels | SIL OFL 1.1 (`OFL.txt` meegeleverd) |
| `public/images/cc/{cc,by,nc,sa}.svg` | mirrors.creativecommons.org, opgehaald 2026-08-18 | Licentie-iconen in de footer | Creative Commons — persmateriaal, vrij te gebruiken bij een CC-licentievermelding |

---

## 7. Licentie-samenvatting (PHP-pakketten)

Aantal composer-pakketten per licentie-aanduiding (prod + dev; sommige pakketten
melden meerdere licenties).

| Licentie | Aantal |
|---|---|
| MIT | 98 |
| BSD-3-Clause | 33 |
| BSD-2-Clause | 2 |
| GPL-2.0-only | 2 |
| GPL-3.0-only | 2 |
| proprietary | 1 |
| Apache-2.0 | 1 |

De GPL-2.0/3.0-regels hierboven komen uitsluitend van twee **tri-licensed**
Nette-pakketten (`nette/schema`, `nette/utils`): die zijn beschikbaar onder
`BSD-3-Clause` **óf** GPL naar keuze, dus ze kunnen permissief worden gebruikt —
er rust geen verplichte copyleft op de meegeleverde applicatiepakketten. Alle
overige open-source-componenten zijn permissief (MIT, BSD-varianten, Apache-2.0).
De enige uitzondering op "open source" is de **proprietary** `livewire/flux`.

---

## 8. Buiten scope

- **Gelicentieerde norm-inhoud** (NEN-EN-ISO/IEC-teksten) is *data*, geen
  software: `controls.json` en `maatregel-capaciteiten.json` zijn gitignored en
  worden nooit gedistribueerd (zie het maatregelseed-beleid). De drie
  controlsets `maatregelen-iso27001.json`, `maatregelen-nen7510.json` en
  `maatregelen-bio2.json` (plus `overheidsmaatregelen-bio2.json`) zijn wél
  distribueerbaar: ze dragen referenties, thema's en titels plus vaste
  markeringen, geen normtekst. Idem `maatregel-kenmerken.json`.
- **CISO-beheerde toets-HTML** (`public/toetsen/*.html`) is content op de
  doelmachine, geen afhankelijkheid van dit project.
- **Transitieve npm-pakketten** zijn hier niet per stuk opgesomd (build-time,
  niet uitgerold); de exacte lijst staat in `package-lock.json`.
