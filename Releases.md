# Releases

De uitgaven van EzISMS, nieuwste bovenaan. Samengesteld uit de git-tags; die
blijven de bron, dit bestand is de leesbare vorm ervan.

**Versienummers.** De eerste cijferreeks springt bij een breuk in wat het product
ís — V2.0.0 markeert het punt waarop de repo uitleverbaar werd. Het tweede cijfer
telt uitgaven met nieuwe functionaliteit, het derde is voor correcties op een
uitgave die al buiten staat. Tot nu toe is er één zo'n correctie geweest: V2.2.1.

Elke uitgave is een annotated tag; `git tag -l --format='%(contents)' V2.9.0`
geeft de oorspronkelijke tekst.

---

## V3.3.0 — een kennisbank die zichzelf uitlegt

*11-09-2026*

De kennisbank telt inmiddels bijna dertig artikelen, en wie hem voor het eerst
opende kreeg het bovenste te zien zonder te weten wat er verder in stond. De
linkerkolom gaf titels, geen antwoord op de vraag welk artikel je nú nodig hebt.

**Er is een Leeswijzer, en die staat vooraan.** Eén pagina met elk artikel in een
of twee regels, gegroepeerd zoals de linkerkolom. Omdat de kennisbank zonder slug
op het eerste artikel opent, is dat overzicht voortaan het eerste wat je ziet.

**De volgorde volgt nu de vulvolgorde.** De categorieën stonden in de volgorde
waarin ze ooit zijn bijgeschreven; ze lopen nu mee met de vier fasen uit *Van lege
installatie naar draaiend ISMS* — eerst het fundament, dan het kader en de inhoud,
dan het ritme, en daarna wat geen harde voorganger heeft. Naslag staat bovenaan,
want dat is waar iemand begint die het systeem nog niet kent. Dezelfde volgorde
geldt voor de Leeswijzer en voor de linkerkolom: ze komen uit één register.

De Leeswijzer bestaat per normprofiel in een eigen uitvoering, want welke
artikelen er zijn verschilt per profiel. Een test bewaakt dat de lijst in elk
profiel precies de zichtbare artikelen dekt — een nieuw artikel dat er niet in
belandt, en een verwijzing naar een artikel dat in dat profiel niet bestaat, laten
de suite vallen.

---

## V3.2.0 — de audit die zichzelf kan verantwoorden

*09-09-2026*

Eén vraag van een externe auditor liep door dit hele blok heen: *hoe weet ik dat u
alles hebt bekeken?* Het systeem kon daar niet op antwoorden.

**De normatieve scope zei wat de bedoeling was, niet wat er is gebeurd.** Een
control die was bekeken en in orde bevonden, was niet te onderscheiden van een
control waar niemand naar had gekeken: het enige spoor van "in orde" was de
*afwezigheid* van een bevinding. Elk object in de scope van een ronde draagt nu
zijn eigen afhandeling — geen opmerkingen, of niet aan toegekomen met de reden
erbij — en de knopjes in het rondedossier kleuren mee: groen behandeld, oranje een
bevinding, rood een gat, grijs nog niets. Daaronder staat de zin die de auditor
zoekt: "9 van de 12 behandeld".

Dat "er zit hier een bevinding" is bewust géén waarde die iemand zelf zet: hij
volgt uit de bevindingen, zodat de twee elkaar nooit kunnen tegenspreken. Om
dezelfde reden verwijst een bevinding niet langer naar een beheersmaatregel maar
naar een auditobject — en kan zij eindelijk óók over de hoofdtekst H4–H10 gaan.
Loopt de auditor tijdens een interview tegen iets buiten de scope aan, dan groeit
de scope mee, herkenbaar gemarkeerd.

**De dekkingsmatrix telt vanaf nu de behandeling en niet de planning.** In de
scope staan is geen dekking meer; dat is het verschil tussen "we waren het van
plan" en "we hebben ernaar gekeken". Een object waar de ronde niet aan toekwam
blijft dus een gat in de matrix, met de reden in het dossier.

**Afronden blokkeert niet, maar dwingt wel een uitspraak af.** "Niet aan
toegekomen" is een reëel auditresultaat — het interview ging niet door, de
beheerder was ziek. Wie dat moet wegpoetsen om te kunnen afronden, vult "geen
opmerkingen" in over iets dat hij nooit heeft bekeken. Bij het afronden vraagt het
scherm daarom per grijs object een reden en gaat dan door.

**Elke constatering noemt zijn bron.** Bij een bevinding en bij een behandeld
object staat nu met wie erover is gesproken — verplicht, want een constatering die
niemand kan navragen is niet na te lopen. Kwam het uit eigen onderzoek in plaats
van uit een gesprek, dan is *eigen waarneming* het antwoord: een nagelezen
procedure is een bron, een verzonnen naam niet.

**En sluiten vraagt wat er is gebeurd.** `gesloten door X op Y` was de hele
verantwoording van een observatie die dicht ging; bij een non-conformiteit staat
het antwoord in de afwijking eronder, maar een observatie of verbeterkans liet
niets na. Sluiten vraagt nu een afhandelingsnotitie. Dat sluiten ná het afronden
van de ronde kan, blijft zo: afronden bevriest het oordeel van de auditor, de
opvolging loopt daarna nog maanden door — en de ronde openhouden tot dat klaar is,
zou de bevindingen al die tijd bewerkbaar laten.

**Een auditronde is als Word-document mee te geven.** De knop "Kopie voor de
auditor" stond al op zes registers; de rondepagina is het eerste dossier dat hem
krijgt. Het document opent met de kenmerken van de ronde — status, uitvoerder,
scope, dekkingsvlag en de titels van het gekoppelde bewijs — daarna de normatieve
scope met per object de afhandeling en de bron, en de bevindingen als bijlage.
Personen staan er als initialen met hun rol, zoals in elk auditdocument hier.

**En van elke meegegeven schermkopie staat nu de sha256 vast.** Het register op
`/schermkopieen` hield al bij welk scherm met welke filters de deur uit ging; wat
het niet kon beantwoorden is de vraag die er in een geschil toe doet — iemand legt
een Word-document op tafel en zegt dat het uit dit ISMS komt. Met de vingerafdruk
erbij is dat na te lopen. De kopieën zelf worden nog steeds niet bewaard: uit een
hash valt niets te reconstrueren.

**Een auditcyclus is nu ook zonder de commandoregel op te zetten.** Wie geen
shell-toegang heeft, of een cyclus wil die van het standaardpatroon afwijkt, liep op
drie plekken vast. Het jaartal van een auditplan is een label geworden en geen
sleutel meer — meerdere plannen in hetzelfde kalenderjaar mogen, wat in de
opstartfase eerder regel dan uitzondering is — en de plankop noemt daarom voortaan
zijn cyclus en programmajaar. Het programmascherm kan zelf een jaarplan toevoegen,
meteen op het eerstvolgende vrije programmajaar, en een programma zonder jaarplannen
zegt dat het er geen heeft in plaats van er compleet uit te zien. En het startjaar
per dekkingsregel is instelbaar geworden, met een knop die de hoofdstukken en thema's
over de cyclusjaren verdeelt volgens dezelfde verdeling die het commando gebruikt —
één definitie, zodat de dekkingsplanning niet afhangt van de weg waarlangs zij is
ontstaan.

