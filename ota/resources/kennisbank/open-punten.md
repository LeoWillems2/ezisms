# Open punten, bedenkingen en ideeën

Elk systeem heeft een lijst met dingen die nog niet af zijn. De meeste systemen
laten die lijst niet zien. Hier staat hij, om twee redenen: u kunt niet sturen op
wat u niet weet, en een auditor die zelf een gat vindt dat u had kunnen noemen,
gaat de rest ook wantrouwen.

Drie soorten staan door elkaar in deze kennisbank, dus hier uit elkaar gehaald:

- **Openstaande beslissingen** — het systeem doet nu iets, maar de keuze is niet
  genomen. Deze horen bij u.
- **Bedenkingen** — bewuste beperkingen. Ze gaan niet weg; u moet ze kennen.
- **Ideeën** — bedacht, soms uitgewerkt, niet gebouwd.

Bijgewerkt op **12 augustus 2026**. Deze pagina wordt met de hand onderhouden en
is dus geen live overzicht: wordt een punt beslist, dan hoort het hier weg.

## Beslissingen die nog aan u zijn

**De Auditor kan niets zelf bevestigen.** De rol heeft `lezen` en `exporteren`,
maar geen `uitvoeren`. Gevolg: een auditor kan geen leesbevestiging op een
beleidsdocument afgeven en zijn eigen training niet registreren — de CISO legt
die voltooiing voor hem vast. Er hangt meer aan vast dan het lijkt: dezelfde
oorzaak sluit vijf schermen af (bewijsstukken, taken, beleid, incidenten en mijn
trainingen), omdat die op `uitvoeren` staan zodat een medewerker er kan melden en
uploaden. De auditor ziet in die registers wél alle rijen zodra hij er is — hij
komt er alleen niet.

De keuze: laat u het zo (een auditor die niets kan schrijven is verdedigbaar), of
krijgt de rol `uitvoeren`? Het tweede is één regel in de rechtenmatrix en raakt
elke bestaande installatie. Zie [Gebruikers, rollen en
rechten](/kennisbank/gebruikers-rollen-en-rechten).

**Wie is onafhankelijk genoeg om intern te auditen?** Het systeem heeft de
technische kant opgelost: de CISO verliest zijn schrijfrecht op de bevindingen
van een toegewezen interne auditor. Wie de organisatie daadwerkelijk als
onafhankelijk aanwijst — een collega van een andere afdeling, iemand van buiten —
is een organisatorische keuze die het systeem niet voor u maakt en ook niet kan
toetsen.

**De AVG-behandeling van persoonsgegevens in het bewijs.** De bewaartermijn staat
op drie jaar, één certificeringscyclus. Dat is een **ondergrens voor de audit,
geen vrijbrief voor de AVG**: trainingscertificaten met namen, incidentmeldingen
met betrokkenen en leesbevestigingen zijn persoonsgegevens met een eigen
grondslag en een eigen bewaartermijn. Gearchiveerd bewijs wordt in dit model
bovendien nooit hard verwijderd. Of dat zo mag blijven, vraagt een AVG-toets die
buiten dit systeem is gebleven.

**De databasegrant op de audit trail.** De trail is append-only in de
applicatie — het model weigert elke wijziging — maar dat is een vangnet tegen
programmeerfouten, geen beveiligingsmaatregel. Wie rechtstreeks op de database
kan, omzeilt het. De echte maatregel is een grant: het applicatieaccount krijgt
`INSERT` en `SELECT` op `audit_logregels`, geen `UPDATE` of `DELETE`. Dat is een
inrichtingsstap bij het opzetten van de omgeving, en het is precies het punt
waarop een auditor doorvraagt. Sinds 3 augustus 2026 wordt zo'n wijziging wél
*gevonden* — elke logregel draagt de hash van zijn voorganger — maar vinden is
niet verhinderen, en de grant blijft dus nodig. Zie [De audit
trail](/kennisbank/de-audit-trail).

**Wie bewaart de kophash?** De keten hierboven vangt de onopgemerkte wijziging,
maar niet iemand die de héle keten opnieuw uitrekent. Wat dát weerlegt is een
oudere kophash die buiten dit systeem ligt. De kopie voor de auditor van het
audit-trailscherm draagt die hash, dus het anker ontstaat zodra een auditor er
één meeneemt — maar het systeem kan dat niet afdwingen. De vraag die openstaat is
of u het bij die gewoonte laat, of er een afspraak van maakt: elke audit een
kopie, en de vorige ernaast.

