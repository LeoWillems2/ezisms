# Open punten, bedenkingen en ideeën

Elk systeem heeft een lijst met onderdelen die nog niet af zijn. De meeste
systemen tonen die lijst niet. Deze pagina toont hem wel, om twee redenen. Ten
eerste kan een organisatie niet sturen op wat zij niet weet. Ten tweede gaat een
auditor die zelf een gat vindt dat de organisatie had kunnen noemen, ook de rest
wantrouwen.

In deze kennisbank staan drie soorten punten door elkaar. Deze pagina haalt ze
uit elkaar:

- **Openstaande beslissingen.** Het systeem doet nu iets, maar de keuze is nog
  niet gemaakt. Deze beslissingen liggen bij de organisatie.
- **Bedenkingen.** Dit zijn bewuste beperkingen. Ze verdwijnen niet, en de
  organisatie hoort ze te kennen.
- **Ideeën.** Deze zijn bedacht en soms uitgewerkt, maar niet gebouwd.

Deze pagina is bijgewerkt op **20 augustus 2026**. De pagina wordt met de hand
onderhouden en is dus geen live overzicht. Een punt waarover is beslist, hoort
van deze pagina te verdwijnen.

## Beslissingen die nog open staan

**De Auditor kan niets zelf bevestigen.** De rol heeft `lezen` en `exporteren`,
maar geen `uitvoeren`. Daardoor kan een auditor geen leesbevestiging op een
beleidsdocument afgeven en de eigen training niet registreren. De CISO legt die
voltooiing voor de auditor vast. De gevolgen zijn groter dan ze lijken. Dezelfde
oorzaak sluit vijf schermen af: bewijsstukken, taken, beleid, incidenten en
*Mijn trainingen*. Die schermen vereisen `uitvoeren`, zodat een medewerker er kan
melden en uploaden. Een auditor zou in die registers alle rijen zien, maar kan
de schermen niet openen.

De keuze is of de huidige situatie blijft, of dat de rol `uitvoeren` krijgt. Een
auditor die niets kan schrijven, is verdedigbaar. De tweede optie is één regel in
de rechtenmatrix en raakt elke bestaande installatie. Zie [Gebruikers, rollen en
rechten](/kennisbank/gebruikers-rollen-en-rechten).

**Een nieuwe medewerker komt niet vanzelf in het proces.** Het systeem kent geen
indiensttreding, maar alleen een account dat op actief staat. De nachtelijke
takengeneratie kijkt niet naar de leeftijd van een account. Een nieuwe
medewerker in een doelgroep krijgt dus de volgende ochtend alle openstaande
leesbevestigingen. Daaraan zitten drie beperkingen:

- **Afdeling is optioneel bij het uitnodigen.** Een medewerker zonder afdeling
  valt buiten elke doelgroep. Die medewerker krijgt geen taak en geen knop om te
  bevestigen, en telt ook niet mee in de noemer van de bevestigingsgraad. Het
  document blijft dan op 100% staan, terwijl een medewerker buiten het proces
  valt. Sinds 26-08-2026 is dit zichtbaar. Het paneel **Leesbevestiging** op het
  dashboard toont bovenaan de documenten waarvan de bevestigingsplicht geen
  enkele afdeling raakt, en toont onder de lijst hoeveel actieve gebruikers geen
  afdeling hebben. Zichtbaar is echter niet hetzelfde als geborgd, want bij het
  uitnodigen mag de afdeling nog steeds leeg blijven.
- **De leestermijn loopt vanaf publicatie** en duurt dertig dagen. Voor zittend
  personeel is dat juist, omdat een taak die elke nacht opnieuw wordt aangeboden
  anders nooit zou verlopen. Voor een medewerker die later in dienst komt,
  betekent het dat de eerste taak vaak al over de deadline is op de dag dat de
  taak verschijnt.
- **Trainingsdoelgroepen werken met expliciet lidmaatschap** en niet met
  afdelingen. Een nieuwkomer krijgt geen enkele trainingstaak totdat iemand de
  nieuwkomer aan een doelgroep toevoegt.

