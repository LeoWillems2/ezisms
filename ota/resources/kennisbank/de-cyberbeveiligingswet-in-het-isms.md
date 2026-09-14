De Cyberbeveiligingswet (Cbw, de Nederlandse uitwerking van NIS2) raakt dit
systeem op drie plaatsen, en slechts één daarvan is diepgaand uitgewerkt. Deze
pagina zet de drie plaatsen naast elkaar. Daardoor is duidelijk waarop de
organisatie kan vertrouwen en waarop niet.

Samengevat ondersteunt het ISMS de **meldplicht** bij incidenten tot op
artikelniveau. Het ISMS kent één **instelling** die aangeeft of de organisatie
onder de wet valt. In een BIO-installatie markeert het ISMS bovendien welke
maatregelen **buiten de reikwijdte** van de wet vallen. De wet als geheel is hier
geen apart onderwerp. Dat geldt voor de zorgplicht, de registratie bij de
toezichthouder en de verplichtingen van bestuurders.

## 1. Eerst de instelling: valt de organisatie onder de wet?

Of de Cbw op een organisatie van toepassing is, hangt af van sector en omvang.
Dat is een juridisch oordeel dat de organisatie één keer maakt, en geen vraag die
per incident beantwoord wordt. Het oordeel staat daarom in de
installatie-instelling `ISMS_CBW_PLICHTIG`.

> **De standaardwaarde is "nee".** Een organisatie die onder de wet valt, hoort de
> instelling aan te zetten. Anders stelt het ISMS de Cbw-vraag nooit en blijven de
> termijnen uit dit artikel buiten beeld. Een uitgeschakelde instelling is geen
> half werk, maar een bewuste keuze. Voor de meerderheid van de organisaties, die
> niet Cbw-plichtig is, zou elk ICT-incident anders een motivatie vragen die
> nergens toe leidt.

Deze instelling staat **los van het normprofiel**. Ook een ISO 27001-installatie
kan Cbw-plichtig zijn, en een organisatie die de BIO volgt, hoeft dat niet te
zijn. Deel 1 §11.1 van de BIO noemt dat geval expliciet. In dat geval geldt de BIO
als verplichtende zelfregulering.

## 2. De meldplicht bij incidenten

Dit onderdeel is volledig uitgewerkt. Als de instelling aan staat, stelt het
incidentscherm bij elk incident de vraag of het om netwerk- of
informatiesystemen gaat. Uit het antwoord volgen de verplichtingen, elk met een
eigen termijn:

| Fase | Grondslag | Termijn | De klok start bij |
|---|---|---|---|
| Vroegtijdige waarschuwing | art. 26 lid 1 | 24 uur | kennisname |
| Incidentmelding | art. 27 lid 1 | 72 uur | kennisname |
| Eindverslag | art. 29 | één maand | de gedane incidentmelding |

Het systeem legt drie punten vast die in de praktijk vaak misgaan:

- **De klok start bij kennisname, niet bij de registratie in dit systeem.** De
  wet zegt "nadat zij kennis heeft gekregen van het significante incident", en
  dat moment ligt vrijwel altijd vóór het aanmaken van het dossier. Daarvoor is
  een apart veld beschikbaar. Dat veld mag achteraf worden gecorrigeerd als het
  onderzoek het moment preciezer bepaalt. De correctie komt in de audit trail.
- **Het eindverslag krijgt pas een datum als de melding gedaan is.** De maand van
  art. 29 telt vanaf de melding uit art. 27, en bij een voortdurend incident pas
  vanaf de afhandeling. Tot dat moment staat de verplichting als *verplicht, nog
  geen datum* in het systeem, en niet als een berekende datum die stilzwijgend
  opschuift.
- **Het systeem toont een uiterste datum en geen aftelklok.** De wet stelt
  "onverwijld" voorop en noemt het getal als buitengrens. Een teller die "nog 20
  uur" meldt, presenteert de uitzondering als de norm.

Het tussentijdse verslag van art. 28 is in het systeem geen verplichting. Dat
verslag wordt opgesteld op verzoek van het CSIRT of de bevoegde autoriteit en heeft
geen termijn, dus er valt niets te bewaken. Het verslag hoort als bewijsstuk aan
het incident te worden gekoppeld.

De volledige werkwijze staat in [Incidenten &
afwijkingen](/kennisbank/incidenten-en-afwijkingen). Dat artikel beschrijft de
twee raakvlakvragen, de motivatieplicht, en de reden waarom een verstreken
termijn het sluiten van een incident juist niet blokkeert. De Cbw kan samenlopen
met de AVG. Bij een datalek in een Cbw-plichtige organisatie is dat de normale
situatie.

**Meting.** Het aandeel tijdig gedane meldingen telt mee als KPI. De KPI deelt de
externe meldingen die op of vóór hun uiterste datum zijn gedaan, door alle
meldingen met een termijn. Verplichtingen zonder termijn vallen buiten die breuk,
omdat die niet te laat kunnen zijn. Het systeem levert bewust geen streefwaarde
mee. 100% is de enige verdedigbare norm, en juist daarom hoort de organisatie dat
cijfer zelf vast te stellen.

