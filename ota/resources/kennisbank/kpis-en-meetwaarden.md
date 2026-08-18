# KPI's en meetwaarden

## Het uitgangspunt: het model meet toestand, geen beweging

De tabellen `soa_regels` en `risicos` vertellen waar de organisatie *nú* staat en
worden overschreven. Maar "werkt PDCA?" is geen vraag over een toestand, het is
het **verschil tussen twee momenten**. Dat is uit een overschreven tabel niet af
te leiden. Daarom is er een aparte, **onveranderlijke meetlaag** die periodiek een
momentopname vastlegt. De [audit trail](de-audit-trail) bevat de ruwe
veranderingen wel, maar dat is bewijs van *verandering*, geen *meting* — bruikbaar
als onderbouwing achteraf, ongeschikt als meetinstrument. Drie KPI's meten wél
rechtstreeks uit de trail; dat zijn juist de metingen over gebeurtenissen in een
periode.

## Wat er precies wordt opgeslagen

**1. De KPI-catalogus (`kpi_definities`) — dít is "de aanpak" op papier.** Per
KPI: `sleutel`, `naam`, PDCA-`fase`, `eenheid`, `richting`, een expliciete
**`berekeningswijze`** in woorden, de norm (`streefwaarde` en `signaalwaarde`) en
een **`definitie_versie`**. Dit is exact wat ISO 27001 §9.1 vraagt: vastleggen
*wát* wordt gemeten, *hoe*, *wanneer* en *waartegen*. Deze catalogus bestaat
onafhankelijk van of er al data is — je kunt 'm vandaag aan de auditor laten zien.

**2. De metingen (`metingen`) — de meetpunten zelf.** Per meetpunt: `gemeten_op`,
`teller`, `noemer`, de `definitie_versie` én de norm waarmee gemeten is, wie het
vastlegde, en een optionele `toelichting`.

Vier bewuste keuzes daarin, elk audit-relevant:

- **Teller en noemer, nooit het percentage.** We slaan "61 van 90" op, niet "68%".
  Reden: het percentage is niet te reconstrueren én de **noemer beweegt mee** — bij
  de SoA verschuift de toepasselijkheid, dus vorig jaar was de noemer misschien 84.
  Een percentage over alle 93 Annex A-maatregelen zou sowieso fout zijn, want
  alleen de toepasselijke tellen.
- **De definitieversie zit ín de meetrij.** Verandert de berekening in jaar twee,
  dan wordt die **breuk zichtbaar** in plaats van verstopt — precies waar een
  auditor doorheen prikt.
- **De norm zit ook ín de meetrij.** Elk meetpunt draagt de streefwaarde die
  tóén gold. Verlaag je volgend jaar de lat, dan kleuren twee jaar rode punten
  níét met terugwerkende kracht groen. Zonder die kopie zou een norm bijstellen
  de hele historie herschrijven, en dat is een veel lagere drempel dan het
  herschrijven van een formule.
- **Metingen zijn onveranderlijk.** Geen herberekening met terugwerkende kracht;
  een fout meetpunt corrigeer je met een nieuw meetpunt plus toelichting. Een
  cijfer dat meebeweegt als je later kijkt, is geen meting. Er is dan ook geen
  knop om een meetpunt te wijzigen of te verwijderen.

**Frequentie:** maandelijks, geautomatiseerd (`isms:meet-kpis`, gepland op de 1e
om 03:00 in `routes/console.php`). Dagelijks zou ruis zijn voor cijfers die in
maanden bewegen; jaarlijks te grof om op bij te sturen.

### Toestand of gebeurtenis

De meeste KPI's meten een **toestand**: hoeveel SoA-regels zijn nu beoordeeld.
Drie meten een **gebeurtenis**: wat is er tussen twee momenten gebeurd — hoeveel
risico's kwamen erbij, hoeveel scores daalden. Die dragen daarom `periode_van` en
`periode_tot` op de meetrij.

