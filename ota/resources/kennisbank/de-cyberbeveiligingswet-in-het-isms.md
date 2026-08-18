De Cyberbeveiligingswet (Cbw, de Nederlandse uitwerking van NIS2) raakt dit
systeem op drie plaatsen, en op precies één daarvan gaat het diep. Deze pagina
zet ze naast elkaar, zodat je weet waar je op kunt leunen — en waar niet.

Kort samengevat: het ISMS ondersteunt de **meldplicht** bij incidenten tot op
artikelniveau, het kent één **instelling** die zegt of je organisatie onder de
wet valt, en in een BIO-installatie markeert het welke maatregelen **buiten de
reikwijdte** van de wet vallen. De wet als geheel — de zorgplicht, de
registratie bij de toezichthouder, de verplichtingen van bestuurders — is hier
geen apart onderwerp.

## 1. Eerst de instelling: valt jouw organisatie eronder?

Of de Cbw op je van toepassing is, hangt af van sector en omvang. Dat is een
juridisch oordeel dat je organisatie één keer maakt, niet iets om per incident
te beantwoorden. Het staat daarom in de installatie-instelling
`ISMS_CBW_PLICHTIG`.

> **De standaard is "nee".** Val je er wél onder, zet hem dan aan — anders stelt
> het ISMS de Cbw-vraag nooit en blijven de termijnen uit dit artikel buiten
> beeld. Staat hij uit, dan is dat geen half werk maar een bewuste keuze: voor de
> meerderheid die niet Cbw-plichtig is, zou elk ICT-incident anders een
> motivatie vragen die nergens toe leidt.

Deze instelling staat **los van je normprofiel**. Ook een ISO 27001-installatie
kan Cbw-plichtig zijn, en een organisatie die de BIO volgt hoeft het niet te
zijn — deel 1 §11.1 van de BIO noemt dat geval expliciet, en dan geldt de BIO
als verplichtende zelfregulering.

## 2. De meldplicht bij incidenten

Dit is het onderdeel dat volledig is uitgewerkt. Zet je de instelling aan, dan
stelt het incidentscherm bij elk incident de vraag of het om netwerk- of
informatiesystemen gaat, en volgen daaruit de verplichtingen met hun eigen
termijn:

| Fase | Grondslag | Termijn | De klok start bij |
|---|---|---|---|
| Vroegtijdige waarschuwing | art. 26 lid 1 | 24 uur | kennisname |
| Incidentmelding | art. 27 lid 1 | 72 uur | kennisname |
| Eindverslag | art. 29 | één maand | de gedane incidentmelding |

Drie dingen die vaak misgaan en die het systeem daarom vastlegt:

- **De klok start bij kennisname, niet bij de registratie in dit systeem.** De
  wet zegt "nadat zij kennis heeft gekregen van het significante incident", en
  dat moment ligt vrijwel altijd vóór het aanmaken van het dossier. Daarvoor is
  een apart veld, dat je achteraf mag corrigeren als het onderzoek het scherper
  maakt; de correctie komt in de audit trail.
- **Het eindverslag krijgt pas een datum als de melding gedaan is.** De maand van
  art. 29 telt vanaf de melding uit art. 27, en bij een voortdurend incident pas
  vanaf de afhandeling. Tot dan staat de verplichting er als *verplicht, nog geen
  datum* — en niet als een berekende datum die stilzwijgend opschuift.
- **Je ziet een uiterste datum, geen aftelklok.** De wet stelt "onverwijld"
  voorop en noemt het getal als buitengrens. Een teller die "nog 20 uur" meldt,
  presenteert de uitzondering als de norm.

Het tussentijdse verslag van art. 28 kent het systeem niet als verplichting: dat
komt op verzoek van het CSIRT of de bevoegde autoriteit en heeft geen termijn,
dus er valt niets te bewaken. Hang het als bewijsstuk aan het incident.

De volledige werkwijze — de twee raakvlakvragen, de motivatieplicht, en waarom
een verstreken termijn het sluiten van een incident juist niet blokkeert — staat
in [Incidenten & afwijkingen](/kennisbank/incidenten-en-afwijkingen). De Cbw kan
samenlopen met de AVG; bij een datalek in een Cbw-plichtige organisatie is dat
het gewone geval.

**Meting.** Hoe vaak de termijnen gehaald zijn, telt mee als KPI: externe
meldingen die op of vóór hun uiterste datum zijn gedaan, gedeeld door alle
meldingen mét een termijn. Verplichtingen zonder termijn vallen buiten die breuk,
want die kunnen niet te laat zijn. Er wordt bewust geen streefwaarde meegeleverd;
100% is de enige verdedigbare norm en juist daarom een cijfer dat je zelf wilt
vaststellen.