## 3. Buiten de Cbw-reikwijdte (alleen in een BIO-installatie)

In een BIO-installatie markeert het systeem welke maatregelen de norm buiten de
reikwijdte van de Cbw plaatst. Het gaat om drie beheersmaatregelen en één
overheidsmaatregel. De drie beheersmaatregelen betreffen intellectueel eigendom,
bescherming van registraties en privacy. Dat zijn onderwerpen die al een eigen
wet hebben. De markering is zichtbaar als badge in de SoA en als teller in het
dekkingsblok. Bij de overige maatregelen is de grondslag een wettelijke plicht
die de RDI kan handhaven. Bij deze maatregelen is de grondslag een bestuurlijke
afspraak.

> **Twee keer "Cbw", twee verschillende vragen.** De instelling uit §1 geeft aan of
> *de organisatie* onder de wet valt. Deze markering geeft aan of *de BIO een
> maatregel* binnen die wet plaatst. De twee vragen zijn onafhankelijk van elkaar
> en niet uitwisselbaar. Een BIO-organisatie die buiten de Cbw valt, houdt alle
> maatregelen. Alleen de handhaving is dan anders.

In een ISO 27001- of NEN 7510-installatie bestaat deze markering niet. De
uitspraak over de reikwijdte komt uit de BIO, en buiten dat profiel heeft die
uitspraak geen betekenis.

## 4. Wat dit systeem niet doet

Dit deel is even belangrijk als het voorgaande, omdat de eigen procedure van de
organisatie hier de taak overneemt:

- **Het melden zelf.** Er is geen koppeling met het meldportaal van het NCSC of de
  toezichthouder. Het ISMS legt vast dat er gemeld is, met de datum. De melding
  zelf gaat via het officiële kanaal, en het ontvangstbewijs wordt als bewijsstuk
  aan het incident gekoppeld.
- **De inhoud van de melding** (art. 27 lid 3). Die inhoud gaat via een formulier
  bij de toezichthouder en wordt hier niet geregistreerd.
- **Bepalen of de organisatie Cbw-plichtig is.** Het systeem biedt de grondslag
  aan. Het oordeel is aan de organisatie en haar juristen.
- **Andere meldregimes als eigen grondslag.** Een organisatie die ook onder DORA
  of de netcode cyberbeveiliging elektriciteit valt, heeft te maken met een
  termijn van vier uur. In dat geval is de uiterste datum per verplichting te
  overschrijven. Het systeem heeft geen aparte grondslag voor deze regimes.
- **De zorgplicht als eigen module.** De maatregelen die de wet vereist, worden
  gewoon als maatregelen in de SoA beheerd. Onder de BIO zijn dat de
  overheidsmaatregelen, en onder ISO 27001 de beheersmaatregelen uit Bijlage A.
  Er is geen aparte Cbw-checklist, en zo'n checklist zou ook niets toevoegen aan
  wat er al staat.
- **De verplichtingen van bestuurders.** Het ISMS kent de rol `Management` en
  legt besluiten met naam en datum vast. Dat is het mechanisme. Het systeem doet
  geen uitspraak over de vraag of daarmee aan de wet is voldaan.

## 5. Waar de bewaking ophoudt

Het **scherm** is het signaal. Openstaande verplichtingen staan met hun uiterste
datum op de incidentpagina, met de stand erbij: open, gemeld, te laat gemeld of
termijn verstreken.

Er is geen e-mail of dashboardmelding die waarschuwt dat een termijn nadert. Dat
is een bewuste keuze en geen vergetelheid. Alle geautomatiseerde sweeps in dit
systeem draaien 's nachts, één keer per etmaal. Voor een termijn van 24 uur
betekent dat één signaal op een willekeurig moment in de nacht, en dat signaal
komt soms uren te laat. Zo'n melding wekt de indruk van bewaking zonder die
bewaking te leveren, en dat is slechter dan geen melding.

**Praktisch gevolg:** een lopend meldingsplichtig incident moet gedurende de
eerste dagen actief worden gevolgd. De organisatie hoort daarvoor te steunen op de
eigen meldprocedure en de piketafspraken daarin, en niet op een alarm uit dit
systeem.

## Verantwoording

De termijnen en artikelverwijzingen op deze pagina zijn op **4 augustus 2026**
gecontroleerd tegen de Cyberbeveiligingswet (BWBR0052872, tekst geldend vanaf
15 augustus 2026) en Verordening (EU) 2016/679. Ze staan in het systeem op één
plek, zodat een wetswijziging op één plek wordt doorgevoerd.

Dit is een administratief hulpmiddel en geen juridisch advies. Of een organisatie
onder de Cbw valt, welke incidenten significant zijn en wat een melding moet
bevatten, zijn vragen voor de eigen juristen en voor de toezichthouder.
