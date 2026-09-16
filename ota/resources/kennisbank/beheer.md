# Beheer: de artisan-commando's

Alle handelingen die dit ISMS buiten de schermen om uitvoert, lopen via de
commandoregel. De commando's draaien vanuit de applicatiemap:

```bash
cd /pad/naar/ota
php artisan isms:...
```

Er zijn twee soorten commando's. Sommige commando's **draaien automatisch**: dat
zijn de nachtelijke onderhoudstaken. Andere commando's worden **handmatig**
gegeven: bij het inrichten, bij het uitleveren of bij het opruimen.

## Wat vanzelf draait

| Wanneer | Commando | Wat het doet |
| --- | --- | --- |
| dagelijks 01:00 | `isms:verval-gebruikersaccounts` | Accounts waarvan de vervaldatum is bereikt, worden gedeactiveerd. |
| dagelijks 01:30 | `isms:archiveer-bewijsstukken` | Bewijs waarvan de bewaartermijn is verstreken, krijgt de status *gearchiveerd*. Er wordt niets verwijderd. |
| dagelijks 01:15 | `isms:herinner-tweefactor` | Het commando mailt gebruikers van wie de termijn voor het instellen van de tweede factor bijna of net is verstreken. Er gaat één mail uit vóór het verstrijken en één mail erna. |
| dagelijks 01:45 | `isms:controleer-audittrail` | De keten-hashes over de audit trail worden gecontroleerd. De uitslag wordt vastgelegd, ook als alles klopt. |
| dagelijks 02:00 | `isms:genereer-taken` | Het commando maakt terugkerende taken aan uit de sjablonen, plus signalen voor achterstallige retouren, SoA-beoordelingen en leesbevestigingen. |
| dagelijks 02:15 | `isms:verloop-taken` | Taken waarvan de deadline is verstreken, krijgen de status *verlopen* en escaleren één niveau. |
| dagelijks 02:30 | `isms:schoon-raadplegingen` | Registraties van bewijs-downloads die ouder zijn dan de bewaartermijn, worden verwijderd. Dit commando verwijdert de gegevens definitief. |
| dagelijks 02:45 | `isms:controleer-hartslag` | Het commando controleert of alle taken hierboven werkelijk hebben gedraaid, en meldt wat er is gemist. |
| maandelijks, de 1e om 03:00 | `isms:meet-kpis` | De maandelijkse KPI-meting legt per KPI de teller en de noemer onveranderlijk vast. |
| jaarlijks, 31 december 23:00 | `isms:leg-restrisico-vast` | Het commando legt de jaarlijkse restrisico-snapshot per control vast. |

De ketencontrole draait vóór de opruimtaken. Daardoor controleert ze de trail van
de vorige dag en niet een half bijgewerkte trail van de lopende nacht.

De volgorde van de twee taakcommando's is bewust gekozen. `genereer-taken` draait
vóór `verloop-taken`. Een taak die 's nachts ontstaat met een deadline in het
verleden, wordt daardoor dezelfde nacht als verlopen gemarkeerd. Anders zou de
taak een dag lang ten onrechte de status open hebben.

**Deze taken staan in de audit trail op naam van "Systeem (geplande taak)".** Er
is geen ingelogde gebruiker. Dat hoort zichtbaar te zijn, en de handeling hoort
niet te worden toegeschreven aan de gebruiker die toevallig als laatste iets
deed.

### De planning zelf

De planning staat in de code (`routes/console.php`) en niet in de crontab. De
crontab bevat één regel die de planner elke minuut start. Zo staat het schema op
één plek, in plaats van op twee plekken die uit elkaar kunnen lopen.

```bash
php artisan schedule:list     # wat staat er gepland, en wanneer
php artisan schedule:test     # één commando kiezen en nu draaien
```

### Een gemiste nacht

Alle nachtelijke taken zijn **idempotent**: twee keer draaien levert geen dubbele
taken en geen dubbele metingen op. Een gemiste nacht is daarom handmatig in te
halen.

`isms:meet-kpis` heeft een aanvullende eigenschap. De KPI's die *gebeurtenissen
in een periode* tellen, zoals nieuwe risico's, statusovergangen en scoredalingen
zonder bewijs, rekenen vanaf het einde van de vorige meetperiode en niet vanaf
een vaste maandgrens. Een overgeslagen maand levert dus geen gat op, maar een
langer venster. Dat venster staat op de meetrij zelf ("14 in 62 dagen"). Alle
gebeurtenissen tellen mee; alleen het detailniveau is grover.

## Wat handmatig wordt gedraaid

### Inrichten