Het venster begint waar het vorige eindigde, en niet bij "de vorige
kalendermaand". Dat is geen detail: wordt een maandelijkse run overgeslagen, dan
mist een toestandsmeting één stip en loopt de reeks daarna gewoon door, maar bij
een gebeurtenismeting zouden de gebeurtenissen uit die maand **permanent buiten
elke meting** vallen. Nu wordt de volgende periode simpelweg langer. De keerzijde
is dat perioden dan ongelijk zijn, en daarom staat de lengte erbij: "14 in 62
dagen" is iets anders dan "14 in 31 dagen".

## Waartegen wordt het beoordeeld?

Een cijfer zonder maatstaf is geen oordeel. Elke KPI kan daarom twee grenzen
hebben:

- **`streefwaarde`** — vanaf hier is de KPI op norm.
- **`signaalwaarde`** — voorbij deze grens is het niet meer aanvaardbaar.

Samen leveren ze een semafoor in plaats van een aan-uitschakelaar. Welke kant op
"beter" is, staat als **`richting`** bij de KPI: bij *gemiddelde overschrijding in
dagen* of *openstaande bevindingen* is omlaag goed, bij de SoA-dekking omhoog.
Die vlag hoort bij de definitie en niet bij de eenheid — anders zou het dashboard
een dalend aantal open bevindingen als achteruitgang rapporteren.

De status per KPI is er dus in vier smaken: **streefwaarde gehaald**, **niet
gehaald**, **voorbij de signaalwaarde**, en **geen streefwaarde vastgesteld**.

> **Zonder streefwaarde is een KPI nooit groen.** Afwezigheid van een norm mag
> niet lezen als "op norm". Dat is de enige manier waarop dit veld schade kan
> aanrichten, en het is de fout die vanzelf ontstaat als je een lege waarde als
> nul behandelt.

### Een meegeleverde norm is een voorstel, geen beleid

Het product levert bij een aantal KPI's een streefwaarde mee. Die staat er als
**voorstel** en telt nergens mee: de KPI blijft op *geen streefwaarde
vastgesteld* staan en kleurt niet, en de waarde gaat niet mee de meetrij in.

De reden is bestuurlijk en niet technisch. Een norm die met de software meekomt,
wordt bij de eerste audit gelezen als vastgesteld beleid — en "die stond er al"
is geen antwoord op de vraag wie hem heeft vastgesteld. Andersom is een
installatie zonder enig voorstel er ook niet mee geholpen: dan staat het hele
dashboard grijs en vult niemand ooit iets in.

Eén klik op **Streefwaarde vaststellen** maakt er de norm van uw organisatie van.
Dat gaat via een bevestigingsscherm dat zegt wat het gevolg is, en de handeling
belandt in de audit trail — zodat "wie heeft deze norm vastgesteld, en wanneer"
een antwoord heeft. Bestaande meetpunten blijven `onbepaald`: die zijn gemeten
toen er nog geen norm was, en dat verandert niet met terugwerkende kracht.

Een streefwaarde die u zelf intikt is meteen vastgesteld — u heeft hem gekozen.
De voorstel-status bestaat alleen voor meegeleverde waarden. Het veld leegmaken
trekt de vaststelling weer in.

## Wát er gemeten wordt

Er staan **23 KPI's** in de catalogus, verdeeld over de vier PDCA-fasen:

