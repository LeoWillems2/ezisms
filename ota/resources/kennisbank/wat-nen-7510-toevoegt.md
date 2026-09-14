# Wat NEN 7510 toevoegt bovenop ISO 27001

Deze installatie draait op het NEN 7510-profiel. Dit artikel legt uit wat dat
profiel verandert ten opzichte van een ISO 27001-ISMS. Belangrijker is dat het
artikel beschrijft **waar de grens ligt tussen wat dit platform doet en wat de
organisatie in haar zorgsystemen regelt.**

> **Dit is één van drie normprofielen.** Deze installatie draait NEN 7510. Er zijn
> ook uitvoeringen op ISO 27001 en op de BIO2. De BIO2 breidt Bijlage A niet uit
> in de breedte, maar in de diepte: dezelfde 93 maatregelen, met daaronder 118
> genummerde overheidsmaatregelen. Het artikel *Wat de BIO toevoegt* staat in een
> BIO-installatie. Hier is dat artikel niet zichtbaar, omdat het niet over de norm
> van deze installatie gaat.

## Een superset, geen andere norm

NEN 7510 is geen alternatief voor ISO 27001, maar een uitbreiding erop die is
toegesneden op de zorg. Concreet betekent dat het volgende:

- **Hoofdstuk 4 tot en met 10 volgen dezelfde Harmonized Structure.** Het gaat om
  context, leiderschap, planning, ondersteuning, uitvoering, evaluatie en
  verbetering, met dezelfde nummers, dezelfde eisen en dezelfde volgorde. Alles
  wat uit ISO 27001 bekend is over §6.1.2 of §9.3, geldt hier onveranderd.
- **De maatregelenbijlage bestaat uit de 93 ISO-maatregelen plus acht.** De
  nummering loopt door en botst nergens.
- **Bij een deel van de bestaande maatregelen staat een zorgspecifieke
  aanvulling.** Het is dezelfde maatregel, met een zwaardere of preciezere eis.

Wie ISO 27001 kent, kent het grootste deel van NEN 7510. De toevoegingen zijn
zorgspecifiek en overzichtelijk.

## De acht extra maatregelen

| Referentie | Titel | Thema |
|---|---|---|
| **A.5.38** | Analyse en specificatie van informatiebeveiligingseisen | organisatorisch |
| **A.5.39** | Zorgontvangers op unieke wijze identificeren | organisatorisch |
| **A.5.40** | Validatie van getoonde/geprinte gegevens | organisatorisch |
| **A.5.41** | Openbaar beschikbare gezondheidsinformatie | organisatorisch |
| **A.5.42** | Communicatie in noodsituaties | organisatorisch |
| **A.5.43** | Incidenten extern melden | organisatorisch |
| **A.6.9** | Managementtraining | mensgericht |
| **A.8.35** | Zero trust-beginselen | technologisch |

Deze maatregelen staan in de Verklaring van Toepasselijkheid tussen de andere 93.
Ze worden op dezelfde manier beoordeeld: van toepassing ja of nee, met een
motivatie.

Het systeem levert **geen** omschrijving van deze maatregelen mee. Het onderstaande
deel legt uit waarom.

## De zorgspecifieke aanvullingen

Een deel van de bestaande ISO-maatregelen heeft in NEN 7510 een aanvulling. Daarvoor
heeft de SoA-modal een eigen veld met een eigen kopje, los van de omschrijving. Dat
aparte blok is een bewuste keuze. Bron, licentiestatus en voorbehoud verschillen,
en dat verschil hoort zichtbaar te blijven.

Het veld kent drie toestanden, en elk van die toestanden heeft een eigen betekenis:

| Weergave | Betekenis |
|---|---|
| Geen blok | Deze maatregel heeft geen zorgspecifieke beheersmaatregel. |
| "Dit ISMS levert bij deze maatregel geen zorgspecifieke maatregel mee." | Deze maatregel heeft wel een zorgspecifieke beheersmaatregel. De inhoud daarvan staat in de norm. |
| "Niet ingelezen…" | Er is helemaal niets ingelezen. Dat is een installatiefout, omdat deze gegevens meegeleverd horen te zijn. |

