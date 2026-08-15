# Implementatie-instructie: de simulatiemotor (`isms:demo-vul`)

Stap 3 uit `saasdemo/scenario.md` §12. Beschrijft hoe de fixtures in
`saasdemo/data/` een gevuld ISMS worden.

Dit document staat bewust in `saasdemo/` en niet in `implementatie/`: de motor
werkt geen deelproduct uit maar is demogereedschap, en de nummering in
`implementatie/` is voorbehouden aan de blokken.

## 0. Ontwerpbeslissingen (vooraf vastgelegd)

- **De motor voert echte handelingen uit, hij schrijft geen rijen.** Elke
  gebeurtenis loopt via de modellen en de bestaande actieklassen, ingelogd als de
  gebruiker die de handeling doet. Dat is niet uit netheid: de audit trail, de
  observers, de notificaties en de taakplanning hángen eraan. Een demo die met
  `DB::table()->insert()` is gevuld heeft een lege audit trail, en juist die is
  bij een ISMS het bewijsstuk.
- **De autorisatiecheck is een assertie, geen sier.** Vóór elke handeling toetst
  de motor of de handelende gebruiker hem daadwerkelijk mág doen. Zo niet: harde
  fout, geen stille afwijking. Daarmee is het vullen meteen een proef op de som
  voor het rechtenmodel uit implementatie/01c — en een fixture die de verkeerde
  persoon aanwijst valt onmiddellijk op in plaats van een onmogelijke historie op
  te leveren.
- **Tijdreizen met `Carbon::setTestNow()`.** Alle bestaande code gebruikt `now()`
  en `Carbon::today()`; dat is geverifieerd voor `isms:meet-kpis`,
  `isms:genereer-taken` en `isms:verloop-taken`. De motor verzet de klok per
  stap en zet hem in een `finally` terug.
- **Deterministisch, op wachtwoorden na.** Dezelfde fixtures leveren dezelfde
  database. Geen `fake()`, geen willekeurige volgorde. Alleen de wachtwoorden
  worden gegenereerd en één keer op de console getoond.
- **Eén handler per gebeurtenistype.** ~30 typen in `tijdlijn.json`; een `match`
  van 400 regels wordt onleesbaar en onbeproefbaar. Een register van kleine
  klassen met een gemeenschappelijke interface is per type los te testen.
- **De motor voegt geen productiecode toe.** Ontbreekt er iets om een
  gebeurtenis uit te voeren, dan is dat een gat in het product en hoort de fix
  in het betreffende blok — niet in `app/Support/Demo/`.

## 1. Wat er al is

| Onderdeel | Waar |
|---|---|
| Fixtures | `saasdemo/data/*.json` — twaalf bestanden, kruisverwijzingen gecontroleerd |
| Scenario | `saasdemo/scenario.md` — de bron; wat daar niet in staat, komt er niet in |
| Publiceren van beleid | `App\Support\Beleidspublicatie::publiceer($versie, $goedkeurder)` |
| Afwijking sluiten | `App\Support\Afwijkingafsluiting::sluit($afwijking, $sluiter)` — met `belemmering()` als voorcontrole |
| Bewijs opslaan | `App\Support\Bewijsopslag::bewaar(UploadedFile, $naam, $omschrijving)` |
| Taken plannen/voltooien | `App\Support\TaakPlanner::planVoorEntiteit()` / `voltooiVoorEntiteit()` |
| Incidentmelding aan de CISO | `App\Support\Incidentmelding::meldAanCiso($incident)` |
| Auditcyclus opzetten | `isms:bereid-auditcyclus-voor`, met `--voorbereiding` sinds plan 11c |
| Terugkerende commando's | `isms:meet-kpis`, `isms:genereer-taken`, `isms:verloop-taken`, `isms:leg-restrisico-vast` |

`isms:meet-kpis` slaat een maand over als er al een meting voor die maand staat —
de motor mag hem dus veilig per maandgrens aanroepen.

## 2. Waar de code komt te staan

