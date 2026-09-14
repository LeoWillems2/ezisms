# EzISMS voor de CISO: past dit systeem bij de organisatie?

Dit artikel beantwoordt in tien minuten de vraag of dit systeem bij een
organisatie past. Het artikel bevat geen technische details en geen
installatiehandleiding. Het beschrijft wat het systeem doet, wat het niet doet en
waarin het van andere ISMS-software verschilt.

## In vijf zinnen

- **EzISMS is de administratie van het managementsysteem.** Het systeem is
  opgezet rond de PDCA-cyclus die de norm oplegt: context en scope, risico's en
  Verklaring van Toepasselijkheid, beleid, taken, incidenten, leveranciers,
  trainingen, audits, meting en directiebeoordeling.
- **Alles wat wordt vastgelegd, is bewijs.** Elk register houdt zijn eigen
  historie bij, en elke wijziging komt in een audit trail: wie, wat, wanneer en op
  welk gebied.
- **Het systeem draait op een eigen server.** Er is één organisatie per
  installatie, geen clouddienst en geen meekijkende leverancier. De bewijsstukken
  staan op de eigen schijf van de organisatie.
- **Het systeem is Nederlandstalig** en volgt ISO/IEC 27001:2022, NEN 7510 of de
  BIO2. Het profiel wordt bij de installatie gekozen.
- **Het systeem levert de normtekst niet mee.** Dat is een bewuste keuze met
  gevolgen, die is beschreven onder *Wat dit systeem uitdrukkelijk niet is*.

## Het probleem waar het voor gemaakt is

Een ISMS begint bijna altijd als een verzameling losse bestanden: een
risicoregister in Excel, een SoA in een tweede werkblad, beleid in een
documentmap, incidenten in de mailbox en een jaarplanning in het hoofd van één
persoon. Die aanpak werkt tot de eerste audit. Bij de audit blijkt dat niemand
kan aantonen wanneer een risicoscore daalde, wie eigenaar van een maatregel was
en of het beleid daadwerkelijk is gelezen.

EzISMS vervangt die verzameling door registers die naar elkaar verwijzen. Een
risico verwijst naar de maatregelen die het risico behandelen. Een maatregel
verwijst naar het beleid dat de maatregel invult. Een incident verwijst naar de
afwijking die eruit volgde en naar de afgesproken corrigerende maatregel. Het
bewijs hangt aan het record waar het over gaat, en niet in een map met een datum
in de naam.

## Dagelijks gebruik

| Taak | Onderdeel in het menu |
| --- | --- |
| Scope vaststellen, issues en belanghebbenden bijhouden | Context & Scope |
| Assets en systemen registreren en classificeren (BIV) | Assets |
| Risico's beoordelen tegen een vastgesteld criteriakader | SoA & Risico's → Risicoregister |
| De Verklaring van Toepasselijkheid onderbouwen en het restrisico vastleggen | SoA & Risico's → Statement of Applicability |
| Beleid vaststellen, publiceren en leesbevestigingen verzamelen | Beleid & procedures |
| Terugkerend werk automatisch laten terugkomen | Taken (met sjablonen en een ritme) |
| Incidenten afhandelen, inclusief de externe meldplicht | Incidenten |
| Afwijkingen, grondoorzaak, corrigerende maatregel en effectiviteitstoets | Afwijkingen |
| Leveranciers en hun periodieke beoordeling | Leveranciers |
| Bewustzijn: trainingen, doelgroepen, toetsen en resultaten | Bewustzijn & training |
| Interne audits plannen, uitvoeren en de dekking over meerdere jaren bewaken | Audits |
| Meten: KPI's met streefwaarde, richting en trend | KPI's |
| De directiebeoordeling met agenda, besluiten en verbeteracties | Management review |

Het dashboard vat dit samen in zes panelen: een KPI-strip, signalen die om
aandacht vragen, de PDCA-trend, de risico- en maatregelverdeling, de stand van de
documenten en bewijzen, en de eigen takenlijst van de gebruiker. Wat het dashboard
toont, hangt af van de rol van de gebruiker. Een medewerker krijgt bijvoorbeeld
geen risicomatrix te zien.

## Assets en incidenten: door de beveiligingsbril

Twee onderdelen worden bijna altijd verkeerd ingeschat. Deze paragraaf beschrijft
ze daarom vooraf.

**Het assetregister is geen assetmanagementsysteem.** Het register is geen CMDB,
geen inkoopadministratie en geen systeem voor licentie- of afschrijvingsbeheer.
Het bevat geen serienummers, aanschafwaarden of contractdata. Het register bevat
wat de norm vraagt: wat binnen de scope valt, wie eigenaar is, wie het beheert,
de BIV-classificatie, of er persoonsgegevens in zitten, aan wie iets is uitgegeven
en wanneer het is teruggekomen. Dat is voldoende om risico's en maatregelen aan te
koppelen, en het register bevat bewust niet meer dan dat.

