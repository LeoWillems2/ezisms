# Wijzigingsbeheer: het register en de sjablonen

Onder **Wijzigingsbeheer** zitten twee tabbladen. Het **Register** is waar u
werkt: alle wijzigingen, van aanmelding tot evaluatie. **Wijzigingssjablonen** is
waar u instelt hóé een wijziging verloopt — de routes. Dat tweede tabblad ziet
alleen de CISO.

Waaróm het zo werkt, staat in het artikel *Wijzigingsbeheer*. Dit artikel gaat
over de schermen.

## Tabblad Register

### Wat u ziet

Eén regel per wijziging, met de soort, de zwaarte, de geraakte systemen, de
geplande datum, de voortgang (*"3 van 5"* stappen afgerond) en de status.

Het scherm opent op **lopende** dossiers. Gesloten, afgewezen en geannuleerde
wijzigingen zitten er dan niet bij — die vindt u via het statusfilter, samen met
de filters op soort en zwaarte. Een register dat opent op de volledige historie
wordt niet gebruikt; bij een audit zet u het filter op *Alle* en heeft u het
overzicht dat gevraagd wordt.

### De signalen bovenaan

Drie meldingen kunnen boven de lijst verschijnen. Ze staan er niet voor de sier:

- **Uitgevoerde wijzigingen zonder terugvalplan** — dit hoort nul te zijn. Staat
  er meer, dan is er buiten het systeem om gewerkt.
- **Spoedwijzigingen die nog op goedkeuring achteraf wachten** — de spoedroute is
  toegestaan, het overslaan van de goedkeuring niet.
- **Systemen afgevoerd zonder afvoerdossier** — een systeem is op `/systemen`
  uitgefaseerd zonder dat er een afgerond afvoerdossier tegenover staat. Dan is
  niet te laten zien dat toegang, gegevens en contract zijn afgehandeld. Dit
  signaal kijkt twaalf maanden terug.

### Een wijziging aanmelden

De knop rechtsboven. **Iedereen mag melden** — in de praktijk is dat de
applicatiebeheerder die de aankondiging van de leverancier binnenkrijgt. U vult
alleen wat u op dat moment weet: titel, soort, eventueel de leverancier, de
aankondigingsdatum, uw eigen ticketnummer en een eerste indruk van de impact.

Daarna staat het dossier open. De rest gebeurt daar.

### Op het dossier

**1. In behandeling nemen (CISO).** Kies de route en de voorgenomen datum. Uit
die twee volgen de stappen en al hun deadlines — een stap staat bijvoorbeeld op
"tien dagen vóór de geplande datum". Dit is het moment waarop de reeks ontstaat.

**2. Stappen toewijzen.** De reeks begint zonder eigenaren, want wie een stap
doet blijkt pas als de wijziging er is. Zolang een stap bij niemand staat, ziet
u daar een melding over: hij verschijnt dan in niemands takenlijst en er gaat
geen bericht uit. Wijs ze toe met de keuzelijst in de kolom *Eigenaar*. Staat de
stap al open, dan krijgt de nieuwe eigenaar meteen bericht.

**3. Het dossier invullen.** Impactanalyse, **terugvalplan** en de geraakte
systemen. Het terugvalplan is niet vrijblijvend: zonder ingevuld terugvalplan
weigert het systeem elke uitvoerstap.

**4. Bewijs koppelen.** Release notes, testverslag, acceptatieverklaring. Sommige
stappen vragen er expliciet om en gaan pas door als er iets hangt.

**5. Stappen afronden.** Een gewone stap heeft *Afronden*. Een goedkeuringsstap
heeft *Goedkeuren* en *Afkeuren* — want "voltooid" zegt niets over de uitkomst,
en juist die wil een auditor zien. Zodra alle stappen met hetzelfde nummer klaar
zijn, wordt de volgende groep actueel.

