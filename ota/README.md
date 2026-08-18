# ISMS (ota)

Laravel-applicatie behorend bij `architectuur.md`, `deelproducten/` en
`implementatie/` in de bovenliggende map. **Alle vijftien blokken zijn
gebouwd** — van blok 1 (Identity, Access & Rollen) tot en met blok 15
(Wijzigingsbeheer) — plus een aantal cross-cutting voorzieningen die geen eigen
blok zijn (kennisbank, export, normprofiel, schedulerhartslag, schermkopie,
installatiebeheer; zie de gelijknamige sectie).

De secties hieronder staan op nummer, met één uitzondering die de bouwvolgorde
laat zien: **blok 6 en 7 kwamen vóór 5**, omdat beleidsversies naar
bewijsstukken verwijzen en de leestermijn een taak is. De feitelijke
bouwvolgorde week vaker af — de KPI-meting van blok 12 is bijvoorbeeld ouder dan
blok 9 — maar dat is voor het lezen van dit bestand niet nuttig.

## Stack

Laravel 12 · PHP 8.4 · MySQL · Livewire 3 + Volt · Flux 2 · Vite/Tailwind 4 ·
Fortify (uitsluitend voor tweefactor, zie `config/fortify.php`).

Afwijking van `implementatie/00-stack-en-conventies.md` §1: daar staat Laravel
Breeze als authenticatie-scaffolding. Breeze ondersteunt Laravel 12 niet meer
(gearchiveerd); gebruikt is de opvolger, de officiële **Livewire starter kit**.
Die levert dezelfde auth-schermen én brengt Flux mee, dat de conventies
sowieso al voorschrijven.

## Eerste installatie

Er zijn **drie routes**, en de rest van dit hoofdstuk beschrijft de eerste:

- **Deze checkout, rechtstreeks op de machine** — de ontwikkel- en
  testopstelling. Stappen hieronder.
- **Bare-metal productie** — uit de tarbal van `scripts/builddistr.sh`, uitgerold
  met `scripts/deploy.sh`. Dat script legt `shared/`, de release-symlink, de
  crontab, het eigendom en de rechten zelf aan; je draait de stappen hieronder
  daar niet met de hand. Is de tarbal met `--geen-bouw` gemaakt, dan draait
  `deploy.sh` `composer install` en `npm run build` zelf op de doelhost, vóór er
  iets aan de installatie verandert; die host heeft dan composer, npm en node
  nodig. Vraagt composer daar om een GitHub-token, zet dat dan in
  `<doelpad>/shared/auth.json` (0600) — dat bestand overleeft elke release en
  wordt na de bouw weer uit de boom verwijderd.
- **De container** — `docker/ezisms/` in de bovenliggende map, met
  `LEESMIJ.md` als instructie en `compose.yml` als startpunt. De entrypoint
  regelt eigendom, sleutel, migraties en seeds zelf.

Alle drie draaien met **één schrijvend account** in `storage/`; wie dat is
verschilt per route. Zie "Schrijfrechten op storage/" hieronder.

```bash
composer install
npm install && npm run build
# Zet de installatie-instellingen in .env vóór de volgende regel — zie hieronder.
php artisan migrate --seed          # referentiedata: rollen, blokken, rechtenmatrix
php artisan isms:eerste-ciso "email@voorbeeld.nl" "wachtwoord" "Naam"
```

### Installatie-instellingen: vóór de eerste seed

Vier sleutels uit `.env.example` bepalen wat er geseed wordt of hoe de
applicatie zich gedraagt. Alleen de eerste is onomkeerbaar.

| Sleutel | Standaard | Wat het doet |
|---|---|---|
| `ISMS_NORM` | `iso27001` | Normprofiel: `iso27001` (93 maatregelen), `nen7510` (101) of `bio2` (93 + 118 overheidsmaatregelen). |
| `ISMS_2FA_AFDWINGEN` | `true` | Tweefactor verplicht, met `ISMS_2FA_RESPIJT_DAGEN` (14) respijt na de eerste login. |
| `ISMS_CBW_PLICHTIG` | `false` | Of de organisatie onder de Cyberbeveiligingswet valt; stuurt de meldtermijnen in blok 8. |
| `ISMS_CAPACITEITEN` | `false` | De kenmerkdimensie "Capaciteiten". Zet hem **niet** met de hand aan — zie hieronder. |

**`ISMS_NORM` is een installatiekeuze en daarna immutable.** `NormprofielSeeder`
schrijft hem één keer naar de tabel `normprofiel`; staat die rij er eenmaal, dan
wint de database en is de `.env`-waarde betekenisloos (`config/norm.php`). Van
norm wisselen betekent de database opnieuw opbouwen — een wissel achteraf zou de
SoA van controlset laten veranderen terwijl de beoordelingen blijven staan, en
dat maakt een auditrapport onbruikbaar.

De waarde wordt bewust in `config/norm.php` gelezen en niet met `env()` in de
seeder. `scripts/deploy.sh` draait `config:cache` vóór `db:seed`, en zodra de
configuratie gecached is slaat Laravel `.env` volledig over: elke `env()` buiten
een configuratiebestand levert dan de standaardwaarde. Dat is één keer echt
misgegaan — een uitrol met `ISMS_NORM=nen7510` seedde 93 in plaats van 101
maatregelen.

**`ISMS_CAPACITEITEN` hoort via het commando, niet met de hand.** Van de
ISO 27002-kenmerkdimensies is "Capaciteiten" de enige waarvan óók het
vocabulaire ISO-eigen is; het repo levert er dus geen waarden en geen toewijzing
bij (`config/maatregelkenmerken.php`). Wie de norm bezit zet hem aan met
`php artisan isms:capaciteiten aan`, dat de waarden uit het lokale, gitignorede
`database/seeders/data/maatregel-capaciteiten.json` leest. De schakelaar
rechtstreeks op `true` zetten levert een actieve maar lege dimensie op.

Het laatste commando maakt het eerste CISO-account direct **actief** met het
opgegeven wachtwoord — bewust geen mailserver-afhankelijkheid voor deze eenmalige
bootstrap. De naam is optioneel (anders afgeleid uit het e-mailadres). Daarna
verloopt alle verder gebruikersbeheer via `/gebruikers`.

`php artisan db:seed` bevat uitsluitend referentiedata (geen testdata) en hoort
daarom ook in productie te draaien — zie conventies §1.

### pandoc voor preview én Word-documenten

Twee dingen lopen via de `pandoc`-binary. De HTML-preview van bewijsstukken
(blok 5) — RTF, DOCX en ODT — is een terugval: zonder pandoc toont die "niet
beschikbaar" en blijft de download de geldende weg. Maar sinds `implementatie/12h`
maakt pandoc ook de **Word-documenten**: de schermkopie voor de auditor en een
kennisartikel als `.docx`. Daar is geen terugval; die knoppen geven dan een 503.

Twee eisen, en de tweede is de eis waar een distributiepakket op zakt:

```bash
pandoc --version    # >= 3.1.7 voor de RTF-lezer; docx/odt lezen ook oudere versies

# Kan deze pandoc een .docx schrijven mét --sandbox? Dit is het commando dat de
# applicatie zelf doet (App\Support\Pandoc::converteer); het hoort 0 te geven.
printf '# Toets\n' | pandoc --sandbox --from=markdown --to=docx --output=/dev/null
echo $?
```

Zakt die tweede regel met `Could not find data file data/data/docx/…`, dan is de
binary gebouwd zónder ingebakken datafiles (de datafiles staan dan in een apart
pakket, bijvoorbeeld `pandoc-data`). `--sandbox` gebruikt uitsluitend de
ingebakken versie, en `--data-dir` maakt daar niets aan uit. De pandoc van
Ubuntu 26.04 (3.7.0.2) heeft dit; die van Ubuntu 22.04 (2.9) mist bovendien de
RTF-lezer.