Verder: de inlogpagina noemt de installatie waarop u inlogt (organisatie en norm,
allebei stamgegevens), en het kennisbankartikel over de interne audit beschrijft nu
ook hoe u zo'n cyclus stap voor stap opzet.

---

## V3.1.0 — SQLite, en drie signalen die ontbraken

*29-08-2026*

Eén nieuwe manier om het ISMS te draaien, en drie plekken waar het systeem iets
wist maar niets zei.

**Het ISMS kan nu op één bestand draaien.** Naast de bestaande stack met MySQL
staat er een tweede: `compose-sqlite.yml`, één container, de hele database in
`data/app/database/ezisms.sqlite`. Geen databasecontainer, geen wachtwoorden om
te verzinnen of te bewaren, en een back-up die één bestand is. Voor een
organisatie van enkele tientallen mensen is dat genoeg, en `LEESMIJ.md` noemt
deze variant daarom als eerste; MySQL is §1b geworden, met erbij wanneer die de
betere keuze is. Wie op MySQL draait hoeft niets te doen — de bestandsnamen zijn
met opzet niet omgedraaid, want `compose.yml` wordt bij elke upgrade opnieuw
gekopieerd en een naam die van betekenis verandert is geen goede naam.

Het image bedient beide varianten en de uitrolketen is gedeeld; alleen het
compose-bestand en het `.env`-voorbeeld zijn eigen. Twee dingen bewaken de fout
die geld kost: `installatie/db-variant` blokkeert een compose-bestand van de
andere variant op bestaande gegevens, en de database staat op de hostmap en niet
in de applicatieboom, die bij een upgrade vervangen wordt.

**Op SQLite is een `enum` een tekstveld zonder controle.** Dat is geen detail
voor een ISMS: 75 kolommen — statussen, ernstniveaus, auditacties — werden op
MySQL door de database bewaakt en zouden op SQLite alles slikken. De toegestane
waarden staan nu in de code, worden op de statusmachines afgedwongen, en een
toets draait de volledige migratieset om te controleren dat het register er
precies op past. Een nieuwe enum zonder waardenlijst laat de suite vallen.

**Een uitnodiging kwam nooit aan als er geen mailserver was.** `MAIL_MAILER=log`
is de standaardwaarde, en in een afgeschermd netwerk blijft dat zo. De verzending
sláágde dan — het log-transport klaagt niet — dus meldde het scherm "Uitnodiging
verstuurd" terwijl er niets uitging. De CISO krijgt nu een bestand om zelf uit te
reiken, en de lijst zegt "Nog uitnodigen" zolang dat niet gebeurd is. Dat laatste
signaal ontbrak ook als een échte mail faalde.

**Een beleidsdocument bleef hangen op "ter goedkeuring".** De statusgang had
precies één overgang zonder taak, en dat was net de overgang waarbij de bal van
de opsteller naar de goedkeurder gaat. Er is nu een goedkeuringstaak, plus twee
signalen op `/beleid` die een wachtende versie zichtbaar maken.

**En het dashboard toont openstaande leesbevestigingen.** Nieuwkomers zonder
afdeling vielen daarbij buiten beeld; dat gat is nu zichtbaar in plaats van
stil.

Verder verholpen: `ISMS_NORM=bio2` werd door de Docker-uitrol geweigerd terwijl
alle documentatie hem aanbood. Een instelling die niet kan blokkeert nu meteen in
plaats van drie keer opnieuw geprobeerd te worden, en het advies om een blokkade
op te heffen is `docker compose up -d --force-recreate` geworden — `restart` mist
een gewijzigde `.env`, en `up -d` doet niets als er aan `.env` niets veranderde.

---

## V3.0.0 — een ander fundament

*23-08-2026*

Het eerste cijfer springt omdat de bodem onder het product verschoven is. De
ontwikkel- en doelomgeving draaien nu Ubuntu 26.04 met **PHP 8.5**, en
`config/database.php` gebruikt `Pdo\Mysql::ATTR_SSL_CA` — een constante die pas
vanaf 8.4 bestaat. Wie op 8.2 of 8.3 draait, draait deze uitgave niet.
Functioneel verandert er voor de gebruiker weinig; wat verandert is waar het op
staat en hoe betrouwbaar het zichzelf uitrolt.

**PHP 8.5 keurde de PDO-constanten af, en dat maakte de testsuite onleesbaar.**
`PDO::MYSQL_ATTR_SSL_CA` stond twee keer in de configuratie, en omdat config bij
het opstarten wordt gelezen meldde élke test die de database aanraakt een
deprecation: 1102 van de 1118. De suite las als "1102 deprecated, 16 passed", en
in die ruis is een échte deprecation niet meer te zien. De vervanging is die van
Collision zelf, met een ternary op `PHP_VERSION_ID` zodat de regel op een oudere
PHP niet fataal is. Geen gedragswijziging: beide constanten zijn 1008.

**Drie fouten in het uitrolmechanisme, alle drie sinds 13-08-2026 open.** Ze
raken elkaar niet, maar komen op hetzelfde neer: de uitrol meldde iets anders dan
er aan de hand was. De `db`-healthcheck had geen `start_period`, dus MySQL kreeg
twee minuten voor zijn eerste initialisatie — op een tragere schijf brak de
allereerste `docker compose up -d --build` daarop af met "db-1 is unhealthy",
terwijl er niets kapot was. Het blokkadescherm adviseerde `docker compose restart
app`, wat bij een blokkade op de normcontrole niet werkt: een `restart` behoudt
de omgeving van aanmaak, dus een gecorrigeerde `.env` doet niets. En `reden:` in
het BLOKKADE-bestand zei bij élke blokkade hetzelfde — "gaf exitcode 1" — waar
het opstartlog de stap en de foutmelding gewoon kent. Nu staat er bijvoorbeeld
`Normcontrole — ISMS_NORM in uw .env (nen7510) wijkt af van de normstempel`.

Het volledige toetsprotocol van veertien paragrafen is opnieuw afgelopen, met
twee stacks naast elkaar. De healthcheck liet zich op de nieuwe host niet
vanzelf betrappen — de database is er in 46 seconden — dus is die met een
A/B-proef afgedwongen: dezelfde tarbal, de database geknepen tot 5% van een
kern, als enige verschil die ene regel. Zonder: exit 1. Met: exit 0.

