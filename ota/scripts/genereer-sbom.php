<?php

declare(strict_types=1);

/**
 * Genereert SBOM.md uit de vastgezette afhankelijkheden.
 *
 * De PHP- en npm-pakketten (met versie en licentie) komen uit `composer.lock`
 * en `package-lock.json`; de systeemsoftware wordt waar mogelijk gedetecteerd
 * en valt anders terug op een onderhouden standaard (zie $INFRA hieronder).
 *
 * Gebruik (vanuit de projectmap `ota/`):
 *   php scripts/genereer-sbom.php            schrijft SBOM.md
 *   php scripts/genereer-sbom.php --stdout   toont het resultaat, schrijft niets
 *
 * Draai dit na elke `composer update` / `npm update` of een wijziging aan de
 * runtime-omgeving, en commit de bijgewerkte SBOM.md.
 */
$PROJECT = dirname(__DIR__);              // ota/
$SBOM = $PROJECT.'/SBOM.md';
$naarStdout = in_array('--stdout', array_slice($argv, 1), true);

// --------------------------------------------------------------- hulpfuncties

function leesJson(string $pad): array
{
    if (! is_file($pad)) {
        fwrite(STDERR, "FOUT: $pad ontbreekt.\n");
        exit(1);
    }

    return json_decode((string) file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
}

/** Licentie-aanduiding van een composer-pakket ('A / B' bij meerdere). */
function licentie(array $pkg): string
{
    return isset($pkg['license']) && $pkg['license'] !== []
        ? implode(' / ', $pkg['license'])
        : '—';
}

/** Best-effort versiedetectie; geeft null als het commando ontbreekt/faalt. */
function detecteer(string $commando, string $patroon): ?string
{
    $uitvoer = @shell_exec($commando.' 2>&1');
    if ($uitvoer === null || $uitvoer === '') {
        return null;
    }

    return preg_match($patroon, $uitvoer, $m) ? trim($m[1]) : null;
}

/**
 * De distributie uit `/etc/os-release` — de regel waar §1 om draait.
 *
 * Zonder naam én versie van het besturingssysteem is de rest van deze tabel niet
 * aan een kwetsbaarhedenfeed te koppelen: "nginx 1.24.0" is geen CVE-vraag,
 * "nginx 1.24.0 op Ubuntu 24.04" wel (A.8.8). Vandaar dat dit gedetecteerd wordt
 * en niet als standaard in $INFRA staat — een verouderde OS-regel is erger dan
 * geen.
 */
function distributie(): ?string
{
    if (! is_readable('/etc/os-release')) {
        return null;
    }

    $velden = @parse_ini_file('/etc/os-release');

    if ($velden === false || $velden === []) {
        return null;
    }

    // PRETTY_NAME bevat bij Ubuntu de puntversie ("24.04.3 LTS"), die bij
    // NAME + VERSION_ID wegvalt.
    return $velden['PRETTY_NAME'] ?? trim(($velden['NAME'] ?? '').' '.($velden['VERSION_ID'] ?? '')) ?: null;
}

/** De versie van een distributiepakket; null buiten dpkg-systemen. */
function pakketversie(string $pakket): ?string
{
    return detecteer('dpkg-query -W -f=\'${Version}\' '.escapeshellarg($pakket), '#^([^\s]+)$#');
}

/**
 * Bouwt een tabelbody uit composer-pakketten, gesorteerd op de gerenderde regel
 * (zoals in de bestaande SBOM). Telt en passant de licenties.
 *
 * @param  array<int, array<string, mixed>>  $pakketten
 * @param  array<string, int>  $telling
 */
function pakkettabel(array $pakketten, array &$telling): string
{
    $regels = [];
    foreach ($pakketten as $p) {
        foreach ($p['license'] ?? ['onbekend'] as $een) {
            $telling[$een] = ($telling[$een] ?? 0) + 1;
        }
        $regels[] = '| `'.$p['name'].'` | '.ltrim((string) $p['version'], 'v').' | '.licentie($p).' |';
    }
    usort($regels, 'strcasecmp');

    return implode("\n", $regels);
}

// ------------------------------------------------------------------- inlezen

$lock = leesJson($PROJECT.'/composer.lock');
$composerJson = leesJson($PROJECT.'/composer.json');
$pkgJson = leesJson($PROJECT.'/package.json');
$npmLock = leesJson($PROJECT.'/package-lock.json');

$phpConstraint = $composerJson['require']['php'] ?? '—';

// --- composer ---
$licTelling = [];
$prodTabel = pakkettabel($lock['packages'], $licTelling);
$devTabel = pakkettabel($lock['packages-dev'], $licTelling);
$prodN = count($lock['packages']);
$devN = count($lock['packages-dev']);

arsort($licTelling);
$licRegels = [];
foreach ($licTelling as $naam => $n) {
    $licRegels[] = "| $naam | $n |";
}
$licTabel = implode("\n", $licRegels);

// --- npm (directe deps, resolved uit de lock) ---
$npmDirect = [
    ...array_keys($pkgJson['dependencies'] ?? []),
    ...array_keys($pkgJson['devDependencies'] ?? []),
];
sort($npmDirect, SORT_FLAG_CASE | SORT_STRING);
$npmRegels = [];
foreach ($npmDirect as $naam) {
    $entry = $npmLock['packages']['node_modules/'.$naam] ?? [];
    $npmRegels[] = '| `'.$naam.'` | '.($entry['version'] ?? '?').' | '.($entry['license'] ?? '—').' |';
}
$npmTabel = implode("\n", $npmRegels);
$npmTotaal = count(array_filter(
    array_keys($npmLock['packages'] ?? []),
    fn ($k) => $k !== '' && str_starts_with($k, 'node_modules/')
));

// --- systeemsoftware: onderhouden standaard + best-effort detectie ---
$phpVersie = PHP_VERSION;
$INFRA = [
    ['Besturingssysteem', distributie() ?? '*(niet gedetecteerd)*', 'Basis onder alle systeempakketten hieronder', 'Distributie — diverse licenties'],
    ['Linux-kernel', detecteer('uname -r', '#^(.+)$#') ?? '—', 'Kernel', 'GPL-2.0'],
    ['PHP', $phpVersie.' (vereist `'.$phpConstraint.'`)', 'Applicatie-runtime', 'PHP License 3.01'],
    ['nginx', detecteer('nginx -v', '#nginx/([^\s]+.*)#') ?? '1.24.0 (Ubuntu)', 'Webserver / reverse proxy', 'BSD-2-Clause'],
    ['HAProxy', '*(aparte server)*', 'TLS-terminatie vóór nginx', 'GPL-2.0-or-later'],
    ['MySQL', detecteer('mysql --version', '#Ver (\d+\.\d+\.\d+)#') ?? '8.0.x', 'Relationele database', 'GPL-2.0 (+ commercieel)'],
    // Niet meer "optioneel": sinds 12h maakt pandoc élke schermkopie voor de
    // auditor. Ontbreekt hij, dan valt een auditfunctie om — de preview was het
    // enige gebruik toen die aanduiding hier kwam te staan.
    ['pandoc', detecteer('pandoc --version', '#pandoc\s+([0-9.]+)#') ?? '—', 'Schermkopie voor de auditor naar Word (12h); RTF→HTML-preview (blok 5/6)', 'GPL-2.0-or-later'],
    ['fonts-dejavu-core', pakketversie('fonts-dejavu-core') ?? '*(systeempakket)*', 'Lettertype voor de matrixafbeelding in de auditorkopie (12h §7a)', 'Bitstream Vera / Arev'],
    ['cron', 'systeem', 'Draait `schedule:run` (taken, KPI\'s, archivering)', 'systeempakket'],
    ['Node.js', detecteer('node -v', '#v?([0-9.]+)#') ?? '—', 'Build-toolchain (alleen build-time)', 'MIT (+ overige)'],
    ['npm', detecteer('npm -v', '#([0-9.]+)#') ?? '—', 'Package-manager frontend (build-time)', 'Artistic-2.0'],
];
$infraRegels = [];
foreach ($INFRA as [$c, $v, $rol, $lic]) {
    $infraRegels[] = "| $c | $v | $rol | $lic |";
}
$infraTabel = implode("\n", $infraRegels);

$datum = date('Y-m-d');

// --------------------------------------------------------------- samenstellen

$md = <<<MD
# Software Bill of Materials (SBOM)

Overzicht van alle software waaruit dit ISMS-platform is opgebouwd en waarvan het
op productie afhankelijk is. Bedoeld voor kwetsbaarheidsbeheer en
licentie-verantwoording (ISO 27001 A.5.19–5.21 leveranciers-/ketenrisico,
A.8.8 technische kwetsbaarheden).

- **Peildatum:** $datum
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
$infraTabel

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

## 3. PHP — alle productiepakketten ($prodN)

Volledig uitgerold op productie (`composer install --no-dev`).

| Pakket | Versie | Licentie |
|---|---|---|
$prodTabel

---

## 4. PHP — dev-pakketten ($devN)

Alleen op de ontwikkel-/CI-machine (test, lint, build). **Niet** op productie.

| Pakket | Versie | Licentie |
|---|---|---|
$devTabel

---

## 5. JavaScript / frontend (build-time)

Directe npm-afhankelijkheden uit `package.json` (resolved versies uit
`package-lock.json`). De volledige `node_modules`-boom bevat $npmTotaal pakketten;
die zijn build-time en worden **niet** uitgerold — alleen de door Vite
gecompileerde bundle (`public/build/`) gaat mee.

| Pakket | Versie | Licentie |
|---|---|---|
$npmTabel

---

## 6. Licentie-samenvatting (PHP-pakketten)

Aantal composer-pakketten per licentie-aanduiding (prod + dev; sommige pakketten
melden meerdere licenties).

| Licentie | Aantal |
|---|---|
$licTabel

De GPL-2.0/3.0-regels hierboven komen uitsluitend van twee **tri-licensed**
Nette-pakketten (`nette/schema`, `nette/utils`): die zijn beschikbaar onder
`BSD-3-Clause` **óf** GPL naar keuze, dus ze kunnen permissief worden gebruikt —
er rust geen verplichte copyleft op de meegeleverde applicatiepakketten. Alle
overige open-source-componenten zijn permissief (MIT, BSD-varianten, Apache-2.0).
De enige uitzondering op "open source" is de **proprietary** `livewire/flux`.

---

## 7. Buiten scope

- **Gelicentieerde norm-inhoud** (NEN-EN-ISO/IEC-teksten) is *data*, geen
  software: `controls.json` en `maatregel-capaciteiten.json` zijn gitignored en
  worden nooit gedistribueerd (zie het maatregelseed-beleid). De twee
  controlsets `maatregelen-iso27001.json` en `maatregelen-nen7510.json` zijn wél
  distribueerbaar: ze dragen referenties, thema's en titels plus vaste
  markeringen, geen normtekst. Idem `maatregel-kenmerken.json`.
- **CISO-beheerde toets-HTML** (`public/toetsen/*.html`) is content op de
  doelmachine, geen afhankelijkheid van dit project.
- **Transitieve npm-pakketten** zijn hier niet per stuk opgesomd (build-time,
  niet uitgerold); de exacte lijst staat in `package-lock.json`.

MD;

// ------------------------------------------------------------------- uitvoer

if ($naarStdout) {
    echo $md;
    exit(0);
}

file_put_contents($SBOM, $md);
fwrite(STDERR, sprintf(
    "SBOM.md bijgewerkt: %d prod- + %d dev-composerpakketten, %d directe npm-deps (%d in node_modules).\n",
    $prodN, $devN, count($npmDirect), $npmTotaal
));
