# Wijzigingsbeheer

Een leverancier kondigt een upgrade van uw HR-systeem aan. Wie beoordeelt de
release notes, wie geeft toestemming, wie waarschuwt de gebruikers, en wat doet
u als het misgaat? Dat is wijzigingsbeheer, en A.8.32 vraagt erom.

Het register beantwoordt bij een audit één vraag: *welke wijzigingen zijn er
geweest, en met welke goedkeuring.*

## De route: een sjabloon met stappen

Een wijziging doorloopt een vaste reeks stappen. Welke stappen dat zijn, staat
in een **sjabloon** — en dat is instelbaar, zonder dat er iemand aan de
programmatuur hoeft te komen. Er worden er drie meegeleverd:

- **Leveranciersrelease — standaard.** De gewone route: beoordelen,
  impactanalyse, informeren, autoriseren, uitvoeren, evalueren.
- **Leveranciersrelease — ingrijpend.** Idem, met een toets op het terugvalplan
  en het bijwerken van documentatie en continuïteitsplannen.
- **Afvoer van een systeem of dienst.** Het uitfaseren van iets dat u niet meer
  gebruikt. Het zwaartepunt ligt ná de buitengebruikstelling: toegang intrekken,
  gegevens exporteren of vernietigen, contract beëindigen, registers bijwerken.
- **Spoedwijziging.** Uitvoeren mag vooropgaan; de goedkeuring verschuift naar
  achteraf.

Pas ze gerust aan. Een aanpassing geldt voor nieuwe dossiers; **lopende dossiers
houden de reeks waarmee ze zijn gestart.** Zo verandert er nooit met
terugwerkende kracht iets aan een wijziging die al onderweg is.

## Stappen zijn taken

Elke stap is een gewone taak. Hij verschijnt bij de eigenaar onder *Taken*, hij
heeft een deadline, hij escaleert als hij blijft liggen, en hij is af te ronden
vanaf twee plekken: het takenscherm of het dossier zelf.

Een stap die nog niet aan de beurt is, staat op **wachtend**. Die telt nergens
mee — niet op uw dashboard, niet in het aantal openstaande taken. Zodra de
vorige stap klaar is, wordt hij actueel en krijgt de eigenaar bericht.

Stappen met **hetzelfde nummer lopen parallel**. In de standaardroute staan de
impactanalyse en het informeren van belanghebbenden allebei op 2: de
communicatie hoeft niet op de analyse te wachten.

## Twee dingen die het systeem tegenhoudt

**Uitvoeren zonder terugvalplan kan niet.** A.8.32 f) vraagt om een vangnet, en
zolang het veld *Terugvalplan* leeg is, weigert de uitvoerstap — ook wanneer u
hem vanaf het takenscherm probeert af te vinken. Dat is geen scherm-cosmetica
maar een controle op het dossier zelf.

**Een stap die om bewijs vraagt, kan niet zonder.** Waar het sjabloon *bewijs
verplicht* zegt, moet er eerst een bewijsstuk aan de wijziging hangen — de
release notes, het testrapport, de acceptatieverklaring.

## Goedkeuren en afkeuren

Een goedkeuringsstap rondt u niet af met "voltooid" maar met **goedkeuren** of
**afkeuren**. Dat verschil is het punt: "voltooid" zegt niets over de uitkomst,
en juist de uitkomst is wat een auditor wil zien.

Bij afkeuren gebeurt er één van twee dingen, afhankelijk van het sjabloon:

- Staat er een terugsprong ingesteld, dan gaat de reeks terug naar die stap en
  loopt het dossier door.
- Staat die er niet, dan wordt de wijziging **afgewezen**.

Let op: keurt u een stap af vanaf het **takenscherm**, dan staat de reeks stil
maar gebeurt er verder niets. Het vervolg is een besluit dat op het
dossierscherm wordt genomen. Open dus het dossier om verder te gaan.

## De planning verzetten

De geplande datum is het anker: alle deadlines zijn eraan opgehangen met een
aantal dagen ervóór of erná. Verzet u de planning, dan **schuiven de stappen die
nog moeten gebeuren mee**. Stappen die al klaar zijn houden hun oorspronkelijke
deadline — die is historie, en de eventuele vertraging telt mee in de meting.

## Wat u eraan afleest

Twee signalen verschijnen boven het register:

- **Uitgevoerd zonder terugvalplan.** Dit hoort nul te zijn. Staat er meer, dan
  is er buiten het systeem om gewerkt.
- **Spoedwijzigingen zonder goedkeuring achteraf.** De spoedroute is toegestaan;
  het overslaan van de goedkeuring niet.
- **Systemen afgevoerd zonder afvoerdossier.** Een systeem dat op `/systemen` is
  afgevoerd zonder dat er een afgerond afvoerdossier tegenover staat. Dan is niet
  te laten zien dat toegang, gegevens en contract zijn afgehandeld. Het signaal
  kijkt twaalf maanden terug; oudere afvoeren zijn niet meer te repareren en
  zouden de melding permanent rood houden.

Dezelfde drie punten komen als KPI terug bij *KPI's*: geslaagde wijzigingen,
uitvoering met terugvalplan, en spoedwijzigingen die achteraf zijn goedgekeurd.

## Wat dit blok niet is

Geen deploytool en geen configuratiedatabase. Het systeem registreert dát er is
getest, goedgekeurd en uitgevoerd — het voert niets uit. Werkt u met een
ticketsysteem, vul dan het veld **ticketnummer** in; er is bewust geen koppeling,
zodat er geen tweede bron van waarheid ontstaat.
