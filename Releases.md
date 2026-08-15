# Releases

De uitgaven van EzISMS, nieuwste bovenaan. Samengesteld uit de git-tags; die
blijven de bron, dit bestand is de leesbare vorm ervan.

**Versienummers.** De eerste cijferreeks springt bij een breuk in wat het product
ís — V2.0.0 markeert het punt waarop de repo uitleverbaar werd. Het tweede cijfer
telt uitgaven met nieuwe functionaliteit, het derde is voor correcties op een
uitgave die al buiten staat. Tot nu toe is er één zo'n correctie geweest: V2.2.1.

Elke uitgave is een annotated tag; `git tag -l --format='%(contents)' V2.6.0`
geeft de oorspronkelijke tekst.

---

## V2.6.0 — de bewaking merkt haar eigen stilstand

*15-08-2026*

Negen commando's staan in de planning: metingen, taken genereren, taken laten
verlopen, herinneringen. Staat de machine uit, dan draaien ze niet, en Laravel
haalt niets in. Tot deze uitgave merkte niemand dat — een ISMS dat zes weken had
stilgelegen zag er daarna precies zo uit als een ISMS dat gewoon doordraaide, en
juist een systeem dat aantoonbaarheid als product levert hoort niet stil te
kunnen vallen zonder spoor.

Eén listener schrijft nu elke geplande run weg in `systeemhartslag`. Machinale
log, dus geen audit trail: de bewijsketen loopt via de taak die uit een gat
volgt, en die is wél geauditeerd. De verwachte momenten komen uit
`Schedule::events()` zelf en niet uit een tweede lijst naast `routes/console.php`,
zodat een nieuw gepland commando vanzelf meeloopt.

Niet elk gemist moment weegt even zwaar, en dat is de kern. Een herstart van tien
minuten hoort geen ruis te maken; een gemiste maandmeting van een toestand-KPI is
onherstelbaar, want die waarde is achteraf niet te reconstrueren. De klasse per
commando staat in `config/hartslag.php` — een inhoudelijk oordeel over wat een
commando doet, geen detectielogica.

**Twee keer een e-mailadres repareren.** Een typefout in een uitnodiging was
nergens te herstellen; wat overbleef was een UPDATE op de database, precies de
ingreep die buiten elke logging om gaat. Nu kan het, in twee vormen die elkaars
spiegelbeeld zijn. Bij een uitgenodigd account (01g) geldt het nieuwe adres
direct en roteert het wachtwoord mee — het uitnodigingstoken bevat het adres
niet, dus wie de mail op het foute adres kreeg hield anders zeven dagen een
werkende link. Bij een actief account (01h) geldt het pas na bevestiging op het
nieuwe adres, blijft het wachtwoord staan en krijgt het oude adres bericht.
Dezelfde regel in beide richtingen levert tegengesteld gedrag op, omdat er in het
tweede geval iemand ís die het account gebruikt.

Verder:

- een bevindingenregister over alle auditrondes heen, met filters in de URL, zodat
  een open minor uit een afgeronde ronde van twee jaar terug niet meer achter een
  badge verdwijnt; read-only, want vastleggen en sluiten blijft achter de
  record-guard van de auditor;
- het versienummer achter de productnaam in de zijbalk, uit `.env` of anders uit
  het manifest van de uitgerolde boom, zodat het na een upgrade vanzelf klopt;
- een voettekst met de herkomst op elke pagina van elk Word-document: organisatie
  en norm, product en omgeving, printdatum en paginanummer — met de organisatie
  op de schermkopie en bewust niet op een meegeleverd kennisartikel;
- pandoc uit de release van jgm/pandoc in plaats van uit apt: de Ubuntu-build kan
  met `--sandbox` geen .docx schrijven, waardoor elke Word-download in de
  Docker-stack een 503 gaf die nergens in de logs stond;
- `deploy.sh` bouwt `vendor/` en `public/build` als de tarbal ze mist; een
  `--geen-bouw`-pakket eist daarom deploy-versie 1.1 en de Docker-route weigert
  het met een eigen melding;