**De machineconfiguratie is bijgewerkt naar wat een verhuizing werkelijk kost.**
`ontwikkelmachine/LEESMIJ.md` gaat uit van 26.04, waar PHP 8.5 en node 22 uit de
distributie zelf komen — de PPA en NodeSource zijn eruit. Drie afhankelijkheden
stonden nergens en kwamen bij het verhuizen pas boven water: `php-sqlite3` (de
suite draait op sqlite `:memory:`, zonder dat pakket zakt de halve suite op "could
not find driver"), `php-intl` (kost stilzwijgend één assertie) en `python3-pypdf`
(voor `scripts/pdf2md`). Plus de doorloopbit: een thuismap is sinds Ubuntu 21.04
0750, dus nginx komt de werkboom niet in en geeft 404 — met de fout in nginx en
niet in php-fpm, want de fpm-pool draait wél als de eigenaar.

**Verder.** Een kennisartikel dat één KPI van aanmaken tot afsluiten doorloopt aan
de hand van een casus, met twee keuzes die het expliciet uitlegt: de streefwaarde
is een planlijn die per kwartaal herijkt wordt in plaats van het einddoel, en de
telling meet risicoblootstelling en niet gedrag. Daarbij volgt de uitleg onder
Teller en Noemer nu de eenheid; hij stond er onvoorwaardelijk als uitleg over
ratio's, ook boven een telling waar de noemer juist niet meetelt.

1118 tests groen, in alle normprofielen.

## V2.9.0 — niets meer bij derden

*21-08-2026*

V2.8.0 ging over wat een auditor te zien krijgt. Deze uitgave gaat over wat een
installatie doet terwijl niemand kijkt: welke verbindingen ze naar buiten maakt,
wat er in de sessietabel staat, en wat er nodig is om haar op een kale machine
neer te zetten. Er is één maatstaf: een ISMS dat achter een firewall zonder
uitgaand verkeer draait, hoort er hetzelfde uit te zien en hetzelfde te doen als
een ISMS met internet.

**Elke paginaweergave ging bij twee vreemde hosts langs.** Het schermlettertype
kwam van fonts.bunny.net en de CC-iconen van mirrors.creativecommons.org. Dat is
niet alleen een afhankelijkheid maar ook een lek: die hosts zien het IP-adres van
elke medewerker die het ISMS opent. Instrument Sans (alleen woff2, met een preload
voor latin-400) en de vier iconen staan nu in `public/`. De licentievermelding
zelf staat in één component onderaan elke pagina, ingehangen in de app-layout en
de drie inloglayouts, en `SBOM.md` heeft een paragraaf voor meegeleverde statische
bestanden van derden — anders was die tabel bij de volgende regeneratie weg.

**Bij de toetsen woog het zwaarder.** Het voorbeeldbestand `owasp1.html` laadde
Tailwind, Font Awesome, Google Fonts en jsDelivr, dus het IP-adres en de
User-Agent van elke deelnemer gingen daarheen — de enige plek in de applicatie
waar persoonsgegevens naar derden liepen. De klant levert die bestanden zelf aan
en plaatst ze zelf, dus keuren aan de deur kan niet en een instructie is geen
maatregel. Wat overblijft is de laag die de leverancier wél beheert:
`Toetsrespons` draagt nu een bronbeperking (`default-src 'self'`, `data:` voor
plaatjes en lettertypen), waarmee een toets alleen laadt wat in het bestand zelf
zit. Dat herziet het besluit van 30-07-2026 uitdrukkelijk; dat gold toen toetsen
alleen intern werden gebruikt. Daarnaast: een werkend skelet van drie vragen
zonder één externe bron, uitgedeeld door de bouwhulp met een kopieerbare opdracht
voor wie zijn toets door een AI laat schrijven, een waarschuwing bij het uploaden
over externe verwijzingen, en `owasp1.html` zelfstandig gemaakt — 82 KB, nul
externe hosts. Of de browser de bronbeperking ook werkelijk handhaaft, staat als
§16 in het dockertoetsprotocol en moet vóór de eerste klantinstallatie gedraaid
worden.

**Twee sessiesleutels bereikten de container nooit.** `SESSION_ENCRYPT` en
`SESSION_SECURE_COOKIE` stonden wel in `env.voorbeeld` maar niet in de
`environment:` van `compose.yml`, en die lijst is uitputtend. Bij de eerste was
het gevolg echt: de sessie-payload stond onversleuteld in de database, ondanks de
`true` in het voorbeeldbestand. Die sleutel verschilt niet per installatie en
staat nu vast in `env.statisch`. De tweede wordt door de entrypoint afgeleid uit
het schema van `APP_URL` — https ⇒ `true`, anders `false` met een waarschuwing —
want `APP_URL` is niet door een bezoeker te sturen en de kop waar het gedrag
eerder aan hing wél. `true` bij een http-`APP_URL` blokkeert de start: zo'n cookie
stuurt de browser nooit terug en dan kan er niemand inloggen.

**Twee dingen om te weten bij het bijwerken.** `compose.yml` moet opnieuw uit de
nieuwe boom gekopieerd worden, anders geeft hij die sleutels nog steeds niet door.
En iedereen wordt precies één keer uitgelogd zodra de versleuteling aangaat: de
bestaande sessies zijn dan onleesbaar en leveren een lege sessie op.

**Een kale machine is nu in één script klaar te maken.** `deploy.sh` zegt in zijn
kop wat hij níét doet — een database aanmaken, een vhost schrijven,
systeempakketten installeren — en dat stond verspreid over drie documenten
beschreven, maar nergens als iets dat je kunt draaien. `ota/scripts/prephost.sh`
is dat: nginx, mysql, php met de extensielijst uit het manifest, composer,
node/npm, pandoc uit de vastgepinde release met dezelfde twee sommen als de
Dockerfile, een eigen fpm-pool met de uploadgrenzen van de container, een vhost,
een lege database met eigen gebruiker. Hij stopt precies waar `deploy.sh` begint:
bij een doelpad met alléén een `.env` erin. De Dockerfile gooit hem uit het image,
want een script dat pakketten installeert en een databasegebruiker aanmaakt hoort
niet in een container.

**De leverancier is verwerker, en dat is nu opgeschreven.** Wie het ISMS als
gehoste dienst afneemt, heeft een verwerkersovereenkomst nodig in de zin van
art. 28 AVG. In `./compliance` staat de set die daarbij hoort: een documentenplan
met de volgorde, een intakelijst met de vragen die alleen de leverancier kan
beantwoorden, een dienstbeschrijving met rolbepaling, en drie bijlagen — de
gegevensinventarisatie (afgeleid uit het datamodel zelf: de audit trail bewaart
oude en nieuwe waarden en houdt de naam vast nadat het account weg is), de
beveiligingsmaatregelen met in §10 uitdrukkelijk wat er níet is, en het
subverwerkersregister. Dat register kan kort blijven, want de servercode maakt
geen enkele uitgaande verbinding; alleen e-mail gaat naar buiten. Het is een
voorstel, geen ingevulde set.

**De testsuite ging van 4m52 naar 2m22**, met ongewijzigde HTML-uitvoer. De
profilering wees naar de renderkant en niet naar migraties of seeders: `blaze`
vouwt de flux-componenten bij compilatie plat, de autorisatiecheck onthoudt per
verzoek het antwoord per (gebruiker, blok, niveau) — van ~226 permissiequery's per
SoA-render naar een handvol — en de traagste Livewire-tests sturen hun
`->set()`-aanroepen als één batch. De SoA-render zelf: 0,67 s naar 0,084 s.

**Verder.** Twee kennisartikelen: over de Cyberbeveiligingswet, en over
communicatie en overleg — clausule 7.4 bestond alleen als auditobject, dus je kon
hem auditeren maar nergens invullen. `isms:demo-vul` weigert nu elk profiel dat
niet `iso27001` is in plaats van te kijken naar capaciteiten; de BIO brengt geen
eigen controlset mee en was daar dus niet aan te herkennen, terwijl het commando
begint met het wissen van de hele database. De open punten zijn tegen de code
herzien. En er liggen twee analyses op de plank die nog niets veranderen: plan 00r
maakt TLS instelbaar voor de dag dat er geen proxy vóór staat, en `docksec.md`
legt de hardening van een host naast de containerstack — met één gat erin, want
een gepubliceerde containerpoort trekt zich niets aan van `ufw`.

1116 tests groen, in alle normprofielen.

## V2.8.0 — de BIO-verplichtingen komen in beeld

*17-08-2026*

V2.7.0 bouwde de 118 overheidsmaatregelen als eigen niveau onder Bijlage A, en dat
datamodel klopt. De weergave niet. Uit "eigen entiteit" was stilzwijgend
"ondergeschikt in beeld" afgeleid, en dat volgt er niet uit: het detailniveau waar
de RDI naar vraagt was het minst zichtbare deel van de applicatie. De SoA-tabel
toonde er niets over, en de Word-kopie die een auditor meekrijgt bevatte één kolom
met "3 / 7" en geen enkel nummer. De vraag "laat 5.24.03 zien" was alleen te
beantwoorden uit een markdownbestand dat uit een terminalcommando rolt.

De maatstaf van deze uitgave is precies die vraag. In de SoA-tabel staat nu een
kolom **Verplichtingen** waarvan het cijfer de verplichtingen onder de regel
openklapt — nummer, status, tekst, onderbouwing, verwijzingen en het bewijs dat
eronder hangt. Dat is een leesweergave; beoordelen blijft in de modal, want twee
bewerkroutes naar hetzelfde veld is hoe een scherm en zijn kopie uit elkaar gaan
lopen. De 39 beheersmaatregelen zonder verplichting houden hun streepje, maar met
de reden erbij: daar schrijft de norm een andere route voor, en zwijgen leest als
"hier is niets te doen".

**De kopie voor de auditor draagt nu een bijlage.** `Schermkopie` kon één tabel;
er kan er een tweede onder, met eigen kop, eigen kolommen en een eigen
omvangregel. Dat is een bouwsteen van blok 12h en niet van dit ene scherm — een
volgend register met een tweede detailniveau krijgt hem gratis. De SoA vult hem
met de verplichtingen als eigen regels, en uitdrukkelijk ongeacht wat er
openstond: een auditdocument dat afhangt van welke rijen iemand toevallig had
opengeklikt is niet reproduceerbaar. Zonder normtekst, om drie redenen die
dezelfde kant op wijzen — hij is er lang niet altijd, hij loopt op tot 1400 tekens
en is in een tabelcel onleesbaar, en hij staat onder CC BY-NC-SA.

**Twee getallen die als één werden gelezen.** De kop van `/soa` toonde twee
blokken tellers die allebei over "maatregelen" spraken, het ene over 93 en het
andere over 118. Elk niveau noemt nu zijn eigen aantal. En de dekking "3 / 7" telt
niet zeven verplichtingen maar zeven niet-uitgezonderde; het aantal uitzonderingen
staat er nu overal naast, zodat de noemer te herleiden is. Zonder dat leest een
volledig ogende breuk over tien verplichtingen waarvan er drie zijn weggelaten.

Per verplichting zijn er twee eigen referentievelden bijgekomen, met dezelfde
namen en lengte als op de SoA-regel: waar het beleidsdocument bij de
beheersmaatregel hangt, staat hier de vindplaats. Ze gaan mee de VvT-export in,
want velden die je kunt invullen en die nooit in het auditdocument komen zijn half
gebouwd. Bewijs koppelen kon al bij 5.24.03 in plaats van bij 5.24, maar er was
geen route naartoe vanuit de SoA; die is er nu, met één paneel tegelijk.

**En de teksten liepen in elkaar.** Veertig van de 118 verplichtingen hebben een
opsomming in hun tekst. De exporthelper voor tabelcellen sloeg elke regelovergang
plat naar een spatie — juist in een tabel, fout in een opsommingsregel — en HTML
deed op het scherm hetzelfde. Beide behouden nu de structuur. Dat werd pas
zichtbaar toen er voor het eerst echte normtekst in een installatie stond; het pad
om die in te lezen was zelf ook nooit helemaal doorlopen, en de bewaking erop
stopte op een zoeksleutel die nooit kon matchen.

1105 tests groen, in alle normprofielen.

## V2.7.0 — de BIO als derde normprofiel

*17-08-2026*

De Baseline Informatiebeveiliging Overheid 2 komt erbij, en ze past minder
vanzelfsprekend dan NEN 7510 deed. Die was een superset op hetzelfde niveau:
93 + 8 maatregelen in Bijlage A, plus een tekstveld per maatregel. De BIO laat
Bijlage A volledig ongemoeid — geen enkele nieuwe maatregel, geen enkele
hernummerd — en hangt er een niveau ónder: 118 genummerde overheidsmaatregelen,
tot zeven onder één beheersmaatregel, elk met eigen status, onderbouwing en
bewijs. Dus een derde *profiel* met een nieuw detailniveau, en geen derde
maatregelenset. Waar ISO zegt "beheer je toegangsrechten", zegt de BIO hoe vaak je
ze beoordeelt.

Het profielmechanisme uit V2.1.0 hield stand: acht plekken in de code zijn op
profiel gesleuteld en de labellaag hoefde nergens aangeraakt. Wat er wél bij moest,
is een eigen impactschaal — `config/beoordelingsschaal.php` gooit bij een ontbrekend
profiel, en dat is hier geen formaliteit. De BIO weegt politieke en diplomatieke
schade, verlies van publiek vertrouwen en verlies van management control. Een
gemeente die op de ISO-schaal scoort ("hinder binnen één team") onderschat dat
stelselmatig; op het hoogste niveau is de schade politiek en niet financieel.

**Geen normtekst in het repo, en deze keer om een andere reden.** Bij ISO en
NEN 7510 is dat een licentie van ISO respectievelijk NEN. De BIO is kosteloos te
downloaden, maar staat onder CC BY-NC-SA 4.0: niet-commercieel en gelijk delen. Of
de aanwijzing in het Cyberbeveiligingsbesluit die beperking opheft — regelgeving
van de openbare macht is vrij van auteursrecht — is een juridische vraag die
openstaat. Tot ze beantwoord is levert het repo alleen openbaar bekende structuur:
nummers, de koppeling aan de beheersmaatregel, de status en de reikwijdte van de
Cyberbeveiligingswet. Wie de BIO zelf heeft, vult de teksten in een gitignored
bestand ernaast.

Drie beheersmaatregelen vallen buiten die reikwijdte: intellectueel eigendom,
archivering en privacy hebben hun eigen wet. Daar geldt verplichtende
zelfregulering in plaats van iets wat de RDI kan handhaven, en de SoA markeert dat
— want het is precies het onderscheid waarop een gesprek vastloopt als niemand het
benoemt.

**Twee fouten die pas bij het uitrollen zichtbaar werden**, en beide van dezelfde
soort: een aanname die klopte zolang er twee profielen waren.

`profiel_uit_database()` in `deploy.sh` leidde het profiel af uit de controlset —
101 maatregelen plus acht zorgmaatregelen was NEN, 93 zonder was ISO. De BIO heeft
óók 93 zonder, dus een volkomen correcte BIO-installatie werd als `iso27001`
gelezen en de uitrol brak af met de melding dat de seeders het verkeerde profiel
hadden opgeleverd — terwijl in hetzelfde log stond dat `bio2` was vastgelegd. Het
raadt nu niet meer maar leest de tabel `normprofiel`, die er sinds V2.1.0 is.
Daarnaast stond de lijst geldige waarden voor `--profiel=` uitgeschreven in het
script; die komt nu uit `config/norm.php` van de uitgave zelf.

En `env_waarde()` las een `.env` anders dan de applicatie: het nam de láátste regel
met een sleutel waar phpdotenv de eerste neemt, en het knipte geen comment achter
een waarde af. Een dubbele `ISMS_NORM` en een `DB_PASSWORD='geheim'   # notitie`
leverden daardoor storingen op waarbij de foutmelding de andere kant op wees. De
functie volgt nu phpdotenv, en een sleutel die meer dan één keer voorkomt geeft een
waarschuwing vóór het seeden in plaats van een verrassing erna.

**Meegegroeid.** `KennisbankNormprofielTest` liep over een hardgecodeerde lijst van
twee profielen en nu over `config('norm.profielen')` — een lijst die niet meegroeit
bewaakt op den duur het verkeerde. `NormprofielSeeder` waarschuwt nu als hij op zijn
standaard terugvalt: een ontbrekende `ISMS_NORM` was niet te onderscheiden van een
bewuste keuze voor ISO, en die vastlegging is onomkeerbaar.

1090 tests groen, in alle normprofielen.

## V2.6.0 — de bewaking merkt haar eigen stilstand

*15-08-2026*

Negen commando's staan in de planning: metingen, taken genereren, taken laten
verlopen, herinneringen. Staat de machine uit, dan draaien ze niet, en Laravel
haalt niets in. Tot deze uitgave merkte niemand dat — een ISMS dat zes weken had
stilgelegen zag er daarna precies zo uit als een ISMS dat gewoon doordraaide, en
juist een systeem dat aantoonbaarheid als product levert hoort niet stil te
kunnen vallen zonder spoor.

Eén listener schrijft nu elke geplande run weg in `systeemhartslag`. Machinale
log, dus geen audit trail: de bewijsketen loopt via de taak die uit een gat
volgt, en die is wél geauditeerd. De verwachte momenten komen uit
`Schedule::events()` zelf en niet uit een tweede lijst naast `routes/console.php`,
zodat een nieuw gepland commando vanzelf meeloopt.

Niet elk gemist moment weegt even zwaar, en dat is de kern. Een herstart van tien
minuten hoort geen ruis te maken; een gemiste maandmeting van een toestand-KPI is
onherstelbaar, want die waarde is achteraf niet te reconstrueren. De klasse per
commando staat in `config/hartslag.php` — een inhoudelijk oordeel over wat een
commando doet, geen detectielogica.

**Twee keer een e-mailadres repareren.** Een typefout in een uitnodiging was
nergens te herstellen; wat overbleef was een UPDATE op de database, precies de
ingreep die buiten elke logging om gaat. Nu kan het, in twee vormen die elkaars
spiegelbeeld zijn. Bij een uitgenodigd account (01g) geldt het nieuwe adres
direct en roteert het wachtwoord mee — het uitnodigingstoken bevat het adres
niet, dus wie de mail op het foute adres kreeg hield anders zeven dagen een
werkende link. Bij een actief account (01h) geldt het pas na bevestiging op het
nieuwe adres, blijft het wachtwoord staan en krijgt het oude adres bericht.
Dezelfde regel in beide richtingen levert tegengesteld gedrag op, omdat er in het
tweede geval iemand ís die het account gebruikt.

Verder:

- een bevindingenregister over alle auditrondes heen, met filters in de URL, zodat
  een open minor uit een afgeronde ronde van twee jaar terug niet meer achter een
  badge verdwijnt; read-only, want vastleggen en sluiten blijft achter de
  record-guard van de auditor;
- het versienummer achter de productnaam in de zijbalk, uit `.env` of anders uit
  het manifest van de uitgerolde boom, zodat het na een upgrade vanzelf klopt;
- een voettekst met de herkomst op elke pagina van elk Word-document: organisatie
  en norm, product en omgeving, printdatum en paginanummer — met de organisatie
  op de schermkopie en bewust niet op een meegeleverd kennisartikel;
- pandoc uit de release van jgm/pandoc in plaats van uit apt: de Ubuntu-build kan
  met `--sandbox` geen .docx schrijven, waardoor elke Word-download in de
  Docker-stack een 503 gaf die nergens in de logs stond;
- `deploy.sh` bouwt `vendor/` en `public/build` als de tarbal ze mist; een
  `--geen-bouw`-pakket eist daarom deploy-versie 1.1 en de Docker-route weigert
  het met een eigen melding;
- storage zonder ACL's op de ontwikkelmachine, waarmee alle drie de omgevingen
  hetzelfde principe volgen met één schrijver, en de machineconfig in
  `ontwikkelmachine/` onder versiebeheer.

---

## V2.5.0 — wijzigingsbeheer, het vijftiende blok

*12-08-2026*

A.8.32 stond wel in de SoA maar had geen register: nergens was te zien welke
wijzigingen er waren geweest en met welke goedkeuring. Dat gat is nu gevuld, en
de weg ernaartoe leverde een laag op waar meer blokken iets aan hebben.

De keuze die het ontwerp draagt: dossiersoort in code, stappenreeks in data. Een
generieke workflow-engine wordt een mini-programmeeromgeving die de CISO moet
bedienen, en configuratie die de compliance-uitkomst bepaalt vraagt daarna zelf
om versiebeheer. Maar een per geval gebouwde workflow levert bij elke variant een
bijna identieke tabel op. Dus: de organisatie richt zelf routes in, nieuwe
dossiersoorten blijven ontwerpwerk.

Onder het blok ligt een reekslaag op de taken-engine (07b). Een reeks is geen
nieuwe tabel maar alle taken op dezelfde entiteit met een gevulde volgorde;
stappen met hetzelfde nummer lopen parallel, en een stap die nog niet aan de
beurt is telt nergens mee.

Zeven meegeleverde routes, één per soort wijziging: twee leveranciersreleases,
configuratie standaard en spoed, infrastructuur, ingebruikname en afvoer. Spoed
vraagt geen enkele uitzondering in de code — het is een route waarin uitvoeren
vóór goedkeuring staat, precies wat A.8.32 f) bedoelt met voorzorgsoverwegingen.

