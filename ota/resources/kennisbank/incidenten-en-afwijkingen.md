Deze pagina beschrijft hoe incidenten en afwijkingen (non-conformiteiten) in het
ISMS zijn opgezet, en hoe die opzet aansluit op de eisen van ISO/IEC 27001.

## Twee gekoppelde levenscycli

Een **incident** is een operationeel beveiligingsvoorval. Een **afwijking** is de
formele constatering dat een beheersmaatregel tekortschoot. Niet elk incident
leidt tot een afwijking, maar die keuze moet expliciet worden gemaakt.

**Incident:** `gemeld → in_onderzoek → opgelost → gesloten`
(met ernst `laag / midden / hoog / kritiek`, optioneel gekoppeld aan een asset of risico)

**Afwijking:** `open → analyse → actie_lopend → gesloten`
(met bron `incident`, `audit_bevinding` of `interne_signalering`)

## "Opgelost" is niet "gesloten"

Deze twee statussen zijn bewust gescheiden:

- **`opgelost`**: het *probleem* is verholpen (containment/herstel, Annex A 5.26).
- **`gesloten`**: het *dossier* is administratief afgerond, na eventuele opvolging.

Een incident kan dus opgelost zijn terwijl de corrigerende-actie-cyclus nog
loopt. Het dossier blijft dan terecht open.

## De blokkade bij het sluiten van een incident

Een incident kan **niet** naar `gesloten` als niet aan vier voorwaarden is
voldaan:

1. De status is al **`opgelost`**. Het operationele herstel is niet over te slaan.
2. Er hangt **geen niet-gesloten afwijking** meer aan het incident.
3. Als er geen afwijking is, is een **vastgelegde motivatie** verplicht waarom er
   geen corrigerende maatregel nodig is. Het alternatief is alsnog een afwijking
   openen.
4. De **externe meldplicht is beoordeeld**. Bij de meeste incidenten kost dat één
   klik; zie hieronder.

Voorwaarde 3 en 4 vormen de kern. Elk incident dwingt twee vragen af: *"vergt dit
een corrigerende actie?"* en *"raakt dit de meldplicht?"*. Een incident
stilzwijgend afsluiten is niet mogelijk.

Een **verstreken meldtermijn blokkeert het sluiten juist niet**. Een gemiste
melding is een feit dat vastgelegd moet worden. Het openhouden van het dossier
neemt dat feit niet weg.

## Externe meldplicht: eerst de vraag of die van toepassing is

Het scherm begint niet met de vraag of het incident gemeld moet worden, maar met
de vraag **of het incident de meldplicht überhaupt raakt**:

1. **Raakt dit incident persoonsgegevens?**
2. **Is dit een incident in netwerk- of informatiesystemen?** Deze vraag
   verschijnt alleen als de organisatie onder de Cyberbeveiligingswet valt.

Als het antwoord op beide vragen "nee" is, is de beoordeling klaar. Er is dan
geen motivatie nodig en er gelden geen termijnen. Een stroomstoring, een mislukte
hersteltest of een tailgating-observatie zonder datatoegang valt buiten beide
wetten, en dan is er ook niets te documenteren. AVG art. 33 lid 5 gaat over
*inbreuken in verband met persoonsgegevens*, en niet over elk
beveiligingsincident.

Pas als er wel een raakvlak is, volgen de motivatie en de meldvraag. **De
grondslag wordt niet handmatig gekozen.** De grondslag volgt uit de twee
antwoorden hierboven.

> **Inrichtingsbeslissing.** Of een organisatie Cbw-plichtig is, hangt af van
> sector en omvang. Dat is een juridisch oordeel dat één keer wordt gemaakt. Het
> oordeel staat daarom in de installatie-instelling `ISMS_CBW_PLICHTIG` en niet per
> incident. **De standaard is "nee".** Een organisatie die wel onder de Cbw valt,
> hoort de instelling aan te zetten. Anders stelt het ISMS de Cbw-vraag nooit.

## De twee wetten

De twee wetten kunnen tegelijk gelden. Bij een datalek in een Cbw-plichtige
organisatie is dat het gewone geval en niet de uitzondering. De wetten verschillen
van aard: de AVG kent één deadline plus enkele open verplichtingen, en de Cbw
kent een schema.

| Verplichting | Grondslag | Termijn |
|---|---|---|
| Melding aan de toezichthouder | AVG art. 33 lid 1 | 72 uur na kennisname |
| Mededeling aan de betrokkenen | AVG art. 34 lid 1 | "onverwijld" bij hoog risico, zonder getal |
| Vroegtijdige waarschuwing | Cbw art. 26 lid 1 | 24 uur na kennisname |
| Incidentmelding | Cbw art. 27 lid 1 | 72 uur na kennisname |
| Eindverslag | Cbw art. 29 | één maand na de incidentmelding |

Vijf punten komen in het scherm terug:

- **De klok start bij kennisname, en niet bij de registratie in het ISMS.** Daarom
  is er een apart veld *Kennisname door de organisatie*, dat achteraf te corrigeren
  is. Het werkelijke moment van kennisname wordt vaak pas tijdens het onderzoek
  duidelijk. De correctie staat in de audit trail.
- **Het gekoppelde asset wordt meegewogen.** Als het incident aan een asset hangt
  dat persoonsgegevens bevat, toont het scherm dat bij de eerste vraag. Als die
  vraag toch op "nee" wordt gezet, geeft het scherm een tegensignaal. Het scherm
  blokkeert niets, omdat het afhangt van wat er is gebeurd of een incident
  werkelijk persoonsgegevens raakt, en niet alleen van wat er in het systeem is
  opgeslagen.
- **"Onverwijld" is de eis, en het getal is de buitengrens.** Beide wetten
  formuleren het zo: "onverwijld of, indien dat niet mogelijk is, binnen 24 uur"
  (Cbw) en "zonder onredelijke vertraging en, indien mogelijk, uiterlijk 72 uur"
  (AVG). Daarom toont het scherm een uiterste datum en geen aftellende klok.
- **Een verplichting zonder datum is normaal.** De mededeling aan betrokkenen heeft
  nooit een termijn, en het Cbw-eindverslag krijgt pas een termijn zodra de
  incidentmelding is gedaan. Die verplichtingen staan als *verplicht, nog geen
  datum*.
- **Ook een "nee" wordt gemotiveerd, maar alleen als de wet van toepassing is.** Bij
  een inbreuk in verband met persoonsgegevens verlangt AVG art. 33 lid 5 dat de
  organisatie de inbreuk documenteert, ook als die niet wordt gemeld. Het oordeel
  dat een risico onwaarschijnlijk is (art. 33 lid 1) of dat een incident niet
  significant is, heeft criteria en hoort navolgbaar te zijn. Buiten die gevallen
  hoeft niets te worden vastgelegd.

Het ISMS houdt **niet** bij bij welke instantie en via welk portaal de melding is
gedaan. Dat staat in de meldprocedure van de organisatie. Het ISMS legt het
besluit, de termijn en het feit vast. Het ontvangstbewijs van de toezichthouder
wordt als bewijsstuk aan het incident gekoppeld.

## De corrigerende-actie-cyclus (onder de afwijking)

Onder een afwijking hangt de volledige verbetercyclus: **grondoorzaak →
corrigerende maatregel** (met eigenaar en deadline) **→ effectiviteitstoets**
(`effectief` / `niet_effectief`). De tussenstatussen `analyse` en `actie_lopend`
worden afgeleid van wat onder de afwijking hangt. De status `gesloten` wordt
bewust niet afgeleid.

Een afwijking sluiten is een **managementbesluit met naam en datum**. Dat besluit
is alleen mogelijk als:

1. er minstens één corrigerende maatregel is;
2. alle maatregelen op `voltooid` staan;
3. elke maatregel een effectiviteitstoets met resultaat `effectief` heeft.

## Aansluiting op ISO/IEC 27001

| Eis | Waar in de opzet |
|---|---|
| **A.5.24** Planning & voorbereiding incidentbeheer | De vaste workflow, rollen en statussen |
| **A.5.25** Beoordeling & besluit over gebeurtenissen | `gemeld → in_onderzoek`, triage via `ernst` |
| **A.5.26** Reactie op incidenten | Stap naar `opgelost`; de CISO wordt per e-mail gealarmeerd |
| **A.5.27** Lering uit incidenten | De afwijking/CAPA-lus met grondoorzaak en effectiviteitstoets |
| **A.5.28** Verzamelen van bewijs | Append-only audit trail + bewijskoppeling |
| **Hoofdstuk 10** Non-conformiteit & corrigerende maatregel | De afwijking-cyclus; de blokkade "open een afwijking of motiveer waarom niet" |

> **Editie-nuance.** In ISO 27001:**2022** is dit **Clause 10.2** "Non-conformiteit
> en corrigerende maatregel" en **10.1** "Continue verbetering". In de
> **2013**-editie waren die nummers omgewisseld. De inhoud is identiek; alleen het
> nummer verschilt per editie.

## Traceerbaarheid & meting

- Incident en afwijking zijn **auditeerbaar**: elke statuswijziging komt
  append-only in de audit trail, met wie en wanneer (Clause 7.5 / 9.2, A.5.28).
- De **doorlooptijd** (van `gemeld` tot `gesloten`) wordt gemeten en voedt de
  KPI's (Clause 9.1, monitoring van de doeltreffendheid van het proces).

**Samengevat:** een incident is pas gesloten als het een afgeronde, op
effectiviteit getoetste corrigerende maatregel heeft, of een vastgelegde
onderbouwing waarom die maatregel niet nodig was. Elk sluitbesluit draagt een naam
en een datum.