**De incidentmodule is geen ticketsysteem.** De module heeft geen wachtrijen, geen
SLA-klok, geen meldportaal voor eindgebruikers en geen prioriteitsroutering naar
teams. De module legt de beveiligingskant vast: de ernst, het verloop, de
koppeling aan een asset of risico, de beoordeling van de externe meldplicht met de
termijnen van de AVG en de Cyberbeveiligingswet, en de route naar afwijking,
grondoorzaak en corrigerende maatregel.

Een organisatie die al een CMDB of een servicedesk heeft, blijft die gewoon
gebruiken. De praktische regel is: **daar staat de operatie, hier staat de
verantwoording.** In dit systeem wordt geregistreerd wat binnen de scope van het
ISMS valt, met in de omschrijving een verwijzing naar het ticketnummer of het
CMDB-nummer. Daarnaast wordt afgesproken welk systeem voor welk gegeven de bron
is. Die afspraak beslaat een halve pagina beleid en voorkomt een discussie die
anders bij elke audit terugkomt.

## Wat het systeem bij een audit oplevert

Bij een audit levert het systeem de meeste waarde op. Het is bewust ontworpen
vanuit de vragen van de auditor en niet vanuit een lijst met functies.

- **Een read-only auditoraccount.** De auditor krijgt inzage in vrijwel alles en
  kan niets wijzigen. Er is vooraf geen export nodig en er hoeft geen dossier te
  worden samengesteld.
- **Een audit trail met een keten.** Elke logregel bevat de hash van de
  voorgaande regel, en een nachtelijke controle bewaart de uitslag. De relevante
  vraag is niet of de trail vandaag klopt, maar of de trail al twee jaar elke
  nacht is gecontroleerd. Het systeem kan dat aantonen.
- **Schermkopieën op verzoek.** Als de auditor om een kopie vraagt, levert het
  scherm een Word-document van precies wat er staat, inclusief de actieve filters
  en de regel *"36 van 214 regels"*. Elke meegegeven kopie wordt in een eigen
  register vastgelegd.
- **Een dekkingsmatrix** over de auditcyclus. De matrix toont welk auditobject in
  welk jaar is gepland, is uitgevoerd of niet is gedekt.

Zie ook het artikel [EzISMS voor de externe
auditor](/kennisbank/ezisms-voor-de-auditor). Dat artikel kan vooraf naar de
auditor worden gestuurd.

## Functiescheiding zit in het model

Rechten zijn geen instellingen die per gebruiker uit de hand kunnen lopen. Er zijn
vier ISMS-rollen (CISO, Medewerker, Auditor en Management) met per functiegebied
een niveau. Daarnaast is er een vijfde rol, de Administrator, die bewust buiten
het ISMS staat. De Administrator beheert de installatie en heeft op geen enkel
functiegebied inzage. Die rol is met geen enkele andere rol te combineren. Wie
zelf de server beheert, heeft daarvoor dus een tweede account nodig.

De belangrijkste consequentie is: **vaststellen is losgekoppeld van
bewerken.** De CISO stelt op, en Management stelt vast. Dat geldt voor het
publiceren van beleid, het activeren van een scope-versie, het accepteren van een
restrisico boven de drempel, het vaststellen van de risicocriteria en het
vastleggen van de directiebeoordeling. Een directielid dat mag goedkeuren, kan
daardoor het risicoregister niet herschrijven.

Een auditor vraagt naar precies dit soort scheiding. In dit systeem is die
scheiding geen afspraak, maar een grens in de software.

## Waar de gegevens staan

Elke installatie bedient één organisatie. De applicatie draait op een eigen
server, of als Docker-stack met twee containers (applicatie en database). De
bewijsstukken staan als bestand op de eigen schijf en niet bij een
dienstverlener. De back-up is daardoor de verantwoordelijkheid van de
organisatie zelf. Het systeem biedt tweefactorauthenticatie, een
uitnodigingsflow voor nieuwe gebruikers en een dagelijkse takenplanner die
vervallen accounts, bewaartermijnen en herinneringen afhandelt.

Aanmelden gebeurt met eigen accounts. Er is (nog) geen koppeling met een
identity-provider.

## Welk normprofiel: ISO 27001, NEN 7510 of BIO2

Het normprofiel wordt bij de installatie gekozen. Geen van de drie profielen is
een andere norm dan ISO 27001. Alle drie bouwen voort op ISO 27001, en dat
gebeurt op twee verschillende manieren.

**NEN 7510 breidt uit in de breedte.** NEN 7510 heeft dezelfde hoofdstukken 4 tot
en met 10 en dezelfde maatregelen, aangevuld met een handvol zorgspecifieke
maatregelen. Bij een deel van de bestaande maatregelen komt een aanvulling. In
dat profiel staan die extra maatregelen gewoon in de Verklaring van
Toepasselijkheid.