De enige harde inhoudelijke eis is het terugvalplan. Die zit op het dossier en
niet op een scherm, want dezelfde stap is ook vanaf de takenlijst af te vinken.
Wat een lopend dossier draagt ligt vast op het moment dat het start: titel,
deadline, eigenaar, staptype, bewijsplicht en de terugsprong bij afkeuren. Een
sjabloon dat later wordt versoepeld zet daarmee geen controle uit die al gold.

Verder:

- de laatste containerlogs bij een mislukte uitrol, en een grens op de logdriver;
- tijdstippen in de lokale zone, met UTC in de opslag;
- handmatig blokkeren van een account door de CISO, met een blokkade die meteen
  werkt;
- de demoklok een maand terug, want de gesimuleerde historie liep tot in de
  toekomst;
- de kennisbank bijgewerkt, met een downloadknop voor de CISO;
- twee kennisbankartikelen over wijzigingsbeheer, en blok 15 in de sitemap.

---

## V2.4.0 — de Administrator, en toetsen buiten de webmap

*11-08-2026*

Een vijfde rol die geen enkel ISMS-recht heeft en alleen bij beheerroutes kan, en
de verhuizing die daaraan vooraf moest gaan.

Toetsen waren losse HTML-pagina's in `public/toetsen`, en daar plaatste alleen
iemand met SSH ze neer. Zodra een minder vertrouwd account dat mag, is zo'n
pagina een escalatiepad: het is door een mens geleverde HTML met JavaScript, en
op de origin van het ISMS draait dat script in de sessie van wie de toets opent.
Toetsbestanden staan nu op een eigen disk buiten `public/` en worden door de
applicatie uitgeserveerd in een CSP-sandbox — geen sessiecookie, geen DOM van het
ISMS, geen opslag. De token blijft het bewijs, en staat in het pad én in
`?callback=`, zodat elk al uitgeleverd toetsbestand blijft terugmelden.

