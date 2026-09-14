# De externe audit in het ISMS

Bij een externe audit levert de **externe auditor** de punten aan. De organisatie
stelt die punten niet zelf op. Dit artikel beschrijft wat de organisatie in dit
ISMS met het rapport doet. Het uitgangspunt luidt:
**het rapport blijft de bron van waarheid**. De organisatie neemt de uitkomsten
over als bevindingen en volgt de non-conformiteiten op.

Onder de BIO speelt daarnaast een bijzonderheid rond de vraag wie er komt kijken.
Die bijzonderheid is het eerste punt om te begrijpen.

## Er is geen BIO-certificaat

*"De BIO verplicht geen NEN-EN-ISO/IEC 27001-certificering."* Die zin staat
letterlijk in de BIO en heeft gevolgen voor de werking van dit hele hoofdstuk. Er
is geen certificerende instelling die een BIO-certificaat afgeeft, omdat dat
certificaat niet bestaat.

In plaats daarvan zijn er **drie verschillende soorten bezoek**, die niet met
elkaar verward mogen worden:

**1. Verantwoording aan de RDI.** De Cyberbeveiligingswet maakt de Rijksinspectie
Digitale Infrastructuur toezichthouder voor de sector Overheid. Dat is toezicht op
grond van de wet en geen certificering: de RDI kan handhaven, maar kan geen
certificaat onthouden. Overheidsmaatregel 5.36.01 en deel 1 §9 vragen daarnaast om
een jaarlijkse **In Control Verklaring**. Die verklaring is een bestuurlijke
verklaring van de organisatie zelf en geen document van een auditor.

**2. Een interne audit of een audit in opdracht van het eigen bestuur.** Deel 1
§12.4 vraagt om een interne toezichthouder, en de reguliere §9.2-auditcyclus geldt
hier onverkort. Dit is het gewone werk, en het loopt via de rondes van het type
`intern`.

**3. Een vrijwillige ISO 27001-certificering.** Sommige overheidsorganisaties
kiezen alsnog voor certificering, meestal omdat opdrachtgevers of ketenpartners
erom vragen. Dat is dan een gewone ISO-certificering tegen ISO 27001 en niet tegen
de BIO. De auditor toetst het managementsysteem en niet de overheidsmaatregelen.

Dit onderscheid heeft de volgende praktische gevolgen voor dit ISMS:

- **Een RDI-bevinding is geen non-conformiteit tegen een norm.** Een RDI-bevinding
  wordt vastgelegd als `observatie` of `verbeterkans`, tenzij de inspectie een
  tekortkoming benoemt die ook tegen een normclausule of een overheidsmaatregel
  ingaat.
- **Er is geen apart rondetype voor toezicht.** Het ISMS kent vier typen:
  `intern`, `intern_nulmeting`, `extern_certificering` en `extern_surveillance`.
  Een RDI-bezoek past bij geen van deze vier. Een RDI-bezoek wordt geregistreerd
  als `extern_surveillance`, omdat dat type qua vorm het dichtst in de buurt komt.
  De RDI komt in het auditorveld, en de omschrijving vermeldt uitdrukkelijk dat het
  om **toezicht en niet om certificering** gaat. Zonder die notitie lijkt het
  rondedossier een jaar later een surveillance-audit te beschrijven die nooit heeft
  plaatsgevonden.
- **De opvolging is wel dezelfde.** Het pad van grondoorzaak, corrigerende
  maatregel en effectiviteitstoets verandert niet. Dat is het voordeel van één
  administratie.

Een vierde variant komt uit de keten: **opdrachtgevers en ketenpartners vragen
steeds vaker om aansluiting bij de BIO**. Dat is geen audit van de organisatie
zelf, maar van haar leverancier. Dat onderwerp hoort in het leveranciersdossier
(blok 9) en niet hier. De inkoopeisen in deel 1 §13 zijn hierbij ook van belang.
Dit systeem legt die inkoopeisen vast als contractclausule.

## Het principe: rapport = bewijs, ISMS = opvolging

De organisatie bouwt het rapport niet na en vervangt het niet. De externe partij
heeft geen account in dit systeem, en haar rapport is en blijft het gezaghebbende
document. In het ISMS gebeuren twee dingen:

1. **Het rapport wordt als bewijs** onder de auditronde gehangen, in het
   bewijs-paneel onderaan het rondedossier. Daardoor is het rapport traceerbaar en
   onveranderlijk bewaard.
2. **De punten worden overgenomen als bevindingen**, zodat opvolging, afwijkingen
   en KPI's in één systeem lopen.

Alleen het PDF-bestand bewaren is onvoldoende. Een non-conformiteit moet een
**corrigerende-actie-cyclus** (§10.2) doorlopen, met een grondoorzaak, een
maatregel en een effectiviteitstoets. Die cyclus is alleen mogelijk als de
bevinding als record in het ISMS staat. Een rapport in een archief stuurt geen
opvolging aan.

## Stap voor stap

1. **Auditronde aanmaken.** In het jaarplan wordt een auditronde aangemaakt met
   het passende type. De naam van de externe partij wordt als vrije tekst
   ingevuld, omdat de externe partij geen account heeft.
