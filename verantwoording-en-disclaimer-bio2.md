# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die nog wordt ingehaald: het is een ontwerpkeuze die bepaalt
wat dit systeem voor je is en wat het niet is.

Deze installatie draait op het **BIO2-profiel**. Daarmee heeft deze pagina twee
verhalen te vertellen in plaats van één, en ze hebben verschillende oorzaken:

1. Bij de 93 **beheersmaatregelen** staat geen omschrijving. Dat is een keuze, en
   die geldt in alle uitvoeringen van dit systeem.
2. Bij de **overheidsmaatregelen** staat geen tekst. Dat is géén keuze maar een
   licentiekwestie, en die is nieuw in dit profiel.

Het tweede punt is het belangrijkste dat je moet weten, want het gaat over de
verplichtingen waar de RDI je op zal aanspreken.

## Waarom de tekst van de overheidsmaatregelen ontbreekt

De BIO is kosteloos te downloaden van [bio-overheid.nl](https://bio-overheid.nl).
Je zou daaruit kunnen opmaken dat de tekst vrij te hergebruiken is. Dat is niet
zonder meer zo.

De BIO wordt uitgegeven onder de licentie **Creative Commons
Naamsvermelding-NietCommercieel-GelijkDelen 4.0 (CC BY-NC-SA 4.0)**. Twee van die
drie voorwaarden raken software die als product wordt aangeboden:

- **NietCommercieel.** Hergebruik in een commercieel aangeboden ISMS valt buiten
  wat de licentie toestaat.
- **GelijkDelen.** Een bewerking — en een seedbestand met de teksten erin is een
  bewerking — zou onder dezelfde licentie moeten worden verspreid.

Daar staat een tegenargument tegenover dat serieus is. De BIO2 is via de
**Cyberbeveiligingswet** en het **Cyberbeveiligingsbesluit** verplicht voor de
sector Overheid, en de wijzigingstabel van v1.3 noemt aanpassingen die zijn gedaan
"vanwege BIO2 als wetgeving". Artikel 11 van de Auteurswet bepaalt dat er geen
auteursrecht bestaat op wetten, besluiten en verordeningen die door de openbare
macht zijn uitgevaardigd. Waar dat artikel geldt, kan een CC-licentie niets
voorbehouden, want er is niets om voor te behouden.

Welke van de twee het is, is een juridische vraag die wij niet hebben laten
beantwoorden. Zolang dat zo is, kiest dit ISMS de veilige kant.

**Wat dat concreet betekent:** je ziet bij elke overheidsmaatregel het **nummer**,
de **beheersmaatregel** waaronder hij hangt, de **status** (geldend, vervallen of
verplaatst) en of hij **binnen de reikwijdte van de Cbw** valt. Dat is allemaal
openbaar bekende structuur. De tekst van de verplichting zelf staat er niet.

Dat is genoeg om te weten waar je bent, en niet genoeg om te weten wat er van je
wordt gevraagd. Voor dat laatste heb je de BIO nodig — en die is gratis.

### Zelf invullen

Heb je de BIO gedownload, dan kun je de teksten in je eigen installatie zetten.
Dat gaat met de meegeleverde generator:

```
python3 ../scripts/genereer_overheidsmaatregelen_seed.py \
    --bron=<pad naar de BIO2-werkmap>.xlsx --met-tekst
php artisan isms:overheidsmaatregelen
```

De teksten komen dan in een bestand dat **niet** in versiebeheer staat en dat bij
een bijwerking van dit ISMS niet wordt overschreven. Precies dezelfde constructie
als bij de vijfde attribuutdimensie van ISO 27002 (`isms:capaciteiten`).

Dit is jouw installatie en jouw exemplaar van een kosteloos gepubliceerde
overheidspublicatie; dat is een andere situatie dan het meeleveren ervan aan
anderen.

## Waarom er geen omschrijving bij de beheersmaatregelen staat

De 93 beheersmaatregelen komen uit NEN-EN-ISO/IEC 27002:2022. Die tekst is van
NEN, en de BIO zegt dat zelf ook: *"Het gebruik van informatie uit
NEN-EN-ISO/IEC 27002 in de BIO is auteursrechtelijk beschermd. Het gebruik van
teksten uit deze normen in de BIO geschiedt met toestemming van NEN."*

Die toestemming is aan het CIP gegeven, niet aan ons en niet aan jou. Dus ook langs
de BIO om komt de ISO-tekst hier niet binnen.

Een eigen omschrijving schrijven zou wél mogen, maar dat doen we niet. Zo'n tekst
is een interpretatie van de norm, op precies de plek waar een auditor de
toepasselijkheid beoordeelt. Elk verschil met de normtekst is een discussie die
jouw organisatie niet hoeft te voeren. Je ziet daarom nummer, titel en thema, en
je vult de omschrijving zelf in met `php artisan isms:maatregelen`.

Een leeg veld is eerlijk. Het zegt: kijk in de norm.

## Wat wél uit openbare bronnen komt

Niet alles in dit systeem is leeg gelaten. Wat hier staat, staat er omdat het uit
openbare bronnen te verantwoorden is:

- **Referenties, thema's en titels** van de 93 beheersmaatregelen. Openbaar bekend.
- **De nummering en indeling** van de 118 overheidsmaatregelen, plus de vervallen
  en verplaatste nummers. Openbaar bekend uit de BIO zelf.
- **De reikwijdte van de Cbw** per beheersmaatregel. De BIO markeert drie
  beheersmaatregelen als buiten die reikwijdte — 5.32 (intellectueel eigendom),
  5.33 (bescherming van registraties) en 5.34 (privacy en bescherming van PII).
  Daar geldt verplichtende zelfregulering in plaats van de wet. Dat is een feit
  over de norm, geen normtekst.
- **De uitgangsclassificatie** per maatregel: type, eigenschappen, concepten en
  domeinen. Die is herleid uit openbare bronnen en is inmiddels ook
  onafhankelijk bevestigd — de Handreiking BIO2-opmaat van het CIP komt op alle
  93 maatregelen tot dezelfde indeling. De vijfde dimensie van ISO 27002
  (operationele capaciteiten) ontbreekt hier, omdat die wél NEN-eigendom is.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van de BIO en geen samenvatting ervan.** Je kunt je
Cbw-zorgplicht er niet mee aantonen zonder de BIO te bezitten en te lezen. Het
systeem ondersteunt de administratie van een managementsysteem — registers,
beoordelingen, taken, bewijs, audits, rapportage — maar het vertelt je niet
gezaghebbend wat de norm van je eist.

Concreet:

- **De verplichtingen zijn hier genummerd, niet uitgeschreven.** Wie wil weten wat
  5.24.03 vraagt, leest de BIO.
- **Het systeem beoordeelt niet of je in control bent.** Het legt vast wat jij
  daarover hebt vastgesteld, met de onderbouwing die je eraan hangt.
- **De In Control Verklaring is een bestuurlijke handeling.** Dit ISMS levert de
  cijfers en het bewijs eronder; de verklaring zelf is van je bestuur.
- **De reikwijdtevraag is niet die van jouw organisatie.** Of jóuw entiteit onder
  de Cyberbeveiligingswet valt, hangt af van sector en omvang, niet van welke norm
  je volgt. Dat is een aparte instelling in dit systeem, en het antwoord komt van
  de RDI en je juridische afdeling — niet hiervandaan.

## Het eigenlijke risico: verifieerbaarheid

Het risico van een ISMS zonder normtekst is niet dat er iets ontbreekt — dat is
zichtbaar. Het risico is dat er iets stáát wat plausibel lijkt en niet klopt. Een
verzonnen omschrijving is van een juiste niet te onderscheiden zonder de norm
ernaast.

Daarom levert dit systeem liever een mededeling dan een gok. Waar je een
markering ziet ("dit ISMS levert hier geen tekst mee"), is dat een expliciete
uitspraak: er hoort hier iets te staan, wij leveren het niet, kijk in de bron.
Dat is iets anders dan een leeg veld en heel iets anders dan een plausibele
invulling.

## Auteursrecht en verspreiding

- **NEN-EN-ISO/IEC 27001 en 27002** zijn auteursrechtelijk beschermd. Ze worden
  hier niet meegeleverd en horen niet in dit systeem te worden gekopieerd, ook
  niet gedeeltelijk, ook niet als "eigen omschrijving die er sterk op lijkt".
- **De BIO2** staat onder CC BY-NC-SA 4.0, met het NEN-deel uitgezonderd. Neem je
  de teksten op in je eigen installatie, dan blijft dat jouw exemplaar; verspreid
  het niet verder zonder de licentie te lezen.
- **Eigenaarschap van de BIO** ligt bij de stelselverantwoordelijke, het
  ministerie van Binnenlandse Zaken en Koninkrijksrelaties. Het Centrum
  Informatiebeveiliging en Privacybescherming (CIP) verzorgt het onderhoud.
- **Wat je zelf invoert** — omschrijvingen, motivaties, beoordelingen, bewijs — is
  van jouw organisatie. Dit systeem doet daar niets mee behalve het bewaren en
  tonen.

## Aansprakelijkheid

Dit systeem is een administratief hulpmiddel. Het geeft geen juridisch advies,
geen normuitleg en geen garantie dat je aan de BIO of aan de Cyberbeveiligingswet
voldoet. De verantwoordelijkheid voor de juistheid van wat er in staat, en voor de
conclusies die je eraan verbindt, ligt bij jouw organisatie.
