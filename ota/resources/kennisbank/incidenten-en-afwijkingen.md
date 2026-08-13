Deze pagina legt uit hoe incidenten en afwijkingen (non-conformiteiten) in het
ISMS zijn opgezet, en hoe die opzet aansluit op de eisen van ISO/IEC 27001.

## Twee gekoppelde levenscycli

Een **incident** is een operationeel beveiligingsvoorval. Een **afwijking** is de
formele constatering dat een beheersmaatregel tekortschoot. Niet elk incident
leidt tot een afwijking — maar die keuze moet expliciet worden gemaakt.

**Incident:** `gemeld → in_onderzoek → opgelost → gesloten`
(met ernst `laag / midden / hoog / kritiek`, optioneel gekoppeld aan een asset of risico)

**Afwijking:** `open → analyse → actie_lopend → gesloten`
(met bron `incident`, `audit_bevinding` of `interne_signalering`)

## "Opgelost" is niet "gesloten"

Deze twee zijn bewust gescheiden:

- **`opgelost`** — het *probleem* is verholpen (containment/herstel, Annex A 5.26).
- **`gesloten`** — het *dossier* is administratief afgerond, ná eventuele opvolging.

Een incident kan dus opgelost zijn terwijl de corrigerende-actie-cyclus nog
loopt; het dossier blijft dan terecht open.

## De sluitpoort van een incident

Een incident kan **niet** naar `gesloten` zonder dat aan vier voorwaarden is
voldaan:

1. De status is al **`opgelost`** — het operationele herstel is niet over te slaan.
2. Er hangt **geen niet-gesloten afwijking** meer aan.
3. Is er géén afwijking, dan is een **vastgelegde motivatie** verplicht waaróm er
   geen corrigerende maatregel nodig is — of je opent er alsnog een.
4. De **externe meldplicht is beoordeeld** — bij de meeste incidenten is dat één
   klik, zie hieronder.

Voorwaarde 3 en 4 zijn de kern: elk incident dwingt twee vragen af — *"vergt dit
een corrigerende actie?"* en *"raakt dit de meldplicht?"*. Stilzwijgend afsluiten
kan niet.

Een **verstreken meldtermijn blokkeert het sluiten juist niet**. Een gemiste
melding is een feit dat vastgelegd moet worden, niet iets wat je wegneemt door
het dossier open te houden.

## Externe meldplicht: eerst de vraag of hij speelt

Het scherm begint niet met "moet dit gemeld worden" maar met **raakt dit incident
de meldplicht überhaupt**:

1. **Raakt dit incident persoonsgegevens?**
2. **Is dit een incident in netwerk- of informatiesystemen?** — deze vraag
   verschijnt alleen als je organisatie onder de Cyberbeveiligingswet valt.

Is het antwoord op alles "nee", dan ben je klaar. Geen motivatie, geen termijnen.
Een stroomstoring, een mislukte hersteltest of een tailgating-observatie zonder
datatoegang valt buiten beide wetten, en dan is er ook niets te documenteren:
AVG art. 33 lid 5 gaat over *inbreuken in verband met persoonsgegevens*, niet
over elk beveiligingsincident.

Pas als er wél raakvlak is, volgen de motivatie en de meldvraag. **De grondslag
kies je niet zelf** — die volgt uit de twee antwoorden hierboven.

> **Inrichtingsbeslissing.** Of je organisatie Cbw-plichtig is, hangt af van
> sector en omvang en is een juridisch oordeel dat je één keer maakt. Het staat
> daarom in de installatie-instelling `ISMS_CBW_PLICHTIG` en niet per incident.
> **De standaard is "nee".** Val je er wél onder, zet hem dan aan: anders stelt
> het ISMS de Cbw-vraag nooit.

## De twee wetten

Ze kunnen tegelijk gelden, en bij een datalek in een Cbw-plichtige organisatie is
dat het gewone geval en niet de uitzondering. Ze verschillen van aard: de AVG
kent één deadline plus een paar open verplichtingen, de Cbw is een schema.

| Verplichting | Grondslag | Termijn |
|---|---|---|
| Melding aan de toezichthouder | AVG art. 33 lid 1 | 72 uur na kennisname |
| Mededeling aan de betrokkenen | AVG art. 34 lid 1 | "onverwijld" bij hoog risico — géén getal |
| Vroegtijdige waarschuwing | Cbw art. 26 lid 1 | 24 uur na kennisname |
| Incidentmelding | Cbw art. 27 lid 1 | 72 uur na kennisname |
| Eindverslag | Cbw art. 29 | één maand ná de incidentmelding |

Vier dingen die in het scherm terugkomen:

- **De klok start bij kennisname, niet bij de registratie hier.** Vandaar het
  aparte veld *Kennisname door de organisatie*, dat je achteraf mag corrigeren:
  wanneer je het écht wist, wordt vaak pas tijdens het onderzoek helder. De
  correctie staat in de audit trail.
- **Het gekoppelde asset praat mee.** Hangt het incident aan een asset dat
  persoonsgegevens bevat, dan zie je dat bij de eerste vraag staan — en zet je
  hem toch op "nee", dan spreekt het scherm je tegen. Het blokkeert niets: of een
  incident écht persoonsgegevens raakt, hangt af van wat er gebeurd is en niet
  alleen van wat er in het systeem zit.
- **"Onverwijld" is de eis; het getal is de buitengrens.** Beide wetten zeggen
  het zo — "onverwijld of, indien dat niet mogelijk is, binnen 24 uur" (Cbw),
  "zonder onredelijke vertraging en, indien mogelijk, uiterlijk 72 uur" (AVG).
  Daarom toont het scherm een uiterste datum en geen aftellende klok.
- **Een verplichting zonder datum is normaal.** De mededeling aan betrokkenen
  heeft nooit een termijn, en het Cbw-eindverslag krijgt er pas een zodra de
  incidentmelding gedaan is. Die staan als *verplicht, nog geen datum*.
- **Ook een "nee" wordt gemotiveerd — maar alleen als de wet speelt.** Is het een
  inbreuk in verband met persoonsgegevens, dan verlangt AVG art. 33 lid 5 dat je
  hem documenteert, óók als je hem niet meldt. Het oordeel dat een risico
  onwaarschijnlijk is (art. 33 lid 1) of dat een incident niet significant is,
  heeft criteria en hoort navolgbaar te zijn. Buiten die gevallen hoef je niets
  op te schrijven.

Wat het ISMS **niet** bijhoudt: bij welke instantie je meldt en via welk portaal.
Dat staat in je meldprocedure; hier staat het besluit, de termijn en het feit.
Het ontvangstbewijs van de toezichthouder hang je als bewijsstuk aan het incident.

## De corrigerende-actie-cyclus (onder de afwijking)

Onder een afwijking hangt de volledige verbetercyclus: **grondoorzaak →
corrigerende maatregel** (met eigenaar en deadline) **→ effectiviteitstoets**
(`effectief` / `niet_effectief`). De tussenstatussen `analyse` en `actie_lopend`
zijn afgeleid van wat eronder hangt; `gesloten` is dat bewust niet.

Een afwijking sluiten is een **managementbesluit met naam en datum** en kan
alleen als:

1. er minstens één corrigerende maatregel is;
2. álle maatregelen op `voltooid` staan;
3. elke maatregel een effectiviteitstoets met resultaat `effectief` heeft.

## Aansluiting op ISO/IEC 27001

| Eis | Waar in de opzet |
|---|---|
| **A.5.24** Planning & voorbereiding incidentbeheer | De vaste workflow, rollen en statussen |
| **A.5.25** Beoordeling & besluit over gebeurtenissen | `gemeld → in_onderzoek`, triage via `ernst` |
| **A.5.26** Reactie op incidenten | Stap naar `opgelost`; CISO wordt per e-mail gealarmeerd |
| **A.5.27** Lering uit incidenten | De afwijking/CAPA-lus met grondoorzaak en effectiviteitstoets |
| **A.5.28** Verzamelen van bewijs | Append-only audit trail + bewijskoppeling |
| **Hoofdstuk 10** Non-conformiteit & corrigerende maatregel | De afwijking-cyclus; de "open een afwijking of motiveer waarom niet"-poort |

> **Editie-nuance.** In ISO 27001:**2022** is dit **Clause 10.2** "Non-conformiteit
> en corrigerende maatregel" en **10.1** "Continue verbetering"; in de
> **2013**-editie waren die nummers omgewisseld. De inhoud is identiek — alleen
> het nummer verschilt per editie.

## Traceerbaarheid & meting

- Incident en afwijking zijn **auditeerbaar**: elke statuswijziging komt
  append-only in de audit trail, met wie en wanneer (Clause 7.5 / 9.2, A.5.28).
- De **doorlooptijd** (van `gemeld` tot `gesloten`) wordt gemeten en voedt de
  KPI's (Clause 9.1, monitoring van de doeltreffendheid van het proces).

**Samengevat:** een incident is pas dicht als het óf een afgeronde, op
effectiviteit getoetste corrigerende maatregel heeft, óf een vastgelegde
onderbouwing waarom die niet nodig was — en elk sluitbesluit draagt een naam en
een datum.
