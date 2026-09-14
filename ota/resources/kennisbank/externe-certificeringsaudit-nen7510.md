# De externe certificeringsaudit in het ISMS

Bij een externe audit (certificering of surveillance) levert de **externe auditor**
de punten aan. De organisatie stelt die punten niet zelf op. Dit artikel beschrijft
wat de organisatie in dit ISMS met het auditrapport doet. Het uitgangspunt luidt:
**het rapport blijft de bron van waarheid**. De organisatie neemt de uitkomsten
over als bevindingen en volgt de non-conformiteiten op.

## Certificering tegen NEN 7510, en wat het niet is

In de zorg lopen twee begrippen door elkaar die strikt gescheiden horen te
blijven.

**Certificering** gebeurt tegen **NEN 7510-1** door een certificerende instelling
die daarvoor door de Raad voor Accreditatie is geaccrediteerd, onder het
certificatieschema **NCS 7510**. Het mechanisme is gelijk aan dat van een
ISO 27001-certificering: een initiële audit, twee opvolgingsaudits en een
hercertificering in het derde jaar. De auditor toetst het managementsysteem van de
organisatie tegen de norm en stelt non-conformiteiten vast.

**Toezicht** is een ander begrip. De **Inspectie Gezondheidszorg en Jeugd (IGJ)**
is geen certificerende instelling en toetst niet tegen NEN 7510-1 als
certificatienorm. De IGJ houdt toezicht op grond van de wet en hanteert een eigen
toetsingskader, waarin informatiebeveiliging één onderdeel is. De bevindingen van
de IGJ hebben ook een ander gevolg: er wordt geen certificaat onthouden, maar er
volgt een handhavingstraject.

Dit onderscheid heeft de volgende praktische gevolgen voor dit ISMS:

- **Een IGJ-bevinding is geen non-conformiteit tegen de norm.** Een IGJ-bevinding
  wordt vastgelegd als `observatie` of `verbeterkans`, tenzij de inspectie
  expliciet een tekortkoming benoemt die ook tegen een normclausule ingaat.
- **Er is geen apart rondetype voor toezicht.** Het ISMS kent vier typen:
  `intern`, `intern_nulmeting`, `extern_certificering` en `extern_surveillance`.
  Een inspectiebezoek past bij geen van deze vier. Een inspectiebezoek wordt
  geregistreerd als `extern_surveillance`, omdat dat type qua vorm het dichtst in
  de buurt komt. De instantie komt in het auditorveld, en de omschrijving vermeldt
  uitdrukkelijk dat het om **toezicht en niet om certificering** gaat. Zonder die
  notitie lijkt het rondedossier een jaar later een surveillance-audit te
  beschrijven die nooit heeft plaatsgevonden.
- **De opvolging is wel dezelfde.** Het pad van grondoorzaak, corrigerende
  maatregel en effectiviteitstoets verandert niet. Dat is het voordeel van één
  administratie.

Een derde variant komt uit de keten: **ketenpartners en opdrachtgevers vragen
steeds vaker om een NEN 7510-verklaring**. Dat is geen audit van de organisatie
zelf, maar van haar leverancier. Zo'n verklaring hoort in het leveranciersdossier
(blok 9) en niet hier.

## Het principe: rapport = bewijs, ISMS = opvolging

De organisatie bouwt het auditrapport niet na en vervangt het niet. De
certificerende instelling heeft geen account in dit systeem, en haar rapport is en
blijft het gezaghebbende document. In het ISMS gebeuren twee dingen:

1. **Het rapport wordt als bewijs** onder de auditronde gehangen, in het
   bewijs-paneel onderaan het rondedossier. Daardoor is het rapport traceerbaar en
   onveranderlijk bewaard.
2. **De punten van de auditor worden overgenomen als bevindingen**, zodat
   opvolging, afwijkingen en KPI's in één systeem lopen.

Alleen het PDF-bestand bewaren is onvoldoende. Een non-conformiteit moet een
**corrigerende-actie-cyclus** (§10.2) doorlopen, met een grondoorzaak, een
maatregel en een effectiviteitstoets. Die cyclus is alleen mogelijk als de
bevinding als record in het ISMS staat. Een rapport in een archief stuurt geen
opvolging aan.

## Stap voor stap

1. **Auditronde aanmaken.** In het jaarplan wordt een auditronde aangemaakt met
   type **`extern_certificering`** of **`extern_surveillance`**. De naam van de
   externe auditor wordt als vrije tekst ingevuld, omdat de auditor geen account
   heeft.
2. **Auditrapport koppelen.** Het auditrapport wordt als bewijsstuk aan de ronde
   gehangen.
3. **Auditpunten overnemen.** Elk auditpunt wordt overgenomen als bevinding, met
   het passende type:

   | Auditpunt | Bevindingtype in het ISMS |
   |---|---|
   | Grote afwijking (major nonconformity) | `non_conformiteit_major` |
   | Kleine afwijking (minor nonconformity) | `non_conformiteit_minor` |
   | Opmerking / observation | `observatie` |
   | Verbeterkans / opportunity for improvement | `verbeterkans` |

   Elke bevinding wordt waar mogelijk gekoppeld aan de betreffende **maatregel**
   (Bijlage A). Daardoor is later te zien welke control geraakt is. De acht
   zorgspecifieke maatregelen (A.5.38–A.5.43, A.6.9, A.8.35) verdienen daarbij
   aandacht, omdat een auditor bij een NEN 7510-audit die maatregelen in ieder
   geval bekijkt.
4. **Non-conformiteiten escaleren.** Een non-conformiteit wordt vanuit de
   bevinding geëscaleerd naar een **Afwijking (§10.2)**. Daar worden de
   grondoorzaak, de corrigerende maatregel en de effectiviteitstoets vastgelegd.
   Dat is de informatie die de certificerende instelling bij de volgende ronde wil
   terugzien.
5. **Ronde afronden.** De ronde wordt afgerond zodra alles is overgenomen.
   Afronden bevriest de bevindingen. De opvolging loopt daarna verder in
   Afwijkingen.

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

De knop wordt per scherm toegekend. Welke schermen de knop krijgen, is een bewuste
keuze, omdat niet elk scherm bedoeld is als bewijsstuk.

> **Let op bij toezicht.** Een kopie meegeven aan een inspecteur is iets anders
> dan een kopie meegeven aan een auditor onder geheimhouding. De organisatie weegt
> daarom per scherm af wat zij verstrekt. De lijst met schermkopieën laat achteraf
> zien wat er is verstrekt.

## Waarom dit past

- **Eén administratie.** Interne en externe audits leveren bevindingen op dezelfde
  manier aan, en de opvolging (§10.2) is identiek. Er is geen apart spoor voor
  externe audits.
- **Traceerbaarheid.** De keten auditpunt → bevinding → afwijking → corrigerende
  maatregel → bewijs is volledig gekoppeld. Bij de surveillance-audit een jaar
  later is daardoor in één keer te tonen wat er met elk punt is gedaan.
- **Het rapport blijft leidend.** Het ISMS interpreteert het oordeel van de auditor
  niet opnieuw, maar registreert dat oordeel en de opvolging door de organisatie.
  Als de samenvatting in het ISMS afwijkt van het rapport, is het rapport het
  bewijs dat telt.

> **Let op: geen bevinding per control.** Een bevinding wordt alleen aangemaakt
> voor de punten die de auditor daadwerkelijk noemt. Controls die in orde waren,
> hoeven niet als "conforme bevinding" te worden vastgelegd, omdat het
> auditrapport de volledige scope al dekt. Dit is hetzelfde detailniveau als bij
> de interne audit.