**6. Evalueren en sluiten.** Als alle stappen klaar zijn: is de wijziging
geslaagd, is er teruggedraaid, en wat kan er beter. Daarmee gaat het dossier op
gesloten.

Verder op dit scherm: **Planning verzetten** (de nog openstaande stappen
schuiven mee, afgeronde stappen houden hun deadline), **Wijziging annuleren**, en
op een afgerond dossier **Dossier heropenen** als er iets niet klopte aan de
afsluiting.

## Tabblad Wijzigingssjablonen

Een sjabloon is een **route**: de stappen die een wijziging van een bepaalde
soort en zwaarte doorloopt. Er worden er zeven meegeleverd, één per soort
wijziging, en u kunt ze aanpassen en aanvullen.

### De labels bij een route

- **Meegeleverd** — deze route komt met het product mee.
- **Aangepast** — een meegeleverde route die u heeft bijgesteld. Dan verschijnt
  ook de knop **Terugzetten**, die de geleverde stappen herstelt.
- **Inactief** — de route is niet meer te kiezen bij een nieuwe wijziging, maar
  blijft bestaan voor de dossiers die hem al gebruikten.

Staat er een waarschuwing bij een route, dan mist die een stap die A.8.32 vraagt:
een autorisatie, de uitvoering zelf of de evaluatie. Afwijken mag — het is uw
ISMS — maar een dossier langs zo'n route laat die punten niet zien.

### Een stap instellen

Per stap legt u vast:

| Veld | Wat het doet |
|---|---|
| **Type** | `analyse`, `goedkeuring`, `informeren`, `uitvoeren` of `evaluatie`. Bepaalt het gedrag: een goedkeuringsstap vraagt om goed- of afkeuren, een uitvoerstap eist een terugvalplan. |
| **Volgorde** | Stappen met hetzelfde nummer lopen **parallel** en worden samen actueel. |
| **Dagen t.o.v. de planning** | Negatief is ervóór, positief erná. De geplande datum van de wijziging is het anker. |
| **Standaard-eigenaar** | Meestal leeg laten; toewijzen doet u per wijziging op het dossier. Vul dit alleen voor een stap die altijd bij dezelfde persoon ligt. |
| **Bij afkeuren terug naar** | Leeg betekent: een afkeuring wijst de wijziging af. Een nummer laat de reeks terugspringen naar die stap. |
| **Bewijs verplicht** | De stap gaat niet door zolang er geen bewijsstuk aan de wijziging hangt. |

### Aanpassen, toevoegen, opruimen

**Aanpassingen gelden voor nieuwe dossiers.** Een wijziging die al loopt houdt de
reeks waarmee hij is gestart — inclusief de eisen die toen golden. U kunt een
route dus gerust bijstellen zonder lopende dossiers te verstoren.

Met **Nieuw sjabloon** maakt u een eigen route: naam, soort, zwaarte, en daarna
de stappen. Zonder stappen is een route niet te kiezen.

**Verwijderen kan alleen bij een eigen route waar nooit een dossier op heeft
gedraaid.** Een meegeleverde route blijft bestaan; is hij niet nodig, zet hem dan
op inactief. Datzelfde geldt voor een eigen route die al gebruikt is: het dossier
moet kunnen blijven tonen welke route het volgde.

## Wie ziet wat

| | Register | Wijziging aanmelden | Stappen afronden | Sjablonen |
|---|---|---|---|---|
| CISO | ja | ja | ja | ja |
| Medewerker | ja | ja | eigen stappen | nee |
| Management | ja | nee | nee | nee |
| Auditor | ja, plus exporteren | nee | nee | nee |

Dat een medewerker het hele register ziet is een bewuste keuze: A.8.32 vraagt dat
belanghebbenden geïnformeerd worden over wijzigingen, en een wijzigingskalender
die alleen de CISO ziet werkt daartegenin. Handelen kan hij alleen op de stappen
die aan hem zijn toegewezen.
