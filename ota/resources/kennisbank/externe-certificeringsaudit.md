# De externe certificeringsaudit in het ISMS

Bij een externe audit (certificering of surveillance) levert de **externe auditor**
de punten aan. De organisatie stelt die punten niet zelf op. Dit artikel beschrijft
wat de organisatie in dit ISMS met het auditrapport doet. Het uitgangspunt luidt:
**het rapport blijft de bron van waarheid**. De organisatie neemt de uitkomsten
over als bevindingen en volgt de non-conformiteiten op.

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
   (Annex A). Daardoor is later te zien welke control geraakt is.
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
