# Een KPI opzetten: een uitgewerkt voorbeeld

KPI's zijn voor een beginnende CISO lastig, en dat komt zelden door de knoppen.
De vragen die vastlopen zijn inhoudelijk: wát moet er in het veld
*berekeningswijze*, welke streefwaarde is verdedigbaar als de organisatie er nog
lang niet is, en wat vult iemand elke maand precies in bij een meetpunt?

Dit artikel loopt één KPI van begin tot eind door aan de hand van een verzonnen
casus. De achtergrond bij de keuzes in het model staat in
[KPI's en meetwaarden](kpis-en-meetwaarden); hier gaat het om de handelingen.

## De casus

FruitBV-medewerkers hebben USB-sticks nodig en verliezen die regelmatig: **40 per
maand**, en dus ook 40 incidentmeldingen per maand. Er loopt een programma dat de
sticks vervangt door een oplossing zonder USB, maar dat is pas over **twee jaar**
volledig uitgerold. Ondertussen wil FruitBV kunnen aantonen dat het verlies
daadwerkelijk afneemt. De uitrol levert naar verwachting **twee incidenten minder
per maand** op.

## Eerst de vraag, dan het veld

De verleiding is om meteen op *Nieuwe KPI* te klikken. Drie vragen vooraf bepalen
zes van de acht velden.

**1. Welke besluitvraag beantwoordt dit cijfer?**
"Loopt de vervanging van USB-sticks op schema, gemeten aan het effect dat ervan
verwacht wordt?" Dat is een andere vraag dan "hoeveel procent is uitgerold" — die
zou de voortgang van het *project* meten en niet het effect op het *risico*. Een
KPI die projectvoortgang meet, staat op groen op de dag dat de laatste laptop is
omgezet, ook als er nog steeds sticks verdwijnen.

**2. Wat is de bron, en is die betrouwbaar genoeg?**
Het incidentregister. Dat is hier een gelukkige omstandigheid: elk verlies levert
al een melding op, dus de meting kost niets extra's en is achteraf te
controleren — 40 tellingen zijn 40 aanwijsbare records. Een KPI waarvan de bron
een schatting is, is geen KPI.

**3. Rekent de applicatie dit uit?**
Nee. Er is geen meetbron voor "incidenten van dit type": incidenten hebben geen
categorieveld, en de enige ingebouwde incident-KPI gaat over tijdig extern melden.
Dit wordt dus een **handmatige KPI**. Dat is geen tweederangs variant — het is de
normale route voor alles wat buiten het ISMS gemeten wordt.

> Bij een handmatige KPI is het veld **berekeningswijze** niet de toelichting bij
> de meting: het *is* de meetmethode. Nergens anders staat hoe teller en noemer
> tot stand komen. Het is dus het belangrijkste veld van het formulier.

## Stap 1 — de KPI aanmaken

Menu **KPI's** → *Nieuwe KPI*.

| Veld | Wat FruitBV invult | Waarom |
|---|---|---|
| **Naam** | `Verloren USB-sticks per maand` | Zegt wát er geteld wordt en over welke periode. Niet "USB-beleid": de naam komt op een dashboardtegel en moet zonder toelichting te lezen zijn. |
| **Meetbron** | *Handmatig — ik voer teller en noemer zelf in* | De applicatie kent deze telling niet. |
| **PDCA-fase** | **Check** | Dit meet of een genomen maatregel het beoogde effect heeft (§9.1: de doeltreffendheid van de maatregelen). De uitrol zelf is Do; de meting erover is Check. De fase bepaalt alleen de groepering op het dashboard en is later vrij te wijzigen. |
| **Eenheid** | **Aantal (telling)** | Het cijfer *is* het aantal verliezen. Geen ratio (dat is een aandeel) en geen dagen (dat is een gemiddelde). **Dit veld ligt vast zodra het eerste meetpunt bestaat.** |
| **Welke kant op is goed?** | **Omlaag — lager is beter** | Zonder dit rapporteert het dashboard een dalende reeks als achteruitgang. **Dit veld hoogt de definitieversie op bij een latere wijziging** — het hoort meteen goed te staan. |
| **Berekeningswijze** | zie hieronder | De meetmethode zelf. |
| **Streefwaarde** | **leeg laten** | Zie stap 3. |
| **Signaalwaarde** | **leeg laten** | Idem. |
| **Actief** | aangevinkt | |

De sleutel wordt automatisch afgeleid (`verloren_usb_sticks_per_maand`) en
verandert daarna nooit meer, ook niet als de naam later wordt bijgesteld. Hij is
de identiteit van de reeks in de export en de audit trail.

### De berekeningswijze, voluit

Vier dingen horen erin te staan: **bron**, **afbakening**, **telmoment** en
**wat er geteld wordt**. Zo ziet dat er voor deze KPI uit:

> Het aantal incidentmeldingen in het incidentregister met een titel die begint
> met `USB-stick verloren`, gekoppeld aan risico R-14 (*Dataverlies door verlies
> van een verwisselbare gegevensdrager*), waarvan `gemeld op` in de betreffende
> kalendermaand valt. Geteld wordt de **melding**, niet de stick: wie twee sticks
> tegelijk kwijtraakt en dat in één melding meldt, telt als één. Meldingen die na
> onderzoek geen verlies blijken (stick teruggevonden vóór sluiting) blijven
> meetellen — het incident heeft zich voorgedaan, en corrigeren met terugwerkende
> kracht maakt de reeks onbetrouwbaar. Peilmoment: de laatste dag van de maand;
> de telling wordt op de eerste werkdag daarna gedaan door de CISO. Meldingen die
> later binnendruppelen over een voorgaande maand tellen mee in de maand waarin ze
> zijn gemeld, niet in de maand van het verlies. De noemer is bij deze telling
> altijd 1.

Waarom zo uitgebreid? Omdat elke zin een keuze afdekt waarover over een jaar
niemand het meer weet, en waar een auditor precies naar vraagt. De laatste twee
zinnen zijn de belangrijkste: ze maken de telling **reproduceerbaar**. Wie
dezelfde regel volgt, komt op hetzelfde getal uit.

> **De afbakening is hier het zwakke punt.** Incidenten krijgen geen categorie
> toegewezen en het incidentregister filtert alleen op status en ernst. De
> telling leunt dus op een titelconventie en op de koppeling aan het risico —
> twee afspraken die iemand moet handhaven. Die conventie hoort vast te staan
> vóórdat de nulmeting wordt gedaan. Gebeurt dat pas na drie maanden, dan begint
> de reeks met drie punten die anders geteld zijn dan de rest.

## Stap 2 — de nulmeting vastleggen

Nu pas het eerste meetpunt, en nog steeds zonder streefwaarde. Bij de KPI staat
de knop **Meetpunt invoeren**:

| Veld | Waarde |
|---|---|
| Gemeten op | `31-08-2026` — de laatste dag van de maand waarover gemeten wordt |
| Teller | `40` |
| Noemer | `1` |
| Toelichting | *Nulmeting. Situatie vóór de uitrol; 500 sticks in omloop. Telregel vastgesteld in het MT van 20-08-2026.* |

Drie dingen om te weten:

- **De noemer is 1.** Dat hoort bij de eenheid *aantal*, niet bij "handmatig": bij
  een telling is de uitkomst de teller zelf en telt de noemer niet mee. Het veld
  blijft verplicht en 0 wordt geweigerd, want 0 betekent "geen populatie" en dat
  is iets anders dan "nul deze periode". De uitleg onder de invoervelden past zich
  aan de eenheid aan en zegt dat er ook bij. In de tabel leest de kolom *Meetpunt*
  daarom als "40 van 1"; de kolom *Uitkomst* toont `40`. Bij een handmatige KPI
  met eenheid *ratio* of *dagen* is de noemer wél een echt getal.
- **Het meetpunt is onveranderlijk.** Er is geen bewerkknop en geen prullenbak.
  Een telfout wordt gecorrigeerd in het meetpunt van de volgende maand, met een
  toelichting die zegt wat er mis was.
- **Eén meetpunt per kalendermaand**, en de datum mag niet in de toekomst liggen.
  Vandaar het peilmoment "laatste dag van de maand, ingevuld op de eerste werkdag
  daarna": dat valt binnen de maand waarover gemeten wordt en is toch pas in te
  vullen als die maand voorbij is.

Na dit meetpunt staat de KPI op **geen streefwaarde vastgesteld** (grijs). Dat is
correct: er is nog niets om aan te toetsen.

## Stap 3 — pas nu de streefwaarde

De verleiding is om bij het aanmaken meteen `0` in te vullen, het einddoel. Twee
redenen om dat niet te doen.

**De bestuurlijke reden.** Een zelf ingetikte streefwaarde geldt onmiddellijk als
**vastgesteld**: de datum van vandaag komt erbij te staan en de handeling belandt
in de audit trail. Daarmee is verklaard dat dit de norm van de organisatie is, en
dat is een uitspraak van de directie en niet van de CISO in een formulier. Het
veld hoort leeg te blijven tot het plan is vastgesteld.

**De praktische reden.** Met `0` als streefwaarde staat de KPI twintig maanden op
rood terwijl alles precies volgens plan verloopt. Naar een semafoor die twintig
maanden op rood staat kijkt niemand meer — en dan valt ook de maand niet op waarin
het écht misgaat. De streefwaarde hoort te meten wat er nú van de organisatie
verwacht wordt, niet waar die over twee jaar wil zijn.

### De streefwaarde als planlijn

Het plan van FruitBV is twee minder per maand. De streefwaarde wordt daarom het
**planniveau van deze periode**, en dat wordt **elk kwartaal opnieuw vastgesteld**
in het MT.

- **Streefwaarde** = het planniveau bij de start van het kwartaal. Op of onder dat
  niveau betekent op schema → groen.
- **Signaalwaarde** = streefwaarde + 4, oftewel twee maanden volledige stilstand.
  Voorbij die grens is het niet meer "iets minder snel dan gehoopt" maar "het
  programma levert niets op" → rood.

Bij richting *omlaag* bewaakt de applicatie dat de signaalwaarde **boven** de
streefwaarde ligt; andersom is de oranje band leeg en is elk punt groen of rood.

| Kwartaal | Streefwaarde | Signaalwaarde |
|---|---|---|
| M1–M3 | 38 | 42 |
| M4–M6 | 32 | 36 |
| M7–M9 | 26 | 30 |
| M10–M12 | 20 | 24 |
| M13–M15 | 14 | 18 |
| M16–M18 | 8 | 12 |
| M19–M21 | 2 | 6 |
| M22–M24 | 0 | 2 |

Dat de signaalwaarde in het eerste kwartaal (42) boven de nulmeting (40) ligt, is
geen fout: rood in maand 1 zou betekenen dat het ná de start van het programma
*slechter* gaat dan ervoor, en dat is inderdaad de enige echt alarmerende uitkomst
die zo vroeg mogelijk is.

> **Is dat geen goalpost-moving?** Het is het omgekeerde, mits de volgorde klopt.
> Elk meetpunt krijgt een **kopie** van de streefwaarde die op dat moment gold, en
> die verandert nooit meer mee. De kolom *Streefwaarde toen* toont dus de hele
> geschiedenis van de eigen lat. Achteraf de lat verlagen om een rood punt groen
> te maken lukt niet: het oude punt houdt zijn oude norm, en de bijstelling staat
> in de trail. Verschuiven mag; stiekem verschuiven niet.
>
> De voorwaarde is de volgorde: de nieuwe streefwaarde gaat erin **vóórdat** het
> meetpunt van de nieuwe periode wordt ingevoerd. Andersom draagt dat meetpunt nog
> de oude norm.

Het bijstellen zelf gaat via **Bewerken** → streefwaarde en signaalwaarde
aanpassen → opslaan. De vaststellingsdatum loopt automatisch mee. De
definitieversie gaat hier **niet** omhoog: de norm veranderen is iets anders dan
de meting veranderen, en de reeks blijft vergelijkbaar.

## Stap 4 — de maandelijkse routine

Elke maand, op de eerste werkdag: het incidentregister openen, tellen volgens de
regel in de berekeningswijze, en één meetpunt vastleggen. Meer is het niet.

| Maand | Gemeten op | Teller | Noemer | Streefwaarde toen | Status |
|---|---|---|---|---|---|
| M0 | 31-08-2026 | 40 | 1 | — | geen streefwaarde vastgesteld |
| M1 | 30-09-2026 | 38 | 1 | 38 | gehaald |
| M2 | 31-10-2026 | 36 | 1 | 38 | gehaald |
| M3 | 30-11-2026 | 34 | 1 | 38 | gehaald |
| M4 | 31-12-2026 | 32 | 1 | 32 | gehaald |
| … | | | | | |
| M12 | 31-08-2027 | 16 | 1 | 20 | gehaald |
| … | | | | | |
| M20 | 30-04-2028 | 0 | 1 | 2 | gehaald |

Na twaalf meetpunten toont de dashboardtegel ook de verandering over twaalf
maanden: **−24**. Die delta staat in stuks en niet in procentpunten — bij een
telling is dat hetzelfde soort getal als de waarde zelf.

### Wanneer de toelichting wordt ingevuld

Niet elke maand. Wel als het cijfer een verhaal nodig heeft dat er over een jaar
niet meer is:

- **M7, teller 30 (streefwaarde 26, niet gehaald):** *"Uitrol drie weken vertraagd
  door levering docking stations; 40 medewerkers nog niet omgezet."* Dat is het
  verschil tussen een KPI die stuurt en een KPI die alleen registreert.
- **M14, teller 18 (verwacht 12):** *"Piek door de verhuizing van vestiging Zuid;
  11 van de 18 meldingen komen uit die vestiging."* Een verklaarbare uitschieter
  hoort verklaard te zijn, niet weggepoetst.
- **Bij een correctie op een eerdere maand:** *"In M9 zijn twee meldingen dubbel
  geteld; de reeks is niet aangepast, want een meetpunt is onveranderlijk."*

### Een gemiste maand

Niets onherstelbaars — de reeks krijgt een gat en loopt daarna door. Wel meldt het
dashboard na **twee** perioden zonder meetpunt dat deze handmatige KPI is
stilgevallen. Dat signaal bestaat omdat een handmatige KPI die niemand invult op
een trendgrafiek niet te onderscheiden is van een KPI die nog niet meet. Inhalen
met een tweede meetpunt in dezelfde maand kan niet: de regel is één per
kalendermaand.

## Stap 5 — het einde van de KPI

Rond M20 staat de teller op 0 en blijft daar. De uitrol loopt nog tot M24.

- **Doormeten tot na de uitrol, en nog een paar maanden daarna.** Nul is pas
  bewijs als het een tijd nul blijft; één maand nul kan ook betekenen dat niemand
  gemeld heeft.
- **Daarna op inactief, niet verwijderen.** Een KPI met meetpunten is ook niet te
  verwijderen, en dat is opzet: de reeks is het bewijs dat de maatregel gewerkt
  heeft, en dat bewijs is nodig bij de directiebeoordeling, bij het afvoeren van
  risico R-14 en bij de eerstvolgende audit. *Inactief* stopt het meten en bewaart
  de historie.
- **De cirkel sluiten.** Deze KPI is de effectiviteitsmeting van een
  risicobehandeling. Het afvoeren of herscoren van R-14 hoort naar deze reeks te
  verwijzen — precies de onderbouwing die een scoredaling nodig heeft.

## Twee valkuilen in deze KPI

### 1. De noemer beweegt mee, maar is hier niet zichtbaar

Dit is de belangrijkste les uit de casus. Het aantal verliezen daalt om **twee**
redenen tegelijk: mensen gaan zorgvuldiger om met sticks, én er zíjn steeds minder
sticks. Die tweede reden is een prestatie van het inkoopschema en niet van de
organisatie. Een telling kan die twee niet uit elkaar houden.

Doorgerekend: bij 500 sticks in omloop en 40 verliezen verdwijnt er per maand
**8%** van de voorraad. Zijn er in M12 nog 260 sticks over, dan zou puur
mechanisch verlies bij gelijkblijvend gedrag ongeveer **21** verliezen opleveren.
FruitBV meet er 16. Van de daling van 24 is dus ruwweg 19 mechanisch en 5
gedragsverandering.

Dat betekent **niet** dat de telling de verkeerde KPI is. De telling meet
risicoblootstelling, en daar gaat het de organisatie om: 16 verloren sticks is 16
keer een mogelijk datalek, ongeacht waarom het er 16 zijn. Maar op de vraag "werkt
het bewustzijnsprogramma?" geeft deze KPI het verkeerde antwoord.

Wie die vraag ook wil beantwoorden, zet er een **tweede** KPI naast:

| Veld | Waarde |
|---|---|
| Naam | `Verlies per 100 uitgegeven USB-sticks` |
| Meetbron | handmatig |
| Fase | Check |
| Eenheid | **Ratio (percentage)** |
| Richting | omlaag |
| Teller | verloren sticks in de maand (16) |
| Noemer | **sticks in omloop op de laatste dag van de maand** (260) |
| Streefwaarde | 8% — het niveau van de nulmeting |

Uitkomst M0: 8,0%. Uitkomst M12: 6,2%. Dát is het gedragssignaal, en dan is 8% als
streefwaarde ook logisch: alles onder de beginstand betekent dat het niet alleen
aan de krimpende voorraad ligt. Voorwaarde is wel dat de voorraad maandelijks
betrouwbaar is vast te stellen; kan dat niet, dan is deze KPI beter weg te laten —
een verzonnen noemer is erger dan geen tweede KPI. Bij een ratio bewaakt de
applicatie dat de teller niet groter is dan de noemer.

### 2. Deze KPI wordt vanzelf groen

De uitrol drukt het cijfer omlaag, ook als niemand verder iets doet. Een KPI die
zichzelf haalt is geen stuurinstrument maar een voortgangsrapportage. Twee
tegenwichten:

- De streefwaarde is een **planlijn** en geen einddoel. Daardoor betekent groen
  "we liggen op schema" in plaats van "het gaat de goede kant op", en kan de KPI
  wél rood worden terwijl de absolute cijfers dalen. Dat is het hele punt van de
  kwartaalherijking.
- Kijk naar **variantie**, niet alleen naar de richting. Een reeks die kaarsrecht
  40-38-36-34 loopt is verdacht: zulke gladde reeksen komen in werkelijkheid niet
  voor. Ze wijzen meestal op iemand die het planniveau invult in plaats van het
  incidentregister te tellen. Een reeks die schommelt, inzakt en zich herstelt is
  béter bewijs dat er echt gemeten wordt.

## Samengevat

**Eenmalig, bij aanmaken:** naam, meetbron *handmatig*, fase *Check*, eenheid
*aantal*, richting *omlaag*, en een berekeningswijze die bron, afbakening,
telmoment en teleenheid benoemt. Streefwaarde en signaalwaarde bewust leeg.

**Eenmalig, na de nulmeting en het MT-besluit:** streefwaarde 38, signaalwaarde 42.

**Elk kwartaal:** streefwaarde en signaalwaarde naar het nieuwe planniveau,
vóórdat het eerste meetpunt van dat kwartaal wordt ingevoerd.

**Elke maand:** één meetpunt — datum (laatste dag van de maand), teller (de
telling), noemer (1), en een toelichting zodra het cijfer uitleg nodig heeft.

**Aan het eind:** doormeten tot na de uitrol, dan op inactief. Nooit verwijderen.