- storage zonder ACL's op de ontwikkelmachine, waarmee alle drie de omgevingen
  hetzelfde principe volgen met één schrijver, en de machineconfig in
  `ontwikkelmachine/` onder versiebeheer.

---

## V2.5.0 — wijzigingsbeheer, het vijftiende blok

*12-08-2026*

A.8.32 stond wel in de SoA maar had geen register: nergens was te zien welke
wijzigingen er waren geweest en met welke goedkeuring. Dat gat is nu gevuld, en
de weg ernaartoe leverde een laag op waar meer blokken iets aan hebben.

De keuze die het ontwerp draagt: dossiersoort in code, stappenreeks in data. Een
generieke workflow-engine wordt een mini-programmeeromgeving die de CISO moet
bedienen, en configuratie die de compliance-uitkomst bepaalt vraagt daarna zelf
om versiebeheer. Maar een per geval gebouwde workflow levert bij elke variant een
bijna identieke tabel op. Dus: de organisatie richt zelf routes in, nieuwe
dossiersoorten blijven ontwerpwerk.

Onder het blok ligt een reekslaag op de taken-engine (07b). Een reeks is geen
nieuwe tabel maar alle taken op dezelfde entiteit met een gevulde volgorde;
stappen met hetzelfde nummer lopen parallel, en een stap die nog niet aan de
beurt is telt nergens mee.

Zeven meegeleverde routes, één per soort wijziging: twee leveranciersreleases,
configuratie standaard en spoed, infrastructuur, ingebruikname en afvoer. Spoed
vraagt geen enkele uitzondering in de code — het is een route waarin uitvoeren
vóór goedkeuring staat, precies wat A.8.32 f) bedoelt met voorzorgsoverwegingen.

De enige harde inhoudelijke eis is het terugvalplan. Die zit op het dossier en
niet op een scherm, want dezelfde stap is ook vanaf de takenlijst af te vinken.
Wat een lopend dossier draagt ligt vast op het moment dat het start: titel,
deadline, eigenaar, staptype, bewijsplicht en de terugsprong bij afkeuren. Een
sjabloon dat later wordt versoepeld zet daarmee geen controle uit die al gold.

Verder:

- de laatste containerlogs bij een mislukte uitrol, en een grens op de logdriver;
- tijdstippen in de lokale zone, met UTC in de opslag;
- handmatig blokkeren van een account door de CISO, met een blokkade die meteen
  werkt;
- de demoklok een maand terug, want de gesimuleerde historie liep tot in de
  toekomst;
- de kennisbank bijgewerkt, met een downloadknop voor de CISO;
- twee kennisbankartikelen over wijzigingsbeheer, en blok 15 in de sitemap.

---

## V2.4.0 — de Administrator, en toetsen buiten de webmap

*11-08-2026*

Een vijfde rol die geen enkel ISMS-recht heeft en alleen bij beheerroutes kan, en
de verhuizing die daaraan vooraf moest gaan.

Toetsen waren losse HTML-pagina's in `public/toetsen`, en daar plaatste alleen
iemand met SSH ze neer. Zodra een minder vertrouwd account dat mag, is zo'n
pagina een escalatiepad: het is door een mens geleverde HTML met JavaScript, en
op de origin van het ISMS draait dat script in de sessie van wie de toets opent.
Toetsbestanden staan nu op een eigen disk buiten `public/` en worden door de
applicatie uitgeserveerd in een CSP-sandbox — geen sessiecookie, geen DOM van het
ISMS, geen opslag. De token blijft het bewijs, en staat in het pad én in
`?callback=`, zodat elk al uitgeleverd toetsbestand blijft terugmelden.