Installeer in beide gevallen de release van
[github.com/jgm/pandoc](https://github.com/jgm/pandoc/releases) — één statische
binary met alles erin — en zet het pad desnoods via `PANDOC_BIN` in `.env`. De
Docker-route doet precies dat, met een vastgepinde versie en hash in
`docker/Dockerfile`. `deploy.sh` draait de controle hierboven bij elke uitrol en
waarschuwt als hij zakt.

### Optioneel: GD met FreeType voor de matrixafbeelding

De schermkopie van de tolerantiematrix (blok 4b / `implementatie/12h` §7a) tekent
de 5×5-matrix als PNG met de GD-extensie. Dat vraagt **twee** dingen: GD moet met
FreeType-ondersteuning gecompileerd zijn (anders geen `imagettftext()`, en dus
geen cijfers in de cellen), en er moet een TTF-lettertype op de machine staan —
`Tolerantiematrixplaat::LETTERTYPEN` probeert DejaVu, Liberation en Noto, in die
volgorde.

```bash
php -r 'var_dump(gd_info()["FreeType Support"] ?? false);'   # moet true zijn
ls /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf           # of Liberation / Noto
sudo apt install php8.4-gd fonts-dejavu-core                 # als er iets ontbreekt
```

Ontbreekt een van beide, dan blijft **het plaatje** weg en verandert er verder
niets: dezelfde matrix staat als tabel in hetzelfde document, dus alle cijfers
zijn er nog. Dat is een ander soort terugval dan bij pandoc hierboven — zonder
pandoc is er géén document, zonder GD een document zonder illustratie.

### Schrijfrechten op storage/

**Eén schrijvend account per omgeving. Geen ACL's, nergens.** Dat is sinds
13-08-2026 in alle drie de omgevingen hetzelfde principe, met per omgeving een
andere identiteit:

| Omgeving | Wie schrijft in `storage/` | Ingericht door |
|---|---|---|
| Deze ontwikkelmachine | `leo` (php-fpm, artisan, tests) | een eigen php-fpm-pool, zie hieronder |
| Bare-metal productie | `www-data` (php-fpm, scheduler, artisan) | `scripts/deploy.sh` → `zet_rechten()` |
| Container | `www-data` (php-fpm, scheduler, artisan) | `docker/ezisms/entrypoint.sh` |

In productie en in de container bezit een **apart account** de code
(`ezisms`), dat niets draait — zo kan het webproces zijn eigen applicatiecode
niet herschrijven (A.8.19). Op de ontwikkelmachine is dat onderscheid er niet:
daar is de checkout gewoon van `leo`.

#### De pool op deze machine

Op deze machine draaien acht nginx-sites op de gedeelde pool `www` als
`www-data`. Dit project is de enige waarin óók artisan en de testsuite in
`storage/` schrijven, en wel als `leo`. Daarom heeft alléén deze site een eigen
pool die als `leo` draait:

- `/etc/php/8.4/fpm/pool.d/ota-isms.conf` — `user`/`group = leo`, eigen socket
  `/run/php/php8.4-fpm-ota-isms.sock` (`listen.group = www-data`, `0660`, want
  nginx draait als www-data en moet de socket kunnen aanspreken);
- `fastcgi_pass` in `/etc/nginx/sites-available/ota-isms` wijst daarheen.

De andere zeven sites blijven ongemoeid op `www`. Na een `git clone` op een
nieuwe machine is er aan `storage/` niets te doen — de bestanden zijn van jou.
Wat je wél inricht is de pool en de vhost, en dat is machineconfiguratie die je
voor nginx toch al doet.

**Beide bestanden staan in versiebeheer**, in `ontwikkelmachine/` in de
bovenliggende map, met installatie-instructies in `LEESMIJ.md`. Ze liggen
bewust búiten `ota/`, want `builddistr.sh` pakt alleen `ota/`, `docker/` en de
demofixtures — een poolbestand met een gebruikersnaam en een hostnaam die alleen
hier betekenis hebben, hoort niet in een klanttarbal.

Controleren of het klopt:

```bash
ps -o user,args -C php-fpm8.4 | grep 'pool ota-isms'   # moet leo zijn
find storage bootstrap/cache ! -user "$USER"           # moet leeg zijn
getfacl -Rsp storage bootstrap/cache                   # moet leeg zijn
```

#### Waarom hier eerder ACL's stonden, en waarom ze weg zijn

Tot 13-08-2026 draaide php-fpm hier als `www-data` terwijl artisan en de tests
als `leo` draaiden. Twee accounts met verschillende umasks strippen elkaars
`g+w`, dus stonden er ACL's op `storage/` en `bootstrap/cache`.

**Die constructie viel stil op de mask.** Een ACL-mask wordt afgeleid van de
groepsbits waarmee een map wordt aangemaakt; een map met mode 0700 krijgt
`mask::---`, en dan is elke benoemde ACL-entry `#effective:---` — ook die van
het account dat de map zelf maakte. Dat was geen theorie: Livewire maakt
`storage/app/private/livewire-tmp` voor tijdelijke uploads zelf aan, op 0700,
buiten elke geconfigureerde disk om. Die map stond op het moment van omzetten op
`drwx--S---` met `www-data` als eigenaar, en `leo` kon er niet in — in zijn
eigen projectmap.

De disks `bewijs` en `toetsen` hebben daarom expliciete `permissions` (0770
mappen, 0660 bestanden) in `config/filesystems.php`, want de local-driver maakt
"private" mappen standaard 0700. Die regels **blijven staan**, maar hun reden is
veranderd: ze repareren niets meer, ze zijn een vangnet voor het geval er ooit
weer een tweede account bij komt.

Wat de omzetting kostte, voor als het ooit teruggedraaid moet worden:

```bash
sudo chown -R leo:leo storage bootstrap/cache
sudo setfacl -R -b -k storage bootstrap/cache          # ACL's én default-ACL's
sudo find storage bootstrap/cache -type d -exec chmod g-s {} +
```

## Autorisatie

Eén generieke Gate-ability `heeft-niveau` (`AppServiceProvider`), gevoed door
rijen in `rol_permissies`. Rechten zijn dus configuratie, geen code.

**Vijf rollen:** CISO, Medewerker, Auditor, Management en Administrator. Twee
daarvan zijn niet vanzelfsprekend. **Management** (01c) krijgt nergens `muteren`
— vaststellen zonder te kunnen herschrijven is de hele reden dat de rol bestaat
— en heeft geen rij op `identity-access`. **Administrator** (01e) staat op
precies één blok (`installatiebeheer`) en dat is geen ISMS-blok; hij mag
toetsbestanden plaatsen en de export starten, en verder niets. Rollen zijn
cumulatief, met de Administrator als enige uitzondering: `App\Support\Rolregels`
verbiedt de combinatie, omdat één account anders een toetsbestand kan plaatsen
én het als ISMS-gebruiker kan openen.

```php
// Route
->middleware("can:heeft-niveau,'identity-access','lezen'")

// Binnen een Livewire-actie
abort_unless(Gate::allows('heeft-niveau', ['identity-access', 'muteren']), 403);
```

**De ladder loopt tot `muteren`** (`lezen` < `uitvoeren` < `muteren`): een hoger
niveau impliceert alle lagere. De rechtenmatrix geeft daarom per (rol, blok)
meestal één niveau — de CISO krijgt `muteren` en kan daarmee ook lezen. De Gate
matcht dus op "minimaal dit niveau", niet exact; de definitie in
`implementatie/01-identity-access.md` §5 toont nog een exacte match, wat de CISO
ten onrechte van elke leespagina zou weren.

**`exporteren` en `goedkeuren` staan bewust búiten die ladder** en impliceren
alleen `lezen` (`LOSSE_NIVEAUS` in `AppServiceProvider`). Het zijn geen "meer
dan muteren" maar andere soorten bevoegdheid: data naar buiten brengen, en
vaststellen. In de ladder zou de Auditor — de rol die per definitie
onafhankelijk moet zijn — als enige muteer- én goedkeurrechten krijgen, en zou
wie beleid opstelt het automatisch ook mogen vaststellen. `goedkeuren` verhuisde
op **29-07-2026** naar buiten de ladder, tegelijk met het weghalen van dat
niveau bij de CISO; zie implementatie/06 §8 en 01c.

### Keuzelijsten: de lege waarde moet een optie zijn

Een `<select>` kan niets tonen wat geen `<option>` is. Bindt een component aan
`public ?int $x = null`, dan komt die `null` met geen enkele optie overeen —
Flux' `placeholder` rendert `<option value="">`, en `null !== ''`. Na een
Livewire DOM-morph toont de browser dan de eerste échte optie terwijl de state
leeg blijft: het formulier lijkt ingevuld en faalt bij opslaan met "veld is
verplicht".

Twee regels, afgedwongen door **`<x-keuzelijst>`**:

- **Bind aan een string** (`public string $x = ''`), niet aan `?int`. Cast bij
  het opslaan. `required` blijft werken, want `''` faalt erop.
- **Verplicht vs. optioneel verschilt.** Bij een verplicht veld is Flux'
  placeholder juist goed: die optie is `disabled`, dus terug naar "niets" kan
  niet. Bij een optioneel veld moet de lege optie **selecteerbaar** zijn —
  anders is een eenmaal gekozen waarde nooit meer te wissen.

```blade
<x-keuzelijst wire:model="eigenaarId" label="Eigenaar" leeg="Kies een eigenaar"
    :opties="$gebruikers->pluck('naam', 'id')" required />

<x-keuzelijst wire:model="accountableId" label="Accountable"
    :opties="$gebruikers->pluck('naam', 'id')" leeg="— geen —" />
```

`label` en `description` gaan als expliciete props naar `flux:select`. Dat is
netter maar niet noodzakelijk: Flux zet het label sowieso óók als attribuut op
het `<select>`-element, of je nu de wrapper of een rauwe `flux:select` gebruikt.

**Alle vijftien keuzelijsten zijn omgezet.** Selects die alleen een filter zijn
(`filterStatus`, `filterBlok`) blijven een rauwe `flux:select` met een expliciete
`<flux:select.option value="">Alle…</flux:select.option>`: die bonden al aan een
string en hebben het probleem dus nooit gehad.

Te controleren met een render-test: een verplichte keuzelijst hoort
`<option value="" disabled …>` te hebben, een optionele `<option value="">`
zónder `disabled`.

### Layout op Volt-componenten

Elk Volt-component dat als volledige pagina wordt gerenderd heeft een
expliciete `#[Layout('components.layouts.app')]` (of `...auth`) nodig. De
default van deze Livewire-versie is `component_layout => 'layouts::app'`, met
een namespace die naar `resources/views/layouts/` wijst — maar de starter kit
zet zijn layouts in `resources/views/components/layouts/`. Zonder het attribuut
faalt de pagina met `No hint path defined for [layouts]`.

De **enkele quotes in de middleware zijn verplicht**. Zonder quotes vat
Laravel's `can`-middleware de argumenten op als routeparameters, vindt die niet,
en geeft `null` door aan de Gate — wat een TypeError (HTTP 500) oplevert in
plaats van een 403. De notatie in `implementatie/01-identity-access.md` §5 mist
deze quotes.

## Blok 2: Context & Scope

Vier schermen, bereikbaar via één zijbalk-item ("Context & Scope" → `/scope`)
met een sub-navigatiebalk ertussen:

- `/scope` — de versioneerbare scope-verklaring. Precies één versie is `actief`;
  een nieuwe versie activeren zet de vorige automatisch op `vervangen` (nooit
  verwijderen — versiehistorie is auditbewijs). De statusovergang loopt via de
  expliciete action `App\Actions\ActiveerScopeVerklaring`, niet via een
  model-observer, zodat het "vervangen"-effect zichtbaar blijft op de aanroep.
- `/organisatie-eenheden` — zelfverwijzende boom (afdeling/locatie/proces),
  recursief gerenderd zonder hiërarchie-package.
- `/issues` en `/belanghebbenden` (met geneste eisen).

Twee zaken zijn op databaseniveau afgedwongen in plaats van op schrijfdiscipline:

- **`uitsluitingen.motivatie` is NOT NULL** — §4.3 van de norm eist een motivatie
  per uitsluiting. Het formulier valideert dit vooraf zodat een lege motivatie
  een nette melding geeft in plaats van een 500.
- **De koppeltabel `scope_verklaring_organisatie_eenheid` heeft expliciete korte
  FK-namen** (`sv_oe_sv_fk`, `sv_oe_oe_fk`): de auto-gegenereerde naam
  overschrijdt de 64-tekengrens van MySQL.

Een verstreken `volgende_herziening_gepland` toont een inline waarschuwing op
`/scope`. Sinds blok 7 plant `ScopeVerklaringObserver` daar bovendien een taak
op, zodat het signaal niet meer alleen bij bezoek zichtbaar is.

De rechtenmatrix voor `context-scope` was al geseed in blok 1 — geen
seeder-wijziging nodig. De listbox- en accordion-componenten van Flux zijn
**Pro**-features en niet beschikbaar in de gratis Flux 2; daarom gebruikt de
scope-multiselect een `flux:checkbox.group` en zijn de belanghebbenden een
native `<details>`-uitklap.

## Blok 3: Asset & Classificatie

Eén zijbalk-item ("Assets") met een sub-navigatie naar `/assets` en
`/systemen`; de assetlijst linkt door naar een detailscherm `/assets/{asset}`.

- **Classificatie op drie dimensies** (C/I/B) staat als drie losse enum-kolommen
  op `assets`, niet als FK's naar `classificatieschemas` — alle dimensies delen
  dezelfde vier niveaus, dus een FK per dimensie zou een composite-check
  vereisen zonder meerwaarde. `classificatieschemas` blijft een naslagtabel
  (12 rijen, geseed; omschrijving/omgangsregels vult de CISO zelf in).
- **Statuslogica.** De overgang naar `actief` is afgeleid (puur een functie van
  de ingevulde velden) en zit daarom in een `AssetObserver` — anders dan de
  bewuste, multi-effect scope-activering in blok 2, die een expliciete action
  is. Afstoten wordt geweigerd zolang er een openstaande `asset_toewijzing` is
  (Annex A 5.11, return of assets) — een validatie in de Livewire-actie, geen
  DB-constraint.
- **Eigenaarschap ≠ systeemrecht.** `accountable_id`/`responsible_id` (RACI) zijn
  puur informatieve velden; ze geven geen mutatierechten zolang het model bij
  "CISO centraal" blijft.
- **Vooruitverwijzing.** `systemen.leverancier_id` is een nullable kolom zonder
  FK-constraint — die tabel kwam pas met blok 9 (Leveranciers), en daar heeft
  deze kolom zijn constraint gekregen (`nullOnDelete`).

## Blok 4: Risico & SoA

Eén zijbalk-item ("SoA & Risico's") met een sub-navigatie naar `/soa` en
`/risicos`; het risicoregister linkt door naar `/risicos/{risico}`.
Medewerkers hebben hier bewust géén toegang — er staat geen rij voor die rol
in de rechtenmatrix, en dat betekent "geen toegang".

- **Eén maatregelbestand per normprofiel** (plan 04f).
  `database/seeders/data/maatregelen-iso27001.json` (93),
  `maatregelen-nen7510.json` (101) en `maatregelen-bio2.json` (dezelfde 93 als
  ISO — de BIO laat Bijlage A ongemoeid); `MaatregelSeeder` kiest op het profiel
  en beslist verder niets. Daarnaast `overheidsmaatregelen-bio2.json` met de 118
  BIO-verplichtingen die een niveau lager hangen (plan 04h). Alle vier staan
  **wél in versiebeheer**: ze dragen
  referenties, thema's en titels — openbaar bekend — plus een vaste markering op
  elke plek waar normtekst zou kunnen staan. Het ISMS levert bewust geen eigen
  maatregelomschrijvingen; dat zou een interpretatie van de norm zijn op de plek
  waar een auditor de toepasselijkheid beoordeelt.
  `../scripts/genereer_maatregelen_seed.py --profiel=<profiel>` bouwt ze uit de
  normtekst en schrijft alleen bij het verwachte aantal. Zonder `--geen-tekst`
  bevat de uitvoer gelicentieerde tekst; die mag nooit gecommit worden. Er is
  geen hook meer die dat tegenhoudt — kijk zelf met `git diff` naar wat er in
  `database/seeders/data/` verandert. `builddistr.sh` bewaakt nog wél de
  uitlevering, dus normtekst reist niet mee in een tarbal.
  Een installatie die de norm bezit vult het bestand zelf aan en draait
  `php artisan isms:maatregelen`; dat controleert eerst en rapporteert erna.
  Gedeeltelijk invullen mag — de markering staat per regel.
- **"Onbeslist" is een eigen stand.** `soa_regels.van_toepassing` is nullable
  zónder default, zodat een onbeoordeelde maatregel (`null`) zichtbaar verschilt
  van een bewuste "niet van toepassing" (`false`). De SoA toont dat als een
  amberkleurige badge plus een teller bovenaan; de validatie gebruikt `present`
  in plaats van `required`, omdat een lege waarde hier geldig is. Een motivatie
  is verplicht zodra er wél een beslissing ligt — ook bij een "nee".
- **Risicoscore is afgeleid** (kans × impact) en zit daarom in een
  `RisicoObserver`, net als de statusafleiding bij Asset in blok 3.
- **Acceptatiedrempel.** Boven de `drempelwaarde_score` van de actieve
  `risicocriteria_versies`-rij (geseed op 15) kan een risico niet op
  "accepteren" zonder ingevuld `geaccepteerd_door` — een `ValidationException`
  in de Livewire-actie, getoond als formulierfout. Sinds 29-07-2026 vraagt
  `accepteerRestrisico()` bovendien `goedkeuren` en niet `muteren`: een
  restrisico boven de drempel accepteren is vaststellen, niet bewerken (§6.1.3).
  De CISO stelt het behandelplan op, de directie tekent voor wat overblijft.
  Hetzelfde geldt in blok 2 voor het activeren van een scope-versie.
- **De risicocriteria zijn een vastgestelde versie, geen instelling** (04g). De
  drempels, de risk appetite én de tien niveaudefinities van kans en impact
  zitten in één versie met de statusgang `concept → ter_goedkeuring → actief →
  vervangen`; de CISO stelt op, Management activeert. Elk beoordeeld risico
  draagt via `risicos.risicocriteria_versie_id` het kader waaronder het
  beoordeeld is, en `config/beoordelingsschaal.php` is sindsdien alleen nog
  seedbron.
- Sorteren op Annex A-referentie gebeurt **in PHP**, niet in SQL: `5.10` na
  `5.9` krijgen vergt `SUBSTRING_INDEX`/`CAST` (MySQL-specifiek), en de
  testsuite draait op sqlite. Bij 93 rijen is dat geen afweging waard.
- **Vooruitverwijzing, inmiddels ingevuld.** `risicos.gekoppeld_leverancier_id`
  begon als nullable kolom zonder FK — zelfde patroon als
  `systemen.leverancier_id` — en kreeg zijn constraint in blok 9. Een
  leveranciersrisico is dus een gewoon risico in dit register, niet iets aparts.

## Blok 6: Bewijsrepository & Audit Trail

Cross-cutting blok, gebouwd vóór blok 5 omdat beleidsversies naar
bewijsstukken verwijzen. Eén zijbalk-item ("Bewijs & audit trail") met
sub-navigatie naar `/bewijsstukken` en `/audit-log`.

- **Massa-updates omzeilen de audit trail.** `Model::where(...)->update([...])`
  gaat rechtstreeks naar de database en vuurt geen Eloquent-events, dus de
  `Auditeerbaar`-trait ziet er niets van. Hetzelfde geldt voor `->delete()` op
  een query builder. Gebruik daarom **`updateGeaudit()`** en **`deleteGeaudit()`**
  (Builder-macro's in `AppServiceProvider`), die de modellen langslopen. Bij de
  retrofit van blok 6 keken we alleen naar de modellen en niet naar wat eromheen
  schrijft; daardoor vielen drie bestaande overgangen stil buiten het logboek —
  de automatische accountdeactivering (Annex A 5.16), het vervangen van de
  actieve scope-versie, en de retourregistratie van een asset (A 5.11). Bij het
  auditeerbaar maken van `BewijsKoppeling` kwamen er nog twee bij aan de
  delete-kant: het ontkoppelen van bewijs en het verwijderen van een
  scope-uitsluiting. Alle vijf hersteld en met een regressietest afgedekt.
- **`BewijsKoppeling` wordt gelogd onder het blok van de *entiteit***, niet
  onder blok 6 — een auditor die op `risico-soa` filtert wil zien dat daar
  bewijs bij is gehangen. Dat volgt ook de autorisatieregel, want koppelen
  vereist muteerrecht op dat blok. Eén uitzondering die bewust zo blijft: bij
  het verwijderen van een bewijsstuk ruimt de FK-cascade de koppelingen in de
  database op, buiten Eloquent om. Die losse koppelingen komen dus niet in het
  logboek; het verwijderen van het bewijsstuk zelf wél, en dat is de
  gebeurtenis waar het om draait.
- **Logging via één trait, niet negen observers.** `App\Models\Concerns\Auditeerbaar`
  haakt de model-events aan en schrijft naar `audit_logregels`. De observers in
  blok 3 en 4 blijven bestaan — die leiden een veld af, dit logt.
  `auditUitgesloten()` is een **beveiligingscontrole, geen opmaak**: zonder de
  uitsluiting op `Gebruiker` belandt de wachtwoordhash leesbaar in een tabel die
  de Auditor mag inzien. Er is een test die daarop staat.
  `Loginpoging` is bewust *niet* auditeerbaar — dat model is zelf al een logboek.
- **Momentopnamen in de logregel.** `gebruiker_naam` en `entiteit_omschrijving`
  worden gedenormaliseerd meegeschreven, zodat een regel leesbaar blijft nadat
  het account is verwijderd of het risico hernoemd. De FK staat ernaast voor
  filteren.
- **Generieke koppeling via een morph-alias**, niet een classnaam: een FQCN in
  de database breekt bij een refactor en zegt een auditor niets. De map staat in
  `AppServiceProvider::registreerMorphMap()` en gebruikt `enforceMorphMap`, dus
  een nieuw blok wordt gedwongen een alias te kiezen.
- **`bestandshash` (sha256) bij upload** maakt "onveranderlijke opslag"
  verifieerbaar via `integriteitIsIntact()` in plaats van een belofte. `Bewijsstuk`
  is auditeerbaar en de hash wordt bewust méé gelogd: daarmee blijft achteraf
  aantoonbaar wélk bestand op dat moment is geüpload, ook als het bewijsstuk
  later is vervangen.
- **Opslag op de aparte disk `bewijs`** (`storage/app/private/bewijsstukken`),
  nooit via `public/storage` — die symlink zou elk bewijsstuk zonder enige check
  opvraagbaar maken op het web. Downloaden loopt via `DownloadBewijsstuk`, dat
  de Gate én de record-scope raadpleegt. Die disk heeft **expliciete
  `permissions`** (0770/0660) omdat de local-driver "private" mappen standaard
  als 0700 maakt. Dat was ooit een echte storing — 0700 zette de ACL-mask op
  `---` en daarmee vielen alle benoemde entries weg, zodat artisan en de tests
  niet meer bij de door php-fpm geüploade bewijsstukken konden. Sinds er per
  omgeving één schrijvend account is, zijn die regels een vangnet in plaats van
  een reparatie; zie "Schrijfrechten op storage/".
- **Record-scoping (`App\Support\Bewijstoegang`).** Medewerker heeft
  `uitvoeren` en dat impliceert `lezen` — met alleen een route-check zou hij
  ieders bewijsmateriaal en de volledige audit trail kunnen inzien. Volledige
  inzage vereist daarom `muteren` (CISO) of `exporteren` (Auditor). De generieke
  Gate blijft ongewijzigd; dit is een extra scope erbovenop. Sinds blok 7 zit
  dit in `App\Support\Recordscope`, gedeeld door beide blokken.
- **Herbruikbaar `BewijsPaneel`**, opgenomen in `RisicoDetail`, `AssetDetail` en
  de SoA-bewerkmodal. Koppelen vereist muteerrecht op het blok van de *entiteit*,
  niet alleen uploadrecht op blok 6.
- **Uploaden en koppelen zijn twee losse handelingen.** Het paneel heeft "Nieuw
  bewijsstuk" én "Bestaand koppelen"; `/bewijsstukken` heeft een koppelknop per
  rij. Eerst was koppelen alleen een bijproduct van uploaden, waardoor een stuk
  dat via `/bewijsstukken` was opgevoerd nergens meer aan te hangen was —
  terwijl dat scherm "ongekoppeld" wél als tekortkoming meldde. Daarmee was ook
  de many-to-many die `bewijs_koppelingen` juist mogelijk maakt (één
  pentestrapport onderbouwt meerdere assets) in de praktijk onbereikbaar.
  De keuzelijst met entiteiten (`App\Support\Koppelbaar`) is gefilterd op
  muteerrecht van het bronblok, anders lekt hij de titels van risico's aan wie
  blok 4 niet mag inzien.
- **`/audit-log` is als enige overzicht gepagineerd** — het is de enige tabel
  die onbeperkt groeit.

## Blok 7: Taken & Workflow

Eén zijbalk-item ("Taken") met sub-navigatie naar `/taken` en — alleen met
muteerrecht — `/taaksjablonen`.

- **Drie signalen die eerder alleen zichtbaar waren bij toeval** lopen nu via
  `App\Support\TaakPlanner`: scope-herziening (blok 2), risico-herbeoordeling
  (blok 4) en asset-retourcontrole (blok 3), plus SoA-herbeoordeling. De
  koppeling loopt één kant op: bronblokken roepen de planner aan, de engine kent
  de bronblokken niet.
- **Veldgestuurd via observers, tijdgestuurd via de cron.** Een wijzigende
  herzieningsdatum is een model-event (`ScopeVerklaringObserver`,
  `RisicoObserver`); een toewijzing die te lang openstaat is dat níet — daar
  verstrijkt alleen tijd, dus dat zit in `isms:genereer-taken`. Die scheidslijn
  is de reden dat `verlopen` óók geen observer is (zie hieronder).
- **`verlopen` is geen observer maar een geplande taak.** Het volgt uit het
  verstrijken van tijd, niet uit een gewijzigd veld, dus een observer zou nooit
  vuren. De UI gebruikt daarnaast `isFeitelijkVerlopen()`, zodat een
  stilstaande scheduler geen taak als "op tijd" toont waarvan de deadline
  gisteren lag.
- **Idempotentie op databaseniveau.** `unique(taaksjabloon_id, deadline)` maakt
  een dubbele run van de generator onschadelijk. Losse taken vallen daar bewust
  buiten: `taaksjabloon_id` is dan `NULL`, en NULL-waarden botsen niet in een
  unique index. Dat is gewenst, geen gat.
- **De Auditor krijgt `exporteren`** op dit blok. Zonder dat zou de record-scope
  alleen negatief te bepalen zijn ("heeft lezen maar niet uitvoeren"), en dat
  klapt stilzwijgend om zodra de rechtenmatrix verandert.
- **Escaleren zet niet door naar iemand anders** — er is geen rol boven de CISO.
  Niveau 1 (verstreken) en 2 (14 dagen erover) zijn zichtbaarheid; het dashboard
  toont daarom "Mijn openstaande taken". Sinds blok 14 gaat er bij de overgang
  naar niveau 2 ook een mail uit, via de dispatcher.
- **Een taak zonder eigenaar is een gap-signaal, geen neutrale stand.** Het
  takenoverzicht toont dan een amberkleurige badge en `/taaksjablonen`
  waarschuwt over sjablonen zonder standaard-eigenaar: die leveren taken op die
  bij niemand op het dashboard verschijnen. Bij een losse taak is de eigenaar
  daarom verplicht.
- **Bewerken kan, met één uitzondering.** De deadline van een door
  `TaakPlanner` beheerde taak (`soort` gevuld) is alleen-lezen — die komt uit
  het bronblok en zou bij de eerstvolgende observer-run toch worden
  overschreven. De handmatig gekozen eigenaar overleeft zo'n herplanning wél;
  de planner raakt alleen titel, deadline en status aan.
- **Eigen keuzes, niet uit de norm:** de herbeoordelingstermijn van 12 maanden,
  de reactietermijn van 14 dagen en de escalatiedrempel van 14 dagen staan als
  constanten in de commando's, met die kanttekening erbij.

## Blok 5: Beleid & Maatregelbeheer

Eén zijbalk-item ("Beleid") naar `/beleid`, met detailscherm `/beleid/{id}`.

- **De status hoort op de versie, niet op het document.** Het deelproduct zette
  de enum op het document, maar het statediagram beschrijft een versie ("nieuwe
  versie wordt actief"). Versie 3 in concept terwijl versie 2 actief is — de
  normale gang van zaken bij een herziening — is met één statusveld niet uit te
  drukken. `beleidsdocumenten.status` bestaat nog wel, maar als afgeleid veld
  dat `BeleidsversieObserver` onderhoudt; `ingetrokken` wint van alles.
- **`App\Support\Beleidspublicatie` is de enige plek die een versie op `actief`
  zet.** Zo wordt "hooguit één actieve versie per document" afgedwongen: MySQL
  kent geen partiële unique index. Publiceren vervangt de vorige versie en legt
  de goedkeuring vast, in één transactie — half uitgevoerd zijn er twee actieve
  versies of geen enkele.
- **Publiceren zonder documentbestand kan niet.** Er is dan niets om te lezen en
  de leesbevestiging wordt een lege handeling.
- **`goedgekeurd_door` is een FK, geen ingetypte naam.** Een string is niet aan
  een account te koppelen en dus geen auditbewijs. Wijkt af van
  `scope_verklaringen.goedgekeurd_door` uit blok 2; dat is bekende schuld en is
  hier bewust niet meegemigreerd.
- **Eerste blok dat `goedkeuren` echt gebruikt, en sinds 29-07-2026 met echte
  functiescheiding.** Alleen de publicatieknop checkt op `goedkeuren`, al het
  overige op `muteren`. Aanvankelijk had de CISO beide en deed hij ze allebei;
  nu stelt de CISO op (`muteren`) en stelt **Management** vast (`goedkeuren`) —
  een seeder-wijziging, geen codewijziging, precies zoals bedoeld. Dat kon pas
  toen `goedkeuren` uit de ladder ging: in de ladder gaf `muteren` het
  automatisch mee.
- **De bevestigingsplicht is per document** (`leesbevestiging_vereist`), met de
  default uit `type`: aan bij `beleid`, uit bij `procedure`. A.5.1 vraagt om
  erkenning door "relevant personeel", en ISO 27002 onderscheidt daarbij het
  organisatiebrede informatiebeveiligingsbeleid van onderwerpspecifieke
  beleidsregels. "Iedereen bevestigt alles" is dus niet de strengere variant
  maar de zwakkere: vijftig bevestigingen op een ontwikkelprocedure tonen niet
  aan dat de vier ontwikkelaars hem gelezen hebben. Het veld staat in de audit
  trail — het uitzetten van de plicht is precies wat een auditor wil zien.
  Een percentage van 0% en "n.v.t." zijn in de UI verschillende dingen.
- **De doelgroep is afdelingsgericht.** Staat de plicht aan, dan kiest de CISO de
  **afdelingen** (organisatie-eenheden van type `afdeling`) die moeten bevestigen,
  en de doelgroep is de actieve gebruikers ván die afdelingen — niet de hele
  organisatie. Een gebruiker hoort bij één afdeling
  (`gebruikers.organisatie_eenheid_id`, in te stellen bij het uitnodigen en te
  wijzigen op `/gebruikers`); wie geen afdeling heeft, valt buiten elke doelgroep.
  De doelgroep komt uit één bron (`Beleidsdocument::doelgroepGebruikerIds()`),
  waarmee de taakgeneratie, de bevestigingsgraad en de waarschuwingen rekenen.
  Krimpt de doelgroep, dan trekt `isms:genereer-taken` verweesde openstaande
  taken weer in. Validatie eist ≥1 afdeling zodra de plicht aan staat;
  organisatiebreed = alle afdelingen aanvinken.
- **Bevestigen is onherroepelijk** en hangt aan de versie, niet aan het
  document: een nieuwe actieve versie betekent dat iedereen opnieuw bevestigt.
  Dat volgt uit de FK, daar is geen code voor nodig.
- **De bevestigingsgraad kan dalen zonder dat er iets misgaat.** Teller en noemer
  worden beide op het moment van bevragen bepaald, dus een nieuwe medewerker
  verlaagt het percentage van elk lopend document. Dat is gewenst gedrag.
- **`App\Support\Beleidstoegang` lost een gat in blok 6 op.** `DownloadBewijsstuk`
  liet alleen door wie volledige inzage heeft of het bestand zelf uploadde — het
  beleidsbestand is door de CISO geüpload, dus een Medewerker kreeg 403 op
  precies het document dat hij moet lezen en bevestigen. Alleen `actief`:
  vervangen versies en concepten blijven achter de normale route. De
  afhankelijkheid loopt van 5 naar 6, niet andersom.
- **`isms:archiveer-bewijsstukken` slaat bestanden van actief beleid over.** De
  download zou blijven werken, maar "gearchiveerd" tonen op het scherm waar
  mensen de geldende versie moeten zien is misleidend.
- **De SoA-koppeling hangt aan het document, niet aan de versie.** Welk beleid
  A.5.1 onderbouwt verandert niet doordat er een nieuwe versie uitkomt. `/soa`
  heeft een kolom "Beleid" met het gap-signaal: van toepassing zonder actief
  beleid is een gat. Alleen actief beleid telt mee.
- **Bevestigen kan alleen op het detailscherm.** De knop in de lijst is een link
  daarheen; het overzichtscomponent heeft geen `bevestig()`-methode. Weglaten uit
  de Blade is niet genoeg — een Livewire-actie is aanroepbaar zodra ze op de
  klasse staat. Bevestigen vanuit een tabelregel legt vast dat iemand een regel
  heeft gezien, niet dat hij het beleid heeft gelezen.
- **Downloads worden geregistreerd in `raadplegingen`, niet in de audit trail.**
  De audit trail bevat wijzigingen; een raadpleging is een ander soort feit, met
  een hoger volume en een andere bewaartermijn. Registratie zit ná de
  autorisatiecontrole: een 403 is geen raadpleging. Append-only, met dezelfde
  guards als `AuditLogregel`.
- **RTF-, DOCX- en ODT-bewijsstukken hebben een HTML-preview**
  (`ToonBewijsstukPreview`, via `pandoc`, zie installatie). Het bronformaat komt
  uit `Bewijsstuk::PREVIEW_FORMATEN` (op de extensie, nooit in de `--from` van
  gebruikersinvoer). De preview deelt de leespoort met de download
  (`Bewijstoegang::magLezen`) en **telt óók als raadpleging** — wie previewt heeft
  het document gezien, dus dat ontkracht het "zonder download"-signaal net als een
  download. De geconverteerde HTML is gebruikersafkomstig en wordt gesaneerd
  (allowlist) én met een strikte CSP geserveerd.
- **Een download is een signaal, geen poort.** Dat iemand het bestand ophaalde is
  bewijsbaar; dat hij het las niet — met geen enkele techniek. Een verplichte
  download zou vals-negatieven opleveren (op papier of in een teamsessie gelezen)
  en is even triviaal te omzeilen als de bevestiging zelf. Daarom telt
  `BeleidsdocumentDetail` achteraf hoeveel bevestigingen zonder download zijn
  afgegeven, gemeten op de **eerste** raadpleging vóór het bevestigen: achteraf
  ophalen maakt de handtekening niet onderbouwd.
- **`raadplegingen` heeft een bewaartermijn van 60 dagen**, elke nacht om 02:30
  afgedwongen door `isms:schoon-raadplegingen`. Anders dan
  `isms:archiveer-bewijsstukken`, dat bewust niets verwijdert: bij een bewijsstuk
  is de inhoud het bewijs, bij een raadpleging is de registratie zélf het gegeven
  waarvan de bewaring verantwoord moet worden. Het opschonen loopt via
  `Raadpleging::verwijderOuderDan()` en dus **niet** via `deleteGeaudit()` — die
  macro zou het leesgedrag per rij naar de audit trail verplaatsen in plaats van
  het te verwijderen. De append-only guard op het model houdt een massa-delete
  niet tegen; die beschermt tegen sleutelen aan losse regels, niet tegen een
  beleidsmatige opschoning.
- **Openstaand:** de OR/AVG-afweging rond het vastleggen van leesgedrag
  (implementatie/05 §14). De termijn is er nu; het formele besluit erover niet.
- **Eigen keuze, niet uit de norm:** de leestermijn van 30 dagen na publicatie
  (`GenereerTaken::LEESTERMIJN_DAGEN`) en de bewaartermijn van 60 dagen
  (`SchoonRaadplegingen::BEWAARTERMIJN_DAGEN`).

## Blok 8: Incident- & Afwijkingenbeheer

Twee zijbalk-items: "Incidenten" (`/incidenten`, op `uitvoeren`) en
"Afwijkingen" (`/afwijkingen`, op `muteren`).

- **Nederlandse namen, conform de norm.** Het deelproduct spreekt van
  `NON_CONFORMITEIT` en `CAPA`; §10.2 van de Nederlandse norm spreekt van
  **afwijkingen** en **corrigerende maatregelen**, en de rest van deze codebase
  is ook Nederlands. Mapping staat in `implementatie/08` §2.
- **De afwijkingstatus is afgeleid, behalve `gesloten`.** Grondoorzaak →
  `analyse`, maatregel → `actie_lopend`. Sluiten is een managementbesluit waarin
  iemand vaststelt dát de afwijking weg is en daar zijn naam onder zet; dat mag
  geen bijproduct van een formulier zijn. Vandaar `App\Support\Afwijkingafsluiting`
  als enige ingang, naar het model van `Beleidspublicatie`.
- **Sluiten weigert met een reden, niet met een grijze knop.**
  `Afwijkingafsluiting::belemmering()` geeft de tekst die het scherm toont: geen
  maatregel, een onvoltooide maatregel, of een maatregel zonder effectieve toets.
- **Een toets met `niet_effectief` heropent de cyclus** — ook een al gesloten
  afwijking. Zonder die terugweg is een effectiviteitstoets een afvinkveld. Wie
  de afwijking eerder sloot blijft in de audit trail staan.
- **Alleen de laatste toets telt** (`latestOfMany` op `uitgevoerd_op`). Een
  maatregel die eerst niet en later wel effectief bleek, is effectief.
- **De incidentstatus is juist niet afgeleid**, maar wél bewaakt. `opgelost` gaat
  over het incident (het probleem is voorbij), `gesloten` over het dossier (er ligt
  een besluit of dit een corrigerende maatregel vergt). Drie regels op die
  overgang, in `Incident::belemmeringVoorSluiten()`: eerst `opgelost`, geen
  openstaande afwijking, en — als er géén afwijking is — een vastgelegde
  `geen_afwijking_reden`. Zonder die laatste eis glipt een incident langs de hele
  CAPA-cyclus zonder dat iemand de vraag ooit stelde, en juist die vraag is wat
  §10.1 verlangt.
- **Sluiten legt `gesloten_op` en `gesloten_door_id` vast.** Dat geeft de
  doorlooptijd uit `deelproducten/08` §6, die anders alleen uit `updated_at` te
  halen was — een veld dat bij elke willekeurige wijziging verschuift. Heropenen
  wist de afsluiting; de audit trail houdt vast wie sloot.
- **De effectiviteitstoets als taak is het belangrijkste signaal van dit blok.**
  Zonder taak blijft "we hebben een maatregel genomen" hangen en komt de toets er
  nooit — en juist die toets is wat §10.2 vraagt. Termijn:
  `CorrigerendeMaatregelObserver::TOETSTERMIJN_DAGEN` (30, eigen keuze; de norm
  noemt geen termijn).
- **Melden staat op `uitvoeren` en is record-scoped.** Iedereen moet kunnen
  melden; drempels daarop leveren minder meldingen op, niet minder incidenten. De
  melder ziet daarna alleen zijn eigen meldingen.
- **Eén mail bij een nieuwe melding**, `App\Mail\IncidentGemeld` naar iedere
  actieve CISO. Dit blok bouwde bewust géén `notificatieregels`-tabel — dat werd
  blok 14, en sindsdien loopt deze mail door `NotificatieDispatcher`. **De
  verzending mag de registratie nooit blokkeren**: er wordt gevangen en gelogd,
  want een onbereikbare mailserver mag geen melding kosten.
- **`audit_bevinding` in de enum is sinds blok 11 een echte stroom.** Een
  auditbevinding wordt daar opgevolgd als gewone `Afwijking` met die bron — een
  koppeling zonder schemawijziging, precies zoals voorzien.
- **Externe meldplicht (08b).** De termijnen staan in `config/meldplicht.php` en
  nergens anders: geen `match` op grondslag in de code, want termijnen veranderen
  bij wet en dan wil je één bestand aanpassen en de diff kunnen lezen.
  Geverifieerd op 04-08-2026 tegen de AVG en de Cyberbeveiligingswet.
  - **Het ankerpunt verschilt per fase**, en dat is de makkelijkste fout om te
    maken: de vroegtijdige waarschuwing en de melding rekenen vanaf
    **kennisname** (`incidenten.kennisname_op`, niet `gemeld_op`), het
    eindverslag vanaf de melding, en bij een voortdurend incident vanaf de
    afhandeling. Er bestaat geen formule die over alle rijen klopt.
  - **Cbw is gefaseerd** (24 uur, 72 uur, één maand), de AVG kent één getal (72
    uur) en art. 34 zegt alleen "onverwijld" — dus daar hoort géén datum.
  - **Cbw-plicht is een installatie-instelling** (`ISMS_CBW_PLICHTIG`), geen
    vraag per incident: of je eronder valt hangt af van sector en omvang en is
    een juridisch oordeel dat de organisatie één keer maakt.
  - **`uiterlijk_op` is een opgeslagen besluit, geen berekening.** Het aanmaken
    is idempotent, maar werkt bestaande rijen niet bij — een datum die meebeweegt
    met een gecorrigeerd ankerpunt is geen vastlegging. Overschrijven mag,
    want er zijn regimes met kortere termijnen (DORA, netcode elektriciteit).

## Blok 9: Leveranciers & Derdenrisico

Eén zijbalk-item ("Leveranciers") naar `/leveranciers`, met detailscherm
`/leveranciers/{leverancier}`.

- **Eén risicomotor, geen tweede.** `leveranciers.risiconiveau` (laag/midden/
  hoog) is een grof registerlabel om op te filteren en te rapporteren. Alles wat
  een échte beoordeling verdient wordt een gewoon `Risico` uit blok 4 met
  `gekoppeld_leverancier_id` gezet — dan telt het mee in de SoA en de
  risicorapportage in plaats van in een tweede matrix die uiteen gaat lopen.
- **De twee vooruitverwijzingen zijn hier ingevuld.** `systemen.leverancier_id`
  en `risicos.gekoppeld_leverancier_id` kregen hun FK, met `nullOnDelete` en niet
  cascade: een leverancier verwijderen mag nooit een systeem of risico wissen.
- **`kandidaat → actief` volgt uit de eerste beoordeling** — geen los knopje dat
  je kunt vergeten, net als bij een asset die pas bij uitreiking "in gebruik"
  komt. **`actief → beeindigd`** is functioneel dezelfde controle als de
  asset-retour en het sluiten van een incident: het mag alleen als teruggave van
  data en toegang bevestigd is (`belemmeringVoorBeeindigen()`).
- **Beëindigd is read-only.** Basisgegevens, diensten, clausules en beoordelingen
  zijn dan niet meer te muteren; de enige uitweg is `heractiveren()`, dat ook de
  teruggavebevestiging wist — die gold de beëindiging.
- **De herbeoordelingstaak is cross-entity:** de datum staat op de *beoordeling*,
  de taak hoort bij de *leverancier*. Let bij zo'n constructie op de stale
  relatie — de bovenliggende leverancier moet vers geladen worden, anders werkt
  een tweede beoordeling in dezelfde transactie op een verouderde cache.
- **Contractclausules zijn een vaste enum**, geen vrij contractmodel: dit blok
  houdt alleen de securityrelevante clausules bij (A 5.19–5.23, plus
  `verwerkersovereenkomst` voor AVG art. 28), niet het contract zelf. Volledig
  contractbeheer blijft bij inkoop en juridisch.
- **Twee gap-signalen** als callout op het register: verstreken beoordeling, en
  `risiconiveau = hoog` zonder recht-op-auditclausule én zonder geldig eigen
  certificaat. Een geldig ISO 27001-certificaat is de tweede manier om "recht op
  audit" aan te tonen.

## Blok 10: Bewustzijn, Training & Toetsen

`/mijn-trainingen` staat op `uitvoeren` (de Medewerker registreert eigen
voltooiing). `/trainingen`, `/doelgroepen` en `/toetsen/resultaten` staan op
`lezen` maar sluiten de Medewerker in het component uit — `uitvoeren` impliceert
`lezen`, dus de route-check alleen zou hem binnenlaten. Uitzetten en de bouwhulp
vragen `muteren`.

- **Alleen de daad wordt vastgelegd, de rest is afgeleid.** Geen
  voltooiingsregistratie per doelgroeplid met een status-enum: dat vergt fan-out
  bij elke wijziging in lidmaatschap, en een opgeslagen `verlopen` liegt tot de
  cron langskomt. Alleen de onherroepelijke voltooiing wordt opgeslagen;
  "te doen", "verlopen" en de trainingsgraad komen op afroep uit doelgroep +
  geldigheidsduur. Hetzelfde patroon als de leesbevestiging in blok 5.
- **Een eigen `Doelgroep`, los van de afdeling-doelgroep van blok 5.** Awareness-
  groepen ("IT-beheerders", "nieuwe medewerkers") lopen dwars door afdelingen
  heen, dus hier expliciet lidmaatschap. Twee verschillende begrippen die naast
  elkaar blijven bestaan.
- **Een geslaagde toets is de machinale variant van een voltooiing.** Eén feit
  met twee bronnen (`zelfregistratie` of `toets`), geen tweede tabel. Heeft een
  module een toets, dan weigert zelfregistratie — anders is de toets vrijblijvend.
- **De toets-callback is het enige punt waar het ISMS gegevens aanneemt van een
  niet-ingelogde bron.** `POST /toetsen/callback/{token}`, met throttling en een
  CSRF-uitzondering. De `callback`-parameter bevat **alleen de token**, nooit een
  volledige URL: de toets stelt de terugmeld-URL zelf relatief samen, zodat er
  geen open doorgeefluik voor phishing ontstaat. Een onbekende token geeft 404
  zonder onderscheid tussen "bestaat niet" en "ingetrokken"; een al afgeronde
  opdracht geeft 200 zonder wijziging.
- **Client-oordeel, geen tweede waarheid.** De toets stuurt `passed` mee volgens
  zijn eigen zakgrens; het ISMS bewaart daarnaast `score`/`total`, zodat later
  met een andere drempel gerapporteerd kan worden zonder de toetsen aan te raken.
- **De actor in de audit trail wordt expliciet gezet.** De callback is niet
  ingelogd, maar de deelnemer moet als actor gelogd worden en niet de
  "Systeem"-terugval van console-commando's.
- **Toetsbestanden staan sinds 01e in `storage/app/private/toetsen`**, niet meer
  in `public/`. Een toets is door een mens geleverde HTML mét JavaScript, en op
  de origin van het ISMS draait die in de sessie van wie hem opent — dus gaat het
  uitserveren via een route die er een CSP-sandbox omheen zet. Plaatsen doet de
  **Administrator** op `/beheer/toetsen`, niet de CISO.
- **De koppeling module ↔ toets bestaat op één plek**
  (`trainingsmodules.toets_bestand`). Bij het uitzetten kies je daarom een
  *module* (bestand en koppeling volgen daaruit) of expliciet een *losse toets*
  zonder module — nooit allebei los, want dat zijn twee bronnen die kunnen
  divergeren.
- **De toetstaak krijgt bewust géén `soort`.** Een `soort` maakt de deadline
  systeembeheerd, en dan kan de CISO hem na een ziekmelding niet meer verzetten.
  Het toets-karakter loopt via de `Toetsopdracht`-relatie.
- **Restgat als signaal:** een module met een toets die nooit is uitgezet, is
  niet af te ronden. Geen harde blokkade, wel een melding op het
  trainingenoverzicht.

## Blok 11: Auditmanagement

Eén zijbalk-item ("Audits") op `lezen` — zodat ook een tijdelijke Auditor het
ziet — naar `/audits`, `/audits/rondes/{auditronde}`, `/audits/programma` en
`/audits/dekking`.

- **Onafhankelijkheid is een record-guard, geen extra rol.** De blok-Gate is te
  grof, dus de regel leeft in het model: `magBevindingBewerkenDoor()` staat bij
  een interne ronde alleen de tóégewezen auditor toe, en alleen zolang de ronde
  `in_uitvoering` is. Bij een externe ronde transcribeert de CISO een reeds
  geautoriseerd rapport, dus daar mag hij het.
- **`afgerond` bevriest de bevindingen voor iedereen, ook voor de CISO.** Dat is
  wat onafhankelijkheid afdwingt in plaats van documenteert. Geen heractivering —
  anders is de onveranderlijkheid boterzacht.
- **Inhoud en opvolging zijn gescheiden rechten.** De inhoud bevriest;
  `magBevindingOpvolgen()` (non-conformiteit starten, sluiten) mag wél na
  `afgerond`, want dat is opvolging en geen herschrijven van het oordeel.
- **Geen tweede CAPA-model.** Een bevinding wordt opgevolgd als gewone
  `Afwijking` uit blok 8 met `bron = audit_bevinding` — de enum-waarde die daar
  al klaarstond. Een major/minor is pas te sluiten als die afwijking gesloten is.
- **Het auditprogramma is een eigen entiteit boven het jaarplan** (11b), niet
  afgeleid uit een reeks jaarplannen. Frequentie is risicogebaseerd per
  auditobject en niet één globale driejaarsknop, want §9.2.2 wil frequentie naar
  belang van het proces en eerdere resultaten. De audit-universe *verwijst* naar
  bestaande `maatregelen`; er wordt geen normtekst gekopieerd.
- **Het programmajaar is de eenheid, niet het kalenderjaar** (11c). Een cyclus
  die op 14 mei begint heeft programmajaren die op 14 mei beginnen; met een
  `jaar`-kolom viel elke ronde in de verkeerde kolom van de dekkingsmatrix.
- **Uitgevoerd ≠ dekkend.** Een nulmeting dekt per definitie alles in één keer;
  registreer je die als programma-ronde, dan kleurt de matrix in jaar 1 volledig
  groen en lijken jaar 2 en 3 overbodig — het omgekeerde van wat §9.2.2 wil
  tonen. De vlag houdt zo'n ronde uit de matrix, nooit uit het dossier: hij blijft
  volwaardige input voor §9.2.2, §9.3 en §10.2.

## Blok 12: Metrics, KPI & Rapportage

`/meetaanpak` (de catalogus met de vastgelegde metingen), vijf panelen op
`/dashboard` naast de takenlijst die er al stond — kerncijfers, signalen,
PDCA-trend, risico's en maatregelen, aantallen — en `/schermkopieen` (het
register van wat de deur uit ging).

- **Het model registreert toestand, geen beweging.** `soa_regels` en `risicos`
  worden overschreven; of PDCA draait is een vraag over het verschil tussen twee
  momenten. De audit trail is bewijs van *verandering*, geen *meting* — bruikbaar
  als onderbouwing achteraf, ongeschikt als meetinstrument.
- **Teller en noemer, nooit het percentage.** "68% geïmplementeerd" is niet uit
  te leggen en niet te reconstrueren; "61 van 90" wel — en dan valt ook op dat de
  noemer vorig jaar 84 was doordat de toepasselijkheid zelf verschoof.
- **Een definitieversie op elke meetrij.** Verandert in jaar twee hoe
  "achterstallig" wordt berekend, dan is de vergelijking met jaar één
  betekenisloos. Met de versie ín de rij is die breuk zichtbaar in plaats van
  verstopt. Bij een **handmatige** KPI hoogt niets automatisch op (12f), want er
  is geen `meetbron` die kan wijzigen.
- **Metingen zijn onveranderlijk.** Een fout meetpunt wordt gecorrigeerd met een
  nieuw meetpunt, niet met een herberekening. Dat is ook waarom de
  bevestigingsgraad in blok 5 bewust een *live* cijfer is: goed voor een
  dashboard, ongeschikt voor een audit.
- **Maandelijks, niet dagelijks.** Dagelijks is ruis voor cijfers die in maanden
  bewegen; jaarlijks is te grof om op bij te sturen.
- **De nadruk ligt op Check en Act.** Plan en Do zijn makkelijk te meten en
  zeggen bijna niets. De sterkste rij is "review-taken op tijd afgerond, en de
  gemiddelde overschrijding in dagen": blok 7 registreert `deadline` én
  `voltooid_op`, dus dat cijfer is gratis, moeilijk te masseren, en meet gedrag
  in plaats van intentie. 23 KPI-definities staan geseed.
- **Twee valkuilen staan in de rapportage zelf, niet alleen in de documentatie.**
  Een dalende risicoscore is te sturen — daarom is *score-daling zonder gekoppeld
  bewijs in dezelfde periode* zelf een meting. En monotone verbetering is
  verdacht: een register waarin nooit een risico omhooggaat is een register waar
  niemand naar kijkt. Een dashboard met alleen groene pijlen omhoog nodigt uit
  tot precies het gedrag dat het zou moeten signaleren.
- **De backfill van jaar één is ingetrokken (02-08-2026).** Bij het nameten bleek
  reconstructie voor een deel van de KPI's niet onbetrouwbaar maar principieel
  onmogelijk: `Auditeerbaar` hangt aan modelgebeurtenissen, en een `sync()` raakt
  de attributen niet — dus staan veel-op-veel-koppelingen er helemaal niet in
  (gemeten: `beleidsdocument_soa_regel` 39 rijen, 0 trailregels). Een reeks
  waarin sommige KPI's echt zijn en andere indicatief, zonder dat de lezer het
  verschil ziet, is erger dan geen reeks.
- **"Kopie voor de auditor" is een knop per scherm, geen vooraf samengesteld
  pakket** (12h). Een externe auditor zit vóór het scherm en vraagt *"mag ik hier
  een kopie van?"* — het moment is "nu, hiervan", en de scope is dit scherm zoals
  het erbij staat. Dat maakt de clausule→register-mapping overbodig, houdt het
  samenstellen buiten de code, en voorkomt een tweede complete kopie van al het
  vertrouwelijke materiaal. Wat eruit gaat wordt vastgelegd in `schermkopieen`,
  met dezelfde append-only guard als de audit trail.
- De **readiness-score** uit het deelproduct is definitief ingetrokken (12d §3).

## Blok 13: Management Review & Verbetercyclus

Eén zijbalk-item ("Management review") naar `/management-review`, met detail
`/management-review/{reviewsessie}`.

- **§9.3-volledigheid is een harde overgangsvoorwaarde, geen
  dashboard-hoop.** `Reviewsessie::belemmeringVoorHouden()` vergelijkt de
  aanwezige agendapunten met de negen verplichte §9.3-inputs; ontbreekt er één,
  dan is `gepland → gehouden` een validatiefout met de ontbrekende onderwerpen —
  geen 403, want het is een volgorde- en geen rechtenkwestie.
- **"Niets te melden" is geldig, maar moet expliciet.** Een agendapunt in die
  categorie met de samenvatting "geen wijzigingen" legt de bewuste afweging vast;
  stilzwijgend overslaan doet dat niet.
- **De verbeteractie is een eigen entiteit**, geen `Taak` en niet het CAPA-model
  van blok 8: een verbeteractie hoeft niet corrigerend op een afwijking te zijn.
  De deadline-bewaking loopt wél via de taken-engine, `perEigenaar`. Bij
  `voltooid` wordt de taak gesloten en niet verlopen — de handeling is verricht.
- **Terugkoppeling is informatief, geen auto-mutatie.** Een besluit tot bijstellen
  van scope of risicocriteria wordt hier vastgelegd, maar dit blok muteert blok 2
  of 4 niet: één bron van waarheid per veld, geen verborgen cross-block-afleiding.
- **Deelnemers zijn vrije tekst.** Geen aparte managementrol-koppeling, consistent
  met blok 2 en 11. Management heeft op dit blok `goedkeuren`.

## Blok 14: Notificatie & Integratielaag

Eén zijbalk-item ("Notificaties & integraties") met sub-navigatie naar
`/notificaties` en `/integraties`.

- **Eén centraal verzendpunt.** `App\Support\NotificatieDispatcher::verzend()`
  bepaalt de ontvangers uit de actieve `notificatieregels` (op rol, of de
  meegegeven contextontvangers) en logt per ontvanger een `Notificatie` met
  resultaat. Geen actieve regel betekent: niets doen — de gebeurtenis is dan
  bewust niet geconfigureerd.
- **De Mailable komt van de aanroeper**, want die heeft de context; de dispatcher
  bepaalt alleen wie het krijgt en hoe het afliep. Zo blijft de laag onafhankelijk
  van wat er in de mail staat.
- **De uitzondering wordt gevangen en niet doorgegooid** — dezelfde afweging als
  bij `Incidentmelding` in blok 8: een onbereikbare mailserver mag de primaire
  handeling (incident registreren, taak escaleren) niet blokkeren. De mislukking
  is terug te zien in de log en de gezondheidsweergave, niet als een crash.
- **`incident_gemeld` is herbedraad**: die zocht eerst zelf de CISO's op. Een
  geseede actieve regel behoudt het oude gedrag; zet de CISO de regel uit, dan is
  dat een bewuste keuze en zichtbaar in het register.
- **`taak_geescaleerd` vuurt op de overgang naar niveau 2**, de betekenisvolle
  escalatie. Omdat het niveau maar éénmaal van 1 naar 2 gaat, mailt de dagelijkse
  sweep niet opnieuw — geen extra idempotentie nodig.
- **`training_verloopt` mailt alleen op het moment dat er echt een nieuwe
  herinneringstaak wordt aangemaakt**, niet elke dag. Zo valt de mailcyclus samen
  met de al idempotente taakplanning.
- **Het integratieregister synchroniseert zelf niets.** Er zijn geen endpoints,
  geen credentials, geen API-calls: het is een administratie van koppelingen. Het
  woord "synchronisatie" slaat hier op het vastléggen van een sync-resultaat dat
  buiten dit systeem tot stand kwam. Het scherm zegt dat ook met een vaste
  info-callout, zodat niemand denkt dat het knopje iets ophaalt. Een echte adapter
  kan later inschuiven zonder schemawijziging — die schrijft in dezelfde twee
  tabellen.

## Blok 15: Wijzigingsbeheer

Eén zijbalk-item ("Wijzigingen") naar `/wijzigingen`, met dossier
`/wijzigingen/{wijziging}` en — met muteerrecht — `/wijzigingssjablonen`.

Dit blok leunt op **stappenreeksen** (07b): een uitbreiding van de taken-engine
met volgorde, uitkomst en een `wachtend`-toestand. Die laag is bewust in blok 7
gebouwd en niet in dit blok, anders zit hij vast aan één dossiersoort.

- **Zes statussen in plaats van acht.** `goedgekeurd` en `gepland` vervallen als
  dossierstatus: de goedkeuringsstap heeft al een uitkomst en de planning is
  `gepland_op`. Ze óók in de status opslaan levert twee bronnen van waarheid op —
  dezelfde fout die blok 10 vermeed door de trainingsstatus af te leiden.
- **Spoed vraagt geen enkele uitzondering in de code.** "Uitvoeren mag vóór
  goedkeuring" is gewoon een sjabloon waarin `uitvoeren` op volgorde 1 staat en
  `goedkeuring` op 2. Geen vlag, geen aftakking — een derde route. Dat is de
  grootste opbrengst van de reekslaag.
- **De engine vraagt het dossier om toestemming.** De eis "terugvalplan gevuld
  vóór een `uitvoeren`-stap" (A.8.32 f) alleen op het dossierscherm zetten werkt
  niet: dezelfde stap is ook af te vinken op `/taken`, en dat scherm kent geen
  wijzigingen. Een controle die je langs één van twee knoppen kunt lopen is geen
  controle. Daarom de interface `Stapbelemmering`, bevraagd in `TaakObserver`
  op `updating` — na afloop tegenhouden kan niet meer. De eenrichtingskoppeling
  blijft intact: de engine kent geen wijzigingen, hij kent een interface.
- **Een verschoven planning verzet de deadlines, behalve de voltooide.** Die zijn
  historie, en de vertraging die eruit volgt is meetdata. Dit is een expliciete
  aanroep en géén observer: een observer zou ook vuren bij een tikfout in de
  titel en moet dan alsnog uitzoeken of `gepland_op` veranderde.
- **Bewust geen record-scoping**, anders dan bij taken en bewijs. A.8.32 c) eist
  dat belanghebbenden geïnformeerd worden, en een wijzigingskalender die alleen de
  CISO ziet werkt daartegenin. Een Medewerker ziet dus alle dossiers maar handelt
  alleen op zijn eigen stappen — die scoping doet `Taak::scopeZichtbaar()` al.
- **Vier meegeleverde routes**, waaronder afvoer van een systeem of dienst, met
  het zwaartepunt ná de buitengebruikstelling: daar blijft het meeste liggen —
  toegang die blijft bestaan (A.5.18), gegevens die bij de leverancier
  achterblijven (A.8.10, A.5.22) en registers die niet worden bijgewerkt.
- **Blok 3 kent blok 15 niet**, dus een systeem afvoeren op `/systemen` maakt geen
  dossier aan. Van niets-doen, signaleren en afdwingen is het de middelste
  geworden: een callout op het register, over twaalf maanden terug, en alleen voor
  systemen mét `afgevoerd_op` — een teller die nooit op nul kan komen wordt
  genegeerd. Afdwingen zou blok 3 afhankelijk maken van blok 15 en bestaande
  installaties breken.
- **Sjablonen zijn zelf te beheren**, inclusief aanmaken — zonder die knop was de
  belofte "wie een variant nodig heeft maakt een tweede sjabloon" leeg. Twee
  verwijdergrenzen: een gebruikt sjabloon gaat niet weg (het dossier zou niet meer
  tonen welke route het volgde), en een stap in een lopend dossier ook niet —
  zonder sjabloonstap geeft `belemmeringVoorStap()` gewoon `null` terug en
  verdwijnt de terugvalplancontrole zonder enige foutmelding.

## Cross-cutting voorzieningen

Geen ISMS-blokken, wel deel van de applicatie.

- **Normprofiel** (00h). ISO 27001, NEN 7510 of BIO2 — geen forks, dus
  een profielschakelaar. De labellaag loopt via een gedeelde `$norm`-variabele
  (`Normlabels`, via `View::share`) en niet via een Blade-directive: die
  compileert niet binnen component-attributen, want `label="…"` van een
  Flux-component is een stringliteral en geen Blade-tekst.
- **Kennisbank** (`/kennisbank`, 00g/00i). Gecureerde uitleg, bewust zónder
  blok-permissie: naslag voor elke ingelogde gebruiker. Het artikel als bestand
  meenemen mag alleen de CISO — lezen is naslag, downloaden is meenemen.
  Artikelen bestaan per normprofiel waar dat uitmaakt.
- **Export** (`isms:exporteer` en `/beheer/export`, 00c–00e). Het ISMS als
  mens-leesbare Markdown-mapstructuur, voor overname in een ander ISMS.
  Uitleveren is een handeling aan de installatie en geen recht op de inhoud: de
  **Administrator** start de export, maar leest hem niet.
- **Schedulerhartslag** (00m). Staat de machine uit, dan draaien de geplande
  commando's niet en haalt Laravel niets in. Zonder spoor daarvan ziet een ISMS
  dat zes weken stillag er daarna precies zo uit als een ISMS dat doordraaide —
  vandaar de hartslag. `isms:controleer-hartslag` leidt eruit af welke momenten
  gemist zijn en maakt van de onherstelbare gaten een taak. Niet
  Docker-specifiek: een bare-metal server die een weekend uit stond heeft
  hetzelfde gat.
- **Installatiebeheer** (01e). De Administrator-rol met `/beheer/toetsen` en
  `/beheer/export`, en verder niets — zie de sectie Autorisatie.
- **Docker** (00l, 00n, 00p). De uitleveringsroute, in `docker/ezisms/`.

## Geplande taken

De planning staat in `routes/console.php`, niet in de crontab:

| Tijd | Commando | Doet |
|---|---|---|
| 01:00 | `isms:verval-gebruikersaccounts` | accounts met bereikte `vervalt_op` → `gedeactiveerd` (blok 1) |
| 01:15 | `isms:herinner-tweefactor` | herinnering aan een openstaande tweede factor (blok 1, plan 01d) |
| 01:30 | `isms:archiveer-bewijsstukken` | bewaartermijn verstreken → `gearchiveerd`; verwijdert niets (blok 6) |
| 01:45 | `isms:controleer-audittrail --stil` | de keten-hashes van de audit trail nalopen (plan 06c) |
| 02:00 | `isms:genereer-taken` | terugkerende taken uit sjablonen, plus achterstallige retouren, SoA-beoordelingen, leesbevestigingen en trainingsherinneringen (blok 3, 4, 5, 7, 10) |
| 02:15 | `isms:verloop-taken` | deadline verstreken → `verlopen`, en escalatie naar niveau 2 (blok 7) |
| 02:30 | `isms:schoon-raadplegingen` | bewaartermijn op de registratie van bewijs-downloads; verwijdert wél (blok 5) |
| 02:45 | `isms:controleer-hartslag --stil` | gemiste geplande momenten opsporen (plan 00m) |
| 1e v/d maand 03:00 | `isms:meet-kpis` | de maandelijkse KPI-meting, onveranderlijk (blok 12) |
| 31 dec 23:00 | `isms:leg-restrisico-vast` | de jaarlijkse restrisico-snapshot per control (plan 04c) |

De volgorde is niet willekeurig, op drie plekken:

- `genereer-taken` draait vóór `verloop-taken`, zodat een taak die vannacht wordt
  aangemaakt met een deadline in het verleden dezelfde nacht nog als verlopen
  wordt gemarkeerd in plaats van een dag lang ten onrechte "open" te heten.
- `herinner-tweefactor` staat vlák ná `verval-gebruikersaccounts`: wie vannacht
  is gedeactiveerd hoeft geen herinnering meer te krijgen.
- `controleer-audittrail` staat vóór de opruimtaken en `controleer-hartslag` als
  laatste van de nacht. Zo gaat elke controle over een afgesloten nacht en niet
  half over de lopende. De hartslagcontrole vangt "de scheduler draaide, maar één
  commando faalde"; het geval "de machine was helemaal weg" wordt bij het
  opstarten gevangen, in `deploy.sh` en `deploy-docker.sh`.

Alle tien zijn idempotent — twee keer draaien levert geen dubbele taken en geen
dubbele metingen op — dus een gemiste nacht inhalen kan gewoon met de hand. De
volledige commandolijst met uitleg staat in de kennisbank onder *Beheer*.

### Crontab

Eén regel, die elke minuut de scheduler wakker maakt:

```
* * * * * cd /home/leo/claude/isms/ota && /usr/bin/php8.4 artisan schedule:run 2>&1 | grep -vE '^[[:space:]]*$|No scheduled commands are ready to run' >> /home/leo/claude/isms/ota/storage/logs/scheduler.log
```

Vier keuzes daarin, elk met een reden:

- **Eén regel, geen regel per commando.** Anders staat de planning op twee
  plekken en lopen `routes/console.php` en de crontab uit elkaar.
- **`/usr/bin/php8.4` en niet `php`.** `/usr/bin/php` is een
  alternatives-symlink en op deze machine staan ook 8.3 en 8.5. Een
  systeemwijziging zou de scheduler ongemerkt naar een andere PHP-versie
  verplaatsen — om 02:00, zonder dat iemand kijkt.
- **Naar een logbestand, niet naar `/dev/null`.** Een mislukte taakgeneratie mag
  niet onzichtbaar zijn. Dit is een ISMS: "de herinnering is nooit verstuurd en
  niemand wist het" is precies de bevinding die je wilt voorkomen.
- **De `grep` filtert alleen het "niets te doen"-bericht.** `schedule:run` drukt
  dat elke minuut af, ook wanneer er niets gepland staat: zonder filter groeit
  het log met 1440 regels per dag en verdrinkt de ene regel die ertoe doet.
  Gebruik hiervoor géén `-q` — dat onderdrukt ook de foutmeldingen.

Controleren of het loopt:

```bash
crontab -l                                  # staat de regel er?
tail -f ota/storage/logs/scheduler.log      # wat deed hij vannacht?
php artisan schedule:list                   # wat staat er gepland, en wanneer
php artisan schedule:test                   # één commando handmatig kiezen en draaien
```

**Openstaand:** `scheduler.log` en `laravel.log` roteren niet
(`LOG_STACK=single`). De scheduler zelf schrijft met het filter hierboven een
handvol regels per nacht en loopt dus niet vol, maar `laravel.log` inmiddels wél:
die staat op **2,2 MB** (was 130 kB toen dit punt werd opgeschreven). Bij het
inrichten van productie hoort logrotate erbij. Voor de container is dit
afzonderlijk geregeld — zie `implementatie/00p-docker-logging.md`.

## Openstaande punten

- **Client-IP achter HAProxy.** TrustProxies is niet geconfigureerd, dus
  `loginpogingen.ip_adres` bevat het IP van de HAProxy-server, niet dat van de
  gebruiker. Niet spoofbaar, maar daardoor onbruikbaar als bewijs bij Annex A
  5.15-5.18. Op te lossen door in `bootstrap/app.php` `trustProxies()` op het
  HAProxy-IP/subnet te zetten. Trust `*` kan hier niet zonder risico, omdat
  nginx op `0.0.0.0:80` luistert en dus ook buiten HAProxy om bereikbaar is.
- **`APP_ENV=local` en `APP_DEBUG=true`** staan aan in `ota/.env`, wat klopt
  voor deze ontwikkelomgeving. De productie-`.env` is een apart bestand op de
  doelmachine en hoort `production` / `false` te hebben.
- **Functiescheiding bij `goedkeuren` is per 29-07-2026 ingericht, maar de
  organisatie moet hem wel invullen.** De CISO heeft `goedkeuren` nergens meer;
  Management vult het in op `context-scope`, `risico-soa`,
  `beleid-maatregelbeheer` en `management-review-verbetercyclus`, en heeft
  nergens `muteren`. Wat het systeem niet kan afdwingen is dát er een tweede
  persoon is: zolang niemand de Management-rol heeft, is er ook niemand die kan
  vaststellen. Rollen zijn cumulatief (`Rolregels`), dus één persoon met twee
  petten blijft mogelijk — zichtbaar gemaakt, niet verboden.
- **Append-only is nog niet op databaseniveau afgedwongen.** De guards op
  `AuditLogregel` zijn een vangnet tegen programmeerfouten, geen
  beveiligingscontrole: wie databasetoegang heeft omzeilt ze met één UPDATE. De
  echte controle is een grant — een applicatiegebruiker met `INSERT, SELECT` op
  `audit_logregels` en geen `UPDATE, DELETE`, met migraties onder een apart
  account dat wél `CREATE`/`DROP` mag. Zolang dat niet is ingericht, is de audit
  trail voor een auditor weerlegbaar.

  De keten-hashing uit `implementatie/06c` maakt zo'n wijziging wél
  **detecteerbaar** — elke logregel draagt de hash van zijn voorganger en
  `isms:controleer-audittrail` loopt de keten elke nacht na — maar detecteren is
  niet verhinderen. De grant blijft nodig.
- **De keten heeft nog geen kopstuk buiten dit systeem.** Wie de database kan
  wijzigen, kan de hele keten herberekenen; wat dat weerlegt is een oudere
  kophash die búiten de organisatie ligt. De kopie voor de auditor op
  `/audit-log` draagt die hash, dus dat anker ontstaat zodra een auditor er één
  meeneemt — maar het systeem kan het niet afdwingen. Zie `06c` §8.
- **AVG-toets op bewijsstukken met persoonsgegevens** (deelproduct 06 §7) staat
  open. Daarom is archiveren het eindstation: `isms:archiveer-bewijsstukken`
  verwijdert bewust niets.

## Tests

```bash
php artisan test                          # alles, precies één keer
php artisan test --testsuite=risico-soa   # tijdens iteratie: één domein
php artisan test --group=nen7510          # de normprofiel-delta
php artisan test --group=bio2              #   idem, voor het BIO-profiel
```

Profielvastheid toets je door de hele suite één keer per profiel te draaien met
`ISMS_NORM=<profiel> php artisan test`; dat hoort schoon te zijn, want elke test
die een profiel nodig heeft zet dat zelf.

PHPUnit (geen Pest — dat zit niet in de dependencies van de starter kit).
**Ruim 1000 tests**, verdeeld over dertien suites: `Unit` plus twaalf
domein-suites (`toegang`, `context-assets`, `risico-soa`, `beleid-bewustzijn`,
`bewijs`, `taken`, `incidenten-leveranciers`, `meten-review`, `audit`,
`kennisbank`, `export`, `demo`).

**De suites zijn disjunct**: elk `tests/Feature`-bestand staat in precies één
suite, en `tests/Feature` staat niet meer als directory-suite in `phpunit.xml`.
Daardoor draait een kale `php artisan test` alles één keer, terwijl je tijdens
het bouwen met `--testsuite=` één blok kunt draaien. De keerzijde is dat een
nieuw testbestand ingedeeld moet worden; `SuiteDekkingTest` bewaakt dat en faalt
op een niet-ingedeeld bestand — anders valt zo'n bestand stilletjes buiten de
volledige run. Draai vóór een commit dus altijd kaal. Zie `implementatie/00f`.

**De normprofiel-delta is een groep, geen suite.** NEN 7510-tests liggen over
vier domeinen verspreid, en een bestand mag maar in één suite staan. Wil je weten
of de applicatie profielvast is, draai de hele suite dan één keer met `ISMS_NORM`
op `nen7510`; dat hoort schoon te zijn, want elke test die een profiel nodig
heeft zet dat zelf (`implementatie/00k`).

`phpunit.xml` pint `ISMS_NORM`, `ISMS_CBW_PLICHTIG` en `ISMS_CAPACITEITEN` vast.
Dat is geen netheid maar noodzaak: een lokale `.env` met `ISMS_CBW_PLICHTIG=true`
liet ooit vijf incidenttests omvallen die niets met die instelling te maken
hadden. Installatie-instellingen horen niet vanuit de werkplek in de suite te
lekken.

**De Docker-toets staat hier los van.** Die is een handprotocol
(`implementatie/00n-dockertoets.md`) en zit niet in `phpunit.xml`; `php artisan
test` zegt dus niets over de container.

De blok 4-tests gebruiken meestal een `MaatregelFactory` in plaats van de echte
catalogus, zodat een test niet omvalt op een gewijzigde titel. Sinds 04f staan
beide controlsets volledig in versiebeheer, dus de suite *mag* er wel van
afhangen — `ControlsetBestandenTest` doet dat met opzet: die vergelijkt de 93
gedeelde maatregelen tussen de twee bestanden en bewaakt dat er geen normtekst
in staat.
