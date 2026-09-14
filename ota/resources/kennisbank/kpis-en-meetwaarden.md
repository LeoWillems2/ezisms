# KPI's en meetwaarden

## Het uitgangspunt: het model meet toestand, geen beweging

De tabellen `soa_regels` en `risicos` beschrijven waar de organisatie op dit
moment staat, en hun inhoud wordt overschreven. De vraag "werkt PDCA?" gaat
echter niet over een toestand. Die vraag gaat over het **verschil tussen twee
momenten**, en dat verschil is uit een overschreven tabel niet af te leiden.
Daarom is er een aparte, **onveranderlijke meetlaag** die periodiek een
momentopname vastlegt. De [audit trail](de-audit-trail) bevat de ruwe
veranderingen wel, maar die veranderingen zijn bewijs van *verandering* en geen
*meting*. De audit trail is bruikbaar als onderbouwing achteraf, maar ongeschikt
als meetinstrument. Drie KPI's meten wel rechtstreeks uit de audit trail. Dat
zijn de metingen over gebeurtenissen in een periode.

## Wat er precies wordt opgeslagen

**1. De KPI-catalogus (`kpi_definities`) legt de aanpak op papier vast.** Per
KPI staan er een `sleutel`, een `naam`, een PDCA-`fase`, een `eenheid`, een
`richting`, een expliciete **`berekeningswijze`** in woorden, de norm
(`streefwaarde` en `signaalwaarde`) en een **`definitie_versie`**. Dit is wat
ISO 27001 §9.1 vraagt: vastleggen wat wordt gemeten, hoe, wanneer en waartegen.
De catalogus bestaat onafhankelijk van de vraag of er al data is. De organisatie
kan hem dus direct aan de auditor tonen.

**2. De metingen (`metingen`) bevatten de meetpunten zelf.** Elk meetpunt heeft
een `gemeten_op`, een `teller`, een `noemer`, de `definitie_versie`, de norm
waarmee gemeten is, de persoon die het meetpunt vastlegde en een optionele
`toelichting`.

In dit ontwerp zitten vier bewuste keuzes, en elke keuze is relevant voor een
audit:

- **Het systeem slaat teller en noemer op, nooit het percentage.** Er staat "61
  van 90" en niet "68%". Het percentage alleen is niet te reconstrueren, en de
  **noemer beweegt mee**. Bij de SoA verschuift de toepasselijkheid, waardoor de
  noemer vorig jaar bijvoorbeeld 84 kan zijn geweest. Een percentage over alle 93
  Annex A-maatregelen zou bovendien onjuist zijn, omdat alleen de toepasselijke
  maatregelen meetellen.
- **De definitieversie staat in de meetrij.** Als de berekening in jaar twee
  verandert, wordt die **breuk zichtbaar** in plaats van verborgen. Een auditor
  controleert juist dat punt.
- **De norm staat ook in de meetrij.** Elk meetpunt draagt de streefwaarde die op
  het moment van meten gold. Als de lat een jaar later lager wordt gelegd, worden
  twee jaar rode punten niet met terugwerkende kracht groen. Zonder die kopie zou
  het bijstellen van een norm de hele historie herschrijven. Een norm bijstellen
  is een veel kleinere stap dan een formule herschrijven, dus dat risico is
  groter.
- **Metingen zijn onveranderlijk.** Er is geen herberekening met terugwerkende
  kracht. Een fout meetpunt wordt gecorrigeerd met een nieuw meetpunt en een
  toelichting. Een cijfer dat verandert wanneer het later wordt bekeken, is geen
  meting. Het systeem heeft daarom geen knop om een meetpunt te wijzigen of te
  verwijderen.

**Frequentie:** de meting draait maandelijks en geautomatiseerd
(`isms:meet-kpis`, gepland op de 1e van de maand om 03:00 in
`routes/console.php`). Een dagelijkse meting zou ruis opleveren voor cijfers die
over maanden bewegen. Een jaarlijkse meting is te grof om op bij te sturen.

### Toestand of gebeurtenis

