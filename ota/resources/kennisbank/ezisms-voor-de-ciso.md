# EzISMS voor de CISO: past dit bij je?

Deze pagina is bedoeld om in tien minuten te beantwoorden of dit systeem bij jouw
organisatie past. Geen techniek, geen installatiehandleiding — wat het doet, wat
het niet doet, en waar het van andere ISMS-software verschilt.

## In vijf zinnen

- **EzISMS is de administratie van je managementsysteem**, opgezet rond de
  PDCA-cyclus die de norm oplegt: context en scope, risico's en Verklaring van
  Toepasselijkheid, beleid, taken, incidenten, leveranciers, trainingen, audits,
  meting en directiebeoordeling.
- **Alles wat je vastlegt, is bewijs.** Elk register houdt zijn eigen historie bij
  en elke wijziging komt in een audit trail: wie, wat, wanneer, op welk gebied.
- **Het draait op je eigen server.** Eén organisatie per installatie, geen
  clouddienst, geen meekijkende leverancier; je bewijsstukken staan op je eigen
  schijf.
- **Het is Nederlandstalig** en volgt ISO/IEC 27001:2022, NEN 7510 of de BIO2 — je kiest
  het profiel bij installatie.
- **Het levert de normtekst niet mee.** Dat is een bewuste keuze met gevolgen;
  zie *Wat dit systeem uitdrukkelijk niet is*.

## Het probleem waar het voor gemaakt is

Een ISMS begint bijna altijd als een verzameling: een risicoregister in Excel,
een SoA in een tweede werkblad, beleid in een documentmap, incidenten in de
mailbox, en een jaarplanning in het hoofd van één persoon. Dat werkt tot de
eerste audit. Dan blijkt dat niemand kan aantonen *wanneer* een risicoscore
daalde, *wie* eigenaar van een maatregel was, of *of* het beleid daadwerkelijk is
gelezen.

EzISMS vervangt die verzameling door registers die naar elkaar verwijzen. Een
risico wijst naar de maatregelen die het behandelen; een maatregel wijst naar het
beleid dat hem invult; een incident wijst naar de afwijking die eruit volgde en
naar de corrigerende maatregel die is afgesproken. Het bewijs hangt aan het
record waar het over gaat, niet in een map met een datum in de naam.

## Wat je er dagelijks mee doet

| Wat je wilt | Waar je het doet |
| --- | --- |
| Scope vaststellen, issues en belanghebbenden bijhouden | Context & Scope |
| Assets en systemen registreren en classificeren (BIV) | Assets |
| Risico's beoordelen tegen een vastgesteld criteriakader | SoA & Risico's → Risicoregister |
| De Verklaring van Toepasselijkheid onderbouwen en het restrisico vastleggen | SoA & Risico's → Statement of Applicability |
| Beleid vaststellen, publiceren en leesbevestigingen verzamelen | Beleid & procedures |
| Terugkerend werk laten terugkomen zonder eraan te denken | Taken (met sjablonen en een ritme) |
| Incidenten afhandelen inclusief de externe meldplicht | Incidenten |
| Afwijkingen, grondoorzaak, corrigerende maatregel en effectiviteitstoets | Afwijkingen |
| Leveranciers en hun periodieke beoordeling | Leveranciers |
| Bewustzijn: trainingen, doelgroepen, toetsen en resultaten | Bewustzijn & training |
| Interne audits plannen, uitvoeren en de dekking over meerdere jaren bewaken | Audits |
| Meten: KPI's met streefwaarde, richting en trend | KPI's |
| De directiebeoordeling met agenda, besluiten en verbeteracties | Management review |

Het dashboard vat dat samen in zes panelen: een KPI-strip, signalen die om
aandacht vragen, de PDCA-trend, de risico- en maatregelverdeling, de stand van je
documenten en bewijzen, en je eigen takenlijst. Wat je ziet hangt af van je rol —
een medewerker krijgt geen risicomatrix te zien.

## Assets en incidenten: door de beveiligingsbril

Twee onderdelen worden bijna altijd verkeerd ingeschat, dus vooraf helder:

**Het assetregister is geen assetmanagementsysteem.** Geen CMDB, geen
inkoopadministratie, geen licentie- of afschrijvingsbeheer. Je vindt er geen
serienummers, aanschafwaarden of contractdata. Wat je er wél vindt is wat de norm
van je vraagt: wat er binnen de scope valt, wie eigenaar is en wie het beheert,
de BIV-classificatie, of er persoonsgegevens in zitten, en aan wie iets is
uitgegeven en wanneer het is teruggekomen. Genoeg om risico's en maatregelen aan
op te hangen — en niet meer dan dat.

