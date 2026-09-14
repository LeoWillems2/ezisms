# Een interne audit opzetten (§9.2)

De interne audit toetst of het ISMS *werkt zoals bedoeld* en *voldoet aan de norm*.
In dit systeem wordt de interne audit niet per jaar los gepland, maar als een
**driejaarlijkse cyclus** die de hele norm dekt: hoofdstukken 4–10 en Bijlage A.
Die cyclus hoeft maar één keer per cyclus te worden opgezet.

## De opbouw: cyclus → jaarplan → ronde

- **Auditprogramma**: de cyclus als geheel (standaard 3 jaar). Het auditprogramma
  is de eigen entiteit waarin de dekking over de jaren wordt gepland.
- **Auditplan**: één per jaar, gekoppeld aan het programma.
- **Auditronde**: de concrete uitvoering binnen een jaar, met een **normatieve
  scope**. De normatieve scope bepaalt welke clausules en controls die ronde
  afdekt.
- **Dekkingsmatrix**: de matrix toont over de cyclusjaren wat *uitgevoerd*,
  *gepland*, een *gat* of nog *leeg* is. De dekkings-KPI telt alleen **afgeronde**
  rondes. Een geplande ronde is nog geen dekking.

## De vaste volgorde

1. **Maak de SoA af.** Alleen controls die **van toepassing** zijn verklaard, gaan
   mee in de audit-universe. Een control die nog op *onbeslist* staat, valt buiten
   de cyclus. Een control van toepassing verklaren voegt die control direct toe
   (zie *De SoA onderbouwen*).
2. **Zet de cyclus op.** De snelste route is het beheercommando
   `isms:bereid-auditcyclus-voor` (zie onder). Dat commando maakt in één keer het
   programma, de jaarplannen, de dekkingsverdeling en een geplande ronde per jaar
   aan. De cyclus is ook met de hand op te zetten. Hieronder staat als voorbeeld een
   cyclus 2028–2030:

   - **Programma**: *Audits → Auditprogramma → Nieuw programma*. Naam "Interne
     auditcyclus 2028–2030", startdatum `2028-01-01`, aantal jaren `3`, aard
     *certificeringscyclus*. Na het opslaan volgt **Activeren**.
   - **Jaarplannen**: in het planningsblok van het programma zelf, met de knop
     **Jaarplan toevoegen (jaar N)**. Die knop maakt het plan aan en koppelt het
     direct aan het eerstvolgende vrije programmajaar. Drie keer klikken levert
     2028 → jaar 1, 2029 → jaar 2 en 2030 → jaar 3 op.
   - **Koppelen**: dit is alleen nodig voor plannen die al bestonden, bijvoorbeeld
     uit de opstartfase of los aangemaakt met *Audits → Overzicht → Nieuw auditplan*.
     Die plannen staan bij "Jaarplannen in de cyclus" als grijze badge met een `+`.
     Ze horen **in volgorde** te worden aangeklikt, omdat het `+` steeds het
     eerstvolgende vrije programmajaar toekent. Een verkeerde klik is te herstellen
     met `×`, dat het plan weer loskoppelt.
   - **Dekkingsplanning**: in hetzelfde blok staat de knop
     **Vul standaard (eenmaal per cyclus)**. Die knop geeft elk in-scope object
     interval 3. Daarna is het interval per object bij te stellen. Clausule 9.2 is
     het klassieke voorbeeld van een object dat *jaarlijks* wordt geaudit.
   - **Rondes**: *Audits → Overzicht → Nieuwe ronde* per jaarplan. In de ronde zelf
     wordt de auditor toegewezen en de normatieve scope aangevinkt: de clausules en
     controls die dat jaar aan de beurt zijn.
   - **Vaststellen**: het jaarplan wordt vastgesteld zodra de rondes erin staan.
     Vaststellen is onomkeerbaar.

   > Spreiden kan ook met de hand. De knop **Verdeel de groepen over de jaren**
   > zet het startjaar per dekkingsregel volgens de groepsverdeling. Daarna is elke
   > regel afzonderlijk bij te stellen in de kolom *Vanaf jaar*. Zonder die stap
   > staat elke regel op jaar 1 en blijven de kolommen voor jaar 2 en 3 leeg. Alleen
   > het commando zet de rondes met hun scope per jaar direct klaar. Bij een
   > handmatige opzet wordt de normatieve scope per ronde zelf gekozen. De matrix
   > leest het *geplande* uit de dekkingsplanning en het *uitgevoerde* uit wat een
   > ronde feitelijk behandelde.