De meeste KPI's meten een **toestand**, bijvoorbeeld hoeveel SoA-regels op dit
moment zijn beoordeeld. Drie KPI's meten een **gebeurtenis**: wat er tussen twee
momenten is gebeurd, zoals hoeveel risico's erbij kwamen of hoeveel scores
daalden. Die KPI's hebben daarom een `periode_van` en een `periode_tot` op de
meetrij.

Het meetvenster begint waar het vorige venster eindigde, en niet bij het begin van
de vorige kalendermaand. Dat onderscheid is belangrijk. Als een maandelijkse run
wordt overgeslagen, mist een toestandsmeting één punt en loopt de reeks daarna
door. Bij een gebeurtenismeting zouden de gebeurtenissen uit die maand dan
**permanent buiten elke meting** vallen. In het huidige ontwerp wordt de volgende
periode langer. Het nadeel is dat perioden ongelijk van lengte zijn. Daarom staat
de lengte erbij: "14 in 62 dagen" is iets anders dan "14 in 31 dagen".

## Waartegen wordt het beoordeeld?

Een cijfer zonder maatstaf levert geen oordeel op. Elke KPI kan daarom twee
grenzen hebben:

- **`streefwaarde`**: vanaf deze waarde is de KPI op norm.
- **`signaalwaarde`**: voorbij deze grens is de waarde niet meer aanvaardbaar.

Samen leveren deze grenzen een semafoor op in plaats van een aan-uitschakelaar.
De richting waarin een waarde beter wordt, staat als **`richting`** bij de KPI.
Bij *gemiddelde overschrijding in dagen* en bij *openstaande bevindingen* is een
lagere waarde beter, bij de SoA-dekking een hogere. Die vlag hoort bij de
definitie en niet bij de eenheid. Anders zou het dashboard een dalend aantal open
bevindingen als achteruitgang rapporteren.

Een KPI heeft daardoor een van vier statussen: **streefwaarde gehaald**, **niet
gehaald**, **voorbij de signaalwaarde** of **geen streefwaarde vastgesteld**.

> **Zonder streefwaarde is een KPI nooit groen.** De afwezigheid van een norm mag
> niet worden gelezen als "op norm". Dit is de enige manier waarop dit veld schade
> kan aanrichten, en de fout ontstaat vanzelf wanneer een lege waarde als nul wordt
> behandeld.

### Een meegeleverde norm is een voorstel, geen beleid

Het product levert bij een aantal KPI's een streefwaarde mee. Die streefwaarde is
een **voorstel** en telt nergens mee. De KPI blijft op *geen streefwaarde
vastgesteld* staan en krijgt geen kleur, en de waarde wordt niet in de meetrij
opgenomen.

De reden is bestuurlijk en niet technisch. Een norm die met de software wordt
meegeleverd, wordt bij de eerste audit gelezen als vastgesteld beleid. De
opmerking dat de waarde er al stond, beantwoordt niet de vraag wie de norm heeft
vastgesteld. Een installatie zonder enig voorstel helpt de organisatie echter ook
niet. Dan is het hele dashboard grijs, en in de praktijk vult niemand de waarden
dan ooit in.

Eén klik op **Streefwaarde vaststellen** maakt van het voorstel de norm van de
organisatie. Die handeling loopt via een bevestigingsscherm dat het gevolg
beschrijft, en de handeling wordt vastgelegd in de audit trail. De vraag wie de
norm heeft vastgesteld en wanneer, heeft daardoor een antwoord. Bestaande
meetpunten blijven `onbepaald`. Die meetpunten zijn gemeten toen er nog geen norm
was, en dat verandert niet met terugwerkende kracht.

Een streefwaarde die de gebruiker zelf invoert, is direct vastgesteld, omdat de
gebruiker de waarde zelf heeft gekozen. De voorstelstatus bestaat alleen voor
meegeleverde waarden. Het leegmaken van het veld trekt de vaststelling weer in.

## Wat er gemeten wordt

Er staan **23 KPI's** in de catalogus, verdeeld over de vier PDCA-fasen:

| Fase | KPI's |
|---|---|
| **Plan** | SoA-regels beoordeeld · toepasselijke regels met actief beleid · risico's met eigenaar én behandelplan · risico's boven de drempel mét behandeling |
| **Do** | toepasselijke regels geïmplementeerd · geïmplementeerde regels mét bewijs · trainingsgraad verplichte modules |
| **Check** | SoA-regels binnen termijn herbeoordeeld · risico's binnen termijn herbeoordeeld · beheerde taken op tijd afgerond · **gemiddelde overschrijding in dagen** · context binnen de herzieningstermijn · openstaande auditbevindingen · dagen sinds de laatste interne audit · externe meldingen binnen de wettelijke termijn · wijzigingen geslaagd · uitvoering met vastgelegd terugvalplan · spoedwijzigingen achteraf goedgekeurd |
| **Act** | corrigerende maatregelen op tijd voltooid · gemiddelde doorlooptijd corrigerende maatregelen · scoredalingen zonder onderbouwing · statusovergangen naar gemitigeerd · nieuw geïdentificeerde risico's |

Het zwaartepunt ligt op **Check**, omdat die fase meet of de cyclus werkelijk
draait en niet alleen of er ooit iets is gepland. *Gemiddelde overschrijding in
dagen* is waarschijnlijk het eerlijkste cijfer in het hele ISMS. Het systeem
registreert zowel `deadline` als `voltooid_op`, waardoor de meting niets extra
kost en moeilijk te manipuleren is. Deze KPI meet **gedrag in plaats van
intentie**.

Bij twee KPI's is vooraf een waarschuwing nodig:

- **Geïmplementeerde regels mét bewijs begint rond 0%.** Dat is geen fout in de
  meting. De KPI meet de keten *maatregel → bewijsstuk*, en in een nieuw ISMS is
  die keten nog nergens gelegd. Een streefwaarde op deze KPI is pas zinvol
  wanneer de organisatie besluit die keten te gaan leggen.
- **Risico's boven de drempel** schrijft geen meetpunt weg als er geen enkel
  risico boven de acceptatiedrempel staat. Dat is een normale toestand, en een
  waarde van 100% zou ten onrechte suggereren dat er iets goed ging.

## Berekend of handmatig

Er zijn twee soorten KPI. Het verschil zit in wie het rekenwerk doet.

**Berekende KPI's** verwijzen naar een **meetbron** uit een vaste lijst. Die lijst
staat in de code (`App\Support\Meetbronnen`). Het maandelijkse commando kiest de
juiste meetbron en schrijft teller en noemer weg. Een nieuwe berekening toevoegen
vereist dus een aanpassing van de applicatie. Dat is een bewuste grens. Een vrij
formule- of queryveld zou de berekeningswijze onreviewbaar maken, en §9.1 vraagt
juist om die reviewbaarheid.

**Handmatige KPI's** worden niet door de applicatie berekend. Voor metingen
buiten het ISMS, zoals de klikratio van een phishingsimulatie, patches binnen de
SLA of meldingen bij de servicedesk, wordt een KPI zonder meetbron aangemaakt. De
gebruiker voert dan per periode zelf teller en noemer in. Daarvoor gelden
dezelfde regels als bij het commando: één meetpunt per maand, geen datum in de
toekomst en een noemer van minstens 1. Een noemer van 0 betekent "geen
populatie". Dat is geen meting, maar een lege rij die als 0% wordt gelezen. Bij
elk handmatig meetpunt legt het systeem vast **wie** het invoerde.

Een berekende KPI krijgt geen handmatige meetpunten. Een reeks die beide soorten
bevat, is niet meer reproduceerbaar: het commando slaat die maand over, en
achteraf is niet te zien welk punt waar vandaan kwam.

> **Een handmatige KPI die niemand invult, valt stil.** Op het trendpaneel is dat
> niet te onderscheiden van een KPI die nog niet meet. Het dashboard meldt daarom
> expliciet welke handmatige KPI's twee perioden geen meetpunt hebben gekregen.

## De catalogus beheren — en wat dat kost

