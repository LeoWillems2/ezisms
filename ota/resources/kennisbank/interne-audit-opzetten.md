# Een interne audit opzetten (§9.2)

De interne audit toetst of het ISMS *werkt zoals bedoeld* en *voldoet aan de norm*.
In dit systeem plan je dat niet los per jaar, maar als een **driejaarlijkse cyclus**
die de hele norm dekt — hoofdstukken 4–10 én Bijlage A — en die je maar één keer
per cyclus hoeft op te zetten.

## De opbouw: cyclus → jaarplan → ronde

- **Auditprogramma** — de cyclus als geheel (standaard 3 jaar). De eigen entiteit
  waarin je de dekking over de jaren plant.
- **Auditplan** — één per jaar, gekoppeld aan het programma.
- **Auditronde** — de concrete uitvoering binnen een jaar, met een **normatieve
  scope**: welke clausules/controls die ronde afdekt.
- **Dekkingsmatrix** — laat over de cyclusjaren zien wat *uitgevoerd*, *gepland*,
  een *gat* of nog *leeg* is. De dekkings-KPI telt alleen **afgeronde** rondes; een
  geplande ronde is nog geen dekking.

## De vaste volgorde

1. **Maak de SoA af.** Alleen **van toepassing** verklaarde controls gaan mee in de
   audit-universe. Staat een control nog op *onbeslist*, dan valt die buiten de
   cyclus. Van-toepassing verklaren voegt de control meteen toe (zie *De SoA
   onderbouwen*).
2. **Zet de cyclus op.** Het snelst met het beheercommando
   `isms:bereid-auditcyclus-voor` (zie onder): dat maakt het programma, de
   jaarplannen, de dekkingsverdeling én een geplande ronde per jaar in één keer.
   Met de hand kan ook — hieronder als voorbeeld een cyclus 2028–2030:

   - **Programma** — *Audits → Auditprogramma → Nieuw programma*. Naam "Interne
     auditcyclus 2028–2030", startdatum `2028-01-01`, aantal jaren `3`, aard
     *certificeringscyclus*. Opslaan, daarna **Activeren**.
   - **Jaarplannen** — *Audits → Overzicht → Nieuw auditplan*, drie keer: 2028,
     2029 en 2030. Het programmascherm kan jaarplannen wel koppelen maar niet
     aanmaken; ze beginnen hier hun leven, los van elke cyclus.
   - **Koppelen** — terug naar *Auditprogramma* en het programma aanklikken. Bij
     "Jaarplannen in de cyclus" staan de drie plannen als grijze badge met een
     `+`. Klik ze **in volgorde** aan: het `+` kent steeds het eerstvolgende
     vrije programmajaar toe, dus 2028 → jaar 1, 2029 → jaar 2, 2030 → jaar 3.
     Verkeerd geklikt? `×` maakt het plan weer los.
   - **Dekkingsplanning** — in hetzelfde blok de knop
     **Vul standaard (eenmaal per cyclus)**: elk in-scope object krijgt interval
     3. Stel daarna per object bij; clausule 9.2 is het klassieke voorbeeld van
     *jaarlijks*.
   - **Rondes** — *Audits → Overzicht → Nieuwe ronde* per jaarplan. In de ronde
     zelf wijs je de auditor toe en vink je de normatieve scope aan: welke
     clausules en controls dít jaar aan de beurt zijn.
   - **Vaststellen** — het jaarplan vaststellen zodra de rondes erin staan. Dat
     is onomkeerbaar.

   > Twee dingen kan alleen het commando: de objecten **spreiden** over de
   > programmajaren — het scherm plant elke dekkingsregel vanaf jaar 1 — en de
   > rondes met hun scope per jaar meteen klaarzetten. Bouw je met de hand, kies
   > dan per ronde zelf de normatieve scope. De matrix leest het *geplande* uit
   > de dekkingsplanning en het *uitgevoerde* uit wat een ronde feitelijk
   > behandelde.
3. **Activeer** het programma, **wijs per ronde een interne auditor toe** en plan de
   datum. De auditor is bewust opengelaten: dat is vaak een (tijdelijk)
   Auditor-account.
4. **Voer uit en rond af.** Afronden bevriest de bevindingen en vult de
   dekkingsmatrix.

## Bewijs en bevindingen: het juiste detailniveau

Per ronde lever je **één auditrapport** als bewijs, waarin je per in-scope control
**Opzet** en **Bestaan** (en waar relevant **Werking**) beoordeelt.

**Bevindingen maak je alleen voor de uitzonderingen:** een tekortkoming
(`non_conformiteit_major`/`minor`), een `observatie` of een `verbeterkans`. Dus
**niet** één bevinding per control — dat zou de lijst en de opvolging waardeloos
maken. Non-conformiteiten escaleer je naar een **Afwijking (§10.2)**.

Een bevinding **sluiten** doe je in het rondedossier, ook nadat de ronde is afgerond:
afronden bevriest het oordeel van de auditor, maar de opvolging loopt daarna gewoon
door. Sluiten vraagt een korte **afhandeling** — wat er met de bevinding is gebeurd.
Een non-conformiteit sluit pas als de bijbehorende afwijking gesloten is.

