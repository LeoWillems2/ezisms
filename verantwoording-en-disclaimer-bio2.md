# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die later nog wordt ingehaald. Het is een ontwerpkeuze die
bepaalt wat dit systeem voor een organisatie is en wat het niet is.

Deze installatie draait op het **BIO2-profiel**. Daardoor behandelt deze pagina
twee onderwerpen in plaats van één, en die onderwerpen hebben verschillende
oorzaken:

1. Bij de 93 **beheersmaatregelen** staat geen omschrijving. Dat is een keuze, en
   die keuze geldt in alle uitvoeringen van dit systeem.
2. Bij de **overheidsmaatregelen** staat geen tekst. Dat is geen keuze maar een
   licentiekwestie, en die kwestie is nieuw in dit profiel.

Het tweede punt is het belangrijkste, omdat het gaat over de verplichtingen
waarop de RDI de organisatie zal aanspreken.

## Waarom de tekst van de overheidsmaatregelen ontbreekt

De BIO is kosteloos te downloaden van [bio-overheid.nl](https://bio-overheid.nl).
Daaruit zou kunnen worden afgeleid dat de tekst vrij te hergebruiken is. Dat is
niet zonder meer het geval.

De BIO wordt uitgegeven onder de licentie **Creative Commons
Naamsvermelding-NietCommercieel-GelijkDelen 4.0 (CC BY-NC-SA 4.0)**. Twee van die
drie voorwaarden raken software die als product wordt aangeboden:

- **NietCommercieel.** Hergebruik in een commercieel aangeboden ISMS valt buiten
  wat de licentie toestaat.
- **GelijkDelen.** Een bewerking zou onder dezelfde licentie moeten worden
  verspreid. Een seedbestand met de teksten erin is zo'n bewerking.

Tegenover deze bezwaren staat een serieus tegenargument. De BIO2 is via de
**Cyberbeveiligingswet** en het **Cyberbeveiligingsbesluit** verplicht voor de
sector Overheid, en de wijzigingstabel van v1.3 noemt aanpassingen die zijn gedaan
"vanwege BIO2 als wetgeving". Artikel 11 van de Auteurswet bepaalt dat er geen
auteursrecht bestaat op wetten, besluiten en verordeningen die door de openbare
macht zijn uitgevaardigd. Waar dat artikel van toepassing is, kan een CC-licentie
niets voorbehouden, omdat er geen auteursrecht is om voor te behouden.

Welke van de twee opvattingen juist is, is een juridische vraag. Die vraag is
niet ter beantwoording aan een jurist voorgelegd. Zolang dat zo is, kiest dit
ISMS de veilige kant.

**Concreet betekent dat het volgende:** bij elke overheidsmaatregel toont het
systeem het **nummer**, de **beheersmaatregel** waaronder de overheidsmaatregel
valt, de **status** (geldend, vervallen of verplaatst) en of de overheidsmaatregel
**binnen de reikwijdte van de Cbw** valt. Dat is allemaal openbaar bekende
structuur. De tekst van de verplichting zelf staat er niet bij.

Die gegevens volstaan om de plaats van een verplichting te bepalen, maar niet om
te weten wat de verplichting van de organisatie vraagt. Daarvoor is de BIO nodig,
en die is gratis.

### Zelf invullen

Een organisatie die de BIO heeft gedownload, kan de teksten in de eigen
installatie zetten. Dat gebeurt met de meegeleverde generator:

```
python3 ../scripts/genereer_overheidsmaatregelen_seed.py \
    --bron=<pad naar de BIO2-werkmap>.xlsx --met-tekst
php artisan isms:overheidsmaatregelen
```

De teksten komen dan in een bestand dat **niet** in versiebeheer staat en dat bij
een bijwerking van dit ISMS niet wordt overschreven. Dat is dezelfde constructie
als bij de vijfde attribuutdimensie van ISO 27002 (`isms:capaciteiten`).

Het gaat dan om de eigen installatie en het eigen exemplaar van een kosteloos
gepubliceerde overheidspublicatie. Dat is een andere situatie dan het meeleveren
van die publicatie aan anderen.

## Waarom er geen omschrijving bij de beheersmaatregelen staat

De 93 beheersmaatregelen komen uit NEN-EN-ISO/IEC 27002:2022. Die tekst is van
NEN, en de BIO vermeldt dat zelf ook: *"Het gebruik van informatie uit
NEN-EN-ISO/IEC 27002 in de BIO is auteursrechtelijk beschermd. Het gebruik van
teksten uit deze normen in de BIO geschiedt met toestemming van NEN."*

Die toestemming is aan het CIP gegeven, en niet aan de makers van dit systeem of
aan de organisatie die het gebruikt. Ook via de BIO komt de ISO-tekst dus niet in
dit systeem.

Een eigen omschrijving schrijven zou wel zijn toegestaan, maar dit systeem doet
dat niet. Zo'n tekst is een interpretatie van de norm, op precies de plek waar een
auditor de toepasselijkheid beoordeelt. Elk verschil met de normtekst leidt tot
een discussie die de organisatie niet hoeft te voeren. Het systeem toont daarom
nummer, titel en thema. De organisatie vult de omschrijving zelf in met
`php artisan isms:maatregelen`.

Een leeg veld is eerlijk. Het geeft aan dat de norm geraadpleegd moet worden.

## Wat wel uit openbare bronnen komt

Niet alles in dit systeem is leeg gelaten. De volgende gegevens staan erin, omdat
ze uit openbare bronnen te verantwoorden zijn:

- **Referenties, thema's en titels** van de 93 beheersmaatregelen. Deze gegevens
  zijn openbaar bekend.
- **De nummering en indeling** van de 118 overheidsmaatregelen, inclusief de
  vervallen en verplaatste nummers. Deze gegevens zijn openbaar bekend uit de BIO
  zelf.
- **De reikwijdte van de Cbw** per beheersmaatregel. De BIO markeert drie
  beheersmaatregelen als buiten die reikwijdte: 5.32 (intellectueel eigendom),
  5.33 (bescherming van registraties) en 5.34 (privacy en bescherming van PII).
  Voor die drie geldt verplichtende zelfregulering in plaats van de wet. Dat is
  een feit over de norm en geen normtekst.
- **De uitgangsclassificatie** per maatregel: type, eigenschappen, concepten en
  domeinen. Deze classificatie is herleid uit openbare bronnen en is inmiddels ook
  onafhankelijk bevestigd. De Handreiking BIO2-opmaat van het CIP komt bij alle 93
  maatregelen tot dezelfde indeling. De vijfde dimensie van ISO 27002
  (operationele capaciteiten) ontbreekt, omdat die dimensie wel eigendom van NEN
  is.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van de BIO en geen samenvatting ervan.** Een organisatie kan
haar Cbw-zorgplicht er niet mee aantonen zonder de BIO te bezitten en te lezen.
Het systeem ondersteunt de administratie van een managementsysteem: registers,
beoordelingen, taken, bewijs, audits en rapportage. Het systeem geeft echter geen
gezaghebbend antwoord op de vraag wat de norm van de organisatie eist.

Concreet betekent dat het volgende:

- **De verplichtingen zijn hier genummerd en niet uitgeschreven.** Wat 5.24.03
  vraagt, staat in de BIO.
- **Het systeem beoordeelt niet of de organisatie in control is.** Het systeem
  legt vast wat de organisatie daarover heeft vastgesteld, met de onderbouwing
  die de organisatie eraan koppelt.
- **De In Control Verklaring is een bestuurlijke handeling.** Dit ISMS levert de
  cijfers en het bewijs voor die verklaring. De verklaring zelf is van het
  bestuur.
- **De reikwijdtevraag van de norm is niet die van de organisatie.** Of een
  entiteit onder de Cyberbeveiligingswet valt, hangt af van sector en omvang, en
  niet van de norm die de organisatie volgt. Dat is een aparte instelling in dit
  systeem. Het antwoord op die vraag komt van de RDI en de juridische afdeling, en
  niet uit dit systeem.

## Het eigenlijke risico: verifieerbaarheid

Het risico van een ISMS zonder normtekst is niet dat er iets ontbreekt, want dat
is zichtbaar. Het risico is dat er iets staat wat plausibel lijkt maar niet klopt.
Zonder de norm ernaast is een verzonnen omschrijving niet van een juiste te
onderscheiden.

Daarom levert dit systeem liever een mededeling dan een gok. Een markering als
"dit ISMS levert hier geen tekst mee" is een expliciete uitspraak: hier hoort iets
te staan, het systeem levert het niet, en de bron moet worden geraadpleegd. Een
markering is iets anders dan een leeg veld, en iets heel anders dan een
plausibele invulling.

## Auteursrecht en verspreiding

- **NEN-EN-ISO/IEC 27001 en 27002** zijn auteursrechtelijk beschermd. Ze worden
  hier niet meegeleverd en horen niet in dit systeem te worden gekopieerd. Dat
  geldt ook voor gedeeltelijke kopieën en voor een "eigen omschrijving" die sterk
  op de normtekst lijkt.
- **De BIO2** staat onder CC BY-NC-SA 4.0, met uitzondering van het NEN-deel.
  Teksten die de organisatie in de eigen installatie opneemt, blijven het eigen
  exemplaar van de organisatie. Die teksten horen niet verder te worden verspreid
  zonder dat de licentie is gelezen.
- **Eigenaarschap van de BIO** ligt bij de stelselverantwoordelijke, het
  ministerie van Binnenlandse Zaken en Koninkrijksrelaties. Het Centrum
  Informatiebeveiliging en Privacybescherming (CIP) verzorgt het onderhoud.
- **Wat de organisatie zelf invoert**, zoals omschrijvingen, motivaties,
  beoordelingen en bewijs, is van de organisatie. Dit systeem doet daar niets mee
  behalve het bewaren en tonen.

## Aansprakelijkheid

Dit systeem is een administratief hulpmiddel. Het systeem geeft geen juridisch
advies, geen normuitleg en geen garantie dat de organisatie aan de BIO of aan de
Cyberbeveiligingswet voldoet. De verantwoordelijkheid voor de juistheid van de
inhoud, en voor de conclusies die de organisatie daaraan verbindt, ligt bij de
organisatie zelf.