Daarop staat de rol: blok installatiebeheer, één rechtenrij, twee schermen. De
CISO heeft op dat blok bewust géén rij, anders is het een rol met extra rechten
in plaats van andere. De combinatie Administrator + een ISMS-rol wordt geweigerd;
dat is de eerste harde onverenigbaarheid in dit model en een bewuste breuk met
01c. Escalatie vraagt daarmee altijd twee personen: de Administrator kan geen
rechten uitdelen, de CISO kan zichzelf geen technische rechten geven.

De export schuurt met "geen enkel ISMS-recht" en dat is gewogen: uitleveren is
een handeling aan de installatie, inzien is een recht op de inhoud. Hij drukt op
de knop en leest de bevestiging; het bestand komt op een pad waar hij vanuit de
applicatie niet bij kan, zonder persoonsgegevens en zonder bewijsstukken, en de
handeling staat in de audit trail.

Verder:

- de uitgang `/var/tmp/isms_export`, in de Docker-route een bind mount naar
  `data/isms_export` op de host, met een waarschuwing bij een stack die hem mist;
- de drie meegeleverde toetsen meldden hun uitslag nooit terug — ze riepen een
  functienaam aan die nergens bestond. Nu gerepareerd, ook voor gezakte pogingen;
- de demo levert een werkende toets mee bij de beheerderstraining;
- `public/toetsen` is uit versiebeheer, met het bijbehorende leidingwerk;
- de NEN 7510-ontwerpnotities zijn verwijderd: de bouw is af en de code is de bron.

873 tests groen, in beide normprofielen. Twee fouten kwamen pas op de draaiende
installatie boven: `/var/tmp` bleek daar niet schrijfbaar voor de webgebruiker,
en een nieuwe waarde in de enum van de audit trail kwam er in de tests doorheen
omdat de suite op sqlite draait. Die tweede is aan de codekant dichtgezet.

> De deelproducten 01, 06 en 10 en `architectuur.md` zijn ná het taggen
> bijgewerkt; de tag is daarom één commit opgeschoven.

---

## V2.3.0 — het ISMS draait als Docker-stack

*11-08-2026*

De volledige Docker-uitrolroute, in twee delen gebouwd en met de hand getoetst op
twee stacks naast elkaar (iso27001 op poort 81, nen7510 op poort 82).

Deel 1 levert de stack: een image dat uit de distributietarbal gebouwd wordt,
nginx + php-fpm + de takenplanner onder supervisord, MySQL ernaast, en één
hostmap voor alles wat een herbouw moet overleven. De APP_KEY wordt eenmalig
gegenereerd en bewaard; wijkt een meegegeven sleutel daarvan af, dan start de
container niet in plaats van stil de 2FA-geheimen onleesbaar te maken.

Deel 2 vervangt het voorlopige "migreer en seed" door `scripts/deploy-docker.sh`,
dat bij elke start de installatie nagaat: normcontrole, verschilrapport, eigen
seeddata, dump, migreren en seeden, het eerste CISO-account, de demo, een
rookproef en de verantwoording. De dump wordt gemaakt zodra er een migratie
klaarstaat en anders niet — anders dan bij `deploy.sh` draait dit bij élke
containerstart. Mislukt de uitrol, dan telt de entrypoint de pogingen en valt de
installatie stil met een BLOKKADE in plaats van eindeloos te herstarten.

De toetsronde bracht drie fouten aan het licht die alleen door te draaien
zichtbaar worden: het script wiste zijn eigen `DB_*`-variabelen, backticks in een
foutmelding werden als commando uitgevoerd, en het slotscherm noemde altijd poort
81. Alle drie hersteld en opnieuw gedraaid.

Verder:

- één maatregelbestand per normprofiel; de eigen omschrijvingen zijn vervallen
  omdat een interpretatie van de norm niet thuishoort in het scherm waar de
  auditor de toepasselijkheid beoordeelt;
- risicocriteria als vastgesteld kader met eigen versies (04g);
- twee oriëntatieartikelen onder Naslag, voor de CISO en de externe auditor;
- de demo leegt alleen nog de tabellen van het eigen schema;
- de pre-commit hook is verwijderd; `ControlsetBestandenTest` en de controle in
  `builddistr.sh` bewaken de normtekst nu.