**De BIO2 breidt uit in de diepte.** Bijlage A blijft ongewijzigd en er komt geen
maatregel bij. Onder een groot deel van de maatregelen hangen wel genummerde
*overheidsmaatregelen*: de verplichte minimale invulling. ISO vraagt bijvoorbeeld
om beheer van toegangsrechten, en de BIO schrijft voor hoe vaak die rechten
worden beoordeeld. Dat profiel is bedoeld voor overheidsentiteiten die onder de
Cyberbeveiligingswet vallen. Het profiel is gericht op verantwoording aan de RDI
en niet op een certificaat.

Delen van de kennisbank beschrijven per profiel de specifieke kant. In de lijst
links staat onder *Naslag* een artikel "Wat … toevoegt" voor de norm die deze
installatie volgt.

De keuze ligt per installatie vast en is achteraf niet om te zetten.

## Wat dit systeem uitdrukkelijk niet is

Deze beperkingen zijn beter vooraf bekend dan bij de eerste audit.

- **Het systeem bevat de normtekst niet.** Bij elke maatregel staan het nummer,
  de titel en het thema, maar geen omschrijving. Normteksten zijn
  auteursrechtelijk beschermd. Een eigen samenvatting op de plek waar een auditor
  de toepasselijkheid beoordeelt, levert alleen discussie op. Een organisatie die
  de norm heeft gekocht, voert de teksten in één keer zelf in. De teksten blijven
  dan binnen de eigen installatie.
- **Het systeem geeft geen compliance-cijfer.** Er is geen functie die aangeeft
  dat de organisatie voor 87% aan de norm voldoet. Dat oordeel is aan de auditor.
- **Het systeem is geen adviesdienst.** Verwijzingen naar paragrafen zijn een
  hulpmiddel om te bepalen waar in het eigen exemplaar van de norm moet worden
  gekeken.
- **Het systeem is geen CMDB en geen servicedesk.** Zoals hierboven beschreven,
  staan assets en incidenten er vanuit het oogpunt van beveiliging in.
- **Het systeem doet het werk niet.** Een risicoregister vullen, beleid schrijven
  en bewijs verzamelen blijft mensenwerk. Het systeem voorkomt dat dat werk
  onvindbaar wordt.
- **Het systeem heeft geen SSO, ondersteunt geen meerdere organisaties in één
  installatie en heeft geen kant-en-klare koppelingen** met HR- of
  ticketsystemen. Het integratieregister legt vast welke koppelingen de
  organisatie heeft, maar legt die koppelingen niet aan.

Een volledige verantwoording staat in [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer). Wat nog openstaat en wat
bewust is weggelaten, staat in [Open punten](/kennisbank/open-punten).

## Wanneer dit systeem past

Dit systeem past bij een organisatie waarvoor het volgende geldt:

- het ISMS betreft één organisatie, en de gegevens moeten in eigen huis blijven;
- de organisatie werkt toe naar certificering of heeft die net behaald, en wil de
  bewijslast kunnen laten zien in plaats van reconstrueren;
- het is een kleine tot middelgrote organisatie waarin de CISO de spil is, met een
  directie die vaststelt en medewerkers die taken afwerken;
- de organisatie wil Nederlandstalige software, met de termijnen van de AVG en de
  Cyberbeveiligingswet ingebouwd in de incidentafhandeling;
- de organisatie bezit de norm of gaat die aanschaffen.

## Wanneer een ander systeem beter past

Een ander systeem past beter als een van de volgende punten geldt:

- de organisatie wil meerdere organisaties of klanten in één omgeving beheren;
- de organisatie wil geen server beheren en zoekt een clouddienst met een SLA;
- aanmelden via de eigen identity-provider (SSO) is een harde eis;
- de organisatie zoekt één systeem dat ook de CMDB en de servicedesk is;
- de organisatie verwacht dat de software vertelt wat de norm van haar eist.

## Wat de eerste maand kost

Installeren kost iemand met serverkennis een dag werk. Daarna volgt het invullen
van de scope, de assets, de risicocriteria, het risicoregister en de Verklaring
van Toepasselijkheid. Dat invullen is het echte werk, en dat werk is met of zonder
deze software nodig. Het voordeel is dat het invullen bij de opvolgingsaudit, een
jaar later, geen werk meer is.

De volgorde van het invullen is van belang, omdat de registers aan elkaar hangen
en een paar stappen door het management moeten worden vastgesteld. Zie [Van lege
installatie naar draaiend ISMS](/kennisbank/van-lege-installatie-naar-draaiend-isms).

Om te zien hoe een gevuld ISMS eruitziet zonder eerst alles in te voeren, kan een
beheerder een **aparte** installatie vullen met een compleet demoscenario. Dat
scenario beschrijft een fictief bedrijf met een samenhangende tijdlijn van
risico's, incidenten, audits en metingen. Het commando wist eerst de hele
database en hoort dus nooit te draaien op de omgeving met de echte gegevens. Zie
[Beheer: de artisan-commando's](/kennisbank/beheer).