| Fase | KPI's |
|---|---|
| **Plan** | SoA-regels beoordeeld · toepasselijke regels met actief beleid · risico's met eigenaar én behandelplan · risico's boven de drempel mét behandeling |
| **Do** | toepasselijke regels geïmplementeerd · geïmplementeerde regels mét bewijs · trainingsgraad verplichte modules |
| **Check** | SoA-regels binnen termijn herbeoordeeld · risico's binnen termijn herbeoordeeld · beheerde taken op tijd afgerond · **gemiddelde overschrijding in dagen** · context binnen de herzieningstermijn · openstaande auditbevindingen · dagen sinds de laatste interne audit · externe meldingen binnen de wettelijke termijn · wijzigingen geslaagd · uitvoering met vastgelegd terugvalplan · spoedwijzigingen achteraf goedgekeurd |
| **Act** | corrigerende maatregelen op tijd voltooid · gemiddelde doorlooptijd corrigerende maatregelen · scoredalingen zonder onderbouwing · statusovergangen naar gemitigeerd · nieuw geïdentificeerde risico's |

Het zwaartepunt ligt op **Check**: dat meet of de cyclus drááit, niet of er ooit
iets is gepland. *Gemiddelde overschrijding in dagen* is waarschijnlijk het
eerlijkste cijfer in het hele ISMS — het registreert zowel `deadline` als
`voltooid_op`, dus het is gratis, moeilijk te masseren, en het meet **gedrag in
plaats van intentie**.

Twee KPI's verdienen een waarschuwing vooraf:

- **Geïmplementeerde regels mét bewijs begint rond 0%.** Dat is geen fout in de
  meting: het meet de keten *maatregel → bewijsstuk*, en die keten is in een vers
  ISMS nergens gelegd. Zet er pas een streefwaarde op als u besluit hem te gaan
  leggen.
- **Risico's boven de drempel** schrijft géén meetpunt weg als er geen enkel
  risico boven de acceptatiedrempel staat. Dat is een normale toestand, en 100%
  zou suggereren dat er iets goed ging.

## Berekend of handmatig

Er zijn twee soorten KPI, en het verschil is wie het rekenwerk doet.

**Berekende KPI's** wijzen een **meetbron** aan uit een vaste lijst. Die lijst
staat in de code (`App\Support\Meetbronnen`); het maandelijkse commando kiest de
juiste en schrijft teller en noemer weg. Een nieuwe berekening toevoegen vraagt
dus een aanpassing van de applicatie. Dat is een bewuste grens: een vrij
formule- of queryveld zou de berekeningswijze onreviewbaar maken, en juist die
reviewbaarheid is wat §9.1 wil.

**Handmatige KPI's** rekent de applicatie niet uit. Meet u iets buiten het ISMS —
de klikratio van een phishingsimulatie, patches binnen SLA, meldingen bij de
servicedesk — dan maakt u een KPI zonder meetbron aan en voert u per periode zelf
teller en noemer in. De regels zijn dezelfde als bij het commando: één meetpunt
per maand, geen datum in de toekomst, en een noemer van minstens 1 (noemer 0
betekent "geen populatie", en dat is geen meting maar een lege rij die als 0%
leest). Bij elk handmatig meetpunt wordt vastgelegd **wie** het invoerde.

Een berekende KPI krijgt geen handmatige meetpunten. Een reeks met beide erin is
niet meer reproduceerbaar: het commando slaat die maand over en achteraf is niet
te zien welk punt waar vandaan kwam.

> **Een handmatige KPI die niemand invult, valt stil** — en op het trendpaneel is
> dat niet te onderscheiden van "meet nog niet". Het dashboard meldt daarom
> expliciet welke handmatige KPI's twee perioden zonder meetpunt zitten.

## De catalogus beheren — en wat dat kost

