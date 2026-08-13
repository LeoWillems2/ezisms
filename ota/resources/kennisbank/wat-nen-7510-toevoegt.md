# Wat NEN 7510 toevoegt bovenop ISO 27001

Deze installatie draait op het NEN 7510-profiel. Dit artikel legt uit wat dat
verandert ten opzichte van een ISO 27001-ISMS, en — belangrijker — **waar de
grens ligt tussen wat dit platform doet en wat je in je zorgsystemen regelt.**

## Een superset, geen andere norm

NEN 7510 is geen alternatief voor ISO 27001 maar een uitbreiding erop, toegesneden
op de zorg. Concreet:

- **Hoofdstuk 4 tot en met 10 is dezelfde Harmonized Structure.** Context,
  leiderschap, planning, ondersteuning, uitvoering, evaluatie, verbetering. Zelfde
  nummers, zelfde eisen, zelfde volgorde. Alles wat je over §6.1.2 of §9.3 weet
  uit ISO 27001, geldt hier onveranderd.
- **De maatregelenbijlage is de 93 ISO-maatregelen plus acht.** De nummering loopt
  door en botst nergens.
- **Bij een deel van de bestaande maatregelen staat een zorgspecifieke
  aanvulling**: dezelfde maatregel, een zwaardere of preciezere eis.

Wie ISO 27001 kent, kent het grootste deel van NEN 7510. Wat erbij komt is
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

Ze staan gewoon in de Verklaring van Toepasselijkheid, tussen de andere 93, en je
beoordeelt ze op dezelfde manier: van toepassing ja/nee, met motivatie.

Wat je er **niet** bij krijgt is hun omschrijving — zie hieronder.

## De zorgspecifieke aanvullingen

Een deel van de bestaande ISO-maatregelen draagt in NEN 7510 een aanvulling.
Daarvoor is een eigen veld met een eigen kopje in de SoA-modal, los van de
omschrijving. Dat is met opzet een apart blok: bron, licentiestatus en voorbehoud
verschillen, en dat hoort zichtbaar te blijven.

Het veld kent drie toestanden, en dat verschil is functioneel:

| Wat je ziet | Wat het betekent |
|---|---|
| Geen blok | Deze maatregel heeft geen zorgspecifieke beheersmaatregel. |
| "Dit ISMS levert bij deze maatregel geen zorgspecifieke maatregel mee." | Deze maatregel heeft er wél een. Wat die inhoudt, lees je in de norm. |
| "Niet ingelezen…" | Er is helemaal niets ingelezen. Dat is een installatiefout: dit hoort meegeleverd te zijn. |

**De lijst wordt meegeleverd, de teksten niet.** Dát een maatregel een
zorgspecifieke beheersmaatregel heeft is openbaar bekend, dus je ziet meteen bij
welke 22 van de 101 je de norm erbij moet pakken. Wat er staat, geeft dit systeem
niet door. Zie [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer) voor het volledige verhaal.

**Bij géén enkele maatregel staat een omschrijving** — ook niet bij de 93 uit
ISO, en ook niet op een ISO-installatie. Dit systeem levert nergens een eigen
uitleg van wat een maatregel beoogt; de reden staat op diezelfde pagina. Voor de
zorgkant kwam daar een tweede reden bij: een ISO-gerichte omschrijving zou de eis
onderschatten bij precies die maatregelen waar NEN 7510 meer vraagt.

### Zelf invoeren, als je de norm hebt

De maatregelteksten voer je in zoals beschreven in [De normteksten
invoeren](/kennisbank/normteksten-invoeren). Op een zorginstallatie is dat
**`database/seeders/data/maatregelen-nen7510.json`**, met alle 101 maatregelen.
De aanvullingen staan in datzelfde bestand, per maatregel in het veld
`zorgaanvulling` — er is dus geen tweede bestand. Dat veld kent twee waarden:

- **22 regels** dragen de mededeling uit de tabel hierboven. Dat zijn precies de
  maatregelen waar NEN 7510 een zorgspecifieke beheersmaatregel bij geeft.
  Vervang die zin door de tekst uit de norm.
- **79 regels** dragen `DO NOT TOUCH`. Daar geeft de norm er géén. Laat die
  regels staan zoals ze zijn; er iets invullen laat dit systeem iets beweren wat
  de norm niet zegt.

Zo zie je in de editor aan elke regel wat er moet gebeuren, en op het scherm komt
geen van beide markeringen: bij een `DO NOT TOUCH`-maatregel blijft het blok
gewoon weg.

Bij de acht maatregelen die alleen NEN 7510 kent, ís de zorgspecifieke
beheersmaatregel de maatregel: hun `omschrijving` blijft de mededeling en de
tekst zet je in `zorgaanvulling`.

Eén commando verwerkt het geheel:

```
php artisan isms:maatregelen
```

## De afbakening: wat dit platform níét voor je bouwt

Dit is het punt waarop een verkeerde verwachting het duurst is, dus het staat hier
en niet onderaan.

**Een groot deel van wat NEN 7510 vraagt, gaat over de zorgsystemen van je
organisatie — niet over dit ISMS-platform.** Denk aan:

- **Cliëntidentificatie (A.5.39).** Dat je zorgontvangers uniek identificeert is
  een eis aan je EPD en je intakeproces. Dit platform kent geen zorgontvangers.
- **Het samenvoegen van dubbele dossiers.** Een dossierbeheerproces in je
  zorgapplicatie.
- **Break-glass-toegang.** Noodtoegang tot een dossier buiten de reguliere
  autorisatie om, mét achteraf-verantwoording. Dat hoort in het systeem dat de
  dossiers houdt.
- **Tweefactor-authenticatie op het EPD.** Dit ISMS heeft zelf tweefactor, maar
  dat zegt niets over je zorgapplicaties.
- **Logging volgens NEN 7513.** Dat is het logboek van toegang tot
  cliëntdossiers. De audit trail hier legt ISMS-mutaties vast — wie een risico
  wijzigde, wie een incident sloot. Zie [De audit
  trail](/kennisbank/de-audit-trail).

Die maatregelen **verdwijnen niet**: ze verschijnen als regels in de SoA, en jij
motiveert daar hoe je organisatie ze invult. Wat het platform doet is de
administratie ervan — de verklaring, de koppeling aan risico's, het bewijs, de
opvolging. Wat het niet doet, is ze uitvoeren.

**Dit platform verwerkt zelf geen persoonlijke gezondheidsinformatie.** Dat blijft
waar, ook in het NEN 7510-profiel. Er zit geen patiëntgegeven in, er komt er geen
in, en dat is geen tijdelijke toestand maar een ontwerpgrens.

## Wat NEN 7510 níét regelt: de meldplicht

Een veelgemaakte aanname is dat de norm bepaalt wanneer je een incident extern
moet melden. Dat doet ze niet.

De meldplicht komt uit de **wet**, niet uit de norm:

- de **AVG** verplicht tot melding bij de Autoriteit Persoonsgegevens bij een
  inbreuk in verband met persoonsgegevens;
- de **Cyberbeveiligingswet** verplicht bepaalde organisaties tot een gefaseerde
  melding bij een significant incident.

Of jij onder die tweede valt, hangt af van je sector en je omvang — niet van welke
norm je volgt. Daarom is de Cbw-plicht in dit ISMS een **aparte instelling** en
geen onderdeel van het normprofiel: een zorgaanbieder kan NEN 7510 volgen zonder
Cbw-plichtig te zijn, en een niet-zorgorganisatie kan Cbw-plichtig zijn zonder
NEN 7510.

Wat de norm wél toevoegt is maatregel **A.5.43 Incidenten extern melden**: dat je
het geregeld moet hébben. Hoe je het regelt, en wanneer je moet melden, staat in
de wet en in je eigen meldprocedure. Zie [Incidenten &
afwijkingen](/kennisbank/incidenten-en-afwijkingen) voor hoe het ISMS dat
vastlegt.

## De andere zorgstandaarden

NEN 7510 staat niet alleen. Twee normen liggen er dicht tegenaan en worden er
regelmatig mee verward:

- **NEN 7512** — de vertrouwensbasis voor elektronische gegevensuitwisseling
  tussen zorgpartijen: welk betrouwbaarheidsniveau een uitwisseling nodig heeft
  en hoe partijen elkaar authenticeren.
- **NEN 7513** — het logboek van toegang tot cliëntdossiers: wat je moet
  vastleggen, hoe lang, en wie het mag inzien.

**Geen van beide zit in dit ISMS**, en dat is terecht: ze stellen eisen aan je
uitwisselingsplatform en je dossiersysteem, niet aan een managementsysteem. Je
kunt ze wel als eis opvoeren in het eisenregister (bron: wettelijk of
contractueel) en er maatregelen aan hangen — dan lopen ze mee in de gewone
ISMS-cyclus zonder dat het platform pretendeert eraan te voldoen. Zie
[Integraties](/kennisbank/integraties-en-normeis) voor waarom het
integratieregister géén bewijs onder 7512 of 7513 is.

## Certificering en toezicht

Certificering gebeurt tegen **NEN 7510-1** door een instelling die daarvoor is
geaccrediteerd door de Raad voor Accreditatie, onder het schema **NCS 7510**. Het
**toezicht** van de Inspectie Gezondheidszorg en Jeugd is iets anders: geen
certificering, een eigen toetsingskader, andere gevolgen. Dat onderscheid en de
gevolgen voor je auditadministratie staan in [De externe
certificeringsaudit](/kennisbank/externe-certificeringsaudit).

## Samengevat

| Vraag | Antwoord |
|---|---|
| Moet ik mijn H4–H10-inrichting omgooien? | Nee. Identiek aan ISO 27001. |
| Hoeveel maatregelen? | 101 in plaats van 93. |
| Krijg ik de normteksten mee? | Nee, en in dit profiel ook de ISO-omschrijvingen niet. |
| Bouwt dit platform break-glass, cliëntidentificatie of 7513-logging? | Nee. Dat zijn eisen aan je zorgsystemen; hier motiveer je ze. |
| Bepaalt NEN 7510 mijn meldtermijnen? | Nee. Dat doen de AVG en de Cyberbeveiligingswet. |