---

## V2.2.1 — twee uitrolfouten

*06-08-2026*

Het normprofiel overleeft `config:cache`. Een uitrol met `ISMS_NORM=nen7510`
leverde een ISO-installatie op: `deploy.sh` cachet de configuratie vóór
`db:seed`, en daarna leest Laravel `.env` niet meer. `ISMS_NORM` en
`MAATREGELEN_BRON` lopen nu via `config/norm.php`. `deploy.sh` controleert het
profiel bovendien meteen na het seeden, in plaats van pas ná het omschakelen.

De databasedump vroeg om het PROCESS-recht. `--no-tablespaces` slaat die uitvraag
over, en de dump wordt nu gecontroleerd op een volledige afsluiting — mysqldump
meldde de fout wel maar sloot af met exitcode 0.

---

## V2.2.0 — de demo rolt mee uit

*06-08-2026*

`deploy.sh` kan het FruitBV-demoscenario in een uitrol meenemen (`--demo-vul` /
`--geen-demo`, en anders een bevestigingsvraag voor het wissen). De fixtures gaan
mee in elke tarbal; `builddistr.sh` exporteert `saasdemo/data` apart, want dat
staat buiten `ota/`.

De gegenereerde demowachtwoorden worden niet meer afgedrukt maar naar
`storage/app/private/demo-inloggegevens.txt` (0600) geschreven — onbeheerd
meelopend zouden ze anders in het bewaarde uitrollog staan. De demopersonen
hebben nu adressen op `acme.example` in plaats van een bestaand domein.

---

## V2.1.0 — NEN 7510 als tweede normprofiel

*05-08-2026*

Het profiel wordt bij de installatie in de database vastgelegd (`ISMS_NORM` bij
het opzetten, daarna de tabel `normprofiel`). Bijlage A telt in zorgmodus 101
maatregelen met een eigen veld voor de zorgspecifieke beheersmaatregel; het ISMS
levert in dat profiel geen enkele maatregeltekst mee.

Verder: de beoordelingsschaal van kans en impact, privacy bij assets, de externe
meldplicht met AVG- en Cbw-termijnen, kennisbankvarianten per norm, en het
artikel over het zelf invoeren van de normtekst.

---

## V2.0.0 — de eerste distribueerbare versie

*04-08-2026*

Vanaf hier is de repo uitleverbaar: er staat geen normtekst in code, gegevens of
kennisbank. De maatregelomschrijvingen zijn eigen formuleringen, met op elk
scherm het voorbehoud dat ze dat zijn; `maatregelen.json` (de gekochte normtekst)
blijft lokaal en de pre-commit-hook bewaakt dat.

De uitlevering zelf gaat als een verse repo zonder historie.

---

## V1.15.0 — disclaimer bij eigen omschrijvingen, en twee auditorknoppen erbij

*04-08-2026*

De 93 eigen maatregelomschrijvingen dragen nu zelf het voorbehoud dat ze niet de
normtekst zijn; de SoA-modal zet die regel rood met een link naar het
verantwoordingsartikel. In een installatie met de gekochte normtekst verschijnt
hij niet.

Kopie voor de auditor op `/risicos` en `/afwijkingen`, met de eigenaarskolom
geanonimiseerd tot initialen + rol. Dat schema staat nu op één plek
(`Gebruiker::anoniemLabel`) en wordt gedeeld met `isms:exporteer`.

---

## V1.14.0 — kopie voor de auditor op de tolerantiematrix

*04-08-2026*

De matrix gaat als afbeelding én als tabel mee in het Word-document. Het plaatje
wordt met GD getekend en als data-URI ingesloten — de enige weg die werkt met
pandoc's `--sandbox`. Zonder GD of lettertype blijft alleen het plaatje weg; de
cijfers staan er dan nog steeds.

