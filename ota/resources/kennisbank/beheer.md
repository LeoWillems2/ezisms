# Beheer: de artisan-commando's

Alles wat dit ISMS buiten de schermen om doet, loopt via één commandoregel. Ze
draaien vanuit de applicatiemap:

```bash
cd /pad/naar/ota
php artisan isms:...
```

Er zijn twee soorten: commando's die **vanzelf draaien** (de nachtelijke
onderhoudstaken) en commando's die u **met de hand** geeft — bij het inrichten,
bij het uitleveren, of bij het opruimen.

## Wat vanzelf draait

| Wanneer | Commando | Wat het doet |
| --- | --- | --- |
| dagelijks 01:00 | `isms:verval-gebruikersaccounts` | Accounts waarvan de vervaldatum is bereikt, worden gedeactiveerd. |
| dagelijks 01:30 | `isms:archiveer-bewijsstukken` | Bewijs waarvan de bewaartermijn is verstreken, gaat naar *gearchiveerd*. Er wordt niets verwijderd. |
| dagelijks 01:15 | `isms:herinner-tweefactor` | Mailt gebruikers van wie de termijn om de tweede factor in te stellen bijna of net verstreken is. Eén mail in de aanloop, één na het verstrijken. |
| dagelijks 01:45 | `isms:controleer-audittrail` | De keten-hashes over de audit trail worden nagelopen. De uitslag wordt vastgelegd, ook als alles klopt. |
| dagelijks 02:00 | `isms:genereer-taken` | Terugkerende taken uit de sjablonen, plus signalen voor achterstallige retouren, SoA-beoordelingen en leesbevestigingen. |
| dagelijks 02:15 | `isms:verloop-taken` | Taken over hun deadline worden *verlopen* en escaleren een niveau. |
| dagelijks 02:30 | `isms:schoon-raadplegingen` | Registraties van bewijs-downloads ouder dan de bewaartermijn worden verwijderd. Dit verwijdert wél echt. |
| dagelijks 02:45 | `isms:controleer-hartslag` | Kijkt of alle taken hierboven ook echt gedraaid hebben, en meldt wat er gemist is. |
| maandelijks, de 1e om 03:00 | `isms:meet-kpis` | De maandelijkse KPI-meting: teller en noemer per KPI, onveranderlijk vastgelegd. |
| jaarlijks, 31 december 23:00 | `isms:leg-restrisico-vast` | De jaarlijkse restrisico-snapshot per control. |

De ketencontrole draait vóór de opruimtaken hieronder: zo gaat ze over de trail
van gisteren en niet half over die van vannacht.

De volgorde van de twee taakcommando's is niet willekeurig: `genereer-taken`
draait vóór `verloop-taken`, zodat een taak die vannacht ontstaat met een deadline
in het verleden dezelfde nacht als verlopen wordt gemarkeerd — in plaats van een
dag lang ten onrechte "open" te heten.

**Deze taken staan in de audit trail op naam van "Systeem (geplande taak)".** Er
is geen ingelogde gebruiker, en dat hoort zichtbaar te zijn in plaats van
toegeschreven aan wie toevallig als laatste iets deed.

### De planning zelf

De planning staat in de code (`routes/console.php`), niet in de crontab. In de
crontab staat één regel die elke minuut de planner wakker maakt. Zo staat het
schema op één plek in plaats van twee die uit elkaar lopen.

```bash
php artisan schedule:list     # wat staat er gepland, en wanneer
php artisan schedule:test     # één commando kiezen en nu draaien
```

### Een gemiste nacht

Alle nachtelijke taken zijn **idempotent**: twee keer draaien levert geen dubbele
taken en geen dubbele metingen op. Een gemiste nacht haalt u gewoon met de hand
in.