De CISO beheert de KPI-catalogus in de applicatie zelf (menu **KPI's**). Niet
alles is even vrij, en dat is de kern:

| Veld | Wijzigbaar | Gevolg |
|---|---|---|
| naam, fase, streefwaarde, signaalwaarde, actief | ja | geen — de historie draagt haar eigen norm |
| berekeningswijze | ja | geen bij een berekende KPI; bij een handmatige KPI wordt gevraagd of de méthode veranderde |
| meetbron, richting | ja | **definitieversie + 1** |
| eenheid | alleen zolang er geen meetpunt is | — |
| sleutel | nee | — |

De **definitieversie loopt automatisch op** en is niet met de hand in te stellen.
Niemand denkt bij het bijstellen van een berekening aan de vergelijkbaarheid van
zijn reeks; dat is precies waarvoor dat veld bestaat. Een handmatig bedienbaar
versienummer is een versienummer dat vergeten wordt.

De **eenheid ligt vast zodra er één meetpunt is**. Hij bepaalt wat teller en
noemer betekenen — percentage of gemiddelde — en een reeks waarin dat halverwege
omslaat is onherstelbaar gemengd. Dan hoort het een nieuwe KPI te zijn.

Een KPI **mét metingen is niet te verwijderen**. Op inactief zetten doet wat u
bedoelt (stoppen met meten) zonder de historie te vernietigen. Een KPI zonder
metingen mag wel weg.

### De vraag bij een handmatige KPI

Bij een berekende KPI is de `berekeningswijze` proza *over* code: de tekst
herformuleren verandert de berekening niet. Bij een handmatige KPI is er geen
code, dus die tekst **ís** de meetmethode — het is de enige plek waar staat hoe
teller en noemer tot stand komen.

De applicatie kan niet zien of een gewijzigde tekst een echte breuk is: een
spelfout herstellen en overstappen op een andere simulatietool zien er in de
database identiek uit. Daarom komt bij het opslaan één vraag: **is de meetmethode
zelf veranderd?**

- **Ja** → de definitieversie gaat omhoog en de reeks toont de breuk.
- **Alleen de formulering** → de reeks blijft ononderbroken, maar die keuze wordt
  wél in de audit trail vastgelegd. Een weggeklikte breuk hoort terug te vinden
  te zijn.

De knop "Ja" staat voorop. Ten onrechte een breuk melden kost een auditorvraag
met een saai antwoord; ten onrechte géén breuk melden laat twee onvergelijkbare
perioden als één trend lezen.

## Een tweede meetstroom: restrisico per control

Naast de KPI-metingen is er een tweede onveranderlijke meetwaarde, met dezelfde
filosofie maar een ander detailniveau: per Annex A-control het **max netto-restrisico**
van de gekoppelde risico's, **jaarlijks** vastgelegd (`restrisico_snapshots`,
`isms:leg-restrisico-vast`). Ook hier zijn de cijfers bevroren en worden ze nooit
herrekend, zit er een `definitie_versie` in de rij, en legt de `toelichting` de
*reden van de beweging* vast (gemitigeerd / herscoord / risico afgevoerd). Alleen
die toelichting mag achteraf bewerkt worden — de getallen niet. De uitwerking
staat in het kennisartikel
[De SoA onderbouwen: van 'ja' tot restrisico](soa-onderbouwen-en-restrisico).

## In de applicatie

**Menu KPI's** (`/meetaanpak`) — de catalogus: per KPI de fase, de
berekeningswijze, de norm, de definitieversie en de vastgelegde meetpunten. Dit
is de "aanpak op papier" die u ook zonder trend al aan de auditor kunt tonen. Per
KPI staat het jongste meetpunt open; de historie zit eronder. Elke meetrij toont
de norm die tóén gold en wie hem vastlegde.

**Dashboard** — drie panelen op basis van dezelfde cijfers:

- **Kerncijfers**: vier tegels met de huidige waarde, teller/noemer, de
  streefwaarde en het oordeel, plus de verandering over twaalf maanden. De
  selectie loopt over *slechtste status eerst*, met één vaste tegel zodat de
  strip niet elke maand van samenstelling wisselt. Drie groene tegels naast
  elkaar is een reclamefolder, geen meting.
- **Signalen**: niet de score maar de afwijking — KPI's voorbij hun
  signaalwaarde, terugvallen, stilgevallen handmatige KPI's, en nadrukkelijk ook
  een *positief* signaal bij een dip die zich herstelde.