**De lijst wordt meegeleverd, de teksten niet.** Het is openbaar bekend welke
maatregelen een zorgspecifieke beheersmaatregel hebben. Het systeem toont daarom
direct bij welke 22 van de 101 maatregelen de norm erbij nodig is. De inhoud van
die beheersmaatregelen geeft dit systeem niet door. Het volledige verhaal staat in
[Verantwoording en disclaimer](/kennisbank/verantwoording-en-disclaimer).

**Bij geen enkele maatregel staat een omschrijving.** Dat geldt ook voor de 93
maatregelen uit ISO, en ook op een ISO-installatie. Dit systeem levert nergens een
eigen uitleg van het doel van een maatregel. De reden staat op dezelfde pagina.
Voor de zorg komt daar een tweede reden bij: een ISO-gerichte omschrijving zou de
eis onderschatten, juist bij de maatregelen waar NEN 7510 meer vraagt.

### Zelf invoeren, als de norm beschikbaar is

De maatregelteksten worden ingevoerd zoals beschreven in [De normteksten
invoeren](/kennisbank/normteksten-invoeren). Op een zorginstallatie is dat het
bestand **`database/seeders/data/maatregelen-nen7510.json`**, met alle 101
maatregelen. De aanvullingen staan in hetzelfde bestand, per maatregel in het veld
`zorgaanvulling`. Er is dus geen tweede bestand. Dat veld kent twee waarden:

- **22 regels** bevatten de mededeling uit de tabel hierboven. Dat zijn precies de
  maatregelen waarbij NEN 7510 een zorgspecifieke beheersmaatregel geeft. Die zin
  wordt vervangen door de tekst uit de norm.
- **79 regels** bevatten `DO NOT TOUCH`. Bij die maatregelen geeft de norm geen
  zorgspecifieke beheersmaatregel. Deze regels blijven ongewijzigd. Een ingevulde
  waarde zou het systeem iets laten beweren wat de norm niet zegt.

Zo is in de editor aan elke regel te zien wat er moet gebeuren. Op het scherm
verschijnt geen van beide markeringen: bij een `DO NOT TOUCH`-maatregel blijft het
blok weg.

Bij de acht maatregelen die alleen NEN 7510 kent, is de zorgspecifieke
beheersmaatregel de maatregel zelf. Hun `omschrijving` blijft de mededeling, en de
tekst komt in `zorgaanvulling`.

Eén commando verwerkt het geheel:

```
php artisan isms:maatregelen
```

## De afbakening: wat dit platform niet bouwt

Op dit punt is een verkeerde verwachting het duurst. Daarom staat dit deel hier en
niet onderaan.

**Een groot deel van wat NEN 7510 vraagt, gaat over de zorgsystemen van de
organisatie en niet over dit ISMS-platform.** Voorbeelden zijn:

- **Cliëntidentificatie (A.5.39).** Het uniek identificeren van zorgontvangers is
  een eis aan het EPD en het intakeproces. Dit platform kent geen zorgontvangers.
- **Het samenvoegen van dubbele dossiers.** Dit is een dossierbeheerproces in de
  zorgapplicatie.
- **Break-glass-toegang.** Dit is noodtoegang tot een dossier buiten de reguliere
  autorisatie om, met verantwoording achteraf. Die functie hoort in het systeem
  dat de dossiers beheert.
- **Tweefactor-authenticatie op het EPD.** Dit ISMS heeft zelf tweefactor, maar
  dat zegt niets over de zorgapplicaties.
- **Logging volgens NEN 7513.** Dat is het logboek van toegang tot
  cliëntdossiers. De audit trail in dit systeem legt ISMS-mutaties vast, zoals wie
  een risico wijzigde of wie een incident sloot. Zie [De audit
  trail](/kennisbank/de-audit-trail).

Die maatregelen **verdwijnen niet**. Ze verschijnen als regels in de SoA, en daar
motiveert de organisatie hoe zij ze invult. Het platform verzorgt de administratie
ervan: de verklaring, de koppeling aan risico's, het bewijs en de opvolging. Het
platform voert de maatregelen niet uit.