Bij elke bevinding leg je de **bron** vast: met wie je erover sprak. Kwam de
constatering niet uit een gesprek maar uit je eigen onderzoek — een logbestand, een
export, een document — dan kies je *Geen gesprek — eigen waarneming*. Het veld leeg
laten kan niet: een constatering die niemand kan navragen is niet na te lopen. Bij
een behandeld object heet het veld hetzelfde en werkt het hetzelfde.

Dat een control gewoon in orde was, leg je daarom niet als bevinding vast maar als
**afhandeling op het object zelf**: in het rondedossier klik je het knopje van de
clausule of control aan en kiest *geen opmerkingen*, met de bron erbij — de collega
met wie je erover sprak, of *eigen waarneming* als je het zelf hebt nagelopen in de
documentatie. Kwam je er niet aan toe, dan kies je *niet aan toegekomen* met de reden;
bij het afronden vraagt het scherm die reden alsnog voor alles wat nog grijs staat.
De knopjes kleuren mee — groen (geen opmerkingen), oranje (er is een bevinding),
rood (niet aan toegekomen) — en dat is precies het overzicht dat een externe auditor
komt natellen.

> Dat "de audit compleet is" blijkt uit drie dingen: de **normatieve scope** die je
> aan de ronde vinkt, de **afhandeling per object** die je tijdens de uitvoering
> vastlegt, en het **rapport** als bewijs — niet uit een bevinding per
> beheersmaatregel.

Alleen een object dat je zo hebt behandeld telt mee voor de dekking. In de scope
staan is niet genoeg: dat is het verschil tussen "we waren het van plan" en "we
hebben ernaar gekeken".

## Voor de beheerder: de artisan-commando's

Deze commando's draai je op de server (shell-toegang), niet vanuit de webinterface.
Ze zijn bedoeld voor het opzetten en **resetten** van de interne audit.

### `isms:bereid-auditcyclus-voor` — cyclus in één keer neerzetten

Maakt het programma, een jaarplan per jaar, verdeelt alle in-scope objecten over de
jaren (volledige dekking) en zet per jaar een **geplande** ronde klaar — met de
auditor opengelaten.

```
php artisan isms:bereid-auditcyclus-voor --start=2026
```

| Optie | Betekenis |
|---|---|
| `--start=` | Startdatum (jjjj-mm-dd; een jaartal wordt 1 januari). Standaard vandaag. |
| `--jaren=<n>` | Aantal jaren in de cyclus (standaard 3, of 1 bij `--voorbereiding`). |
| `--voorbereiding` | De opstartfase: één plan met een nulmeting over alles, zonder dekkingsverdeling. |
| `--naam="…"` | Eigen naam (standaard afgeleid van de aard en het venster). |
| `--activeer` | Zet het programma meteen op actief i.p.v. concept. |
| `--forceer` | Ga door ook als de SoA nog controls op *onbeslist* heeft (alleen de in-scope controls gaan mee). |
| `--vervang` | Ruim een botsende cyclus (zelfde naam of overlappend venster) eerst op. |

De `--voorbereiding`-variant bestaat omdat de echte auditcyclus pas begint ná de
certificeringsaudit. Daarvóór is er één ronde die een nulmeting is en geen
oordeel — die telt dan ook niet mee voor de dekking.

Twee vangnetten: bij een **onbesliste SoA** breekt het af tenzij `--forceer`; bij
een **botsende cyclus** breekt het af tenzij `--vervang`. Ruimt `--vervang` daarbij
jaren op die *buiten* het nieuwe venster vallen (bv. een lopende 2026 bij een start
in 2027), dan benoemt het commando die jaren en vraagt eerst bevestiging.

### `isms:verwijder-auditdata` — de interne audit resetten

Verwijdert **alle** auditmanagement-data (programma's, jaarplannen, rondes,
bevindingen, dekkingen en de bijbehorende koppelingen) voor een schone start.

```
php artisan isms:verwijder-auditdata            # dry-run: toont alleen de telling en vraagt bevestiging
php artisan isms:verwijder-auditdata --bevestig # verwijdert direct
```

| Optie | Betekenis |
|---|---|
| *(geen)* | Toont de telling per tabel en vraagt bevestiging (default **nee**). Zonder bevestiging wordt niets verwijderd. |
| `--bevestig` | Verwijdert direct, zonder interactieve vraag. |
| `--met-trail` | Wist óók de audit-trail-regels van blok *auditmanagement* (de trail van andere blokken blijft staan). |
| `--met-universe` | Wist óók de `auditobjecten` (daarna opnieuw synchroniseren nodig). |

**Afwijkingen (§10.2)** die uit auditbevindingen ontstonden blijven altijd staan;
ze verliezen alleen hun koppeling naar de verwijderde bevinding, en daar
waarschuwt het commando voor.

### `isms:sync-auditobjecten` — audit-universe bijwerken

Zet de van-toepassing verklaarde controls om in auditobjecten. Meestal **niet
nodig**: van-toepassing verklaren in de SoA doet dit al automatisch. Handig als
bulk-hersteltool, bijvoorbeeld na `--met-universe` of na een grote SoA-wijziging.

```
php artisan isms:sync-auditobjecten
```

## Een cyclus opnieuw opzetten

Een schone herstart is dus: **`isms:verwijder-auditdata --bevestig`** →
**`isms:bereid-auditcyclus-voor --start=<jaar>`** → in de webinterface activeren,
auditors toewijzen en plannen.