Bij `isms:meet-kpis` zit er nog iets extra's in. De KPI's die *gebeurtenissen in
een periode* tellen — nieuwe risico's, statusovergangen, scoredalingen zonder
bewijs — rekenen vanaf het einde van de vorige meetperiode, niet vanaf een vaste
maandgrens. Een overgeslagen maand levert dus geen gat maar een langer venster,
en dat venster staat op de meetrij zelf ("14 in 62 dagen"). Wat er gebeurd is,
telt mee; alleen het detailniveau is grover.

## Wat u met de hand draait

### Inrichten

**`isms:eerste-ciso {email} {wachtwoord} {naam?}`**
Maakt het eerste, direct actieve CISO-account. Dit is de kip-en-ei-stap: een
account aanmaken vereist normaal een ingelogde CISO, en die is er bij een verse
installatie nog niet. Bestaat het e-mailadres al, dan weigert het commando, en
het wachtwoord moet aan dezelfde eis voldoen als elders: minimaal 12 tekens.

> Let op waar u dit intikt: het wachtwoord staat als argument op de
> commandoregel en belandt daarmee in de shell-historie. Wijzig het na de eerste
> aanmelding, of wis de regel.

**`isms:tweefactor-resetten {email}`**
Zet de tweefactorauthenticatie van één account terug. Daarna volgt bij de
volgende aanmelding opnieuw de instelprocedure, met een nieuwe respijtperiode.

Dit is niet hetzelfde als de knop *Tweefactor resetten* in het
gebruikersoverzicht — die doet hetzelfde, maar vanaf een scherm. Het commando
bestaat voor het geval dat er niemand meer bij dat scherm kan: de CISO die zijn
telefoon én zijn herstelcodes kwijt is. Zonder deze weg is de enige uitweg een
handmatige `UPDATE` op de database, en dat is precies het soort ingreep dat
buiten elke logging omgaat. Beide routes komen in de audit trail, met het
onderscheid erbij: vanaf een scherm staat de naam van de CISO erbij, vanaf de
commandoregel staat dat vermeld.

**`isms:bereid-auditcyclus-voor`**
Zet een volledige interne-auditcyclus op: programma, jaarplannen, dekkings­verdeling
over de norm en de geplande rondes. De auditor blijft open — die wijst u zelf toe.

| Optie | Wat het doet |
| --- | --- |
| `--start=` | Startdatum (jjjj-mm-dd; een jaartal wordt 1 januari). Standaard vandaag. |
| `--jaren=` | Aantal jaren in de cyclus. Standaard 3, of 1 bij `--voorbereiding`. |
| `--voorbereiding` | De opstartfase: één plan met een nulmeting over alles, zonder dekkingsverdeling. |
| `--naam=` | Naam van het programma. Standaard afgeleid van de aard en het venster. |
| `--activeer` | Zet het programma meteen op actief in plaats van concept. |
| `--forceer` | Ga door ook als de SoA nog niet volledig is beslist. |
| `--vervang` | Ruim een botsende bestaande cyclus eerst op. |

De `--voorbereiding`-variant bestaat omdat de echte auditcyclus pas begint ná de
certificeringsaudit; daarvóór is er één ronde die een nulmeting is en geen
oordeel.

**`isms:sync-auditobjecten`**
Brengt de audit-universe in lijn met de SoA: elke maatregel die van toepassing is
krijgt of houdt een auditobject, een control die dat niet meer is wordt inactief.
Idempotent, en het meldt hoeveel objecten nieuw zijn — dat zijn de controls die
mid-cyclus alsnog van toepassing werden en dus in geen enkel programma zitten.
Die melding ís het punt: zo wordt drift zichtbaar.

**`isms:maatregelen`**
Leest de maatregelcatalogus opnieuw in. Dit is het commando dat u draait nadat u
de normteksten hebt ingevoerd; zie [De normteksten
invoeren](/kennisbank/normteksten-invoeren). Het controleert het bestand eerst
volledig — is er iets mis, dan gaat er niets naar de database en hoort u wát er
mis is. Achteraf meldt het hoeveel maatregelen een eigen normtekst hebben en
hoeveel er nog op de meegeleverde mededeling staan, zodat u ziet of uw werk is
aangekomen. Idempotent, en het raakt uw SoA-beoordelingen niet aan.

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen controleren, niets naar de database schrijven. |