De keuze is of dit een **procesafspraak** blijft of dat het systeem het hoort te
borgen. Een procesafspraak betekent: uitnodigen met afdeling, direct toevoegen
aan de juiste trainingsdoelgroep, en dat vastleggen in de onboardingprocedure van
de organisatie. Voor A.6.1 en A.6.3 is die route verdedigbaar, mits de afspraak
is opgeschreven. Het signaal voor actieve gebruikers zonder afdeling bestaat
inmiddels. De tweede route vraagt daarom nog twee ingrepen: een leestermijn die
loopt vanaf toetreding tot de doelgroep in plaats van vanaf publicatie, en
trainingsdoelgroepen die de afdelingsindeling volgen. Het eerste probleem valt op
het dashboard nu op als "over de leestermijn" zodra een nieuwkomer in dienst
komt.

**Wie is onafhankelijk genoeg om intern te auditen?** Het systeem heeft de
technische kant opgelost: de CISO verliest het schrijfrecht op de bevindingen van
een toegewezen interne auditor. Wie de organisatie daadwerkelijk als
onafhankelijk aanwijst, bijvoorbeeld een collega van een andere afdeling of een
externe partij, is een organisatorische keuze. Het systeem maakt die keuze niet
en kan haar ook niet toetsen.

**De AVG-behandeling van persoonsgegevens in het bewijs.** De bewaartermijn staat
op drie jaar, wat gelijk is aan één certificeringscyclus. Die termijn is een
**ondergrens voor de audit en geen vrijbrief voor de AVG**. Trainingscertificaten
met namen, incidentmeldingen met betrokkenen en leesbevestigingen zijn
persoonsgegevens met een eigen grondslag en een eigen bewaartermijn. Daarnaast
wordt gearchiveerd bewijs in dit model nooit definitief verwijderd. Of dat zo mag
blijven, vraagt om een AVG-toets die buiten dit systeem is gebleven. Het systeem
beperkt wel de verspreiding. Documenten die de organisatie verlaten, zoals de
schermkopie en de uitvoer van `isms:exporteer`, bevatten initialen plus rol in
plaats van namen. Dat beantwoordt de vraag over de bewaring echter niet.

**De databasegrant op de audit trail.** De trail is append-only in de applicatie,
omdat het model elke wijziging weigert. Dat is een vangnet tegen
programmeerfouten en geen beveiligingsmaatregel. Iemand met directe toegang tot
de database omzeilt het. De echte maatregel is een grant: het applicatieaccount
krijgt `INSERT` en `SELECT` op `audit_logregels`, maar geen `UPDATE` of `DELETE`.
Die grant is een inrichtingsstap bij het opzetten van de omgeving, en het is
precies het punt waarop een auditor doorvraagt. Sinds 3 augustus 2026 wordt zo'n
wijziging wel *gevonden*, omdat elke logregel de hash van de voorgaande regel
bevat. Vinden is echter niet verhinderen, en de grant blijft dus nodig. Zie [De
audit trail](/kennisbank/de-audit-trail).

**Wie bewaart de kophash?** De hashketen detecteert een onopgemerkte wijziging,
maar niet een wijziging waarbij iemand de hele keten opnieuw berekent. Alleen een
oudere kophash die buiten dit systeem is bewaard, weerlegt zo'n wijziging. De
kopie voor de auditor van het audit-trailscherm bevat die hash. Het anker ontstaat
dus zodra een auditor zo'n kopie meeneemt, maar het systeem kan dat niet
afdwingen. De open vraag is of de organisatie dit bij een gewoonte laat of er een
afspraak van maakt: bij elke audit een kopie, met de vorige kopie ernaast.

**Periodieke wachtwoordwijziging.** De minimale lengte is vastgesteld op 12
tekens, en complexiteitseisen zijn bewust weggelaten (3 augustus 2026). Er is
nog niet besloten of een wachtwoord na verloop van tijd moet verlopen. Het
systeem dwingt dat nu niet af. Dat is de verdedigbare instelling, omdat verplicht
wijzigen vooral kleine varianten op een oud wachtwoord oplevert. Sommige auditors
vragen er echter nog steeds naar. Een gebruiker kan een vergeten wachtwoord zelf
herstellen, dus daarvoor is de CISO niet nodig.

