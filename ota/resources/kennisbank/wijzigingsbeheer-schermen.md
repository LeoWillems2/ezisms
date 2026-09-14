# Wijzigingsbeheer: het register en de sjablonen

Onder **Wijzigingsbeheer** staan twee tabbladen. Op het tabblad **Register**
wordt het werk gedaan: het bevat alle wijzigingen, van aanmelding tot evaluatie.
Op het tabblad **Wijzigingssjablonen** wordt ingesteld hoe een wijziging
verloopt. Dat zijn de routes. Alleen de CISO ziet het tweede tabblad.

Het artikel *Wijzigingsbeheer* legt uit waarom het proces zo werkt. Dit artikel
beschrijft de schermen.

## Tabblad Register

### Wat het scherm toont

Het register toont één regel per wijziging, met de soort, de zwaarte, de
geraakte systemen, de geplande datum, de voortgang (*"3 van 5"* stappen afgerond)
en de status.

Het scherm opent met de **lopende** dossiers. Gesloten, afgewezen en
geannuleerde wijzigingen staan er dan niet bij. Die wijzigingen zijn te vinden
via het statusfilter, dat naast de filters op soort en zwaarte staat. Een
register dat opent met de volledige historie wordt in de praktijk niet gebruikt.
Bij een audit wordt het filter op *Alle* gezet, en dan toont het register het
gevraagde overzicht.

### De signalen bovenaan

Boven de lijst kunnen drie meldingen verschijnen. Elke melding wijst op een
concreet probleem:

- **Uitgevoerde wijzigingen zonder terugvalplan.** Dit aantal hoort nul te zijn.
  Een hoger aantal betekent dat er buiten het systeem om is gewerkt.
- **Spoedwijzigingen die nog op goedkeuring achteraf wachten.** De spoedroute is
  toegestaan, maar het overslaan van de goedkeuring niet.
- **Systemen afgevoerd zonder afvoerdossier.** Een systeem is op `/systemen`
  uitgefaseerd zonder dat er een afgerond afvoerdossier tegenover staat. In dat
  geval is niet aan te tonen dat toegang, gegevens en contract zijn afgehandeld.
  Dit signaal kijkt twaalf maanden terug.

### Een wijziging aanmelden

De knop voor aanmelden staat rechtsboven. **Iedere gebruiker mag een wijziging
melden.** In de praktijk is dat meestal de applicatiebeheerder die de
aankondiging van de leverancier ontvangt. Bij het aanmelden wordt alleen
ingevuld wat op dat moment bekend is: titel, soort, eventueel de leverancier, de
aankondigingsdatum, het eigen ticketnummer en een eerste inschatting van de
impact.

Daarna staat het dossier open. Het verdere werk gebeurt op het dossier.

### Op het dossier

**1. In behandeling nemen (CISO).** De CISO kiest de route en de voorgenomen
datum. Uit die twee gegevens volgen de stappen en alle deadlines. Een stap staat
bijvoorbeeld op "tien dagen vóór de geplande datum". Op dit moment ontstaat de
reeks.

**2. Stappen toewijzen.** De reeks begint zonder eigenaren, omdat pas bij een
concrete wijziging blijkt wie een stap uitvoert. Zolang een stap geen eigenaar
heeft, toont het dossier daarover een melding. Een stap zonder eigenaar staat in
geen enkele takenlijst, en er gaat geen bericht uit. Stappen worden toegewezen
met de keuzelijst in de kolom *Eigenaar*. Als de stap al open staat, krijgt de
nieuwe eigenaar direct bericht.

**3. Het dossier invullen.** Het dossier bevat de impactanalyse, het
**terugvalplan** en de geraakte systemen. Het terugvalplan is verplicht: zonder
ingevuld terugvalplan weigert het systeem elke uitvoerstap.

**4. Bewijs koppelen.** Voorbeelden van bewijs zijn release notes, een
testverslag en een acceptatieverklaring. Sommige stappen vragen expliciet om
bewijs en gaan pas door als er een bewijsstuk aan de wijziging hangt.

**5. Stappen afronden.** Een gewone stap heeft de knop *Afronden*. Een
goedkeuringsstap heeft de knoppen *Goedkeuren* en *Afkeuren*, omdat "voltooid"
niets zegt over de uitkomst en een auditor juist de uitkomst wil zien. Zodra alle
stappen met hetzelfde nummer klaar zijn, wordt de volgende groep actueel.