De CISO beheert de KPI-catalogus in de applicatie zelf (menu **KPI's**). Niet elk
veld is even vrij te wijzigen, en dat onderscheid is de kern van het beheer:

| Veld | Wijzigbaar | Gevolg |
|---|---|---|
| naam, fase, streefwaarde, signaalwaarde, actief | ja | Geen gevolg. De historie draagt haar eigen norm. |
| berekeningswijze | ja | Geen gevolg bij een berekende KPI. Bij een handmatige KPI vraagt het systeem of de methode is veranderd. |
| meetbron, richting | ja | **definitieversie + 1** |
| eenheid | alleen zolang er geen meetpunt is | — |
| sleutel | nee | — |

De **definitieversie loopt automatisch op** en is niet handmatig in te stellen.
Bij het bijstellen van een berekening denkt vrijwel niemand aan de
vergelijkbaarheid van de reeks, en voor precies die situatie bestaat dit veld.
Een versienummer dat handmatig moet worden bijgewerkt, wordt vergeten.

De **eenheid ligt vast zodra er één meetpunt is**. De eenheid bepaalt wat teller
en noemer betekenen, bijvoorbeeld een percentage of een gemiddelde. Een reeks
waarin die betekenis halverwege omslaat, is onherstelbaar gemengd. In dat geval
hoort het een nieuwe KPI te zijn.

Een KPI **met metingen is niet te verwijderen**. Een KPI op inactief zetten stopt
het meten zonder de historie te vernietigen. Een KPI zonder metingen mag wel
worden verwijderd.

### De vraag bij een handmatige KPI

Bij een berekende KPI beschrijft de `berekeningswijze` de code in gewone taal.
Het herformuleren van die tekst verandert de berekening niet. Bij een handmatige
KPI is er geen code, en is die tekst dus de meetmethode zelf. Het is de enige
plek waar staat hoe teller en noemer tot stand komen.

De applicatie kan niet vaststellen of een gewijzigde tekst een echte breuk is.
Het herstellen van een spelfout en de overstap naar een andere simulatietool zien
er in de database identiek uit. Daarom stelt het systeem bij het opslaan één
vraag: **is de meetmethode zelf veranderd?**

- **Ja**: de definitieversie gaat omhoog en de reeks toont de breuk.
- **Alleen de formulering**: de reeks blijft ononderbroken, maar het systeem legt
  die keuze wel vast in de audit trail. Een weggeklikte breuk hoort terug te
  vinden te zijn.

De knop "Ja" staat voorop. Een onterecht gemelde breuk kost een vraag van de
auditor met een eenvoudig antwoord. Een onterecht niet gemelde breuk laat twee
onvergelijkbare perioden als één trend lezen.

## Een tweede meetstroom: restrisico per control

Naast de KPI-metingen is er een tweede onveranderlijke meetwaarde. Die volgt
dezelfde filosofie, maar op een ander detailniveau. Per Annex A-control legt het
systeem **jaarlijks** het **max netto-restrisico** van de gekoppelde risico's vast
(`restrisico_snapshots`, `isms:leg-restrisico-vast`). Ook hier zijn de cijfers
bevroren en worden ze nooit herrekend. De rij bevat een `definitie_versie`, en de
`toelichting` legt de *reden van de beweging* vast (gemitigeerd / herscoord /
risico afgevoerd). Alleen die toelichting mag achteraf worden bewerkt; de getallen
niet. De uitwerking staat in het kennisartikel
[De SoA onderbouwen: van 'ja' tot restrisico](soa-onderbouwen-en-restrisico).

## In de applicatie

**Menu KPI's** (`/meetaanpak`) toont de catalogus: per KPI de fase, de
berekeningswijze, de norm, de definitieversie en de vastgelegde meetpunten. Dit
is de "aanpak op papier" die ook zonder trend al aan de auditor te tonen is. Per
KPI staat het jongste meetpunt open, met de historie eronder. Elke meetrij toont
de norm die op het moment van meten gold en de persoon die het meetpunt vastlegde.

**Dashboard.** Het dashboard heeft drie panelen die op dezelfde cijfers zijn
gebaseerd:

- **Kerncijfers** toont vier tegels met de huidige waarde, teller en noemer, de
  streefwaarde en het oordeel, plus de verandering over 12 maanden. De selectie
  toont de slechtste status eerst. Eén tegel staat vast, zodat de strip niet elke
  maand van samenstelling wisselt. Drie groene tegels naast elkaar zouden een
  reclamefolder zijn en geen meting.
- **Signalen** toont niet de score maar de afwijking: KPI's voorbij hun
  signaalwaarde, terugvallen, stilgevallen handmatige KPI's, en nadrukkelijk ook
  een *positief* signaal bij een dip die zich herstelde.
- **PDCA-trend** toont één minidiagram per KPI, gegroepeerd per fase, met de
  streefwaarde als gestreepte lijn. Elk diagram heeft een eigen as, en nergens is
  een tweede y-as gebruikt. Een tweede y-as zou een verband suggereren dat niet in
  de data zit. Onder elk diagram staat de reeks ook als tabel, zodat geen enkele
  waarde alleen met een muis af te lezen is.

**Export.** `php artisan isms:exporteer` neemt in
`08-meten-en-directiebeoordeling.md` de **volledige meethistorie** mee, met per
meetpunt de persoon die het vastlegde (leeg betekent berekend). Meethistorie is
achteraf niet te reconstrueren. Daarom hoort de meethistorie mee te gaan wanneer
de organisatie het ISMS naar een ander systeem overzet.

**Restrisico.** De tweede meetstroom staat bij de SoA. Het tabblad
**Restrisico-trend** toont per control het restrisico over de jaren. De actuele
stand staat als kolom **Restrisico** op de SoA zelf.

## Wat er (nog) niet is

- **Historie van vóór de ingebruikname.** Het systeem reconstrueert geen
  meetpunten uit het verleden. Er is alleen wat `isms:meet-kpis` sinds de
  ingebruikname heeft vastgelegd. Die reconstructie stond op de planning en is
  **ingetrokken**. Koppelingen tussen tabellen, zoals welk beleid welke maatregel
  dekt en wie in welke doelgroep zit, werden niet in de audit trail vastgelegd.
  Daardoor is een deel van de KPI's er principieel niet uit te herleiden. Een
  reeks waarin sommige punten echt zijn en andere geschat, zonder dat de grafiek
  laat zien welke punten welke zijn, is slechter dan een reeks die eerlijk kort
  is.

  Dat lek is inmiddels gedicht. Wijzigingen in koppelingen komen nu wel in de
  audit trail, met de persoon en het tijdstip. Dat werkt alleen vooruit. Voor
  koppelingen die vóór 3 augustus 2026 zijn gelegd, is er nog steeds geen datum en
  geen naam, en van koppelingen die toen zijn verwijderd, bestaat geen enkel spoor.
  De reconstructie komt daarmee niet terug, maar toekomstige perioden zijn er wel
  mee te onderbouwen.
- **Een readiness-score.** Die score is ontworpen en vervolgens **ingetrokken**.
  Eén samengesteld cijfer voor de vraag of de organisatie certificeerbaar is,
  vereist een weging. Die weging bepaalt welke slechte KPI wegvalt tegen welke
  goede KPI. Juist over die instelling wil een organisatie geen discussie hebben
  wanneer de auditor ernaar vraagt. De onderliggende vraag, namelijk waar het
  schort, wordt per KPI beantwoord tegen een norm die de organisatie zelf heeft
  vastgesteld. Er is geen weging en geen totaalcijfer.
- **Uitsplitsing per doelgroep of organisatie-eenheid.** Het model kent één teller
  en één noemer per KPI. De trainingsgraad is daarom geaggregeerd. De uitsplitsing
  staat op het trainingsscherm.

## Twee valkuilen — ze laten zien dat de meting serieus is

- **Een dalende risicoscore is te sturen.** Wie `kans_niveau` verlaagt, laat het
  lijken alsof een maatregel werkt. Daarom is *score-daling zonder gekoppeld bewijs
  in dezelfde periode* zelf een meting. Die meting is een signaal en geen
  prestatie. De meting bestaat sinds `12g` als `scoredaling_zonder_bewijs`. Ze
  begint op 100%, omdat er in een nieuw ISMS nog geen bewijs aan risico's hangt.
  De meting meet daarom eerst de afwezigheid van die gewoonte, en pas daarna het
  opschonen van cijfers.
- **Een monotone verbetering is verdacht.** Een register waarin nooit een risico
  omhooggaat, is een register waar niemand serieus naar kijkt. **Variantie** is
  juist een goed teken: opwaartse herbeoordelingen en nieuwe risico's zijn bewijs
  dat de Check-fase plaatsvindt. Om dezelfde reden telt een reeks die inzakt en
  zich herstelt op het signalenpaneel als *positief*.
