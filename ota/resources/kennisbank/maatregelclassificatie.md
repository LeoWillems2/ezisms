# Maatregelclassificatie: uitgangspunt en eigen vaststelling

Elke maatregel in de SoA heeft kenmerken. De kenmerken beschrijven of de maatregel
preventief of detectief is, welke eigenschappen de maatregel beschermt, in welke
fase van de cyclus de maatregel werkt en in welk domein de maatregel thuishoort.
Die kenmerken zijn **geen vaste eigenschap van de maatregel**, maar een uitspraak
over de manier waarop de organisatie de maatregel heeft ingericht.

Daarom werkt dit ISMS met twee lagen: een meegeleverd **uitgangspunt** en de
**eigen vaststelling** van de organisatie daarbovenop.

## Het uitgangspunt is een startpunt, geen waarheid

Bij de installatie krijgt elke maatregel een classificatie mee. Dat is een bewuste
keuze. Een lege SoA-modal ziet eruit als een defect, en geen enkele gebruiker wil
eerst vier keuzes maal het aantal maatregelen maken voordat er iets te zien is.

De meegeleverde classificatie is echter alleen een startpunt. Het is een redelijke
invulling voor een gemiddelde organisatie en geen uitspraak over een specifieke
organisatie. Er zijn drie situaties:

| Situatie | Betekenis |
|---|---|
| Meegeleverd uitgangspunt | Niemand heeft ernaar gekeken. Dit is de classificatie uit de installatie. |
| **Eigen vaststelling** | De organisatie heeft de classificatie bekeken en bevestigd. |
| **Afgeweken van uitgangspunt** | De organisatie heeft de classificatie bekeken en iets anders vastgesteld. |

De laatste twee situaties krijgen een badge in het SoA-scherm. De eerste situatie
krijgt geen badge, omdat dat de begintoestand van elke regel is en een badge die
overal staat geen informatie bevat. In de export staat de herkomst wel bij elke
maatregel voluit. Daar worden de regels los van elkaar gelezen, en dan is de
informatie "hier heeft niemand naar gekeken" precies wat de lezer moet weten.

Het verschil tussen de eerste twee situaties is belangrijk. Een classificatie die
wordt geopend en zonder wijziging wordt opgeslagen, verandert inhoudelijk niets.
De opslag legt wel vast dat iemand ernaar heeft gekeken. Dat is een andere
uitspraak dan "er stond nog niets", en het is precies de uitspraak waarnaar een
auditor kan vragen.

## Waarom dat de SoA sterker maakt

Een auditor die de SoA doorneemt, toetst niet of de classificatie overeenkomt met
een tabel. De auditor toetst of de organisatie **heeft nagedacht**. Twee
organisaties kunnen dezelfde maatregel volledig verschillend inrichten:

> A.8.16 Monitoring is bij de ene organisatie **preventief, detectief en
> corrigerend**: logging voedt alarmering, en alarmering activeert automatisch
> blokkades. De andere organisatie heeft alleen centrale logopslag die achteraf
> wordt doorzocht. Daar is de maatregel **puur detectief**, en die vaststelling is
> eerlijker dan het overnemen van de tabel.

Een classificatie die de organisatie zelf heeft vastgesteld, is dus sterker
onderbouwingsmateriaal dan een overgenomen tabel. Dat geldt zeker in combinatie
met de motivatie. Samen beschrijven de classificatie en de motivatie waarom de
maatregel van toepassing is en hoe de maatregel in de organisatie werkt.

## Alles of niets per maatregel

De dimensies van een maatregel worden **volledig ingevuld of helemaal niet**. Er is
geen mengvorm waarin twee dimensies zelf zijn vastgesteld en de andere twee op het
uitgangspunt blijven staan.