**Dit platform verwerkt zelf geen persoonlijke gezondheidsinformatie.** Dat blijft
ook in het NEN 7510-profiel zo. Het systeem bevat geen patiëntgegevens en neemt
ook geen patiëntgegevens op. Dat is geen tijdelijke toestand, maar een
ontwerpgrens.

## Wat NEN 7510 niet regelt: de meldplicht

Een veelgemaakte aanname is dat de norm bepaalt wanneer een incident extern gemeld
moet worden. Dat is niet zo.

De meldplicht komt uit de **wet** en niet uit de norm:

- de **AVG** verplicht tot melding bij de Autoriteit Persoonsgegevens bij een
  inbreuk in verband met persoonsgegevens;
- de **Cyberbeveiligingswet** verplicht bepaalde organisaties tot een gefaseerde
  melding bij een significant incident.

Of een organisatie onder de Cyberbeveiligingswet valt, hangt af van haar sector en
omvang en niet van de norm die zij volgt. Daarom is de Cbw-plicht in dit ISMS een
**aparte instelling** en geen onderdeel van het normprofiel. Een zorgaanbieder kan
NEN 7510 volgen zonder Cbw-plichtig te zijn, en een niet-zorgorganisatie kan
Cbw-plichtig zijn zonder NEN 7510 te volgen.

De norm voegt wel maatregel **A.5.43 Incidenten extern melden** toe. Die maatregel
eist dat het melden geregeld is. Hoe het geregeld wordt en wanneer er gemeld moet
worden, staat in de wet en in de eigen meldprocedure van de organisatie. Zie
[Incidenten & afwijkingen](/kennisbank/incidenten-en-afwijkingen) voor de manier
waarop het ISMS dat vastlegt.

## De andere zorgstandaarden

NEN 7510 staat niet alleen. Twee normen liggen er dicht tegenaan en worden er
regelmatig mee verward:

- **NEN 7512** is de vertrouwensbasis voor elektronische gegevensuitwisseling
  tussen zorgpartijen. De norm beschrijft welk betrouwbaarheidsniveau een
  uitwisseling nodig heeft en hoe partijen elkaar authenticeren.
- **NEN 7513** is het logboek van toegang tot cliëntdossiers. De norm beschrijft
  wat vastgelegd moet worden, hoe lang, en wie het mag inzien.

**Geen van beide normen zit in dit ISMS**, en dat is terecht. Ze stellen eisen aan
het uitwisselingsplatform en het dossiersysteem, niet aan een managementsysteem.
Ze kunnen wel als eis worden opgevoerd in het eisenregister (bron: wettelijk of
contractueel), met maatregelen eraan gekoppeld. Dan lopen ze mee in de gewone
ISMS-cyclus, zonder dat het platform pretendeert eraan te voldoen. Zie
[Integraties](/kennisbank/integraties-en-normeis) voor de reden waarom het
integratieregister geen bewijs onder 7512 of 7513 is.

## Certificering en toezicht

Certificering gebeurt tegen **NEN 7510-1**, onder het schema **NCS 7510**, door een
instelling die daarvoor door de Raad voor Accreditatie is geaccrediteerd. Het
**toezicht** van de Inspectie Gezondheidszorg en Jeugd is iets anders. Dat
toezicht is geen certificering, heeft een eigen toetsingskader en heeft andere
gevolgen. Dat onderscheid en de gevolgen voor de auditadministratie staan in [De
externe certificeringsaudit](/kennisbank/externe-certificeringsaudit).

## Samengevat

| Vraag | Antwoord |
|---|---|
| Verandert de inrichting van H4–H10? | Nee. Die is identiek aan ISO 27001. |
| Hoeveel maatregelen zijn er? | 101 in plaats van 93. |
| Levert het systeem de normteksten mee? | Nee, en in dit profiel ook de ISO-omschrijvingen niet. |
| Bouwt dit platform break-glass, cliëntidentificatie of 7513-logging? | Nee. Dat zijn eisen aan de zorgsystemen. In dit platform worden ze gemotiveerd. |
| Bepaalt NEN 7510 de meldtermijnen? | Nee. Die volgen uit de AVG en de Cyberbeveiligingswet. |