## Bedenkingen: wat het systeem bewust niet doet

Deze punten zijn geen achterstand. Ze zijn met een reden zo gekozen. De
organisatie hoort ze wel te kennen voordat zij erop vertrouwt.

**Een toetsresultaat is een registratie van awareness en geen examenbewijs.** De
toets wordt in de browser nagekeken. De antwoorden staan in het bestand, en de
terugmeld-URL staat in de taak van de deelnemer zelf. Een deelnemer die die link
gebruikt zonder de toets te openen, staat op geslaagd. Het resultaat toont aan
dat het programma loopt en wie eraan deelnam, maar niet dat iemand de stof
beheerst. Een manipulatiebestendige toets vereist nakijken op de server, met de
vragen en antwoorden in de ISMS-database. Dat is een ander en groter blok. Sinds
19 augustus 2026 is de andere kant wel afgesloten: een toets kan niets meer bij
derden ophalen (zie de tabel onderaan).

**De vijfde attribuutdimensie van ISO 27002 ontbreekt.** Beveiligingscapaciteiten
staan alleen in de norm zelf en zijn niet meegeleverd. Een organisatie die de
norm bezit, mag ze in de eigen installatie invullen. `php artisan
isms:capaciteiten aan` is daarvoor de ondersteunde route. De andere vier
dimensies zijn een eigen uitgangspunt dat de organisatie hoort te overschrijven.
Zie [Maatregelclassificatie](/kennisbank/maatregelclassificatie) en
[Verantwoording en disclaimer](/kennisbank/verantwoording-en-disclaimer).

**Er is geen readiness-cijfer.** Dat cijfer is ontworpen en weer ingetrokken. Eén
samengesteld getal vraagt om een weging, en die weging bepaalt welke slechte KPI
wegvalt tegen welke goede KPI. De vraag waar het tekortschiet, wordt per KPI
beantwoord, tegen een norm die de organisatie zelf heeft vastgesteld.

**De meethistorie begint bij de ingebruikname.** Het systeem reconstrueert het
verleden niet: de eerste maandmeting valt in de eerste maand dat het systeem
draait. Bij een installatie die al vóór 3 augustus 2026 draaide, missen de
koppelingen van vóór die datum bovendien een datum en een naam, omdat de
vastlegging daarvan pas toen is toegevoegd. Zie [KPI's en
meetwaarden](/kennisbank/kpis-en-meetwaarden) voor wat dat wel en niet betekent.

**Er is geen delegatie, geen vier-ogenprincipe op persoonsniveau en geen
rollenbeheer in de gebruikersinterface.** De rechtenmatrix is referentiedata. Een
rol wijzigen is een codewijziging plus een deploy, en geen instelling in het
scherm. Functiescheiding werkt op rollen en niet op personen. Een persoon met
beide rollen stelt dus zowel op als vast. Het gebruikersoverzicht toont alle
rollen per persoon, zodat een auditor die combinatie zelf kan beoordelen.

**Eén installatie bedient één organisatie met eigen accounts.** Het rechtenmodel
kent geen scheiding tussen organisaties, en er is geen SSO. Voor twee
organisaties zijn twee installaties nodig. Beide functies zijn uitbreidingen van
de integratielaag en staan nu niet gepland.

**Anoniem melden is niet mogelijk.** Een incident wordt op naam gemeld. Als daar
behoefte aan blijkt, is dat een eigen uitbreiding, omdat anoniem melden de hele
opvolgingsketen raakt en niet alleen het formulier.

**Meldingen gaan alleen per e-mail.** Er zijn geen meldingen in de applicatie en
geen koppelingen met chat of ticketing. Concrete integraties (HR-systeem,
ticketing, vulnerability scanner) hebben bewust geen prioriteit. De
integratielaag blijft abstract totdat er een concrete behoefte is. Zie
[Integraties: welke norm-eis onderbouwt het
register?](/kennisbank/integraties-en-normeis).