Dat is een bewuste keuze. Bij een mengvorm is niet meer te beantwoorden wat de
organisatie eigenlijk heeft vastgesteld, en bij elke lezing zou per dimensie de
herkomst moeten worden nagegaan. Nu is het antwoord altijd eenduidig: de badge
geeft aan of de classificatie van de organisatie komt of uit het meegeleverde
uitgangspunt.

Voor de weg terug is er de knop **Terug naar uitgangspunt**. Die knop wist de eigen
vaststelling en laat het meegeleverde uitgangspunt weer gelden. Dit is bewust een
aparte knop en geen "leeg opslaan", omdat een lege vaststelling iets anders is dan
geen vaststelling.

## Waar de vocabulaires vandaan komen

De toegestane waarden per dimensie staan in het schema van de applicatie
(`config/maatregelkenmerken.php`). Bij elke dimensie staat de herkomst vermeld, en
die herkomst verschijnt in het scherm als toelichting.

De waarden zelf zijn korte, algemeen gangbare begrippen uit de vakliteratuur over
informatiebeveiliging, zoals preventief, detectief en corrigerend. Ze zijn te kort
en te algemeen om beschermd te zijn. De **toewijzing** van waarden aan maatregelen
is een andere zaak. Daarom is het meegeleverde uitgangspunt een eigen beoordeling
en geen overgenomen tabel.

## Waarom vier dimensies en niet vijf

ISO 27002:2022 kent bij elke maatregel vijf attribuutdimensies. Dit ISMS levert er
standaard **vier** mee:

- Type maatregel
- Informatiebeveiligingseigenschappen
- Cyberbeveiligingsconcepten
- Operationele domeinen

De vijfde dimensie, **beveiligingscapaciteiten**, ontbreekt bewust. Dat is geen
vergissing en geen omissie die later nog wordt ingehaald.

De reden is dat die dimensie niet in te vullen is zonder de normtekst zelf. Welke
maatregel welke capaciteit heeft, is een toewijzing die alleen in de norm staat, en
die norm is auteursrechtelijk beschermd. Dit systeem kan de toewijzing daarom niet
meeleveren.

Een plausibele schatting is hier **slechter dan niets**. Een verkeerde toewijzing
is namelijk niet van een juiste te onderscheiden, en zou als "wat de tabel zegt"
in SoA-onderbouwingen terechtkomen. Een lege dimensie is eerlijk. Een verzonnen
dimensie is misleidend.

**Een organisatie die de norm zelf bezit**, mag de dimensie in de eigen
installatie vullen. Dat gebeurt met:

```
php artisan isms:capaciteiten status   # laat zien hoe het er nu voor staat
php artisan isms:capaciteiten aan      # zet de dimensie aan
php artisan isms:capaciteiten uit      # zet de dimensie uit
```

Deze commando's vereisen een bestand
`database/seeders/data/maatregel-capaciteiten.json` met de eigen toewijzing van de
organisatie. Dat bestand blijft lokaal, omdat het in `.gitignore` staat. Zo is de
installatie van elke organisatie die de norm bezit compleet, zonder dat de
normtekst in een repository terechtkomt.

## In de applicatie

De classificatie staat in het scherm **Statement of Applicability** (`/soa`) en is
daar te bewerken, in dezelfde modal als de beoordeling. Die modal opent met de knop
**Beoordelen** bij een maatregel. Bovenaan staan de huidige kenmerken met de
herkomstbadge, en onderaan staat het formulier waarin de kenmerken worden
vastgesteld.

Bewerken vereist het recht `muteren` op het blok Risico & SoA. Dat zijn dezelfde
rechten als voor de rest van de SoA-beoordeling. Elke wijziging komt in de audit
trail onder blok `risico-soa`, op naam van de gebruiker die de wijziging maakte.

De classificatie gaat ook mee in **`isms:exporteer`**, in het bestand
`03-risico-en-soa.md`, met de herkomst erbij. Zo is in het geëxporteerde
SoA-bewijs te zien wat de organisatie zelf heeft bepaald en wat nog op het
uitgangspunt staat.