**Periodieke wachtwoordwijziging.** De lengte is vastgesteld op 12 tekens en
complexiteit is bewust achterwege gelaten (3 augustus 2026). Wat niet is
besloten: of een wachtwoord na verloop van tijd moet verlopen. Het systeem
dwingt dat nu niet af, en dat is de verdedigbare stand — verplicht wijzigen
levert vooral kleine varianten op een oud wachtwoord op — maar sommige auditors
vragen er nog steeds naar. Een vergeten wachtwoord kan de gebruiker zelf
herstellen, dus daar is de CISO niet voor nodig.

## Bedenkingen: wat het systeem bewust niet doet

Deze punten zijn geen achterstand. Ze zijn zo gekozen, met een reden — maar u
moet ze kennen voordat u erop leunt.

**Een toetsresultaat is awareness-registratie, geen examenbewijs.** De toets
wordt in de browser nagekeken; de antwoorden staan in het bestand en de
terugmeld-URL staat in de taak van de deelnemer zelf. Wie die link gebruikt
zonder de toets te openen, staat op geslaagd. Het toont aan *dát* het programma
loopt en wie eraan deelnam, niet dat iemand de stof beheerst. Manipulatievast
maken vraagt nakijken op de server, met de vragen en antwoorden in de
ISMS-database — dat is een ander en groter blok.

**De vijfde attribuutdimensie van ISO 27002 ontbreekt.** Beveiligingscapaciteiten
staan alleen in de norm zelf; ze zijn niet meegeleverd. De andere vier zijn een
eigen uitgangspunt dat u hoort te overschrijven. Zie
[Maatregelclassificatie](/kennisbank/maatregelclassificatie) en
[Verantwoording en disclaimer](/kennisbank/verantwoording-en-disclaimer).

**Er is geen readiness-cijfer.** Dat is ontworpen en ingetrokken: één
samengesteld getal vraagt een weging, en die weging bepaalt welke slechte KPI
wegvalt tegen welke goede. De vraag *waar schort het* wordt per KPI beantwoord,
tegen een norm die u zelf heeft vastgesteld.

**Meethistorie begint bij de ingebruikname.** Er is geen reconstructie van het
verleden. Voor koppelingen die vóór 3 augustus 2026 zijn gelegd is er bovendien
geen datum en geen naam. Zie [KPI's en
meetwaarden](/kennisbank/kpis-en-meetwaarden) voor wat dat wel en niet betekent.

**Geen delegatie, geen vier-ogen op persoonsniveau, geen rollenbeheer in de UI.**
De rechtenmatrix is referentiedata: een rol wijzigen is een codewijziging plus
deploy, geen klik. Functiescheiding zit op rollen, niet op personen — wie beide
rollen heeft, stelt op én stelt vast. Het gebruikersoverzicht toont alle rollen
per persoon, zodat een auditor die combinatie zelf kan wegen.

**Eén organisatie, eigen accounts.** Er is geen scheiding tussen organisaties in
het rechtenmodel en geen SSO. Beide zijn uitbreidingen van de integratielaag en
staan nu niet gepland.

**Anoniem melden kan niet.** Een incident wordt op naam gemeld. Als daar behoefte
aan blijkt, is het een eigen uitbreiding — anoniem melden raakt de hele
opvolgingsketen, niet alleen het formulier.

**Alleen e-mail.** Er zijn geen in-app-meldingen en geen koppeling met chat of
ticketing. Concrete integraties (HR-systeem, ticketing, vulnerability scanner)
zijn bewust niet geprioriteerd; de integratielaag blijft abstract tot er een
echte behoefte is. Zie [Integraties: welke norm-eis onderbouw je
ermee?](/kennisbank/integraties-en-normeis).

**Een leverancier heeft één risiconiveau.** Het voorstel om de kans×impact-matrix
uit het risicoregister te hergebruiken is nooit bevestigd, en zo is het ook niet
gebouwd. Wilt u een leveranciersrisico op die matrix beoordelen, dan legt u een
risico aan en koppelt u de leverancier eraan.

**De schermkopie zit op vijf schermen.** De knop *Kopie voor de auditor* staat op
de SoA, de audit trail, het risicoregister, de tolerantiematrix en de
afwijkingen. Andere schermen volgen; tot die tijd is
[`isms:exporteer`](/kennisbank/beheer) het alternatief als de auditor meer wil
meenemen.

**Geen PDF, geen deelbare link.** De kopie is een Word-document, en er is geen
URL die u een auditor stuurt zodat hij zonder account meekijkt. Dat laatste is
onbevoegde toegang met een token als enige slot; dat verdient een eigen
afweging, niet een bijvangst.