SBOM: besturingssysteem en kernel erin (A.8.8), fonts-dejavu-core erbij, pax
eruit, en de rol van pandoc bijgewerkt.

---

## V1.13.0 — zoeken in de kennisbank

*03-08-2026*

Zoekveld op `/kennisbank`: hoofdletter- en accentongevoelig, deelwoordmatching,
een passage met de treffer gemarkeerd, en een diepe link naar de paragraaf via
kopankers. Geen index, geen zoekdienst, geen nieuwe afhankelijkheid.

---

## V1.12.0 — het aanmelden krijgt een tweede slot

*03-08-2026*

**Tweefactorauthenticatie** (01d), verplicht voor alle rollen. TOTP via Fortify,
maar alleen die feature: de loginroute van dit ISMS blijft de enige, en het
instelscherm, de challenge, de afdwing-middleware en de CISO-reset zijn van
onszelf. Een nieuwe gebruiker koppelt zijn app meteen bij de uitnodiging; wie er
al was krijgt veertien dagen respijt, met twee herinneringsmails onderweg.

Twee dingen zijn bewust niet gedaan, en dat is de kern van het ontwerp. Een foute
verificatiecode blokkeert het account níet — brute-force op zes cijfers is met
vijf pogingen per kwartier kansloos, terwijl blokkeren op typefouten en klokdrift
alleen zelf-DoS oplevert en werk voor de CISO. En verlopen sluit niemand buiten:
je komt niet verder dan het instelscherm, maar je helpt jezelf daar zonder
beheerder. Wat de mail voorkomt is niet een lockout maar het moment: inloggen om
iets te doen en dan pas je telefoon moeten zoeken.

Het 2FA-secret en de herstelcodes zijn uitgesloten van de audit trail. Zonder die
regel belandt het geheim in een tabel die de Auditor mag inzien én exporteren.

**Het wachtwoordbeleid** is vastgesteld: minimaal twaalf tekens, geen verplichte
samenstelling, geen periodieke wijziging. Eén plek in de code, vier aanroepers
die ernaar verwijzen, en een test op de grens zelf.

Beide punten stonden als openstaande beslissing in de kennisbank en zijn daar nu
weg. A.5.17 en A.8.5 hebben hiermee onderbouwing die er niet was.

672 tests groen.

---

## V1.11.0 — de audit trail wordt onweerlegbaarder, en de suite twee keer zo snel

*03-08-2026*

**Keten-hashing van de audit trail** (06c). Elke logregel draagt de hash van zijn
voorganger, zodat het stil verwijderen, wijzigen of tussenvoegen van een regel
detecteerbaar wordt — tot nu toe liet een DELETE alleen een gat in de nummering
achter, en die ontstaan ook door teruggerolde transacties. Een nachtelijke
controle loopt de keten na en legt de uitslag vast, ook als alles klopt: een
auditor vraagt niet of de keten vandaag klopt maar of hij al twee jaar elke nacht
is gecontroleerd.

Wat het niet doet staat er overal bij. Het verhindert niets, en wie de database
kan wijzigen kan de hele keten herberekenen. Daar helpt alleen een kophash tegen
die buiten dit systeem ligt; de kopie voor de auditor van het trailscherm draagt
die hash. De databasegrant blijft onverminderd nodig.

Het lastigste stuk bleek niet de hash maar de canonieke vorm: MySQL herordent de
sleutels in een json-kolom, dus de opgeslagen bytes hashen levert per database een
andere uitkomst. Een test met een letterlijk opgeschreven hash bewaakt die vorm.
Verzegelen gebeurt in één transactie — 2190 losse commits duurden 274 seconden en
braken halverwege af; nu 10,8 seconden en niet meer te stranden.