**`isms:eerste-ciso {email} {wachtwoord} {naam?}`**
Dit commando maakt het eerste CISO-account aan, dat direct actief is. Deze stap
is nodig omdat het aanmaken van een account normaal een ingelogde CISO vereist,
en een nieuwe installatie heeft nog geen CISO. Als het e-mailadres al bestaat,
weigert het commando. Het wachtwoord moet aan dezelfde eis voldoen als elders:
minimaal 12 tekens.

> Het wachtwoord staat als argument op de commandoregel en komt daardoor in de
> shell-historie terecht. Het wachtwoord hoort daarom na de eerste aanmelding te
> worden gewijzigd, of de regel hoort uit de historie te worden gewist.

**`isms:tweefactor-resetten {email}`**
Dit commando zet de tweefactorauthenticatie van één account terug. Bij de
volgende aanmelding volgt opnieuw de instelprocedure, met een nieuwe
respijtperiode.

De knop *Tweefactor resetten* in het gebruikersoverzicht doet hetzelfde, maar dan
vanaf een scherm. Het commando bestaat voor de situatie waarin niemand dat
scherm meer kan bereiken, bijvoorbeeld wanneer de CISO zowel de telefoon als de
herstelcodes kwijt is. Zonder dit commando is de enige uitweg een handmatige
`UPDATE` op de database, en zo'n ingreep valt buiten elke logging. Beide routes
komen in de audit trail, met het onderscheid erbij: bij de schermroute staat de
naam van de CISO vermeld, en bij de commandoregel staat vermeld dat de handeling
via de commandoregel is uitgevoerd.

**`isms:inlogmethode-wachtwoord {email}`**
Dit commando zet een account dat via de identiteitsprovider inlogt terug op een
wachtwoord. De koppeling wordt verwijderd en het commando drukt een link af
waarmee de gebruiker een wachtwoord instelt. Die link werkt zonder mailserver en
is 60 minuten geldig.

Het commando bestaat voor de situatie waarin de identiteitsprovider wegvalt,
bijvoorbeeld door een verlopen client secret of een beëindigd tenant. De CISO
kan dan vaak zelf niet inloggen. Daarom heeft deze handeling geen knop in het
gebruikersoverzicht. De audit trail vermeldt dat de wijziging via de
commandoregel is uitgevoerd.

**`isms:bereid-auditcyclus-voor`**
Dit commando zet een volledige interne-auditcyclus op: het programma, de
jaarplannen, de verdeling van de dekking over de norm en de geplande rondes. De
auditor wordt niet ingevuld; die wordt handmatig toegewezen.

| Optie | Wat het doet |
| --- | --- |
| `--start=` | Startdatum (jjjj-mm-dd; een jaartal wordt 1 januari). Standaard vandaag. |
| `--jaren=` | Aantal jaren in de cyclus. Standaard 3, of 1 bij `--voorbereiding`. |
| `--voorbereiding` | De opstartfase: één plan met een nulmeting over alles, zonder dekkingsverdeling. |
| `--naam=` | Naam van het programma. Standaard afgeleid van de aard en het venster. |
| `--activeer` | Zet het programma direct op actief in plaats van concept. |
| `--forceer` | Gaat door, ook als de SoA nog niet volledig is beslist. |
| `--vervang` | Ruimt eerst een botsende bestaande cyclus op. |

De variant `--voorbereiding` bestaat omdat de echte auditcyclus pas begint na de
certificeringsaudit. Vóór die audit is er één ronde, en die ronde is een
nulmeting en geen oordeel.

**`isms:sync-auditobjecten`**
Dit commando brengt de audit-universe in lijn met de SoA. Elke maatregel die van
toepassing is, krijgt of houdt een auditobject. Een control die niet meer van
toepassing is, wordt inactief. Het commando is idempotent en meldt hoeveel
objecten nieuw zijn. Die nieuwe objecten zijn de controls die tijdens de cyclus
alsnog van toepassing werden en daardoor in geen enkel programma zitten. Die
melding is het doel van het commando, omdat afwijkingen daardoor zichtbaar
worden.

**`isms:maatregelen`**
Dit commando leest de maatregelcatalogus opnieuw in. Het wordt gedraaid nadat de
normteksten zijn ingevoerd; zie [De normteksten
invoeren](/kennisbank/normteksten-invoeren). Het commando controleert eerst het
volledige bestand. Als er een fout in staat, wordt er niets naar de database
geschreven en meldt het commando wat er fout is. Na afloop meldt het commando
hoeveel maatregelen een eigen normtekst hebben en hoeveel maatregelen nog de
meegeleverde mededeling bevatten. Zo is te zien of de ingevoerde teksten zijn
verwerkt. Het commando is idempotent en laat de SoA-beoordelingen ongemoeid.

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen controleren, niets naar de database schrijven. |