Daarop staat de rol: blok installatiebeheer, één rechtenrij, twee schermen. De
CISO heeft op dat blok bewust géén rij, anders is het een rol met extra rechten
in plaats van andere. De combinatie Administrator + een ISMS-rol wordt geweigerd;
dat is de eerste harde onverenigbaarheid in dit model en een bewuste breuk met
01c. Escalatie vraagt daarmee altijd twee personen: de Administrator kan geen
rechten uitdelen, de CISO kan zichzelf geen technische rechten geven.

De export schuurt met "geen enkel ISMS-recht" en dat is gewogen: uitleveren is
een handeling aan de installatie, inzien is een recht op de inhoud. Hij drukt op
de knop en leest de bevestiging; het bestand komt op een pad waar hij vanuit de
applicatie niet bij kan, zonder persoonsgegevens en zonder bewijsstukken, en de
handeling staat in de audit trail.

Verder:

- de uitgang `/var/tmp/isms_export`, in de Docker-route een bind mount naar
  `data/isms_export` op de host, met een waarschuwing bij een stack die hem mist;
- de drie meegeleverde toetsen meldden hun uitslag nooit terug — ze riepen een
  functienaam aan die nergens bestond. Nu gerepareerd, ook voor gezakte pogingen;
- de demo levert een werkende toets mee bij de beheerderstraining;
- `public/toetsen` is uit versiebeheer, met het bijbehorende leidingwerk;
- de NEN 7510-ontwerpnotities zijn verwijderd: de bouw is af en de code is de bron.