- **PDCA-trend**: één minidiagram per KPI, gegroepeerd per fase, met de
  streefwaarde als gestreepte lijn. Elk paneel heeft een eigen as en er is
  nergens een tweede y-as — die zou een verband suggereren dat niet in de data
  zit. Onder elk diagram staat de reeks ook als tabel, zodat geen enkele waarde
  alleen met een muis te lezen is.

**Export.** `php artisan isms:exporteer` neemt in
`08-meten-en-directiebeoordeling.md` de **volledige meethistorie** mee, met per
meetpunt wie het vastlegde (leeg = berekend). Meethistorie is achteraf niet te
reconstrueren, dus hij hoort mee te gaan als u uw ISMS naar een ander systeem
overzet.

**Restrisico.** De tweede meetstroom staat bij de SoA: het tabblad
**Restrisico-trend** toont per control het restrisico over de jaren. De actuele
stand staat als kolom **Restrisico** op de SoA zelf.

## Wat er (nog) niet is

- **Historie van vóór de ingebruikname.** Er is geen reconstructie van
  meetpunten uit het verleden, alleen wat `isms:meet-kpis` sinds de ingebruikname
  heeft vastgelegd. Die reconstructie stond op de planning en is **ingetrokken**:
  koppelingen tussen tabellen (welk beleid dekt welke maatregel, wie zit in welke
  doelgroep) werden niet in de audit trail vastgelegd, waardoor een deel van de
  KPI's er principieel niet uit te herleiden is. Een reeks waarin sommige punten
  echt zijn en andere geschat, zonder dat u aan de grafiek ziet welke welke is,
  is slechter dan een reeks die eerlijk kort is.

  Dat lek is inmiddels gedicht — koppelingen wijzigen komt nu wél in de audit
  trail, met wie en wanneer. Dat werkt vooruit, niet terug: voor koppelingen die
  vóór 3 augustus 2026 zijn gelegd is er nog steeds geen datum en geen naam, en
  van koppelingen die toen zijn weggehaald bestaat geen enkel spoor. De
  reconstructie komt daar niet mee terug; toekomstige perioden zijn er wel mee
  te onderbouwen.
- **Een readiness-score.** Die is ontworpen en vervolgens **ingetrokken**. Eén
  samengesteld cijfer voor "zijn we certificeerbaar" vraagt een weging, en die
  weging bepaalt welke slechte KPI wegvalt tegen welke goede — precies de knop
  waar u de hand niet op wilt hebben liggen als de auditor ernaar vraagt. De
  onderliggende vraag, *waar schort het*, wordt beantwoord per KPI tegen een norm
  die uw organisatie zelf heeft vastgesteld. Zonder weging, zonder totaalcijfer.
- **Uitsplitsing per doelgroep of organisatie-eenheid.** Het model kent één
  teller en noemer per KPI; de trainingsgraad is daarom geaggregeerd. De
  uitsplitsing staat op het trainingsscherm.

## Twee valkuilen — ze laten zien dat de meting serieus is

- **Een dalende risicoscore is te sturen** (verlaag `kans_niveau` en het lijkt te
  werken). Daarom is *score-daling zónder gekoppeld bewijs in dezelfde periode*
  zelf een meting — een signaal, geen prestatie. Die meting bestaat sinds `12g`
  als `scoredaling_zonder_bewijs`. Ze begint op 100%: er hangt in een vers ISMS
  geen bewijs aan risico's, dus ze meet eerst de afwezigheid van die gewoonte en
  pas daarna het poetsen van cijfers.
- **Monotone verbetering is verdacht.** Een register waarin nooit een risico
  omhooggaat, is een register waar niemand serieus naar kijkt. Wijs juist op
  **variantie**: opwaartse herbeoordelingen en nieuwe risico's zijn bewijs dát
  Check gebeurt. Om diezelfde reden telt een reeks die inzakt en zich herstelt op
  het signalenpaneel als *positief*.