```
ota/app/Console/Commands/DemoVul.php        dun: opties lezen, motor starten, samenvatting tonen
ota/app/Support/Demo/Simulatie.php          de lus: maand voor maand, gebeurtenis voor gebeurtenis
ota/app/Support/Demo/Fixtures.php           inlezen, valideren, sleutel → model bijhouden
ota/app/Support/Demo/Klok.php               maandoffset → datum, setTestNow, terugzetten
ota/app/Support/Demo/Handelt.php            "doe dit namens X", inclusief de Gate-assertie
ota/app/Support/Demo/Bewijsgenerator.php    de ~35 .md-bewijsstukken
ota/app/Support/Demo/Handlers/*.php         één klasse per gebeurtenistype
```

`DemoVul` blijft dun genoeg om in één scherm te passen. Alles wat een beslissing
neemt, hoort in `Support/Demo/` waar het te testen is.

## 3. Het tijdmodel

`M0` = de eerste dag van de maand, 22 maanden vóór vandaag. `M22` = vandaag.

```php
public function datum(int $maand, int $dagInMaand = 1): CarbonImmutable
{
    return $this->m0->addMonths($maand)->addDays($dagInMaand - 1);
}
```

`m0` = `CarbonImmutable::today()->subMonths(22)->startOfMonth()`.

Binnen een maand krijgen gebeurtenissen een oplopende dag, zodat de audit trail
een leesbare volgorde houdt en niet 40 regels op dezelfde seconde toont. Vaste
verdeling: de eerste gebeurtenis op dag 3, daarna telkens twee dagen verder,
met een plafond op dag 26 zodat niets in de volgende maand valt.

**Jaargrenzen.** Passeert de klok een 31 december, dan draait
`isms:leg-restrisico-vast`. Bij 22 maanden gebeurt dat twee keer. Welke
kalenderjaren dat zijn hangt van de draaidag af; dat is inherent aan een
relatieve tijdlijn en geen fout.

**Terugzetten is verplicht.** `Carbon::setTestNow(null)` in een `finally`, ook
als de motor halverwege faalt. Een proces dat met een verzette klok eindigt,
schrijft de rest van zijn werk in het verleden.

## 4. Handelen namens een gebruiker

Dit is de kern van de motor en de reden dat de demo overtuigend wordt.

```php
Handelt::als($directeur)
    ->mits('heeft-niveau', ['context-scope', 'goedkeuren'])
    ->doe(fn () => (new ActiveerScopeVerklaring)($verklaring, $directeur->naam));
```

Wat `Handelt` doet:

1. `Auth::login($gebruiker)` — zodat de `Auditeerbaar`-trait `auth()->user()`
   vindt en de trail de juiste naam vastlegt in plaats van
   "Systeem (geplande taak)".
2. De autorisatiecheck toetsen met `Gate::forUser($gebruiker)`. Mag het niet, dan
   een `DemoFixtureFout` met de gebeurtenis, de maand en de gebruiker erin.
3. De handeling uitvoeren.
4. `Auth::logout()` — altijd, ook bij een uitzondering.

De vier handelingen die per implementatie/01c bij een directeur horen, staan in
`tijdlijn.json` met `"door": "bobo"` of `"baas"`. Voert de motor ze per ongeluk
als CISO uit, dan slaat stap 2 alarm — precies waarvoor die er is.

## 5. De wipe

`isms:demo-vul` begint met het volledig legen van de database en het opnieuw
draaien van de referentieseeders (scenario §11.6). Enige beveiliging: een
**omgevingsblokkade** op `local` en `demo`. Geen bevestigingsvraag, geen
`--force` — herhaald vullen tijdens het ontwikkelen mag niet in de weg zitten.

**Niet `migrate:fresh`.** Op de ontwikkelmachine kost `migrate:fresh --seed`
ongeveer zes minuten, vrijwel volledig aan DDL. Dat is per demo-run onaanvaardbaar
en het levert niets op: het schema verandert niet tussen twee runs. In plaats
daarvan:

1. foreign-key-controle uit,
2. `truncate` op alle tabellen behalve `migrations`,
3. foreign-key-controle aan,
4. `DatabaseSeeder` draaien — dat is precies de set referentiedata (elf
   seeders, van rollen tot auditobject-clausules), dus die lijst hoeft niet in
   de motor gedupliceerd te worden.

