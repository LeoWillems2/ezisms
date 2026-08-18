# De externe audit in het ISMS

Bij een externe audit levert de **externe auditor** de punten aan — jij stelt ze
niet zelf op. De vraag is dan: wat doe je met dat rapport in dit ISMS? Kort: **het
rapport blijft de bron van waarheid, jij neemt de uitkomsten over als bevindingen
en volgt de non-conformiteiten op.**

Onder de BIO is er wel iets bijzonders aan de hand met de vraag *wie* er komt
kijken, en dat is het eerste dat je moet weten.

## Er is geen BIO-certificaat

*"De BIO verplicht geen NEN-EN-ISO/IEC 27001-certificering."* Dat staat er zo, en
het heeft gevolgen voor hoe dit hele hoofdstuk werkt: er is geen certificerende
instelling die je een BIO-certificaat komt geven, want dat certificaat bestaat
niet.

Wat er in de plaats komt zijn **drie verschillende soorten bezoek**, die je niet
door elkaar mag halen:

**1. Verantwoording aan de RDI.** De Cyberbeveiligingswet maakt de Rijksinspectie
Digitale Infrastructuur toezichthouder voor de sector Overheid. Dat is toezicht op
grond van de wet, geen certificering: de RDI kan handhaven en niet een certificaat
onthouden. Overheidsmaatregel 5.36.01 en deel 1 §9 vragen daarnaast om een
jaarlijkse **In Control Verklaring** — een bestuurlijke verklaring van jouw
organisatie, geen document van een auditor.

**2. Een interne audit of een audit in opdracht van je eigen bestuur.** Deel 1 §12.4
vraagt om een interne toezichthouder, en de reguliere §9.2-auditcyclus geldt hier
onverkort. Dit is het gewone werk en het loopt via de rondes van het type `intern`.

**3. Een vrijwillige ISO 27001-certificering.** Sommige overheidsorganisaties
kiezen daar alsnog voor, meestal omdat opdrachtgevers of ketenpartners erom vragen.
Dat is dan een gewone ISO-certificering, tegen ISO 27001 en niet tegen de BIO — de
auditor toetst je managementsysteem, niet je overheidsmaatregelen.

Praktische gevolgen voor dit ISMS:

- **Een RDI-bevinding is geen non-conformiteit tegen een norm.** Leg hem vast als
  `observatie` of `verbeterkans`, tenzij de inspectie een tekortkoming benoemt die
  ook tegen een normclausule of een overheidsmaatregel staat.
- **Er is geen apart rondetype voor toezicht.** Het ISMS kent vier typen:
  `intern`, `intern_nulmeting`, `extern_certificering` en `extern_surveillance`.
  Een RDI-bezoek is geen van vieren. Registreer het als `extern_surveillance` — qua
  vorm het dichtst in de buurt — met de RDI in het auditorveld en in de
  omschrijving met zoveel woorden dat het om **toezicht en niet om certificering**
  gaat. Zonder die notitie leest het rondedossier een jaar later als een
  surveillance-audit die je nooit hebt gehad.
- **De opvolging is wél dezelfde.** Grondoorzaak, corrigerende maatregel,
  effectiviteitstoets — dat pad verandert niet, en dat is de winst van één
  administratie.

Een vierde variant komt uit de keten: **opdrachtgevers en ketenpartners vragen
steeds vaker aansluiting bij de BIO**. Dat is geen audit van jou maar van je
leverancier, en die hoort in het leveranciersdossier (blok 9), niet hier. Zie ook
de inkoopeisen in deel 1 §13; dit systeem legt die vast als contractclausule.

## Het principe: rapport = bewijs, ISMS = opvolging

Je bouwt het rapport niet na en je vervangt het niet. De externe partij heeft geen
account in dit systeem; haar rapport is en blijft het gezaghebbende document. In het
ISMS doe je twee dingen:

1. **Het rapport als bewijs** onder de auditronde hangen (het bewijs-paneel
   onderaan het rondedossier). Zo is het traceerbaar en onveranderlijk bewaard.
2. **De punten overnemen als bevindingen**, zodat opvolging, afwijkingen en KPI's
   in één systeem lopen.

Waarom overnemen en niet alleen het PDF bewaren? Omdat een non-conformiteit een
**corrigerende-actie-cyclus** (§10.2) moet doorlopen — grondoorzaak, maatregel,
effectiviteitstoets. Dat kan alleen als de bevinding als record in het ISMS staat.
Een rapport in een la stuurt geen opvolging aan.

## Stap voor stap

1. **Maak een auditronde** in het jaarplan met het passende type. Vul de naam van
   de externe partij in (vrije tekst — die heeft immers geen account).