3. **Activeer** het programma, **wijs per ronde een interne auditor toe** en plan de
   datum. Het veld auditor is bewust leeg gelaten, omdat de auditor vaak een
   (tijdelijk) Auditor-account is.
4. **Voer uit en rond af.** Afronden bevriest de bevindingen en vult de
   dekkingsmatrix.

## Bewijs en bevindingen: het juiste detailniveau

Per ronde wordt **één auditrapport** als bewijs geleverd. In dat rapport beoordeelt
de auditor per in-scope control de **Opzet** en het **Bestaan**, en waar relevant
de **Werking**.

**Bevindingen worden alleen vastgelegd voor de uitzonderingen:** een tekortkoming
(`non_conformiteit_major`/`minor`), een `observatie` of een `verbeterkans`. Er
hoort dus **niet** één bevinding per control te komen, omdat dat de lijst en de
opvolging waardeloos maakt. Non-conformiteiten worden geëscaleerd naar een
**Afwijking (§10.2)**.

Een bevinding wordt **gesloten** in het rondedossier, ook nadat de ronde is
afgerond. Afronden bevriest het oordeel van de auditor, maar de opvolging loopt
daarna door. Sluiten vraagt een korte **afhandeling**: een beschrijving van wat er
met de bevinding is gebeurd. Een non-conformiteit sluit pas als de bijbehorende
afwijking gesloten is.

Bij elke bevinding legt de auditor de **bron** vast: de persoon met wie de auditor
erover sprak. Als de constatering niet uit een gesprek kwam maar uit eigen
onderzoek, zoals een logbestand, een export of een document, dan is de keuze
*Geen gesprek — eigen waarneming*. Het veld leeg laten is niet mogelijk, omdat een
constatering zonder bron niet na te lopen is. Bij een behandeld object heeft het
veld dezelfde naam en werkt het op dezelfde manier.

Een control die in orde was, wordt daarom niet als bevinding vastgelegd, maar als
**afhandeling op het object zelf**. In het rondedossier klikt de auditor het
knopje van de clausule of control aan en kiest *geen opmerkingen*, met de bron
erbij. De bron is de collega met wie de auditor erover sprak, of *eigen
waarneming* als de auditor het zelf in de documentatie heeft nagelopen. Als de
auditor er niet aan toe kwam, is de keuze *niet aan toegekomen* met de reden. Bij
het afronden vraagt het scherm die reden alsnog voor alles wat nog grijs staat.
De knopjes krijgen een kleur: groen (geen opmerkingen), oranje (er is een
bevinding) en rood (niet aan toegekomen). Dat overzicht is precies wat een externe
auditor komt natellen.

> Dat de audit compleet is, blijkt uit drie dingen: de **normatieve scope** die aan
> de ronde is aangevinkt, de **afhandeling per object** die tijdens de uitvoering
> is vastgelegd, en het **rapport** als bewijs. Een bevinding per
> beheersmaatregel is daarvoor niet nodig.

Alleen een object dat op deze manier is behandeld, telt mee voor de dekking. In de
scope staan is niet genoeg. Dat is het verschil tussen een voornemen om een object
te beoordelen en een feitelijke beoordeling.

## Voor de beheerder: de artisan-commando's

Deze commando's worden op de server uitgevoerd (shell-toegang) en niet vanuit de
webinterface. Ze zijn bedoeld voor het opzetten en **resetten** van de interne
audit.