Schema-wijzigingen blijven dus de verantwoordelijkheid van `migrate`, en dat
hoort ook zo — een demovulling is geen migratiegereedschap. Meld in de uitvoer
welke migratiestand is aangetroffen, zodat een verouderd schema opvalt.

Bewijsbestanden van een vorige run worden ook opgeruimd; anders groeit de
bewijsmap bij elke run.

## 6. De verwerkingslus

Per maand, in deze volgorde:

1. Klok op de eerste dag van de maand.
2. De gebeurtenissen uit `tijdlijn.json`, in de volgorde waarin ze staan, elk op
   zijn eigen dag.
3. Aan het einde van de maand de terugkerende commando's, in deze volgorde:
   `isms:genereer-taken` → `isms:verloop-taken` → `isms:meet-kpis`.
   Verloop ná genereren, anders verloopt een taak die net is aangemaakt niet;
   meten als laatste, zodat de meting de stand ná de maand vastlegt.
4. Passeert de maand een 31 december, dan `isms:leg-restrisico-vast` op die dag.

De volgorde binnen een maand is die van het bestand. `tijdlijn.json` is met die
afhankelijkheid geschreven: in M4 staat `soa_golf` vóór de nulmeting, want de
audit-universe groeit mee met de SoA.

## 7. De gebeurtenistypen

Elk type uit `tijdlijn.json` krijgt een handler. De volledige lijst, met wat
elke handler moet doen:

| Type | Wat de handler doet |
|---|---|
| `gebruikers_aanmaken` | Gebruikers uit `personen.json`, met rol, eenheid, NDA en screening. Wachtwoord gegenereerd. |
| `context_vastleggen` | Issues en belanghebbenden met hun eisen. |
| `scope_concept` / `scope_activeren` | Verklaring met uitsluitingen, raakvlakken en koppelingen; activeren door een directeur. |
| `assets_registreren` / `assets_classificeren` / `systemen_registreren` | Uit `assets.json`, inclusief de asset-systeemkoppelingen. |
| `risicos_identificeren` / `risicos_beoordelen` | Titel, dreiging, kwetsbaarheid, kans/impact, eigenaar. |
| `risico_behandelplan(nen)` | Behandeling met optie en restrisico; koppeling aan SoA-regels. |
| `risico_accepteren` | Onder de drempel door de CISO, erboven via de goedkeuractie door een directeur. |
| `risico_herbeoordelen` | Nieuwe kans/impact uit `verloop`; werkt `volgende_beoordeling_gepland` bij. |
| `risico_eigenaar_wijzigen` | Eigenaarswissel (M19, na het uitdiensttreden). |
| `soa_golf` | Een deel van de 93 regels beoordelen; de vijf niet-toepasselijke met hun motivatie. |
| `soa_implementatiegolf` | Implementatiestatus ophogen tot het aantal uit `soa.json`. |
| `soa_herbeoordelen` | `laatst_beoordeeld_op` verversen; herstelt de Check-KPI. |
| `soa_eigen_classificatie` | `kenmerken_eigen` zetten (plan 04d fase 2). |
| `leveranciers_opvoeren` | Leveranciers, diensten en clausules. |
| `leveranciersbeoordelingen` | Een ronde uit `leveranciers.json`. |
| `leverancier_risiconiveau_wijzigen` | Tegenslag 3 (SnijBoon). |
| `beleid_publiceren` | Document en versie; publiceren via `Beleidspublicatie` door een directeur. |
| `leesbevestigingen_uitzetten` | Per doelgroep; laat de openstaande uit `beleid.json` staan. |
| `training_ronde` | Voltooiingen per gebruiker; `openstaand` blijft open. |
| `incident` | Melding, ernst, koppelingen, oplossen na N dagen; met of zonder afwijking. |
| `afwijking` | Met bron, grondoorzaak en een corrigerende maatregel met deadline. |
| `corrigerende_maatregel_voltooien` / `effectiviteitstoets` / `afwijking_sluiten` | De CAPA-keten; sluiten via `Afwijkingafsluiting`. |
| `auditprogramma_aanmaken` / `auditprogramma_afsluiten` | Het voorbereidingsprogramma. |
| `auditcyclus_voorbereiden` | Roept `isms:bereid-auditcyclus-voor` aan met de startdatum uit de klok. |
| `auditronde` | Ronde met type, dekkingsvlag, auditor, scope en bevindingen; afronden als `afgerond: true`. |
| `directiebeoordeling` | Sessie met de negen inputs, besluiten en verbeteracties; als gehouden vastgelegd door een directeur. |
| `taak_te_laat` / `taak_verlopen` / `taak_afronden` | Sturen de taken-KPI's. |
| `gebruiker_uit_dienst` | Deactiveren en toegang intrekken. |
| `bewijsstuk` | Een `.md`-bestand genereren en koppelen. |
| `eindstand` | Geen handeling: de controlelijst uit M22 wordt geverifieerd (zie §11). |