**`isms:overheidsmaatregelen`**
Dit commando bestaat alleen in het BIO-profiel. Het leest de
BIO-overheidsmaatregelen opnieuw in: de nummering, de koppeling aan de
beheersmaatregel, de status (geldend, vervallen of verplaatst) en de reikwijdte
van de Cyberbeveiligingswet. Net als bij de maatregelcatalogus controleert het
commando eerst het volledige bestand. Na afloop meldt het hoeveel verplichtingen
een eigen tekst hebben en hoeveel verplichtingen nog niet zijn beoordeeld.

Dit commando wordt ook gedraaid nadat de BIO-teksten in de eigen installatie zijn
gezet. Het systeem levert die teksten niet mee, omdat de licentie van de BIO dat
niet toestaat. Zie [Verantwoording en
disclaimer](/kennisbank/verantwoording-en-disclaimer). Bij een nieuwe BIO-uitgave
verhuist het commando de beoordeling van een verplaatst nummer naar de opvolger,
en markeert het beoordelingen die ouder zijn dan een gewijzigde verplichting.

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen controleren, niets naar de database schrijven. |

**`isms:kenmerken`**
Dit commando leest de meegeleverde uitgangsclassificatie opnieuw in. Het draait
automatisch bij elke uitrol. Als daarbij een uitgangswaarde wijzigt, maakt het
commando een taak aan voor elke SoA-regel waarop de organisatie zelf een
classificatie heeft vastgelegd. Die regels volgen het uitgangspunt namelijk niet
meer, en zonder taak zou de correctie ongemerkt blijven. Zie [De
maatregelclassificatie](/kennisbank/maatregelclassificatie).

| Optie | Wat het doet |
| --- | --- |
| `--controleer` | Alleen tonen wat er zou wijzigen, niets schrijven. |

**`isms:capaciteiten {aan|uit|status}`**
Dit commando zet de vijfde attribuutdimensie van ISO 27002 aan of uit. Dat is
alleen zinvol voor een organisatie die de norm bezit. Het systeem levert die
dimensie bewust niet mee, omdat de toewijzing alleen in de norm staat. Zonder
argument toont het commando de huidige stand.

### Controleren

**`isms:controleer-audittrail`**
Dit commando controleert de keten-hashes over de audit trail: klopt elke schakel,
en klopt de inhoud van elke regel nog met de bijbehorende hash. Het commando
draait elke nacht automatisch. Handmatig draaien is nuttig om de uitslag zelf te
zien, of wanneer een auditor ernaar vraagt.

| Optie | Wat het doet |
| --- | --- |
| `--stil` | Alleen de slotregel. Zo staat het commando in de planning. |
| `--vanaf=` | Begin bij dit regelnummer, na een bewuste verzegeling. |
| `--kop` | Druk alleen de huidige kophash af en stop. |

Bij een breuk meldt het commando het regelnummer, stopt het op die plek en
eindigt het met een foutcode. Het commando stopt omdat alle regels na een breuk
per definitie afwijken. De uitslag wordt altijd vastgelegd, ook als alles klopt,
omdat het feit dat de controle elke nacht heeft gedraaid zelf het bewijs is. Zie
[De audit trail](de-audit-trail).

**`isms:extern-inloggen-controleren`**
Dit commando controleert de koppeling met de identiteitsprovider zonder dat er
een testaccount nodig is. Het leest de configuratie, haalt het
discovery-document en de sleutelset op, en drukt de redirect-URI af die in de
app-registratie bij de identiteitsprovider moet staan. Een verschil in die URI
achter de TLS-terminatie wordt zo zichtbaar vóór de eerste gebruiker zich
aanmeldt. Het client secret zelf is alleen met een echte login te controleren.
Als `OIDC_CLIENT_SECRET_VERLOOPT_OP` is ingevuld, waarschuwt het commando 30
dagen voordat het secret verloopt.

De instellingen zelf, en wat er per provider anders is, staan in
[Inloggen via een identiteitsprovider](inloggen-via-een-identiteitsprovider).

**`isms:controleer-hartslag`**
Dit commando controleert of de geplande taken hierboven werkelijk hebben
gedraaid. Als de machine een tijd uit staat, draaien de taken niet, en het
systeem haalt ze niet in. Zonder deze controle ziet een ISMS dat zes weken heeft
stilgelegen er daarna precies hetzelfde uit als een ISMS dat gewoon heeft
doorgedraaid.