### `isms:bereid-auditcyclus-voor` — cyclus in één keer neerzetten

Dit commando maakt het programma en een jaarplan per jaar aan, verdeelt alle
in-scope objecten over de jaren (volledige dekking) en zet per jaar een
**geplande** ronde klaar. Het veld auditor blijft daarbij leeg.

```
php artisan isms:bereid-auditcyclus-voor --start=2026
```

| Optie | Betekenis |
|---|---|
| `--start=` | Startdatum (jjjj-mm-dd; een jaartal wordt 1 januari). Standaard vandaag. |
| `--jaren=<n>` | Aantal jaren in de cyclus (standaard 3, of 1 bij `--voorbereiding`). |
| `--voorbereiding` | De opstartfase: één plan met een nulmeting over alles, zonder dekkingsverdeling. |
| `--naam="…"` | Eigen naam (standaard afgeleid van de aard en het venster). |
| `--activeer` | Zet het programma direct op actief in plaats van concept. |
| `--forceer` | Gaat door, ook als de SoA nog controls op *onbeslist* heeft (alleen de in-scope controls gaan mee). |
| `--vervang` | Ruimt eerst een botsende cyclus op (zelfde naam of overlappend venster). |

De variant `--voorbereiding` bestaat omdat de echte auditcyclus pas begint na de
certificeringsaudit. Daarvóór is er één ronde die een nulmeting is en geen oordeel.
Die ronde telt daarom niet mee voor de dekking.

Het commando heeft twee vangnetten. Bij een **onbesliste SoA** breekt het af,
tenzij `--forceer` is meegegeven. Bij een **botsende cyclus** breekt het af, tenzij
`--vervang` is meegegeven. Als `--vervang` daarbij jaren opruimt die buiten het
nieuwe venster vallen (bijvoorbeeld een lopend 2026 bij een start in 2027), dan
benoemt het commando die jaren en vraagt het eerst om bevestiging.

### `isms:verwijder-auditdata` — de interne audit resetten

Dit commando verwijdert **alle** auditmanagement-data (programma's, jaarplannen,
rondes, bevindingen, dekkingen en de bijbehorende koppelingen) voor een schone
start.

```
php artisan isms:verwijder-auditdata            # dry-run: toont alleen de telling en vraagt bevestiging
php artisan isms:verwijder-auditdata --bevestig # verwijdert direct
```

| Optie | Betekenis |
|---|---|
| *(geen)* | Toont de telling per tabel en vraagt bevestiging (standaard **nee**). Zonder bevestiging wordt niets verwijderd. |
| `--bevestig` | Verwijdert direct, zonder interactieve vraag. |
| `--met-trail` | Wist ook de audit-trail-regels van blok *auditmanagement* (de trail van andere blokken blijft staan). |
| `--met-universe` | Wist ook de `auditobjecten` (daarna is opnieuw synchroniseren nodig). |

**Afwijkingen (§10.2)** die uit auditbevindingen zijn ontstaan, blijven altijd
staan. Ze verliezen alleen hun koppeling met de verwijderde bevinding, en het
commando waarschuwt daarvoor.

### `isms:sync-auditobjecten` — audit-universe bijwerken

Dit commando zet de controls die van toepassing zijn verklaard om in
auditobjecten. Meestal is het commando **niet nodig**, omdat het van toepassing
verklaren in de SoA dit al automatisch doet. Het commando is bruikbaar als
hersteltool voor bulkwijzigingen, bijvoorbeeld na `--met-universe` of na een grote
SoA-wijziging.

```
php artisan isms:sync-auditobjecten
```

## Een cyclus opnieuw opzetten

Een schone herstart bestaat uit drie stappen: **`isms:verwijder-auditdata --bevestig`**
→ **`isms:bereid-auditcyclus-voor --start=<jaar>`** → in de webinterface
activeren, auditors toewijzen en plannen.
