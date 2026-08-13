# Maatregelclassificatie: uitgangspunt en eigen vaststelling

Elke maatregel in de SoA heeft kenmerken: is hij preventief of detectief, welke
eigenschappen beschermt hij, in welke fase van de cyclus werkt hij, en in welk
domein hoort hij thuis. Die kenmerken zijn **geen vaste eigenschap van de
maatregel** maar een uitspraak over hoe jouw organisatie hem heeft ingericht.

Daarom werkt dit ISMS met twee lagen: een meegeleverd **uitgangspunt** en jouw
**eigen vaststelling** daarbovenop.

## Het uitgangspunt is een startpunt, geen waarheid

Bij installatie krijgt elke maatregel een classificatie mee. Dat is bewust: een
lege SoA-modal ziet eruit als een defect, en niemand begint met vier keuzes maal
het aantal maatregelen voordat er iets te zien is.

Maar het is precies dát — een startpunt. Het is een redelijke invulling voor een
gemiddelde organisatie, niet een uitspraak over de jouwe. Er zijn drie situaties:

| Situatie | Betekenis |
|---|---|
| Meegeleverd uitgangspunt | Niemand heeft ernaar gekeken; dit is wat er bij de installatie in zat. |
| **Eigen vaststelling** | De organisatie heeft de classificatie bekeken en bevestigd. |
| **Afgeweken van uitgangspunt** | De organisatie heeft bekeken én iets anders vastgesteld. |

De laatste twee krijgen een badge in het SoA-scherm. De eerste niet: dat is de
begintoestand van elke regel, en een badge die overal staat draagt geen
informatie. In de export staat de herkomst wél bij elke maatregel voluit — daar
lees je de regels los van elkaar en is "hier heeft niemand naar gekeken" precies
wat je moet weten.

Let op het verschil tussen de eerste twee. Een classificatie die je opent en
zonder wijziging opslaat, verandert inhoudelijk niets — maar legt wel vast dát je
ernaar gekeken hebt. Dat is een andere uitspraak dan "er stond nog niets", en het
is precies de uitspraak waar een auditor naar kan vragen.

## Waarom dat je SoA sterker maakt

Een auditor die de SoA doorneemt, toetst niet of je classificatie "klopt" tegen
een tabel. Hij toetst of je **hebt nagedacht**. Twee organisaties kunnen dezelfde
maatregel volstrekt verschillend inrichten:

> A.8.16 Monitoring is bij de één **preventief én detectief én corrigerend** —
> logging voedt alarmering, alarmering triggert automatisch blokkades. Bij de
> ander is er alleen centrale logopslag die je achteraf doorzoekt. Dan is die
> maatregel **puur detectief**, en dat opschrijven is eerlijker dan de tabel
> overnemen.

Een classificatie die je zelf hebt vastgesteld is dus sterker
onderbouwingsmateriaal dan een overgenomen tabel. Zeker in combinatie met de
motivatie: samen vertellen ze *waarom* de maatregel van toepassing is en *hoe*
hij bij jou werkt.

## Alles of niets per maatregel

Je vult de dimensies voor een maatregel **compleet in, of helemaal niet**. Er is
geen mengvorm waarbij je twee dimensies zelf vaststelt en voor de andere twee het
uitgangspunt laat staan.

Dat is een bewuste keuze. Bij een mengvorm is de vraag "wat heeft deze
organisatie nu eigenlijk vastgesteld" niet meer te beantwoorden, en bij elke
lezing zou je per dimensie moeten terugvallen. Nu is het antwoord altijd
eenduidig: de badge zegt of het van jou is of van ons.

Wil je terug, dan is er de knop **Terug naar uitgangspunt**. Die wist je eigen
vaststelling en laat het meegeleverde uitgangspunt weer gelden. Bewust een aparte
knop en niet "leeg opslaan": een lege vaststelling is iets anders dan geen
vaststelling.

## Waar de vocabulaires vandaan komen

De toegestane waarden per dimensie staan in het schema van de applicatie
(`config/maatregelkenmerken.php`). Bij elke dimensie staat de herkomst vermeld,
en die herkomst zie je in het scherm terug als toelichting.

De waarden zelf zijn korte, algemeen gangbare begrippen uit de vakliteratuur over
informatiebeveiliging — preventief, detectief, corrigerend, en zo verder. Ze zijn
te kort en te algemeen om beschermd te zijn. De **toewijzing** van waarden aan
maatregelen is een ander verhaal; daarom is het meegeleverde uitgangspunt een
eigen beoordeling en geen overgenomen tabel.

## Waarom vier dimensies en niet vijf

ISO 27002:2022 kent bij elke maatregel vijf attribuutdimensies. Dit ISMS levert
er standaard **vier** mee:

- Type maatregel
- Informatiebeveiligingseigenschappen
- Cyberbeveiligingsconcepten
- Operationele domeinen

De vijfde — **beveiligingscapaciteiten** — ontbreekt bewust. Dat is geen
vergissing en geen omissie die nog ingehaald wordt.

De reden: die dimensie is niet in te vullen zonder de normtekst zelf. Welke
maatregel welke capaciteit heeft, is een toewijzing die alleen in de norm staat,
en die norm is auteursrechtelijk beschermd. Wij kunnen hem dus niet meeleveren.

En een plausibele gok is hier **erger dan niets**. Een verkeerde toewijzing is
namelijk niet van een juiste te onderscheiden, en zou als "wat de tabel zegt"
gaan rondzingen in SoA-onderbouwingen. Een lege dimensie is eerlijk; een
verzonnen dimensie is misleidend.

**Heb je de norm zelf?** Dan mag je hem in je eigen installatie vullen. Dat
gaat met:

```
php artisan isms:capaciteiten status   # laat zien hoe het er nu voor staat
php artisan isms:capaciteiten aan      # zet de dimensie aan
php artisan isms:capaciteiten uit      # zet de dimensie uit
```

Dit vraagt om een bestand `database/seeders/data/maatregel-capaciteiten.json`
met jouw eigen toewijzing. Dat bestand blijft lokaal: het staat in `.gitignore`. Zo
blijft de installatie van iedereen die de norm bezit compleet, zonder dat de
normtekst in een repository belandt.

## In de applicatie

Je vindt en bewerkt de classificatie in het scherm **Statement of Applicability**
(`/soa`), in dezelfde modal als de beoordeling: klik **Beoordelen** bij een
maatregel. Bovenaan staan de huidige kenmerken met de herkomst-badge, onderaan
het formulier waarin je ze vaststelt.

Bewerken vergt `muteren` op het blok Risico & SoA — dezelfde rechten als de rest
van de SoA-beoordeling. Elke wijziging komt in de audit trail te staan onder blok
`risico-soa`, op naam van wie hem maakte.

De classificatie gaat ook mee in **`isms:exporteer`**, in het bestand
`03-risico-en-soa.md`, met de herkomst erbij vermeld. Zo is in het geëxporteerde
SoA-bewijs terug te zien wat de organisatie zelf heeft bepaald en wat er nog op
het uitgangspunt staat.