**Een leverancier heeft één risiconiveau.** Het voorstel om de kans×impact-matrix
uit het risicoregister te hergebruiken is nooit bevestigd, en is daarom niet
gebouwd. Om een leveranciersrisico op die matrix te beoordelen, legt de
organisatie een risico aan en koppelt zij de leverancier aan dat risico.

**De schermkopie is beschikbaar op zes schermen.** De knop *Kopie voor de
auditor* staat op de SoA, de audit trail, het risicoregister, de
tolerantiematrix, de afwijkingen en het bevindingenregister. Andere schermen
volgen. Tot die tijd is [`isms:exporteer`](/kennisbank/beheer) het alternatief
als de auditor meer wil meenemen.

**Er is geen PDF en geen deelbare link.** De kopie is een Word-document. Er is
geen URL waarmee een auditor zonder account kan meekijken. Zo'n link zou
onbevoegde toegang geven met een token als enige beveiliging. Dat verdient een
eigen afweging en hoort geen bijproduct te zijn.

**Het paginanummer wordt pas in de lezer ingevuld.** Onderaan elke pagina staat
"n van N". Het totaal wordt niet door EzISMS berekend, maar door Word of
LibreOffice bij het openen of afdrukken. Het systeem weet niet op hoeveel
pagina's een document uitkomt. In een viewer die zulke velden niet berekent,
zoals een snelle preview in een browser of e-mailprogramma, kan het getal
daarom leeg blijven. Openen in Word of afdrukken lost dat op. Het alternatief was
een vast getal invullen, maar dat getal zou op elke pagina behalve één onjuist
zijn.

**De uitlevering gaat uit van een proxy die TLS termineert.** De applicatie
serveert zelf geen HTTPS. Het certificaat staat in een reverse proxy vóór de
applicatie, en de applicatie leidt uit een header van die proxy af dat de
verbinding beveiligd was. Dat is een aanname en geen instelling. Het punt valt in
dezelfde categorie als de databasegrant hierboven: een inrichtingsstap die de
applicatie zelf niet kan afdwingen. Bij een installatie op één machine zonder
zo'n proxy moet die header worden verwijderd. Zonder proxy die de header
overschrijft, kan een bezoeker de header zelf meesturen, en beschouwt de
applicatie een onversleutelde HTTP-verbinding als beveiligd. Het plan om hiervan
een keuze te maken ligt klaar (zie hieronder).

## Ideeën die klaarliggen

Deze ideeën zijn bedacht en soms uitgewerkt, maar niet gebouwd. Ze staan hier
zodat ze niet opnieuw bedacht hoeven te worden.

- **De schermkopie op de overige schermen.** Het mechanisme bestaat en wordt per
  scherm uitgebreid. Per scherm moet worden bepaald welke kolommen in de kopie
  horen en of de kopie dezelfde beperking op records krijgt als het scherm.
- **TLS als instelling in plaats van als aanname.** Dit idee is uitgewerkt als
  één sleutel met drie standen: een proxy vóór de applicatie (de huidige
  situatie), een certificaat in de applicatie zelf, of onversleuteld HTTP voor
  een afgeschermd netwerk. Het is niet gebouwd, omdat elke bestaande opstelling
  de eerste stand gebruikt.
- **Toetsherhaling via een taaksjabloon.** Voor modules loopt herhaling al via de
  geldigheidsduur. Losse toetsen zet de CISO nu met de hand uit. Jaarlijkse
  herhaling raakt de takengenerator en vraagt daarom om een aparte beslissing.
  A.6.3 verwacht terugkerende awareness.
- **Antwoorden per vraag bewaren.** Nu worden alleen de score en het totaal
  bewaard. Inzicht in welke vraag structureel fout wordt beantwoord, zou de
  lesstof verbeteren. Het maakt de registratie echter gevoeliger voor
  persoonsgegevens, en dat is de afweging.
- **Delegatie aan proceseigenaren.** Het datamodel ondersteunt al meerdere rollen
  per gebruiker, en assets hebben al een veld voor de verantwoordelijke en een
  veld voor de eigenaar. De logische volgende stap is die velden te koppelen aan
  rechten: de verantwoordelijke mag muteren en de eigenaar mag goedkeuren. Die
  stap wordt relevant zodra één CISO het werk niet meer alleen doet.