**De testsuite van 205 naar 102 seconden** (00f). De demovulling draaide vijftien
keer waar het klassecommentaar één keer beloofde, en `MeetaanpakTest` rendeerde
negentien KPI's per aanroep. Daarnaast stonden twaalf identieke autorisatietests
in twaalf bestanden; die zijn vervangen door één matrix van 36 schermen maal vier
rollen, plus een test die de schermlijst uit de router afleidt. Dat legde meteen
bloot dat de Auditor 403 krijgt op vijf schermen waar hij in de componenttests
juist alle rijen hoort te zien — vastgelegd, niet stilzwijgend gerepareerd: het is
een rechtenbesluit.

**Kennisbank: alle open punten op één pagina.** Openstaande beslissingen,
bedenkingen en ideeën uit elkaar getrokken, met een tabel van wat al beslist is
zodat afgesloten discussies niet terugkomen.

Verder: de kopie voor de auditor noemt personen met initialen, een datumfilter
neemt de hele periode mee in plaats van de zichtbare vijftig, en de ongebruikte
starter-kit-layout is verwijderd.

641 tests groen.

---

## V1.10.0 — koppelingen in de audit trail, en de schermkopie voor de auditor

*03-08-2026*

Twee gaten gedicht die allebei pas zichtbaar werden door ernaar te gaan zoeken.

**Koppelingen komen in de audit trail** (06b). Een sync op een
veel-op-veel-relatie raakt de attributen van een model niet aan, dus wijzigingen
aan koppelingen lieten geen enkel spoor na: 442 koppelrijen, nul trailregels.
Welk beleid welke maatregel dekt, wie in welke doelgroep zat, welke clausules
binnen een auditronde vielen — normale auditorvragen zonder antwoord. Nu één
logregel per handeling met de delta erin, met namen in plaats van id's, bewaakt
door een structurele test die rauwe koppelmutaties in `app/Livewire` tegenhoudt.
Die bevinding heeft ook de backfill van blok 12 doen intrekken.

**De schermkopie voor de auditor** (12h). Het vooraf samengestelde auditdossier
is vervallen: een auditor vraagt om een kopie van het scherm waar hij naar kijkt.
Het mechanisme staat — markdown naar Word via pandoc, een register van wat er is
meegegeven, en een kop die noemt hoeveel van hoeveel regels er in staan en op
welke filters. De SoA is het eerste scherm met de knop.

Verder: twee kennisbankpagina's (de audit trail, en alle beheercommando's), en de
deelplannen bijgewerkt voor het vervallen exportpakket.

626 tests groen.

---

## V1.9.0 — blok 12 compleet

*02-08-2026*

De drie Act-metingen op een periodevenster: scoredaling zonder onderbouwing,
overgangen naar gemitigeerd, en nieuw geïdentificeerde risico's.

- `periode_van`/`periode_tot` op de meetrij, zodat een gemiste run geen
  gebeurtenissen kost en een telling te normaliseren is, plus 'aantal' als derde
  eenheid;
- de versiebreuk bij een gewijzigde meetmethode van een handmatige KPI: de
  applicatie kan niet zien of een tekstwijziging een echte breuk is, dus vraagt
  ze het;
- de meetaanpak, de norm en haar vaststellingsstatus mee in `isms:exporteer` —
  die gaf cijfers zonder te zeggen wat er geteld was;
- per KPI alleen het jongste meetpunt open op `/meetaanpak`;
- het kennisartikel over KPI's herschreven naar negentien KPI's, normering en het
  onderscheid tussen toestand en gebeurtenis;
- in de saasdemo het bewijs bij een scoredaling gekoppeld aan het risico in
  plaats van alleen aan de afwijking.

---

## V1.8.0 — KPI-normering en KPI-beheer

*02-08-2026*

- richting als eigen vlag in plaats van afgeleid uit de eenheid;
- streefwaarde en signaalwaarde op definitie én meetrij, zodat een bijgestelde
  norm de historie niet herkleurt;
- acht nieuwe KPI's uit de inmiddels gebouwde bronblokken — zestien in totaal, en
  Act niet langer leeg;
- de meetbron los van de sleutel met een registry, en een luidruchtige exitcode
  bij een verweesde definitie;
