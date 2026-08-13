# De externe certificeringsaudit in het ISMS

Bij een externe audit (certificering of surveillance) levert de **externe auditor**
de punten aan — jij stelt ze niet zelf op. De vraag is dan: wat doe je met dat
auditrapport in dit ISMS? Kort: **het rapport blijft de bron van waarheid, jij
neemt de uitkomsten over als bevindingen en volgt de non-conformiteiten op.**

## Het principe: rapport = bewijs, ISMS = opvolging

Je bouwt het auditrapport niet na en je vervangt het niet. De certificerende
instelling heeft geen account in dit systeem; hun rapport is en blijft het
gezaghebbende document. In het ISMS doe je twee dingen:

1. **Het rapport als bewijs** onder de auditronde hangen (het bewijs-paneel
   onderaan het rondedossier). Zo is het traceerbaar en onveranderlijk bewaard.
2. **De punten van de auditor overnemen als bevindingen**, zodat opvolging,
   afwijkingen en KPI's in één systeem lopen.

Waarom overnemen en niet alleen het PDF bewaren? Omdat een non-conformiteit een
**corrigerende-actie-cyclus** (§10.2) moet doorlopen — grondoorzaak, maatregel,
effectiviteitstoets. Dat kan alleen als de bevinding als record in het ISMS staat.
Een rapport in een la stuurt geen opvolging aan.

## Stap voor stap

1. **Maak een auditronde** in het jaarplan met type **`extern_certificering`** of
   **`extern_surveillance`**. Vul de naam van de externe auditor in (vrije tekst —
   die heeft immers geen account).
2. **Hang het auditrapport** als bewijsstuk aan de ronde.
3. **Neem elk auditpunt over als bevinding**, met het passende type:

   | Auditpunt | Bevindingtype in het ISMS |
   |---|---|
   | Grote afwijking (major nonconformity) | `non_conformiteit_major` |
   | Kleine afwijking (minor nonconformity) | `non_conformiteit_minor` |
   | Opmerking / observation | `observatie` |
   | Verbeterkans / opportunity for improvement | `verbeterkans` |

   Koppel elke bevinding waar mogelijk aan de betreffende **maatregel** (Annex A),
   zodat je later ziet welke control geraakt is.
4. **Escaleer de non-conformiteiten** vanuit de bevinding naar een **Afwijking
   (§10.2)**. Daar leg je grondoorzaak, corrigerende maatregel en
   effectiviteitstoets vast — precies wat de certificerende instelling bij de
   volgende ronde wil terugzien.
5. **Rond de ronde af** als alles is overgenomen. Afronden bevriest de
   bevindingen; de opvolging loopt daarna verder in Afwijkingen.

## Tijdens de audit: "mag ik hier een kopie van?"

Een externe auditor zit vóór de schermen en vraagt bij wat hij ziet om een kopie.
Daarvoor is er een knop **Kopie voor de auditor**, die het scherm zoals het er op
dat moment bij staat als Word-document oplevert.

Twee dingen die daarbij van belang zijn:

- **Het document zegt zelf wat het is.** In de kop staan de organisatie, het
  moment, wie de kopie maakte, en — dit is het belangrijkste — hoeveel van hoeveel
  regels er in staan en op welke filters. Een gefilterd overzicht dat zichzelf als
  het volledige register presenteert, is in een auditdossier het gevaarlijkst
  denkbare document.
- **U ziet achteraf wat u hebt meegegeven.** Elke kopie komt als regel in
  **Bewijs & audit trail → Schermkopieën**: welk scherm, welke filters, hoeveel
  regels, door wie en wanneer. Op een auditdag gaan er makkelijk tien schermen
  mee; die lijst ís uw overdrachtsdossier. De kopieën zelf worden niet bewaard.

De knop verschijnt per scherm, en welke schermen hem krijgen is een bewuste keuze
— niet elk scherm is bedoeld als bewijsstuk.

## Waarom dit past

- **Eén administratie.** Interne én externe audits leveren bevindingen op dezelfde
  manier aan; de opvolging (§10.2) is identiek. Geen apart spoor voor extern.
- **Traceerbaarheid.** Van auditpunt → bevinding → afwijking → corrigerende
  maatregel → bewijs, allemaal gekoppeld. Bij de surveillance-audit een jaar later
  laat je in één keer zien wat je met elk punt hebt gedaan.
- **Het rapport blijft leidend.** Je interpreteert de auditor niet opnieuw; je
  registreert zijn oordeel en jouw opvolging. Wijkt jouw samenvatting af van het
  rapport, dan is het rapport het bewijs dat telt.

> **Let op — geen bevinding per control.** Je maakt alleen een bevinding voor de
> punten die de auditor daadwerkelijk noemt. De controls die in orde waren, hoef
> je niet als "conforme bevinding" vast te leggen; het auditrapport dekt de
> volledige scope al. Dit is hetzelfde detailniveau als bij de interne audit.