873 tests groen, in beide normprofielen. Twee fouten kwamen pas op de draaiende
installatie boven: `/var/tmp` bleek daar niet schrijfbaar voor de webgebruiker,
en een nieuwe waarde in de enum van de audit trail kwam er in de tests doorheen
omdat de suite op sqlite draait. Die tweede is aan de codekant dichtgezet.

> De deelproducten 01, 06 en 10 en `architectuur.md` zijn ná het taggen
> bijgewerkt; de tag is daarom één commit opgeschoven.

---

## V2.3.0 — het ISMS draait als Docker-stack

*11-08-2026*

De volledige Docker-uitrolroute, in twee delen gebouwd en met de hand getoetst op
twee stacks naast elkaar (iso27001 op poort 81, nen7510 op poort 82).

Deel 1 levert de stack: een image dat uit de distributietarbal gebouwd wordt,
nginx + php-fpm + de takenplanner onder supervisord, MySQL ernaast, en één
hostmap voor alles wat een herbouw moet overleven. De APP_KEY wordt eenmalig
gegenereerd en bewaard; wijkt een meegegeven sleutel daarvan af, dan start de
container niet in plaats van stil de 2FA-geheimen onleesbaar te maken.

Deel 2 vervangt het voorlopige "migreer en seed" door `scripts/deploy-docker.sh`,
dat bij elke start de installatie nagaat: normcontrole, verschilrapport, eigen
seeddata, dump, migreren en seeden, het eerste CISO-account, de demo, een
rookproef en de verantwoording. De dump wordt gemaakt zodra er een migratie
klaarstaat en anders niet — anders dan bij `deploy.sh` draait dit bij élke
containerstart. Mislukt de uitrol, dan telt de entrypoint de pogingen en valt de
installatie stil met een BLOKKADE in plaats van eindeloos te herstarten.

De toetsronde bracht drie fouten aan het licht die alleen door te draaien
zichtbaar worden: het script wiste zijn eigen `DB_*`-variabelen, backticks in een
foutmelding werden als commando uitgevoerd, en het slotscherm noemde altijd poort
81. Alle drie hersteld en opnieuw gedraaid.

Verder:

- één maatregelbestand per normprofiel; de eigen omschrijvingen zijn vervallen
  omdat een interpretatie van de norm niet thuishoort in het scherm waar de
  auditor de toepasselijkheid beoordeelt;
- risicocriteria als vastgesteld kader met eigen versies (04g);
- twee oriëntatieartikelen onder Naslag, voor de CISO en de externe auditor;
- de demo leegt alleen nog de tabellen van het eigen schema;
- de pre-commit hook is verwijderd; `ControlsetBestandenTest` en de controle in
  `builddistr.sh` bewaken de normtekst nu.

---

## V2.2.1 — twee uitrolfouten

*06-08-2026*

Het normprofiel overleeft `config:cache`. Een uitrol met `ISMS_NORM=nen7510`
leverde een ISO-installatie op: `deploy.sh` cachet de configuratie vóór
`db:seed`, en daarna leest Laravel `.env` niet meer. `ISMS_NORM` en
`MAATREGELEN_BRON` lopen nu via `config/norm.php`. `deploy.sh` controleert het
profiel bovendien meteen na het seeden, in plaats van pas ná het omschakelen.

De databasedump vroeg om het PROCESS-recht. `--no-tablespaces` slaat die uitvraag
over, en de dump wordt nu gecontroleerd op een volledige afsluiting — mysqldump
meldde de fout wel maar sloot af met exitcode 0.

---

## V2.2.0 — de demo rolt mee uit

*06-08-2026*

`deploy.sh` kan het FruitBV-demoscenario in een uitrol meenemen (`--demo-vul` /
`--geen-demo`, en anders een bevestigingsvraag voor het wissen). De fixtures gaan
mee in elke tarbal; `builddistr.sh` exporteert `saasdemo/data` apart, want dat
staat buiten `ota/`.