**Het paginanummer vult zich pas in de lezer.** Onderaan elke pagina staat "n van
N". Dat totaal wordt niet door EzISMS berekend maar door Word of LibreOffice, bij
het openen of afdrukken — wij weten niet op hoeveel pagina's een document
uitkomt. In een viewer die zulke velden niet uitrekent (een snelle preview in een
browser of in de mail) kan het getal daarom leeg blijven. Openen in Word of
afdrukken lost het op. Een vast getal invullen was het alternatief, maar dat zou
op elke pagina behalve één onwaar zijn.

## Ideeën die klaarliggen

Bedacht en soms uitgewerkt, niet gebouwd. Ze staan hier zodat ze niet twee keer
bedacht hoeven te worden.

- **De schermkopie op de overige schermen.** Het mechanisme staat; per scherm is
  het bepalen welke kolommen erin horen en of de kopie dezelfde
  record-beperking krijgt als het scherm.
- **Toetsherhaling via een taaksjabloon.** Voor modules loopt herhaling al via de
  geldigheidsduur; losse toetsen zet de CISO nu met de hand uit. Jaarlijkse
  herhaling raakt de takengenerator en is daarom apart te beslissen. A.6.3
  verwacht terugkerende awareness.
- **Antwoorden per vraag bewaren.** Nu worden alleen score en totaal bewaard.
  Welke vraag structureel fout gaat, zou de lesstof verbeteren — maar het maakt
  de registratie gevoeliger voor persoonsgegevens, en dat is de afweging.
- **Delegatie aan proceseigenaren.** Het datamodel staat meerdere rollen per
  gebruiker al toe, en assets hebben al een verantwoordelijke en een
  eigenaar-veld. Die velden koppelen aan echte rechten (verantwoordelijke mag
  muteren, eigenaar mag goedkeuren) is de logische volgende stap zodra één CISO
  het niet meer alleen doet.
- **Een claimsregister voor norm-afgeleide beweringen.** Dit systeem is gebouwd
  zonder de normtekst mee te leveren, en het echte risico daarvan is niet dat er
  iets ontbreekt maar dat een juiste en een verzonnen bewering er even stellig
  uitzien. Een register waarin elke norm-afgeleide bewering staat met bron en
  zekerheid, maakt daar één verificatieronde van door iemand die de norm wél
  bezit — in plaats van blijvende twijfel. Zie [Verantwoording en
  disclaimer](/kennisbank/verantwoording-en-disclaimer).

## Besloten — deze hoeven niet opnieuw

Om te voorkomen dat ze telkens terugkomen als "moeten we daar nog iets mee?":

| Punt | Besluit |
| --- | --- |
| Externe assets in het toetssjabloon | Blijft. De toets is losstaand lesmateriaal, geen onderdeel van de applicatie zelf. |
| Meetfrequentie van de KPI's | Maandelijks, met de meting onveranderlijk vastgelegd. Geen dagelijkse snapshot. |
| Bewaartermijn bewijs | Drie jaar, één certificeringscyclus — met de AVG-kanttekening hierboven. |
| Readiness-score | Ingetrokken, zie hierboven. |
| Toetsbestanden zijn publiek bereikbaar | Bewust, en dat blijft: de token doet het beveiligingswerk, niet de onvindbaarheid van het bestand. Sinds 11 augustus 2026 staan de bestanden niet meer in de webmap maar worden ze door de applicatie uitgeserveerd, in een afgeschermde omgeving. Dat verandert niets aan wie erbij kan, wel aan wat een toets kan. |
| Tweefactor bij de uitnodiging | Een nieuwe gebruiker koppelt zijn app meteen bij het instellen van zijn wachtwoord (3 augustus 2026). De respijtperiode is er voor de accounts die er al wáren. |
| Keten-hashing van de audit trail | Gebouwd op 3 augustus 2026. Wat er nog niet is, staat hierboven bij *Wie bewaart de kophash?* |

## Wat hier níét op hoort

Deze pagina gaat over het **systeem**, niet over uw managementsysteem. Een
tekortkoming in uw eigen ISMS — een maatregel die nog niet is geïmplementeerd,
een risico zonder behandelplan, een auditbevinding — hoort thuis in het systeem
zelf: als afwijking, als corrigerende maatregel of als verbeteractie uit de
directiebeoordeling. Daar krijgt het een eigenaar en een deadline, en die krijgt
het hier niet.