| Optie | Wat het doet |
| --- | --- |
| `--stil` | Alleen de samenvatting. Zo staat het commando in de planning en in de uitrol. |
| `--geen-taken` | Wel melden wat er gemist is, maar geen taken aanmaken. |

Niet elk gemist moment weegt even zwaar, en dat onderscheid is het doel van dit
commando. Een herstart van een kwartier levert geen melding op. Een gemiste
opruimtaak wordt gemeld, maar de volgende nacht haalt die taak de achterstand
alsnog in. Een gemiste **maandmeting** van een toestand-KPI is niet te herstellen,
omdat de stand van 1 september in oktober niet meer op te vragen is. Zo'n gemiste
meting levert een taak op bij de betreffende KPI. Hetzelfde geldt voor de
jaarlijkse restrisico-snapshot.

Het commando haalt niets in. Een meetpunt met terugwerkende kracht zou de reeks
onbetrouwbaar maken. Het gat wordt daarom zichtbaar gemaakt en niet verborgen.

Het commando draait op twee momenten: elke nacht als laatste taak, en bij elke
uitrol. Die twee momenten detecteren verschillende problemen. De nachtelijke run
detecteert dat één taak is mislukt. De uitrol detecteert dat de hele machine
niet beschikbaar was.

### Uitleveren

**`isms:exporteer`**
Dit commando schrijft het hele ISMS weg als een leesbare mapstructuur met
Markdown-bestanden, bedoeld om over te nemen in een ander systeem.

| Optie | Wat het doet |
| --- | --- |
| `--doel=` | Doelmap. Standaard `storage/app/exports`. |
| `--met-bewijs` | Kopieert de bewijsstukken en beleidsdocumenten mee in `_bewijs/`. |
| `--met-persoonsgegevens` | Toont volledige namen in plaats van initialen + rol. |

Standaard bevat de export initialen en rollen in plaats van namen. Dat is een
bewuste keuze voor de export, omdat die als bestand wordt verspreid. Voor een
**schermkopie** geldt het omgekeerde. Een schermkopie wordt gemaakt terwijl de
auditor meekijkt en de namen al op het scherm ziet.

### Opruimen: hier is voorzichtigheid nodig

**`isms:verwijder-auditdata`**
Dit commando verwijdert alle gegevens van auditmanagement voor een schone start.
Het commando vraagt eerst om bevestiging.

| Optie | Wat het doet |
| --- | --- |
| `--bevestig` | Direct verwijderen, zonder de interactieve vraag. |
| `--met-trail` | Verwijdert ook de audit-trail-regels van blok auditmanagement. |
| `--met-universe` | Verwijdert ook de auditobjecten (clausules en maatregel-objecten). |

`--met-trail` is de zwaarste optie. Die optie verwijdert het bewijs dat er ooit
audits waren en breekt de keten-hashes. Het commando verzegelt de keten daarna
opnieuw en legt vast dat de verwijdering heeft plaatsgevonden. Vanaf dat moment
bewijst de trail alleen nog wat er na die handeling is gebeurd. Deze optie is
alleen bedoeld voor het opnieuw inrichten van een omgeving die nog niet in
gebruik is.

> **`isms:demo-vul` wist eerst de volledige database.**
>
> Dit commando vult het systeem met het FruitBV-demoscenario en begint met het
> volledig leegmaken van de database. Het commando weigert te draaien buiten een
> local- of demo-omgeving. Die grens is een vangnet en geen garantie. Het
> commando mag nooit draaien op een omgeving met echte gegevens.
>
> Opties: `--fixtures=` (andere fixturemap), `--stil` (alleen de samenvatting),
> `--ontgrendel` (een vergrendeling opheffen die na een afgebroken vulling is
> blijven staan).

## Drie dingen die voor alle commando's gelden

- **Wat een commando wijzigt, komt in de audit trail.** Dat geldt ook voor
  handelingen die 's nachts plaatsvinden. Zie [De audit trail](de-audit-trail).
- **Onveranderlijke gegevens blijven onveranderlijk.** `isms:meet-kpis` en
  `isms:leg-restrisico-vast` schrijven metingen die daarna niet meer worden
  herrekend. Twee keer draaien in dezelfde periode levert geen tweede meting op.
- **Draaien zonder bijwerkingen is de norm en niet de uitzondering.** Met
  uitzondering van de twee opruimcommando's hierboven kan elk commando opnieuw
  worden gegeven zonder dat er iets dubbel gebeurt. Bij twijfel of een
  nachtelijke taak heeft gedraaid, is de taak nog een keer draaien de goedkoopste
  controle.
