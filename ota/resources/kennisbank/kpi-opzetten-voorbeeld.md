# Een KPI opzetten: een uitgewerkt voorbeeld

KPI's zijn voor een beginnende CISO lastig, en dat komt zelden door de knoppen.
De vragen waarop het vastloopt, zijn inhoudelijk: wat hoort in het veld
*berekeningswijze*, welke streefwaarde is verdedigbaar als de organisatie er nog
lang niet is, en wat vult iemand elke maand precies in bij een meetpunt?

Dit artikel doorloopt één KPI van begin tot eind aan de hand van een verzonnen
casus. De achtergrond bij de keuzes in het model staat in
[KPI's en meetwaarden](kpis-en-meetwaarden). Dit artikel gaat over de
handelingen.

## De casus

Medewerkers van FruitBV hebben USB-sticks nodig en verliezen die regelmatig. Er
gaan **40 sticks per maand** verloren, wat ook 40 incidentmeldingen per maand
oplevert. Een programma vervangt de sticks door een oplossing zonder USB, maar dat
programma is pas over **twee jaar** volledig uitgerold. In de tussentijd wil
FruitBV kunnen aantonen dat het verlies daadwerkelijk afneemt. De uitrol levert
naar verwachting **twee incidenten minder per maand** op.

## Eerst de vraag, dan het veld

Het ligt voor de hand om direct op *Nieuwe KPI* te klikken. Drie vragen vooraf
bepalen echter zes van de acht velden.

**1. Welke besluitvraag beantwoordt dit cijfer?**
De besluitvraag is: "Loopt de vervanging van USB-sticks op schema, gemeten aan
het effect dat ervan verwacht wordt?" Dat is een andere vraag dan "hoeveel procent
is uitgerold". Die tweede vraag meet de voortgang van het *project* en niet het
effect op het *risico*. Een KPI die projectvoortgang meet, staat op groen op de
dag dat de laatste laptop is omgezet, ook als er nog steeds sticks verdwijnen.

**2. Wat is de bron, en is die betrouwbaar genoeg?**
De bron is het incidentregister. Dat is in deze casus een gunstige omstandigheid.
Elk verlies levert al een melding op, waardoor de meting niets extra kost en
achteraf te controleren is: 40 tellingen zijn 40 aanwijsbare records. Een KPI
waarvan de bron een schatting is, is geen KPI.

**3. Rekent de applicatie dit uit?**
Nee. Er is geen meetbron voor "incidenten van dit type". Incidenten hebben geen
categorieveld, en de enige ingebouwde incident-KPI gaat over tijdig extern melden.
Dit wordt dus een **handmatige KPI**. Een handmatige KPI is geen tweederangs
variant, maar de normale route voor alles wat buiten het ISMS wordt gemeten.

> Bij een handmatige KPI is het veld **berekeningswijze** geen toelichting bij de
> meting, maar de meetmethode zelf. Nergens anders staat hoe teller en noemer tot
> stand komen. Het is daarom het belangrijkste veld van het formulier.

## Stap 1 — de KPI aanmaken

Menu **KPI's** → *Nieuwe KPI*.

| Veld | Wat FruitBV invult | Waarom |
|---|---|---|
| **Naam** | `Verloren USB-sticks per maand` | De naam zegt wat er geteld wordt en over welke periode. "USB-beleid" is ongeschikt, omdat de naam op een dashboardtegel verschijnt en zonder toelichting leesbaar moet zijn. |
| **Meetbron** | *Handmatig — ik voer teller en noemer zelf in* | De applicatie kent deze telling niet. |
| **PDCA-fase** | **Check** | De KPI meet of een genomen maatregel het beoogde effect heeft (§9.1: de doeltreffendheid van de maatregelen). De uitrol zelf valt onder Do, en de meting erover onder Check. De fase bepaalt alleen de groepering op het dashboard en is later vrij te wijzigen. |
| **Eenheid** | **Aantal (telling)** | Het cijfer is het aantal verliezen. Het is geen ratio, want dat is een aandeel, en geen dagen, want dat is een gemiddelde. **Dit veld ligt vast zodra het eerste meetpunt bestaat.** |
| **Welke kant op is goed?** | **Omlaag — lager is beter** | Zonder deze instelling rapporteert het dashboard een dalende reeks als achteruitgang. **Een latere wijziging van dit veld verhoogt de definitieversie.** Het veld hoort daarom direct goed te staan. |
| **Berekeningswijze** | zie hieronder | Dit veld bevat de meetmethode zelf. |
| **Streefwaarde** | **leeg laten** | Zie stap 3. |
| **Signaalwaarde** | **leeg laten** | Zie stap 3. |
| **Actief** | aangevinkt | |

De sleutel wordt automatisch afgeleid (`verloren_usb_sticks_per_maand`) en
verandert daarna nooit meer, ook niet wanneer de naam later wordt bijgesteld. De
sleutel is de identiteit van de reeks in de export en de audit trail.

### De berekeningswijze, voluit

De berekeningswijze bevat vier onderdelen: **bron**, **afbakening**,
**telmoment** en **wat er geteld wordt**. Voor deze KPI ziet dat er zo uit:

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

De tekst is zo uitgebreid omdat elke zin een keuze vastlegt die na een jaar
niemand meer kent, en waar een auditor juist naar vraagt. De laatste twee zinnen
zijn de belangrijkste, omdat ze de telling **reproduceerbaar** maken. Wie dezelfde
regel volgt, komt op hetzelfde getal uit.

> **De afbakening is hier het zwakke punt.** Incidenten krijgen geen categorie
> toegewezen, en het incidentregister filtert alleen op status en ernst. De
> telling steunt dus op een titelconventie en op de koppeling aan het risico. Dat
> zijn twee afspraken die iemand moet handhaven. De conventie hoort vast te staan
> vóórdat de nulmeting wordt gedaan. Als dat pas na drie maanden gebeurt, begint
> de reeks met drie punten die anders zijn geteld dan de rest.

## Stap 2 — de nulmeting vastleggen

Pas nu wordt het eerste meetpunt vastgelegd, nog steeds zonder streefwaarde. Bij
de KPI staat de knop **Meetpunt invoeren**:

| Veld | Waarde |
|---|---|
| Gemeten op | `31-08-2026`, de laatste dag van de maand waarover gemeten wordt |
| Teller | `40` |
| Noemer | `1` |
| Toelichting | *Nulmeting. Situatie vóór de uitrol; 500 sticks in omloop. Telregel vastgesteld in het MT van 20-08-2026.* |

Bij dit meetpunt zijn drie punten van belang:

- **De noemer is 1.** Dat hoort bij de eenheid *aantal* en niet bij "handmatig".
  Bij een telling is de uitkomst de teller zelf, en telt de noemer niet mee. Het
  veld blijft verplicht en de waarde 0 wordt geweigerd, omdat 0 "geen populatie"
  betekent. Dat is iets anders dan "nul in deze periode". De uitleg onder de
  invoervelden past zich aan de eenheid aan en vermeldt dit ook. In de tabel toont
  de kolom *Meetpunt* daarom "40 van 1", en de kolom *Uitkomst* toont `40`. Bij een
  handmatige KPI met eenheid *ratio* of *dagen* is de noemer wel een echt getal.
- **Het meetpunt is onveranderlijk.** Er is geen bewerkknop en geen
  verwijderknop. Een telfout wordt gecorrigeerd in het meetpunt van de volgende
  maand, met een toelichting die beschrijft wat er fout was.
- **Er is één meetpunt per kalendermaand**, en de datum mag niet in de toekomst
  liggen. Daarom is het peilmoment "de laatste dag van de maand, ingevuld op de
  eerste werkdag daarna". Die datum valt binnen de maand waarover gemeten wordt,
  en is toch pas in te vullen wanneer die maand voorbij is.

Na dit meetpunt staat de KPI op **geen streefwaarde vastgesteld** (grijs). Dat is
correct, omdat er nog niets is om aan te toetsen.

## Stap 3 — pas nu de streefwaarde

Het ligt voor de hand om bij het aanmaken direct `0` in te vullen, het einddoel.
Er zijn twee redenen om dat niet te doen.

**De bestuurlijke reden.** Een zelf ingevoerde streefwaarde geldt direct als
**vastgesteld**. De datum van vandaag wordt erbij vastgelegd en de handeling komt
in de audit trail. Daarmee is verklaard dat dit de norm van de organisatie is. Die
uitspraak hoort bij de directie en niet bij de CISO die een formulier invult. Het
veld hoort leeg te blijven totdat het plan is vastgesteld.

**De praktische reden.** Met `0` als streefwaarde staat de KPI 20 maanden op rood,
terwijl alles precies volgens plan verloopt. Een semafoor die 20 maanden op rood
staat, wordt niet meer bekeken. Daardoor valt ook de maand niet op waarin het
werkelijk misgaat. De streefwaarde hoort te meten wat er op dit moment van de
organisatie wordt verwacht, en niet waar de organisatie over twee jaar wil zijn.

### De streefwaarde als planlijn

Het plan van FruitBV is een daling van twee verliezen per maand. De streefwaarde
wordt daarom het **planniveau van de lopende periode**, en het MT stelt die waarde
**elk kwartaal opnieuw vast**.

- **Streefwaarde** = het planniveau bij de start van het kwartaal. Een waarde op of
  onder dat niveau betekent dat de uitrol op schema ligt → groen.
- **Signaalwaarde** = streefwaarde + 4, wat overeenkomt met twee maanden volledige
  stilstand. Voorbij die grens gaat het niet meer "iets minder snel dan gehoopt",
  maar levert het programma niets op → rood.

Bij richting *omlaag* bewaakt de applicatie dat de signaalwaarde **boven** de
streefwaarde ligt. Bij de omgekeerde volgorde is de oranje band leeg en is elk
punt groen of rood.

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
geen fout. Rood in maand 1 betekent dat het na de start van het programma
*slechter* gaat dan ervoor. Dat is de enige werkelijk alarmerende uitkomst die zo
vroeg al mogelijk is.

> **Dit is geen goalpost-moving.** Het is het omgekeerde, mits de volgorde klopt.
> Elk meetpunt krijgt een **kopie** van de streefwaarde die op dat moment gold, en
> die kopie verandert nooit meer mee. De kolom *Streefwaarde toen* toont dus de
> hele geschiedenis van de eigen lat. Achteraf de lat verlagen om een rood punt
> groen te maken is niet mogelijk, omdat het oude punt zijn oude norm houdt en de
> bijstelling in de audit trail staat. Verschuiven van de lat is toegestaan;
> ongemerkt verschuiven is niet mogelijk.
>
> De voorwaarde is de volgorde. De nieuwe streefwaarde wordt ingevoerd **vóórdat**
> het meetpunt van de nieuwe periode wordt ingevoerd. Bij de omgekeerde volgorde
> draagt dat meetpunt nog de oude norm.

Het bijstellen gaat via **Bewerken** → streefwaarde en signaalwaarde aanpassen →
opslaan. De vaststellingsdatum wordt automatisch bijgewerkt. De definitieversie
gaat hier **niet** omhoog, omdat het veranderen van de norm iets anders is dan het
veranderen van de meting. De reeks blijft vergelijkbaar.

## Stap 4 — de maandelijkse routine

Elke maand wordt op de eerste werkdag het incidentregister geopend, wordt geteld
volgens de regel in de berekeningswijze en wordt één meetpunt vastgelegd. Meer
handelingen zijn er niet.

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

Na 12 meetpunten toont de dashboardtegel ook de verandering over 12 maanden:
**−24**. Die delta staat in stuks en niet in procentpunten, omdat de delta bij een
telling hetzelfde soort getal is als de waarde zelf.

### Wanneer de toelichting wordt ingevuld

De toelichting wordt niet elke maand ingevuld. De toelichting is wel nodig
wanneer het cijfer een verklaring vraagt die na een jaar niet meer bekend is:

- **M7, teller 30 (streefwaarde 26, niet gehaald):** *"Uitrol drie weken vertraagd
  door levering docking stations; 40 medewerkers nog niet omgezet."* Die
  toelichting maakt het verschil tussen een KPI die stuurt en een KPI die alleen
  registreert.
- **M14, teller 18 (verwacht 12):** *"Piek door de verhuizing van vestiging Zuid;
  11 van de 18 meldingen komen uit die vestiging."* Een verklaarbare uitschieter
  hoort verklaard te zijn en niet weggepoetst.
- **Bij een correctie op een eerdere maand:** *"In M9 zijn twee meldingen dubbel
  geteld; de reeks is niet aangepast, want een meetpunt is onveranderlijk."*

### Een gemiste maand

Een gemiste maand is niet onherstelbaar, en meestal ontstaat er zelfs geen gat in
de reeks. Een maand zonder meetpunt is later nog in te vullen. Bij **Gemeten op**
wordt dan de laatste dag van die maand gekozen, en er wordt volgens dezelfde regel
geteld. De applicatie plaatst het punt op de juiste plek in de reeks, ook als het
later is ingevoerd dan het punt van de maand erna. De toelichting vermeldt dat het
punt achteraf is ingevoerd, en wanneer.

Bij deze KPI kost inhalen geen betrouwbaarheid. De bron blijft bestaan, en de
telregel gebruikt het moment van melden als ankerpunt: de meldingen van oktober
zijn in januari nog precies dezelfde. Dat geldt niet voor elke KPI. Een telling
van een toestand, zoals *sticks in omloop op de laatste dag van de maand*, is
achteraf alleen te reconstrueren als de administratie haar historie bewaart.

Bij inhalen gaan twee zaken niet vanzelf goed:

- **Het meetpunt krijgt de streefwaarde van vandaag.** Een meetpunt krijgt bij het
  invoeren een kopie van de streefwaarde die op dat moment geldt, en niet van de
  streefwaarde van de maand waarover het gaat. Als er een kwartaalwissel ligt
  tussen de gemiste maand en het inhalen, wordt het ingehaalde punt aan de lat van
  het nieuwe kwartaal gemeten. Het inhalen hoort daarom te gebeuren vóórdat de
  nieuwe streefwaarde wordt ingevoerd. Als dat al is gebeurd, vermeldt de
  toelichting de lat die toen gold.
- **De gemiste maand hoort niet bij de volgende maand te worden opgeteld.** De
  applicatie kan dat niet detecteren, maar het resultaat is een punt van twee
  maanden in een reeks van maanden. Bij een telling ziet dat punt eruit als een
  piek. Elke maand krijgt een eigen punt. Een tweede meetpunt in dezelfde
  kalendermaand weigert de applicatie.

Als er niet wordt ingehaald, krijgt de reeks een gat en loopt de reeks daarna
door. Na **twee** perioden zonder meetpunt meldt het dashboard dat deze handmatige
KPI is stilgevallen. Dat signaal bestaat omdat een handmatige KPI die niemand
invult, op een trendgrafiek niet te onderscheiden is van een KPI die nog niet
meet.

## Stap 5 — het einde van de KPI

Rond M20 staat de teller op 0 en blijft de teller daar. De uitrol loopt nog tot
M24.

- **Doormeten tot na de uitrol, en nog enkele maanden daarna.** Nul is pas bewijs
  wanneer de waarde een tijd nul blijft. Eén maand nul kan ook betekenen dat
  niemand een melding heeft gedaan.
- **Daarna op inactief zetten, niet verwijderen.** Een KPI met meetpunten is ook
  niet te verwijderen, en dat is zo ontworpen. De reeks is het bewijs dat de
  maatregel heeft gewerkt. Dat bewijs is nodig bij de directiebeoordeling, bij het
  afvoeren van risico R-14 en bij de eerstvolgende audit. *Inactief* stopt het
  meten en bewaart de historie.
- **De cirkel sluiten.** Deze KPI is de effectiviteitsmeting van een
  risicobehandeling. Het afvoeren of herscoren van R-14 hoort naar deze reeks te
  verwijzen. Dat is precies de onderbouwing die een scoredaling nodig heeft.

## Twee valkuilen in deze KPI

### 1. De noemer beweegt mee, maar is hier niet zichtbaar

Dit is de belangrijkste les uit de casus. Het aantal verliezen daalt om **twee**
redenen tegelijk: medewerkers gaan zorgvuldiger om met sticks, en er zijn steeds
minder sticks. De tweede reden is een resultaat van het inkoopschema en niet van
het gedrag in de organisatie. Een telling kan die twee redenen niet van elkaar
onderscheiden.

Een berekening maakt dit concreet. Bij 500 sticks in omloop en 40 verliezen
verdwijnt er per maand **8%** van de voorraad. Als er in M12 nog 260 sticks over
zijn, levert puur mechanisch verlies bij gelijkblijvend gedrag ongeveer **21**
verliezen op. FruitBV meet er 16. Van de daling van 24 is dus ruwweg 19 mechanisch
en 5 het gevolg van gedragsverandering.

Dat betekent **niet** dat de telling de verkeerde KPI is. De telling meet de
blootstelling aan het risico, en daar gaat het de organisatie om. 16 verloren
sticks zijn 16 mogelijke datalekken, ongeacht de reden van dat aantal. Op de vraag
of het bewustzijnsprogramma werkt, geeft deze KPI echter het verkeerde antwoord.

Om die vraag ook te beantwoorden, wordt er een **tweede** KPI naast gezet:

| Veld | Waarde |
|---|---|
| Naam | `Verlies per 100 uitgegeven USB-sticks` |
| Meetbron | handmatig |
| Fase | Check |
| Eenheid | **Ratio (percentage)** |
| Richting | omlaag |
| Teller | verloren sticks in de maand (16) |
| Noemer | **sticks in omloop op de laatste dag van de maand** (260) |
| Streefwaarde | 8%, het niveau van de nulmeting |

De uitkomst in M0 is 8,0% en de uitkomst in M12 is 6,2%. Dat is het signaal over
gedrag, en daarmee is 8% als streefwaarde ook logisch: elke waarde onder de
beginstand betekent dat de daling niet alleen door de krimpende voorraad komt. De
voorwaarde is wel dat de voorraad elke maand betrouwbaar is vast te stellen. Als
dat niet kan, is het beter deze KPI weg te laten, omdat een verzonnen noemer
erger is dan geen tweede KPI. Bij een ratio bewaakt de applicatie dat de teller
niet groter is dan de noemer.

### 2. Deze KPI wordt vanzelf groen

De uitrol drukt het cijfer omlaag, ook als verder niemand iets doet. Een KPI die
zichzelf haalt, is geen stuurinstrument maar een voortgangsrapportage. Er zijn
twee tegenwichten:

- De streefwaarde is een **planlijn** en geen einddoel. Daardoor betekent groen
  "de uitrol ligt op schema" in plaats van "het gaat de goede kant op", en kan de
  KPI wel rood worden terwijl de absolute cijfers dalen. Dat is het doel van de
  kwartaalherijking.
- De **variantie** is net zo belangrijk als de richting. Een reeks die kaarsrecht
  40-38-36-34 loopt, is verdacht, omdat zulke gladde reeksen in werkelijkheid niet
  voorkomen. Een gladde reeks wijst meestal op iemand die het planniveau invult in
  plaats van het incidentregister te tellen. Een reeks die schommelt, inzakt en
  zich herstelt, is beter bewijs dat er echt gemeten wordt.

## Samengevat

**Eenmalig, bij het aanmaken:** naam, meetbron *handmatig*, fase *Check*, eenheid
*aantal*, richting *omlaag*, en een berekeningswijze die bron, afbakening,
telmoment en teleenheid benoemt. Streefwaarde en signaalwaarde blijven bewust
leeg.

**Eenmalig, na de nulmeting en het MT-besluit:** streefwaarde 38, signaalwaarde 42.

**Elk kwartaal:** streefwaarde en signaalwaarde worden naar het nieuwe planniveau
gezet, vóórdat het eerste meetpunt van dat kwartaal wordt ingevoerd.

**Elke maand:** één meetpunt met datum (laatste dag van de maand), teller (de
telling), noemer (1), en een toelichting zodra het cijfer uitleg nodig heeft.

**Aan het eind:** doormeten tot na de uitrol, daarna op inactief zetten. Een KPI
met meetpunten wordt nooit verwijderd.