2. **Rapport koppelen.** Het rapport wordt als bewijsstuk aan de ronde gehangen.
3. **Punten overnemen.** Elk punt wordt overgenomen als bevinding, met het
   passende type:

   | Auditpunt | Bevindingtype in het ISMS |
   |---|---|
   | Grote afwijking (major nonconformity) | `non_conformiteit_major` |
   | Kleine afwijking (minor nonconformity) | `non_conformiteit_minor` |
   | Opmerking / observation | `observatie` |
   | Verbeterkans / opportunity for improvement | `verbeterkans` |

   Elke bevinding wordt waar mogelijk gekoppeld aan de betreffende
   **beheersmaatregel** (Bijlage A). Daardoor is later te zien welke control
   geraakt is.

4. **Non-conformiteiten escaleren.** Een non-conformiteit wordt vanuit de
   bevinding geëscaleerd naar een **Afwijking (§10.2)**. Daar worden de
   grondoorzaak, de corrigerende maatregel en de effectiviteitstoets vastgelegd.
5. **Ronde afronden.** De ronde wordt afgerond zodra alles is overgenomen.
   Afronden bevriest de bevindingen. De opvolging loopt daarna verder in
   Afwijkingen.

## Wat een BIO-auditor extra vraagt

Bij ISO gaat het gesprek over de beheersmaatregel. Bij de BIO gaat het gesprek één
niveau dieper, en op dat verschil moet de organisatie zich voorbereiden.

- **Per overheidsmaatregel, niet per beheersmaatregel.** Deel 1 §4 vraagt per
  maatregel om *opzet, bestaan en werking*. "A.5.24 is geïmplementeerd" is geen
  antwoord als daar zeven genummerde verplichtingen onder vallen. De vraag is of
  5.24.03 belegd is en waaruit dat blijkt. In de SoA-modal legt elke
  overheidsmaatregel daarom een eigen status en eigen bewijs vast.
- **Uitzonderingen met een risicoanalyse erbij.** Een overheidsmaatregel met de
  status "niet van toepassing" hoort een verwijzing naar de onderbouwende
  risicoanalyse te hebben. Deel 1 §7 vraagt die verwijzing in een bijlage bij de
  VvT. De ISMS-export levert die bijlage, en de SoA-pagina toont een teller voor
  uitzonderingen waarbij de verwijzing nog ontbreekt. Die teller hoort op nul te
  staan voordat het rapport wordt geschreven.
- **De datum van de laatste beoordeling.** Elke beoordeling houdt de datum bij. De
  status "Belegd" zonder recente datum heeft onder een jaarlijkse
  verantwoordingscyclus weinig waarde. De teller op de SoA-pagina laat zien welke
  beoordelingen verouderd zijn.
- **De drie maatregelen buiten de Cbw-reikwijdte.** Bij 5.32, 5.33 en 5.34 is de
  grondslag verplichtende zelfregulering en niet de wet. Dat verandert niets aan
  wat de organisatie moet doen, maar wel aan wat de RDI kan handhaven. Dit is
  precies het soort onderscheid waarop een gesprek vastloopt als niemand het
  benoemt.

## Tijdens de audit: een kopie voor de auditor

Een externe auditor kijkt tijdens de audit mee op de schermen en vraagt soms om
een kopie van wat daar staat. Daarvoor is er een knop **Kopie voor de auditor**.
Die knop levert het scherm, zoals het op dat moment is, op als Word-document.

Daarbij zijn twee punten van belang:

- **Het document beschrijft zichzelf.** De kop vermeldt de organisatie, het moment
  en de persoon die de kopie maakte. Het belangrijkste gegeven in de kop is hoeveel
  van het totale aantal regels het document bevat en welke filters actief waren.
  Een gefilterd overzicht dat zichzelf als het volledige register presenteert, is
  het gevaarlijkste document dat in een auditdossier kan belanden.
- **Achteraf is te zien wat is meegegeven.** Elke kopie komt als regel in
  **Bewijs & audit trail → Schermkopieën**, met het scherm, de filters, het aantal
  regels, de maker en het tijdstip. Op een auditdag gaan er al snel 10 schermen
  mee. Die lijst vormt het overdrachtsdossier. De kopieën zelf worden niet
  bewaard.

> **Let op bij toezicht.** Een kopie meegeven aan een toezichthouder is iets anders
> dan een kopie meegeven aan een auditor onder geheimhouding. De organisatie weegt
> daarom per scherm af wat zij verstrekt. De lijst met schermkopieën laat achteraf
> zien wat er is verstrekt.

## Waarom dit past

- **Eén administratie.** Interne audits, RDI-toezicht en een eventuele vrijwillige
  certificering leveren bevindingen op dezelfde manier aan, en de opvolging
  (§10.2) is identiek. Er is geen apart spoor per soort bezoek.
- **Traceerbaarheid.** De keten auditpunt → bevinding → afwijking → corrigerende
  maatregel → bewijs is volledig gekoppeld. Bij het volgende bezoek is daardoor in
  één keer te tonen wat er met elk punt is gedaan.
- **Het rapport blijft leidend.** Het ISMS interpreteert het oordeel van de auditor
  niet opnieuw, maar registreert dat oordeel en de opvolging door de organisatie.
  Als de samenvatting in het ISMS afwijkt van het rapport, is het rapport het
  bewijs dat telt.

> **Let op: geen bevinding per control.** Een bevinding wordt alleen aangemaakt
> voor de punten die daadwerkelijk zijn genoemd. Controls die in orde waren,
> hoeven niet als "conforme bevinding" te worden vastgelegd, omdat het rapport de
> volledige scope al dekt.
