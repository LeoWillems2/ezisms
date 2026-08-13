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
2. **Zet de cyclus op** met het beheercommando `isms:bereid-auditcyclus-voor` (zie
   onder) — of leg programma, jaarplannen en dekking met de hand aan via
   **Audits → Auditprogramma**.
3. **Activeer** het programma, **wijs per ronde een interne auditor toe** en plan de
   datum. De auditor is bewust opengelaten: dat is vaak een (tijdelijk)
   Auditor-account.
4. **Voer uit en rond af.** Afronden bevriest de bevindingen en vult de
   dekkingsmatrix.

## Bewijs en bevindingen: het juiste detailniveau

Per ronde lever je **één auditrapport** als bewijs, waarin je per in-scope control
**Opzet** en **Bestaan** (en waar relevant **Werking**) beoordeelt. Dat rapport is
je dekkingsbewijs — óók voor de controls die gewoon in orde zijn.

**Bevindingen maak je alleen voor de uitzonderingen:** een tekortkoming
(`non_conformiteit_major`/`minor`), een `observatie` of een `verbeterkans`. Dus
**niet** één bevinding per control — dat zou de lijst en de opvolging waardeloos
maken. Non-conformiteiten escaleer je naar een **Afwijking (§10.2)**.

> Dat "de audit compleet is" blijkt uit twee dingen: de **normatieve scope** die je
> aan de ronde vinkt, en het **rapport** als bewijs — niet uit een positief record
> per beheersmaatregel.

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