**Verzamelwaarden.** `"sleutels": "alle"`, `"alle_bekende"` en
`"alle_bestaande"` betekenen: alles van dat soort dat op dát moment in de
simulatie bestaat. `Fixtures` houdt bij wat er al is aangemaakt, dus dit is een
opzoeking en geen gok. Een onbekende verzamelwaarde is een harde fout.

## 8. Bewijsstukken

Ongeveer 35 `.md`-bestanden met titel en datum, verder leeg. Ze worden bij het
vullen gegenereerd en gaan **niet** de repository in.

`Bewijsopslag::bewaar()` verwacht een `UploadedFile` en dat heeft de motor niet:
hij genereert de inhoud in het geheugen. **Besluit (29-07-2026): omzeilen.** De
`Bewijsgenerator` schrijft rechtstreeks naar de `bewijs`-disk en maakt het
`Bewijsstuk` zelf aan.

De afweging: de andere weg is `UploadedFile::fake()->createWithContent()`, en dat
haalt een testhelper de productiecode in — voor een pad dat óók in productie
geladen wordt, ook al draait het daar nooit. Omzeilen kost dat niet, maar zet wel
een tweede plek in de codebase die weet hoe bewijsopslag werkt. Houd die daarom
zo dun mogelijk: pad bepalen, bestand wegschrijven, rij aanmaken, klaar. Wijzigt
`Bewijsopslag` van opslagvorm, dan moet dit mee — zet er een comment bij dat naar
die klasse verwijst.

De kwartaalreviews toegang (`herhaal_per_kwartaal_vanaf_maand`) leveren meerdere
bestanden op — dat is bewust, want één review is geen aantoonbare cyclus.

## 9. Wachtwoorden

Bij het vullen gegenereerd, één keer op de console getoond, nergens opgeslagen
buiten de hash. Toon ze als tabel aan het eind, na de samenvatting, zodat ze niet
tussen de voortgangsregels wegvallen.

### 9b. Tweefactor in de demo (03-08-2026)

2FA is verplicht in de applicatie (`implementatie/01d`), maar **de demo-omgeving
zet `ISMS_2FA_AFDWINGEN=false`**. Anders is de eerste handeling van iedere
bezoeker het koppelen van een authenticator-app aan een wegwerpaccount, en dat is
niet wat de demo laat zien.

Vergeet die regel niet bij het opbouwen van een demo-omgeving: zonder de vlag
staat de bezoeker na het inloggen voor een QR-code in plaats van voor het ISMS.
De simulatiemotor zelf raakt 2FA niet aan — hij handelt namens gebruikers via
`Handelt::als()` en komt nooit langs het inlogscherm.

## 10. Raakvlakken in bestaande code