## 3. Buiten de Cbw-reikwijdte (alleen in een BIO-installatie)

Volg je de BIO, dan markeert het systeem welke maatregelen de norm buiten de
reikwijdte van de Cbw plaatst: drie beheersmaatregelen (intellectueel eigendom,
bescherming van registraties, en privacy — de onderwerpen die hun eigen wet al
hebben) en één overheidsmaatregel. Je ziet dat als badge in de SoA en als teller
in het dekkingsblok. Bij de overige maatregelen is de grondslag een wettelijke
plicht die de RDI kan handhaven; hier is het een bestuurlijke afspraak.

> **Twee keer "Cbw", twee verschillende vragen.** De instelling uit §1 zegt of
> *jouw organisatie* onder de wet valt. Deze markering zegt of *de BIO een
> maatregel* binnen die wet plaatst. Ze vermenigvuldigen elkaar en zijn niet
> uitwisselbaar: een BIO-organisatie die buiten de Cbw valt, houdt alle
> maatregelen — alleen de handhaving is dan een andere.

In een ISO 27001- of NEN 7510-installatie bestaat deze markering niet. De
reikwijdte-uitspraak is van de BIO, en buiten dat profiel heeft ze geen
betekenis.

## 4. Wat dit systeem niet voor je doet

Even belangrijk als het bovenstaande, want hier moet je eigen procedure het
overnemen:

- **Melden zelf.** Er is geen koppeling met het meldportaal van het NCSC of de
  toezichthouder. Het ISMS legt vast *dát* er gemeld is, met datum; de melding
  gaat langs het officiële kanaal en het ontvangstbewijs hang je als bewijsstuk
  aan het incident.
- **De inhoud van de melding** (art. 27 lid 3). Dat is een formulier bij de
  toezichthouder, geen registratie hier.
- **Bepalen óf je Cbw-plichtig bent.** Het systeem biedt de grondslag aan; het
  oordeel is aan je organisatie en haar juristen.
- **Andere meldregimes als eigen grondslag.** Val je ook onder DORA of de
  netcode cyberbeveiliging elektriciteit, met hun termijn van vier uur, dan is
  de uiterste datum per verplichting te overschrijven. Er is geen aparte
  grondslag voor ingebouwd.
- **De zorgplicht als eigen module.** De maatregelen die de wet van je verlangt,
  beheer je gewoon als maatregelen in de SoA — onder de BIO zijn dat de
  overheidsmaatregelen, onder ISO 27001 de beheersmaatregelen uit Bijlage A. Er
  is geen aparte Cbw-checklist, en die zou ook niets toevoegen aan wat er al
  staat.
- **De verplichtingen van bestuurders.** Het ISMS kent de rol `Management` en
  legt besluiten met naam en datum vast; dat is het mechanisme. Of daarmee aan
  de wet is voldaan, is geen uitspraak die dit systeem doet.

## 5. Waar de bewaking ophoudt

Het **scherm** is het signaal. Openstaande verplichtingen staan met hun uiterste
datum op de incidentpagina, met de stand erbij: open, gemeld, te laat gemeld of
termijn verstreken.

Wat er níét is: een e-mail of dashboardmelding die waarschuwt dat een termijn
nadert. Dat is een bewuste keuze en geen vergetelheid. Alle geautomatiseerde
sweeps in dit systeem draaien 's nachts, één keer per etmaal — voor een termijn
van 24 uur betekent dat één signaal, op een willekeurig moment in de nacht, soms
uren te laat. Zo'n melding wekt de indruk van bewaking zonder die te leveren, en
dat is slechter dan geen melding.

**Praktisch gevolg:** een lopend meldingsplichtig incident is iets waar je
gedurende de eerste dagen zelf naar kijkt. Reken op je eigen meldprocedure en op
de piketafspraken daarin, niet op een alarm uit dit systeem.

## Verantwoording

De termijnen en artikelverwijzingen op deze pagina zijn op **4 augustus 2026**
gecontroleerd tegen de Cyberbeveiligingswet (BWBR0052872, tekst geldend vanaf
15 augustus 2026) en Verordening (EU) 2016/679. Ze staan in het systeem op één
plek, zodat een wetswijziging op één plek wordt doorgevoerd.

Dit is een administratief hulpmiddel en geen juridisch advies. Of jouw
organisatie onder de Cbw valt, welke incidenten significant zijn en wat een
melding moet bevatten, zijn vragen voor je eigen juristen en voor de
toezichthouder.