2. **Hang het rapport** als bewijsstuk aan de ronde.
3. **Neem elk punt over als bevinding**, met het passende type:

   | Auditpunt | Bevindingtype in het ISMS |
   |---|---|
   | Grote afwijking (major nonconformity) | `non_conformiteit_major` |
   | Kleine afwijking (minor nonconformity) | `non_conformiteit_minor` |
   | Opmerking / observation | `observatie` |
   | Verbeterkans / opportunity for improvement | `verbeterkans` |

   Koppel elke bevinding waar mogelijk aan de betreffende **beheersmaatregel**
   (Bijlage A), zodat je later ziet welke control geraakt is.

4. **Escaleer de non-conformiteiten** vanuit de bevinding naar een **Afwijking
   (§10.2)**. Daar leg je grondoorzaak, corrigerende maatregel en
   effectiviteitstoets vast.
5. **Rond de ronde af** als alles is overgenomen. Afronden bevriest de bevindingen;
   de opvolging loopt daarna verder in Afwijkingen.

## Wat een BIO-auditor extra vraagt

Bij ISO gaat het gesprek over de beheersmaatregel. Bij de BIO gaat het één niveau
dieper, en daar zit het verschil dat je moet voorbereiden.

- **Per overheidsmaatregel, niet per beheersmaatregel.** Deel 1 §4 vraagt per
  maatregel om *opzet, bestaan en werking*. "A.5.24 is geïmplementeerd" is geen
  antwoord als daar zeven genummerde verplichtingen onder hangen; de vraag is of
  5.24.03 belegd is en waar dat uit blijkt. In de SoA-modal legt elke
  overheidsmaatregel daarom zijn eigen status en bewijs vast.
- **Uitzonderingen met een risicoanalyse erbij.** Een overheidsmaatregel op "niet
  van toepassing" hoort een verwijzing naar de onderbouwende risicoanalyse te
  hebben; deel 1 §7 vraagt die verwijzing in een bijlage bij de VvT. De
  ISMS-export levert die bijlage, en op de SoA-pagina staat een teller voor
  uitzonderingen die de verwijzing nog missen. Die hoort op nul te staan vóórdat
  het rapport wordt geschreven.
- **Wanneer is er voor het laatst gekeken?** Elke beoordeling houdt de datum bij.
  "Belegd" zonder recente datum is onder een jaarlijkse verantwoordingscyclus
  weinig waard; de teller op de SoA-pagina laat zien wat is verouderd.
- **De drie maatregelen buiten de Cbw-reikwijdte.** Bij 5.32, 5.33 en 5.34 is de
  grondslag verplichtende zelfregulering en niet de wet. Dat verandert niets aan
  wat je moet doen, maar wel aan wat de RDI kan handhaven — en het is precies het
  soort onderscheid waarop een gesprek vastloopt als niemand het benoemt.

## Tijdens de audit: "mag ik hier een kopie van?"

Een externe auditor zit vóór de schermen en vraagt bij wat hij ziet om een kopie.
Daarvoor is er een knop **Kopie voor de auditor**, die het scherm zoals het er op
dat moment bij staat als Word-document oplevert.

Twee dingen die daarbij van belang zijn:

- **Het document zegt zelf wat het is.** In de kop staan de organisatie, het moment,
  wie de kopie maakte, en — dit is het belangrijkste — hoeveel van hoeveel regels er
  in staan en op welke filters. Een gefilterd overzicht dat zichzelf als het
  volledige register presenteert, is in een auditdossier het gevaarlijkst denkbare
  document.
- **U ziet achteraf wat u hebt meegegeven.** Elke kopie komt als regel in **Bewijs
  & audit trail → Schermkopieën**: welk scherm, welke filters, hoeveel regels, door
  wie en wanneer. Op een auditdag gaan er makkelijk tien schermen mee; die lijst ís
  uw overdrachtsdossier. De kopieën zelf worden niet bewaard.

> **Let op bij toezicht.** Een kopie meegeven aan een toezichthouder is iets anders
> dan een kopie meegeven aan een auditor onder geheimhouding. Weeg per scherm wat u
> verstrekt; de lijst met schermkopieën laat achteraf zien wat het was.

## Waarom dit past

- **Eén administratie.** Interne audits, RDI-toezicht en een eventuele vrijwillige
  certificering leveren bevindingen op dezelfde manier aan; de opvolging (§10.2) is
  identiek. Geen apart spoor per soort bezoek.
- **Traceerbaarheid.** Van auditpunt → bevinding → afwijking → corrigerende
  maatregel → bewijs, allemaal gekoppeld. Bij het volgende bezoek laat je in één
  keer zien wat je met elk punt hebt gedaan.
- **Het rapport blijft leidend.** Je interpreteert de auditor niet opnieuw; je
  registreert zijn oordeel en jouw opvolging. Wijkt jouw samenvatting af van het
  rapport, dan is het rapport het bewijs dat telt.

> **Let op — geen bevinding per control.** Je maakt alleen een bevinding voor de
> punten die daadwerkelijk zijn genoemd. De controls die in orde waren, hoef je niet
> als "conforme bevinding" vast te leggen; het rapport dekt de volledige scope al.