**`isms:overheidsmaatregelen`**
Alleen in het BIO-profiel. Leest de BIO-overheidsmaatregelen opnieuw in: de
nummering, de koppeling aan de beheersmaatregel, de status (geldend, vervallen of
verplaatst) en de reikwijdte van de Cyberbeveiligingswet. Net als bij de
maatregelcatalogus controleert het het bestand eerst volledig, en achteraf meldt
het hoeveel verplichtingen een eigen tekst dragen en hoeveel er nog niet
beoordeeld zijn.

Dit is ook het commando dat u draait nadat u de BIO-teksten in uw eigen
installatie hebt gezet — het systeem levert die niet mee, want de BIO staat onder
een licentie die dat niet toestaat. Zie [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer). Verhuist bij een nieuwe
BIO-uitgave de beoordeling van een verplaatst nummer mee naar zijn opvolger, en
markeert beoordelingen die ouder zijn dan een gewijzigde verplichting.

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen controleren, niets naar de database schrijven. |

**`isms:kenmerken`**
Leest de meegeleverde uitgangsclassificatie opnieuw in. Draait vanzelf bij elke
uitrol. Wijzigt daarbij een uitgangswaarde, dan maakt dit commando een taak aan
voor elke SoA-regel waar ú zelf een classificatie hebt vastgelegd — die regels
volgen het uitgangspunt namelijk niet meer, en zonder taak zou die correctie u
stilzwijgend passeren. Zie [De
maatregelclassificatie](/kennisbank/maatregelclassificatie).

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen tonen wat er zou wijzigen, niets schrijven. |

**`isms:capaciteiten {aan|uit|status}`**
Zet de vijfde attribuutdimensie van ISO 27002 aan of uit. Alleen zinvol als u de
norm bezit: het systeem levert die dimensie bewust niet mee, omdat de toewijzing
alleen in de norm staat. Zonder argument toont het commando de huidige stand.

### Controleren

**`isms:controleer-audittrail`**
Loopt de keten-hashes over de audit trail na: klopt elke schakel, en klopt de
inhoud van elke regel nog met zijn hash. Draait elke nacht vanzelf; met de hand
geeft u hem als u het zelf wilt zien, of als een auditor ernaar vraagt.

| Optie | Wat het doet |
| --- | --- |
| `--stil` | Alleen de slotregel. Zo staat hij in de planning. |
| `--vanaf=` | Begin bij dit regelnummer, na een bewuste verzegeling. |
| `--kop` | Druk alleen de huidige kophash af en stop. |

Bij een breuk meldt het commando het regelnummer, stopt het daar — alles ná een
breuk wijkt per definitie af — en eindigt het met een foutcode. De uitslag wordt
altijd vastgelegd, ook als alles klopt: dat de controle elke nacht heeft gelopen
is zelf het bewijs. Zie [De audit trail](de-audit-trail).

**`isms:controleer-hartslag`**
Kijkt of de geplande taken hierboven ook werkelijk gedraaid hebben. Staat de
machine een tijd uit, dan draaien ze niet, en het systeem haalt ze niet in — zonder
deze controle ziet een ISMS dat zes weken heeft stilgelegen er daarna precies zo
uit als een ISMS dat gewoon doordraaide.

| Optie | Wat het doet |
| --- | --- |
| `--stil` | Alleen de samenvatting. Zo staat hij in de planning en in de uitrol. |
| `--geen-taken` | Wel melden wat er gemist is, maar geen taken aanmaken. |