De gegenereerde demowachtwoorden worden niet meer afgedrukt maar naar
`storage/app/private/demo-inloggegevens.txt` (0600) geschreven — onbeheerd
meelopend zouden ze anders in het bewaarde uitrollog staan. De demopersonen
hebben nu adressen op `acme.example` in plaats van een bestaand domein.

---

## V2.1.0 — NEN 7510 als tweede normprofiel

*05-08-2026*

Het profiel wordt bij de installatie in de database vastgelegd (`ISMS_NORM` bij
het opzetten, daarna de tabel `normprofiel`). Bijlage A telt in zorgmodus 101
maatregelen met een eigen veld voor de zorgspecifieke beheersmaatregel; het ISMS
levert in dat profiel geen enkele maatregeltekst mee.

Verder: de beoordelingsschaal van kans en impact, privacy bij assets, de externe
meldplicht met AVG- en Cbw-termijnen, kennisbankvarianten per norm, en het
artikel over het zelf invoeren van de normtekst.

---

## V2.0.0 — de eerste distribueerbare versie

*04-08-2026*

Vanaf hier is de repo uitleverbaar: er staat geen normtekst in code, gegevens of
kennisbank. De maatregelomschrijvingen zijn eigen formuleringen, met op elk
scherm het voorbehoud dat ze dat zijn; `maatregelen.json` (de gekochte normtekst)
blijft lokaal en de pre-commit-hook bewaakt dat.

De uitlevering zelf gaat als een verse repo zonder historie.

---

## V1.15.0 — disclaimer bij eigen omschrijvingen, en twee auditorknoppen erbij

*04-08-2026*

De 93 eigen maatregelomschrijvingen dragen nu zelf het voorbehoud dat ze niet de
normtekst zijn; de SoA-modal zet die regel rood met een link naar het
verantwoordingsartikel. In een installatie met de gekochte normtekst verschijnt
hij niet.

Kopie voor de auditor op `/risicos` en `/afwijkingen`, met de eigenaarskolom
geanonimiseerd tot initialen + rol. Dat schema staat nu op één plek
(`Gebruiker::anoniemLabel`) en wordt gedeeld met `isms:exporteer`.

---

## V1.14.0 — kopie voor de auditor op de tolerantiematrix

*04-08-2026*

De matrix gaat als afbeelding én als tabel mee in het Word-document. Het plaatje
wordt met GD getekend en als data-URI ingesloten — de enige weg die werkt met
pandoc's `--sandbox`. Zonder GD of lettertype blijft alleen het plaatje weg; de
cijfers staan er dan nog steeds.

SBOM: besturingssysteem en kernel erin (A.8.8), fonts-dejavu-core erbij, pax
eruit, en de rol van pandoc bijgewerkt.

---

## V1.13.0 — zoeken in de kennisbank

*03-08-2026*

Zoekveld op `/kennisbank`: hoofdletter- en accentongevoelig, deelwoordmatching,
een passage met de treffer gemarkeerd, en een diepe link naar de paragraaf via
kopankers. Geen index, geen zoekdienst, geen nieuwe afhankelijkheid.

---

## V1.12.0 — het aanmelden krijgt een tweede slot

*03-08-2026*

**Tweefactorauthenticatie** (01d), verplicht voor alle rollen. TOTP via Fortify,
maar alleen die feature: de loginroute van dit ISMS blijft de enige, en het
instelscherm, de challenge, de afdwing-middleware en de CISO-reset zijn van
onszelf. Een nieuwe gebruiker koppelt zijn app meteen bij de uitnodiging; wie er
al was krijgt veertien dagen respijt, met twee herinneringsmails onderweg.

Twee dingen zijn bewust niet gedaan, en dat is de kern van het ontwerp. Een foute
verificatiecode blokkeert het account níet — brute-force op zes cijfers is met
vijf pogingen per kwartier kansloos, terwijl blokkeren op typefouten en klokdrift
alleen zelf-DoS oplevert en werk voor de CISO. En verlopen sluit niemand buiten:
je komt niet verder dan het instelscherm, maar je helpt jezelf daar zonder
beheerder. Wat de mail voorkomt is niet een lockout maar het moment: inloggen om
iets te doen en dan pas je telefoon moeten zoeken.

Het 2FA-secret en de herstelcodes zijn uitgesloten van de audit trail. Zonder die
regel belandt het geheim in een tabel die de Auditor mag inzien én exporteren.

**Het wachtwoordbeleid** is vastgesteld: minimaal twaalf tekens, geen verplichte
samenstelling, geen periodieke wijziging. Eén plek in de code, vier aanroepers
die ernaar verwijzen, en een test op de grens zelf.

Beide punten stonden als openstaande beslissing in de kennisbank en zijn daar nu
weg. A.5.17 en A.8.5 hebben hiermee onderbouwing die er niet was.

672 tests groen.

---

## V1.11.0 — de audit trail wordt onweerlegbaarder, en de suite twee keer zo snel

*03-08-2026*

**Keten-hashing van de audit trail** (06c). Elke logregel draagt de hash van zijn
voorganger, zodat het stil verwijderen, wijzigen of tussenvoegen van een regel
detecteerbaar wordt — tot nu toe liet een DELETE alleen een gat in de nummering
achter, en die ontstaan ook door teruggerolde transacties. Een nachtelijke
controle loopt de keten na en legt de uitslag vast, ook als alles klopt: een
auditor vraagt niet of de keten vandaag klopt maar of hij al twee jaar elke nacht
is gecontroleerd.

Wat het niet doet staat er overal bij. Het verhindert niets, en wie de database
kan wijzigen kan de hele keten herberekenen. Daar helpt alleen een kophash tegen
die buiten dit systeem ligt; de kopie voor de auditor van het trailscherm draagt
die hash. De databasegrant blijft onverminderd nodig.

Het lastigste stuk bleek niet de hash maar de canonieke vorm: MySQL herordent de
sleutels in een json-kolom, dus de opgeslagen bytes hashen levert per database een
andere uitkomst. Een test met een letterlijk opgeschreven hash bewaakt die vorm.
Verzegelen gebeurt in één transactie — 2190 losse commits duurden 274 seconden en
braken halverwege af; nu 10,8 seconden en niet meer te stranden.

**De testsuite van 205 naar 102 seconden** (00f). De demovulling draaide vijftien
keer waar het klassecommentaar één keer beloofde, en `MeetaanpakTest` rendeerde
negentien KPI's per aanroep. Daarnaast stonden twaalf identieke autorisatietests
in twaalf bestanden; die zijn vervangen door één matrix van 36 schermen maal vier
rollen, plus een test die de schermlijst uit de router afleidt. Dat legde meteen
bloot dat de Auditor 403 krijgt op vijf schermen waar hij in de componenttests
juist alle rijen hoort te zien — vastgelegd, niet stilzwijgend gerepareerd: het is
een rechtenbesluit.

**Kennisbank: alle open punten op één pagina.** Openstaande beslissingen,
bedenkingen en ideeën uit elkaar getrokken, met een tabel van wat al beslist is
zodat afgesloten discussies niet terugkomen.

Verder: de kopie voor de auditor noemt personen met initialen, een datumfilter
neemt de hele periode mee in plaats van de zichtbare vijftig, en de ongebruikte
starter-kit-layout is verwijderd.

