# Wijzigingsbeheer

Een leverancier kondigt een upgrade van het HR-systeem van de organisatie aan.
Iemand moet de release notes beoordelen, toestemming geven en de gebruikers
waarschuwen, en er moet een plan zijn voor het geval de upgrade mislukt. Dat
proces heet wijzigingsbeheer, en A.8.32 vereist het.

Bij een audit beantwoordt het register één vraag: *welke wijzigingen zijn er
geweest, en met welke goedkeuring.*

## De route: een sjabloon met stappen

Een wijziging doorloopt een vaste reeks stappen. Welke stappen dat zijn, staat in
een **sjabloon**. Een sjabloon is instelbaar zonder dat de programmatuur hoeft te
worden aangepast. Er worden zeven sjablonen meegeleverd, één per soort
wijziging. Vier daarvan laten de verschillen goed zien:

- **Leveranciersrelease — standaard.** Dit is de gewone route: beoordelen,
  impactanalyse, informeren, autoriseren, uitvoeren en evalueren.
- **Leveranciersrelease — ingrijpend.** Deze route bevat dezelfde stappen, plus
  een toets op het terugvalplan en het bijwerken van documentatie en
  continuïteitsplannen.
- **Afvoer van een systeem of dienst.** Deze route faseert een systeem of dienst
  uit dat niet meer wordt gebruikt. Het zwaartepunt ligt na de
  buitengebruikstelling: toegang intrekken, gegevens exporteren of vernietigen,
  het contract beëindigen en de registers bijwerken.
- **Spoedwijziging.** Bij deze route mag de uitvoering voorafgaan aan de
  goedkeuring. De goedkeuring vindt achteraf plaats.

De sjablonen mogen worden aangepast. Een aanpassing geldt voor nieuwe dossiers.
**Lopende dossiers houden de reeks waarmee ze zijn gestart.** Daardoor verandert
er nooit met terugwerkende kracht iets aan een wijziging die al loopt.

## Stappen zijn taken

Elke stap is een gewone taak. De stap verschijnt bij de eigenaar onder *Taken*,
heeft een deadline en escaleert als hij te lang blijft liggen. Een stap is op
twee plekken af te ronden: op het takenscherm en op het dossier zelf.

Een stap die nog niet aan de beurt is, staat op **wachtend**. Een wachtende stap
telt nergens mee, niet op het dashboard en niet in het aantal openstaande taken.
Zodra de vorige stap klaar is, wordt de stap actueel en krijgt de eigenaar
bericht.

Stappen met **hetzelfde nummer lopen parallel**. In de standaardroute staan de
impactanalyse en het informeren van belanghebbenden allebei op 2. De
communicatie hoeft daardoor niet op de analyse te wachten.

## Twee dingen die het systeem tegenhoudt

**Uitvoeren zonder terugvalplan is niet mogelijk.** A.8.32 f) vraagt om een
vangnet. Zolang het veld *Terugvalplan* leeg is, weigert het systeem de
uitvoerstap. Dat geldt ook voor een poging om de stap op het takenscherm af te
ronden. De controle zit niet alleen in het scherm, maar in het dossier zelf.

**Een stap die om bewijs vraagt, gaat niet door zonder bewijs.** Als het sjabloon
bij een stap *bewijs verplicht* aangeeft, moet er eerst een bewijsstuk aan de
wijziging hangen. Voorbeelden zijn de release notes, het testrapport en de
acceptatieverklaring.

## Goedkeuren en afkeuren

Een goedkeuringsstap wordt niet afgerond met "voltooid", maar met **goedkeuren**
of **afkeuren**. Dat verschil is essentieel. "Voltooid" zegt niets over de
uitkomst, en een auditor wil juist de uitkomst zien.

Bij afkeuren gebeurt één van twee dingen, afhankelijk van het sjabloon:

- Als er een terugsprong is ingesteld, gaat de reeks terug naar die stap en loopt
  het dossier verder.
- Als er geen terugsprong is ingesteld, wordt de wijziging **afgewezen**.

Een stap die op het **takenscherm** wordt afgekeurd, zet de reeks stil. Verder
gebeurt er dan niets. Het vervolg is een besluit dat op het dossierscherm wordt
genomen. Om verder te gaan, moet dus het dossier worden geopend.

## De planning verzetten

De geplande datum is het anker. Alle deadlines zijn eraan gekoppeld met een
aantal dagen vóór of na die datum. Bij het verzetten van de planning **schuiven
de stappen die nog moeten gebeuren mee**. Stappen die al klaar zijn, houden hun
oorspronkelijke deadline. Die deadline is historie, en een eventuele vertraging
telt mee in de meting.

## Wat het register laat zien

Drie signalen verschijnen boven het register:

- **Uitgevoerd zonder terugvalplan.** Dit aantal hoort nul te zijn. Een hoger
  aantal betekent dat er buiten het systeem om is gewerkt.
- **Spoedwijzigingen zonder goedkeuring achteraf.** De spoedroute is toegestaan,
  maar het overslaan van de goedkeuring niet.
- **Systemen afgevoerd zonder afvoerdossier.** Dit signaal toont een systeem dat
  op `/systemen` is afgevoerd zonder dat er een afgerond afvoerdossier tegenover
  staat. In dat geval is niet aan te tonen dat toegang, gegevens en contract zijn
  afgehandeld. Het signaal kijkt twaalf maanden terug. Oudere afvoeren zijn niet
  meer te herstellen en zouden de melding permanent rood houden.

Onder *KPI's* komen drie verwante punten terug als KPI: geslaagde wijzigingen,
uitvoering met terugvalplan en spoedwijzigingen die achteraf zijn goedgekeurd.

## Wat dit blok niet is

Dit blok is geen deploytool en geen configuratiedatabase. Het systeem registreert
dat er is getest, goedgekeurd en uitgevoerd, maar voert zelf niets uit. Een
organisatie die met een ticketsysteem werkt, vult het veld **ticketnummer** in.
Er is bewust geen koppeling, zodat er geen tweede bron van waarheid ontstaat.