- KPI-beheer in de applicatie, met handmatige KPI's en handmatige meetpunten;
- een meegeleverde streefwaarde als expliciet voorstel, tot de organisatie hem
  vaststelt;
- statuskleur en streefwaardelijn op het dashboard;
- de meetaanpak en de norm mee in `isms:exporteer`;
- PDCA hernoemd naar KPI's waar het een etiket was.

---

## V1.7.0 — isms:exporteer compleet

*31-07-2026*

- trainingsdeelname en leesbevestigingen achter `--met-persoonsgegevens` (dat was
  tot nu toe een lege belofte);
- issue-register §4.1 met de doorvertaling naar risico's;
- risicocriteria §6.1.2 a bovenaan de risicoparagraaf;
- bewijskoppelingen bij de entiteit;
- KPI-meethistorie, scope-interfaces en systemen;
- uitgifte van bedrijfsmiddelen A.5.11, diensten en contractclausules;
- verbeteracties onder hun besluit;
- dekkingsmatrix per auditprogramma en restrisico-jaartrend per control;
- een expliciete weglatingslijst.

---

## V1.6.0 — de simulatiemotor en het dashboard

*31-07-2026*

- `isms:demo-vul`: de simulatiemotor die 23 maanden FruitBV-scenario opbouwt;
- eigen maatregelclassificatie (04d fase 1–4) met `isms:capaciteiten`;
- auditcyclus 11c: nulmeting en programmajaar;
- grafische panelen op het dashboard (plan 12c): KPI-strip, signalen, PDCA-trend,
  risicomatrix, maatregelen per thema;
- SMTP-timeout tegen blokkerende notificaties;
- PDF- en Markdown-preview bij bewijsstukken;
- kennisbankartikel verantwoording en disclaimer;
- issue-risicokoppeling (plan 02b): §4.1 doorvertaald naar §6.1 met
  dekkingssignaal;
- losse toetsen op `/mijn-trainingen`;
- de productnaam EzISMS.

---

## V1.5.0 — export als Markdown-boom

*28-07-2026*

Testsuite-blokken en `isms:exporteer` (Markdown-boom), een refactor van
`deploy.sh` met uitsluiting van editor-artefacten, de kennisbankterm
"detailniveau", en de OTA-banner.

---

## V1.4.0 — het interne auditprogramma

*28-07-2026*

Plan 11b: de 3-jaarscyclus en de dekkingsmatrix, met de auditcyclus-tooling
(`isms:bereid-auditcyclus-voor`, `isms:verwijder-auditdata`), een live
SoA-koppeling en de audit-kennisbank.

---

## V1.3.0 — kennisbank

*27-07-2026*

De sitestructuur als SVG, en de integraties met de norm-onderbouwing.

---

## V1.2.0 — restrisico per control

*27-07-2026*

Plan 04c: rollup, jaartrend en een bewerkbare toelichting. Plus R-nummers, de
PDCA-hernoeming en de demo-seeder voor A.8.8.

---

## V1.1.0 — PDCA-weergave

*27-07-2026*

De PDCA-weergave, en de aanzet tot plan 04c (restrisico per control).

---

## V1.0.0 — de eerste zeven blokken

*27-07-2026*

De eerste uitgave, 126 commits vanaf 21-07-2026. Deze tag draagt zelf geen
bericht; wat erin zat is af te lezen aan de commits die eraan voorafgaan:

| Blok | |
|---|---|
| 1 | Identity, Access & Rollen — inclusief de autorisatiekern |
| 2 | Context & Scope — met een volledige kopie bij elke nieuwe scopeversie |
| 3 | Asset- & Informatieclassificatie |
| 4 | Risicomanagement & Statement of Applicability |
| 5 | Beleid & Maatregelbeheer |
| 6 | Bewijsrepository & Audit Trail |
| 7 | Taken- & Workflow-engine |

Plus de Laravel-scaffolding, een eigen ISMS-logo, de organisatienaam in de
zijbalk, en `x-keuzelijst` als gedeelde component.