**6. Evalueren en sluiten.** Als alle stappen klaar zijn, wordt de wijziging
geëvalueerd: is de wijziging geslaagd, is er teruggedraaid en wat kan er beter.
Daarmee krijgt het dossier de status gesloten.

Het dossierscherm biedt daarnaast drie acties. **Planning verzetten** verschuift
de nog openstaande stappen, terwijl afgeronde stappen hun deadline houden.
**Wijziging annuleren** annuleert de wijziging. **Dossier heropenen** is
beschikbaar op een afgerond dossier, voor het geval er iets niet klopte aan de
afsluiting.

## Tabblad Wijzigingssjablonen

Een sjabloon is een **route**: de stappen die een wijziging van een bepaalde
soort en zwaarte doorloopt. Er worden zeven sjablonen meegeleverd, één per soort
wijziging. De organisatie kan ze aanpassen en aanvullen.

### De labels bij een route

- **Meegeleverd.** Deze route wordt met het product meegeleverd.
- **Aangepast.** Dit is een meegeleverde route die de organisatie heeft
  bijgesteld. Bij zo'n route verschijnt ook de knop **Terugzetten**, die de
  meegeleverde stappen herstelt.
- **Inactief.** De route is niet meer te kiezen bij een nieuwe wijziging, maar
  blijft bestaan voor de dossiers die de route al gebruikten.

Een waarschuwing bij een route betekent dat de route een stap mist die A.8.32
vraagt: een autorisatie, de uitvoering zelf of de evaluatie. Afwijken is
toegestaan, want het ISMS is van de organisatie. Een dossier dat zo'n route
volgt, toont die punten echter niet.

### Een stap instellen

Per stap worden de volgende velden vastgelegd:

| Veld | Wat het doet |
|---|---|
| **Type** | `analyse`, `goedkeuring`, `informeren`, `uitvoeren` of `evaluatie`. Het type bepaalt het gedrag: een goedkeuringsstap vraagt om goedkeuren of afkeuren, en een uitvoerstap vereist een terugvalplan. |
| **Volgorde** | Stappen met hetzelfde nummer lopen **parallel** en worden tegelijk actueel. |
| **Dagen t.o.v. de planning** | Een negatief getal ligt vóór de planning, een positief getal erna. De geplande datum van de wijziging is het anker. |
| **Standaard-eigenaar** | Dit veld blijft meestal leeg, omdat stappen per wijziging op het dossier worden toegewezen. Het veld is alleen bedoeld voor een stap die altijd bij dezelfde persoon ligt. |
| **Bij afkeuren terug naar** | Een leeg veld betekent dat een afkeuring de wijziging afwijst. Een nummer laat de reeks terugspringen naar die stap. |
| **Bewijs verplicht** | De stap gaat niet door zolang er geen bewijsstuk aan de wijziging hangt. |

### Aanpassen, toevoegen, opruimen

**Aanpassingen gelden voor nieuwe dossiers.** Een wijziging die al loopt, houdt
de reeks waarmee zij is gestart, inclusief de eisen die toen golden. Een route
kan dus worden bijgesteld zonder lopende dossiers te verstoren.

Met **Nieuw sjabloon** wordt een eigen route aangemaakt: eerst naam, soort en
zwaarte, en daarna de stappen. Een route zonder stappen is niet te kiezen.

**Verwijderen is alleen mogelijk bij een eigen route waarop nooit een dossier
heeft gelopen.** Een meegeleverde route blijft altijd bestaan. Als zo'n route
niet nodig is, wordt hij op inactief gezet. Hetzelfde geldt voor een eigen route
die al is gebruikt, omdat het dossier moet blijven tonen welke route het volgde.

## Wie ziet wat

| | Register | Wijziging aanmelden | Stappen afronden | Sjablonen |
|---|---|---|---|---|
| CISO | ja | ja | ja | ja |
| Medewerker | ja | ja | eigen stappen | nee |
| Management | ja | nee | nee | nee |
| Auditor | ja, plus exporteren | nee | nee | nee |

Een medewerker ziet bewust het hele register. A.8.32 vraagt dat belanghebbenden
over wijzigingen worden geïnformeerd, en een wijzigingskalender die alleen de
CISO ziet, werkt dat tegen. Een medewerker kan alleen handelen op de stappen die
aan die medewerker zijn toegewezen.