**De incidentmodule is geen ticketsysteem.** Geen wachtrijen, geen SLA-klok, geen
meldportaal voor eindgebruikers, geen prioriteitsroutering naar teams. Het legt
de beveiligingskant vast: ernst, verloop, koppeling aan een asset of risico, de
beoordeling van de externe meldplicht met de termijnen van de AVG en de
Cyberbeveiligingswet, en de weg naar afwijking, grondoorzaak en corrigerende
maatregel.

Heb je al een CMDB of een servicedesk, dan houd je die gewoon. De praktische
regel: **daar staat de operatie, hier staat de verantwoording.** Registreer hier
wat binnen de scope van het ISMS valt, verwijs in de omschrijving naar het
ticketnummer of het CMDB-nummer, en spreek af welk systeem voor welk gegeven de
bron is. Die afspraak is een halve pagina beleid, en ze voorkomt de discussie die
anders bij elke audit terugkomt.

## Wat het bij een audit voor je doet

Dit is waar het systeem zijn geld verdient, en het is bewust ontworpen vanuit de
vraag van de auditor in plaats van vanuit een featurelijst.

- **Een read-only auditoraccount.** De auditor krijgt inzage in vrijwel alles en
  kan niets wijzigen. Geen exportklus vooraf, geen dossier samenstellen.
- **Een audit trail met een keten.** Elke logregel draagt de hash van zijn
  voorganger, en een nachtelijke controle bewaart de uitslag. De vraag is niet of
  de trail vandaag klopt, maar of hij al twee jaar elke nacht is gecontroleerd —
  en dat kun je laten zien.
- **Schermkopieën op verzoek.** Vraagt de auditor "mag ik hier een kopie van?",
  dan levert het scherm een Word-document van precies wat er staat, inclusief de
  actieve filters en de regel *"36 van 214 regels"*. Wat je meegaf, komt in een
  eigen register te staan.
- **Een dekkingsmatrix** over de auditcyclus: welk auditobject in welk jaar
  gepland, uitgevoerd of niet gedekt is.

Zie ook het artikel [EzISMS voor de externe
auditor](/kennisbank/ezisms-voor-de-auditor) — dat kun je de auditor vooraf
sturen.

## Functiescheiding zit in het model

Rechten zijn geen instellingen die per gebruiker uit de hand lopen: er zijn vier
ISMS-rollen (CISO, Medewerker, Auditor, Management) met per functiegebied een
niveau. Daarnaast is er een vijfde, de Administrator, die juist buiten het ISMS
staat: hij beheert de installatie en heeft op geen enkel functiegebied inzage.
Die rol gaat met geen enkele andere samen — beheert u zelf de server, dan is dat
een tweede account.

De belangrijkste consequentie: **vaststellen is losgekoppeld van bewerken.** De
CISO stelt op, Management stelt vast. Dat geldt voor het publiceren van beleid,
het activeren van een scope-versie, het accepteren van een restrisico boven de
drempel, het vaststellen van de risicocriteria en het vastleggen van de
directiebeoordeling. Een directielid dat mag goedkeuren kan daarmee níét het
risicoregister herschrijven.

Dat is precies het soort scheiding waar een auditor naar vraagt, en het is hier
niet een afspraak maar een grens in de software.

## Waar je gegevens staan

Eén organisatie per installatie. De applicatie draait op je eigen server, of als
Docker-stack met twee containers (applicatie en database). Je bewijsstukken staan
als bestand op je eigen schijf, niet bij een dienstverlener; de back-up is jouw
back-up. Er is tweefactorauthenticatie, een uitnodigingsflow voor nieuwe
gebruikers en een dagelijkse takenplanner die vervallen accounts, bewaartermijnen
en herinneringen afhandelt.

Aanmelden gaat met eigen accounts; er is (nog) geen koppeling met je
identity-provider.

## Welk normprofiel: ISO 27001, NEN 7510 of BIO2

Bij installatie kies je het normprofiel. Geen van de drie is een andere norm dan
ISO 27001; ze bouwen er allemaal op voort, en ze doen dat op twee verschillende
manieren.