Niet elk gemist moment weegt even zwaar, en dat is het punt van dit commando. Een
herstart van een kwartier levert niets op. Een gemiste opruimtaak wordt gemeld,
maar de volgende nacht haalt hem alsnog in. Een gemiste **maandmeting** van een
toestand-KPI is onherstelbaar — de stand van 1 september is in oktober niet meer
op te vragen — en levert een taak op bij de betreffende KPI. Hetzelfde geldt voor
de jaarlijkse restrisico-snapshot.

Het commando haalt niets in. Een meetpunt met terugwerkende kracht zou de reeks
onbetrouwbaar maken; het gat wordt zichtbaar gemaakt, niet weggepoetst.

Hij draait op twee momenten: elke nacht als laatste taak, en bij elke uitrol —
die twee vangen verschillende dingen. De nachtelijke run merkt dat één taak
faalde; de uitrol merkt dat de hele machine weg was.

### Uitleveren

**`isms:exporteer`**
Schrijft het hele ISMS weg als leesbare Markdown-mapstructuur, bedoeld om over te
nemen in een ander systeem.

| Optie | Wat het doet |
| --- | --- |
| `--doel=` | Doelmap. Standaard `storage/app/exports`. |
| `--met-bewijs` | Kopieert de bewijsstukken en beleidsdocumenten mee in `_bewijs/`. |
| `--met-persoonsgegevens` | Toont volledige namen in plaats van initialen + rol. |

Standaard staan er initialen en rollen in plaats van namen. Dat is een bewuste
keuze voor de export, die als bestand rondgaat. Voor een **schermkopie** ligt dat
andersom: die maakt u terwijl de auditor naast u zit en de namen al op het scherm
ziet staan.

### Opruimen — hier goed kijken

**`isms:verwijder-auditdata`**
Verwijdert alle auditmanagement-gegevens voor een schone start. Vraagt eerst om
bevestiging.

| Optie | Wat het doet |
| --- | --- |
| `--bevestig` | Direct verwijderen, zonder de interactieve vraag. |
| `--met-trail` | Verwijder óók de audit-trail-regels van blok auditmanagement. |
| `--met-universe` | Verwijder óók de auditobjecten (clausules en maatregel-objecten). |

`--met-trail` is de zwaarste: die haalt bewijs weg dat er ooit audits waren, en
breekt de keten-hashes. Het commando verzegelt de keten daarna opnieuw en legt
vast dat het is gebeurd — maar de trail bewijst vanaf dat moment alleen nog wat
er ná die handeling gebeurde. Doe dit alleen bij het opnieuw inrichten van een
omgeving die nog niet in gebruik is.

> **`isms:demo-vul` wist eerst de héle database.**
>
> Dit commando vult het systeem met het FruitBV-demoscenario en begint met een
> volledige leegmaak. Het weigert te draaien buiten een local- of demo-omgeving,
> maar die grens is een vangnet en geen garantie: draai het nooit op een omgeving
> waar echte gegevens in staan.
>
> Opties: `--fixtures=` (andere fixturemap), `--stil` (alleen de samenvatting),
> `--ontgrendel` (een vergrendeling opheffen die na een afgebroken vulling is
> blijven hangen).

## Drie dingen die voor alle commando's gelden

- **Wat een commando wijzigt, komt in de audit trail.** Ook wat 's nachts
  gebeurt. Zie [De audit trail](de-audit-trail).
- **Onveranderlijke gegevens blijven onveranderlijk.** `isms:meet-kpis` en
  `isms:leg-restrisico-vast` schrijven metingen die daarna niet meer herrekend
  worden — twee keer draaien in dezelfde periode levert geen tweede meting op.
- **Draaien zonder gevolg is de norm, niet de uitzondering.** Op de twee
  opruimcommando's hierboven na kunt u elk commando opnieuw geven zonder dat er
  iets dubbel gebeurt. Twijfelt u of een nachtelijke taak is gelopen, dan is hem
  nog eens draaien het goedkoopste antwoord.