641 tests groen.

---

## V1.10.0 — koppelingen in de audit trail, en de schermkopie voor de auditor

*03-08-2026*

Twee gaten gedicht die allebei pas zichtbaar werden door ernaar te gaan zoeken.

**Koppelingen komen in de audit trail** (06b). Een sync op een
veel-op-veel-relatie raakt de attributen van een model niet aan, dus wijzigingen
aan koppelingen lieten geen enkel spoor na: 442 koppelrijen, nul trailregels.
Welk beleid welke maatregel dekt, wie in welke doelgroep zat, welke clausules
binnen een auditronde vielen — normale auditorvragen zonder antwoord. Nu één
logregel per handeling met de delta erin, met namen in plaats van id's, bewaakt
door een structurele test die rauwe koppelmutaties in `app/Livewire` tegenhoudt.
Die bevinding heeft ook de backfill van blok 12 doen intrekken.

**De schermkopie voor de auditor** (12h). Het vooraf samengestelde auditdossier
is vervallen: een auditor vraagt om een kopie van het scherm waar hij naar kijkt.
Het mechanisme staat — markdown naar Word via pandoc, een register van wat er is
meegegeven, en een kop die noemt hoeveel van hoeveel regels er in staan en op
welke filters. De SoA is het eerste scherm met de knop.

Verder: twee kennisbankpagina's (de audit trail, en alle beheercommando's), en de
deelplannen bijgewerkt voor het vervallen exportpakket.

626 tests groen.

---

## V1.9.0 — blok 12 compleet

*02-08-2026*

De drie Act-metingen op een periodevenster: scoredaling zonder onderbouwing,
overgangen naar gemitigeerd, en nieuw geïdentificeerde risico's.

- `periode_van`/`periode_tot` op de meetrij, zodat een gemiste run geen
  gebeurtenissen kost en een telling te normaliseren is, plus 'aantal' als derde
  eenheid;
- de versiebreuk bij een gewijzigde meetmethode van een handmatige KPI: de
  applicatie kan niet zien of een tekstwijziging een echte breuk is, dus vraagt
  ze het;
- de meetaanpak, de norm en haar vaststellingsstatus mee in `isms:exporteer` —
  die gaf cijfers zonder te zeggen wat er geteld was;
- per KPI alleen het jongste meetpunt open op `/meetaanpak`;
- het kennisartikel over KPI's herschreven naar negentien KPI's, normering en het
  onderscheid tussen toestand en gebeurtenis;
- in de saasdemo het bewijs bij een scoredaling gekoppeld aan het risico in
  plaats van alleen aan de afwijking.

---

## V1.8.0 — KPI-normering en KPI-beheer

*02-08-2026*

- richting als eigen vlag in plaats van afgeleid uit de eenheid;
- streefwaarde en signaalwaarde op definitie én meetrij, zodat een bijgestelde
  norm de historie niet herkleurt;
- acht nieuwe KPI's uit de inmiddels gebouwde bronblokken — zestien in totaal, en
  Act niet langer leeg;
- de meetbron los van de sleutel met een registry, en een luidruchtige exitcode
  bij een verweesde definitie;
- KPI-beheer in de applicatie, met handmatige KPI's en handmatige meetpunten;
- een meegeleverde streefwaarde als expliciet voorstel, tot de organisatie hem
  vaststelt;
- statuskleur en streefwaardelijn op het dashboard;
- de meetaanpak en de norm mee in `isms:exporteer`;
- PDCA hernoemd naar KPI's waar het een etiket was.

---

## V1.7.0 — isms:exporteer compleet

*31-07-2026*

- trainingsdeelname en leesbevestigingen achter `--met-persoonsgegevens` (dat was
  tot nu toe een lege belofte);
- issue-register §4.1 met de doorvertaling naar risico's;
- risicocriteria §6.1.2 a bovenaan de risicoparagraaf;
- bewijskoppelingen bij de entiteit;
- KPI-meethistorie, scope-interfaces en systemen;
- uitgifte van bedrijfsmiddelen A.5.11, diensten en contractclausules;
- verbeteracties onder hun besluit;
- dekkingsmatrix per auditprogramma en restrisico-jaartrend per control;
- een expliciete weglatingslijst.

---

## V1.6.0 — de simulatiemotor en het dashboard

*31-07-2026*

- `isms:demo-vul`: de simulatiemotor die 23 maanden FruitBV-scenario opbouwt;
- eigen maatregelclassificatie (04d fase 1–4) met `isms:capaciteiten`;
- auditcyclus 11c: nulmeting en programmajaar;
- grafische panelen op het dashboard (plan 12c): KPI-strip, signalen, PDCA-trend,
  risicomatrix, maatregelen per thema;
- SMTP-timeout tegen blokkerende notificaties;
- PDF- en Markdown-preview bij bewijsstukken;
- kennisbankartikel verantwoording en disclaimer;
- issue-risicokoppeling (plan 02b): §4.1 doorvertaald naar §6.1 met
  dekkingssignaal;
- losse toetsen op `/mijn-trainingen`;
- de productnaam EzISMS.

---

## V1.5.0 — export als Markdown-boom

*28-07-2026*

Testsuite-blokken en `isms:exporteer` (Markdown-boom), een refactor van
`deploy.sh` met uitsluiting van editor-artefacten, de kennisbankterm
"detailniveau", en de OTA-banner.

---

## V1.4.0 — het interne auditprogramma

*28-07-2026*

Plan 11b: de 3-jaarscyclus en de dekkingsmatrix, met de auditcyclus-tooling
(`isms:bereid-auditcyclus-voor`, `isms:verwijder-auditdata`), een live
SoA-koppeling en de audit-kennisbank.

---

## V1.3.0 — kennisbank

*27-07-2026*

De sitestructuur als SVG, en de integraties met de norm-onderbouwing.

---

## V1.2.0 — restrisico per control

*27-07-2026*

Plan 04c: rollup, jaartrend en een bewerkbare toelichting. Plus R-nummers, de
PDCA-hernoeming en de demo-seeder voor A.8.8.

---

## V1.1.0 — PDCA-weergave

*27-07-2026*

De PDCA-weergave, en de aanzet tot plan 04c (restrisico per control).

---

## V1.0.0 — de eerste zeven blokken

*27-07-2026*

De eerste uitgave, 126 commits vanaf 21-07-2026. Deze tag draagt zelf geen
bericht; wat erin zat is af te lezen aan de commits die eraan voorafgaan:

| Blok | |
|---|---|
| 1 | Identity, Access & Rollen — inclusief de autorisatiekern |
| 2 | Context & Scope — met een volledige kopie bij elke nieuwe scopeversie |
| 3 | Asset- & Informatieclassificatie |
| 4 | Risicomanagement & Statement of Applicability |
| 5 | Beleid & Maatregelbeheer |
| 6 | Bewijsrepository & Audit Trail |
| 7 | Taken- & Workflow-engine |

Plus de Laravel-scaffolding, een eigen ISMS-logo, de organisatienaam in de
zijbalk, en `x-keuzelijst` als gedeelde component.