**NEN 7510 breidt uit in de breedte.** Dezelfde hoofdstukken 4 tot en met 10,
dezelfde maatregelen plus een handvol zorgspecifieke, en bij een deel van de
bestaande maatregelen een aanvulling. In dat profiel zie je die extra maatregelen
gewoon in de Verklaring van Toepasselijkheid staan.

**De BIO2 breidt uit in de diepte.** Bijlage A blijft ongewijzigd — er komt geen
maatregel bij — maar onder een groot deel ervan hangen genummerde
*overheidsmaatregelen*: de verplichte minimale invulling. Waar ISO zegt "beheer je
toegangsrechten", zegt de BIO hoe vaak je ze beoordeelt. Dat profiel is voor
overheidsentiteiten die onder de Cyberbeveiligingswet vallen, en het werkt naar
verantwoording aan de RDI in plaats van naar een certificaat.

Delen van de kennisbank leggen per profiel de eigen kant uit; in de lijst links
staat onder *Naslag* een artikel "Wat … toevoegt" voor de norm die deze installatie
volgt.

De keuze ligt vast per installatie en is niet achteraf om te zetten.

## Wat dit systeem uitdrukkelijk niet is

Liever nu duidelijk dan bij de eerste audit.

- **Het bevat de normtekst niet.** Bij elke maatregel staan nummer, titel en
  thema — geen omschrijving. Normteksten zijn auteursrechtelijk beschermd, en een
  eigen samenvatting op de plek waar een auditor de toepasselijkheid beoordeelt
  levert alleen discussie op. Heb je de norm gekocht, dan voer je de teksten in
  één keer zelf in; ze blijven dan binnen je eigen installatie.
- **Het geeft geen compliance-cijfer.** Er is geen knop die zegt dat je voor 87%
  aan de norm voldoet. Dat oordeel is aan je auditor.
- **Het is geen adviesdienst.** Verwijzingen naar paragrafen zijn een hulpmiddel
  om te weten waar je in je eigen exemplaar moet kijken.
- **Het is geen CMDB en geen servicedesk.** Zie hierboven: assets en incidenten
  staan er met de beveiligingsbril op.
- **Het doet je werk niet.** Een risicoregister vullen, beleid schrijven en bewijs
  verzamelen blijft mensenwerk. Wat het systeem doet, is voorkomen dat dat werk
  onvindbaar wordt.
- **Geen SSO, geen meerdere organisaties in één installatie, en geen kant-en-klare
  koppelingen** met HR- of ticketsystemen. Het integratieregister legt vast wélke
  koppelingen je hebt; het legt ze niet aan.

Een volledige verantwoording staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer); wat er nog openstaat en
bewust is weggelaten in [Open punten](/kennisbank/open-punten).

## Dit past bij je als…

- je het ISMS van één organisatie beheert en de gegevens in eigen huis wilt houden;
- je toewerkt naar certificering of die net hebt, en de bewijslast wilt kunnen
  laten zien in plaats van reconstrueren;
- je een kleine tot middelgrote organisatie bent waar de CISO de spil is, met
  directie die vaststelt en medewerkers die taken afwerken;
- je Nederlandstalige software wilt, met AVG- en Cyberbeveiligingswet-termijnen
  ingebouwd in de incidentafhandeling;
- je de norm bezit of gaat aanschaffen.

## Kijk verder als…

- je meerdere organisaties of klanten in één omgeving wilt beheren;
- je geen server wilt beheren en een clouddienst zoekt met een SLA;
- je aanmelden via je identity-provider (SSO) als harde eis hebt;
- je één systeem zoekt dat óók je CMDB en je servicedesk is;
- je verwacht dat de software je vertelt wat de norm van je eist.

## Wat de eerste maand kost

Installeren is een dag werk voor iemand met serverkennis. Daarna is het invullen:
scope, assets, risicocriteria, het risicoregister en de Verklaring van
Toepasselijkheid. Dat is het echte werk, en het is werk dat je met of zonder deze
software zou doen. Wat je ervoor terugkrijgt, is dat het de tweede keer — bij de
opvolgingsaudit, een jaar later — geen werk meer is.

Om te zien hoe een gevuld ISMS eruitziet zonder eerst alles in te voeren, kan een
beheerder een **aparte** installatie vullen met een compleet demoscenario: een
fictief bedrijf met een samenhangende tijdlijn van risico's, incidenten, audits
en metingen. Dat commando wist eerst de hele database en hoort dus nooit op de
omgeving met je echte gegevens; zie [Beheer: de
artisan-commando's](/kennisbank/beheer).