- **Een claimsregister voor beweringen die van de norm zijn afgeleid.** Dit
  systeem is gebouwd zonder de normtekst mee te leveren. Het werkelijke risico
  daarvan is niet dat er iets ontbreekt, maar dat een juiste en een verzonnen
  bewering even stellig lijken. Een register met elke van de norm afgeleide
  bewering, met bron en zekerheid, maakt daar één verificatieronde van. Die ronde
  wordt uitgevoerd door iemand die de norm bezit, en vervangt blijvende twijfel.
  Zie [Verantwoording en
  disclaimer](/kennisbank/verantwoording-en-disclaimer).

## Besloten: deze punten hoeven niet opnieuw

De volgende punten zijn besloten. Deze tabel voorkomt dat ze telkens terugkomen
met de vraag of er nog iets mee moet gebeuren.

| Punt | Besluit |
| --- | --- |
| Externe bronnen in een toetsbestand | **Herzien op 19 augustus 2026.** Het besluit van 30 juli stond toe dat toetsen bronnen van een CDN laadden. Dat besluit gold toen toetsen alleen intern werden gebruikt. Van elke deelnemer gingen het IP-adres en de browsergegevens naar die partijen. Sindsdien geldt een bronbeperking op het punt waar een toets het systeem verlaat: alleen wat in het bestand zelf staat, wordt geladen. Nieuwe toetsen krijgen eenvoudige opmaak en systeemlettertypen. Het bestaande OWASP-materiaal is eenmalig omgezet en ziet er hetzelfde uit. |
| Externe bronnen in de applicatie zelf | Hetzelfde besluit geldt hier. Lettertypen en scripts komen sinds 18 augustus 2026 van de eigen server. Geen enkel scherm haalt bij het openen nog iets op bij een derde partij. |
| Meetfrequentie van de KPI's | De meting is maandelijks en wordt onveranderlijk vastgelegd. Er is geen dagelijkse snapshot. |
| Bewaartermijn bewijs | De termijn is drie jaar, gelijk aan één certificeringscyclus. De AVG-kanttekening hierboven blijft van toepassing. |
| Readiness-score | Deze score is ingetrokken, zie hierboven. |
| Toetsbestanden zijn publiek bereikbaar | Dit is bewust zo en blijft zo. De token zorgt voor de beveiliging, niet de onvindbaarheid van het bestand. Sinds 11 augustus 2026 staan de bestanden niet meer in de webmap, maar worden ze door de applicatie geserveerd in een afgeschermde omgeving. Dat verandert niets aan wie erbij kan, maar wel aan wat een toets kan. |
| Tweefactor bij de uitnodiging | Een nieuwe gebruiker koppelt de authenticator-app direct bij het instellen van het wachtwoord (3 augustus 2026). De respijtperiode geldt alleen voor accounts die al bestonden. |
| Keten-hashing van de audit trail | Gebouwd op 3 augustus 2026. Wat nog ontbreekt, staat hierboven bij *Wie bewaart de kophash?* |
| Routes voor alle soorten wijzigingen | Geleverd op 12 augustus 2026. Elk van de vijf soorten (leveranciersrelease, configuratie, infrastructuur, ingebruikname, afvoer) heeft een sjabloon. Daardoor is geen enkele soort meer alleen via de spoedroute te kiezen. |

## Wat hier niet op hoort

Deze pagina gaat over het **systeem** en niet over het managementsysteem van de
organisatie. Een tekortkoming in het eigen ISMS hoort in het systeem zelf thuis.
Voorbeelden zijn een maatregel die nog niet is geïmplementeerd, een risico zonder
behandelplan en een auditbevinding. Zo'n tekortkoming wordt vastgelegd als
afwijking, als corrigerende maatregel of als verbeteractie uit de
directiebeoordeling. Daar krijgt zij een eigenaar en een deadline, en die krijgt
zij op deze pagina niet.