| Bestand | Waarom het meedoet |
|---|---|
| `app/Observers/SoaRegelObserver.php` | Van toepassing verklaren maakt meteen een auditobject. De audit-universe groeit dus tijdens de run; de scope van de nulmeting in M4 moet ná de SoA-golf worden bepaald. |
| `app/Models/Concerns/Auditeerbaar.php` | Leest `auth()->user()`. Zonder ingelogde gebruiker komt er "Systeem (geplande taak)" in de trail te staan — voor een demo waardeloos. |
| `app/Models/Auditronde.php` | `creating` zet `telt_mee_voor_dekking` op basis van het type. De fixtures geven de vlag expliciet mee; dat wint en dat is de bedoeling. |
| `app/Console/Commands/BereidAuditcyclusVoor.php` | Weigert bij een onvolledige SoA zonder `--forceer`. In M12 is de SoA compleet, dus dat gaat goed — maar de motor moet de exitcode controleren in plaats van hem te negeren. |
| `database/seeders/TaaksjabloonSeeder.php` | Levert de zeven terugkerende taken. De maandelijkse patchronde geeft het volume voor de taken-KPI's. |
| `app/Support/NotificatieDispatcher.php` | Notificaties komen op de gesimuleerde datum binnen. Controleer bij het bouwen dat er niets echt verstuurd wordt. |

## 11. Tests

De motor test je niet op "het draait", maar op wat er ná afloop staat.

- Een verkorte tijdlijn (M0–M2) levert de verwachte entiteiten op;
- de audit trail staat op naam van de handelende gebruiker, niet op "Systeem";
- de vier directiehandelingen zijn door een Management-account uitgevoerd;
- een fixture die de verkeerde persoon aanwijst, faalt met een `DemoFixtureFout`
  in plaats van stilletjes door te lopen;
- `Carbon::setTestNow()` staat na afloop weer op `null`, ook als de motor
  halverwege faalt;
- het commando weigert buiten `local` en `demo`;
- **de eindstandcontroles uit M22 in `tijdlijn.json` zijn assertions**: de open
  minor staat op `non_conformiteit_gestart`, er is precies één afgeronde dekkende
  ronde, één account is gedeactiveerd, en er staan twee leesbevestigingen open.
  Deze test is de echte poort — hij faalt zodra het scenario en de motor uit
  elkaar lopen.

De testsuite krijgt een eigen blok in `phpunit.xml` (`SuiteDekkingTest` bewaakt
de indeling).

## 12. Caveats (vasthouden bij het bouwen)

- **Niet de klok vergeten bij commando's die de motor aanroept.** `$this->call()`
  draait binnen hetzelfde proces, dus `setTestNow()` geldt daar ook. Dat is wat
  je wilt — maar het betekent ook dat een vergeten reset zich voortplant.
- **De demo is geen migratiegereedschap.** Draait de wipe op een verouderd
  schema, dan faalt het vullen met een onbegrijpelijke foutmelding. Vandaar de
  melding van de migratiestand in §5.
- **Geen productiecode aanpassen om de motor te laten werken.** Loopt de motor
  vast omdat het product iets niet kan, dan is dat een bevinding over het
  product. Los het daar op, met een test in het betreffende blok.
- **Bewijsbestanden staan in privéopslag.** Ze horen niet in de repository en
  niet in een export zonder `--met-bewijs`.
- **De eindstand is de specificatie.** Wijkt de gevulde demo af van §9 M22 in
  `scenario.md`, dan is het scenario leidend en de motor fout — tenzij het
  scenario zelf niet kan kloppen, en dan wordt het scenario aangepast met een
  aantekening, zoals bij risico 15 al gebeurd is.

## 13. Wat hier niet bij hoort (bewust buiten scope)

- **Een tweede demo-organisatie.** De motor leest één set fixtures; meerdere
  scenario's naast elkaar is een ander vraagstuk.
- **Terugdraaien of gedeeltelijk vullen.** De wipe is het beginpunt; een
  `--vanaf-maand` klinkt handig maar vraagt een volledige toestandsopbouw en dat
  is duurder dan opnieuw vullen.
- **De drie Act-KPI's.** Bewust uitgesteld in de applicatie; de demo maakt wel
  zichtbaar waaróm ze interessant zijn (de score-daling van risico 8 in M17 na
  een patchronde met bewijs).
- **Productiedata benaderen.** De motor draait uitsluitend in `local` en `demo`.
