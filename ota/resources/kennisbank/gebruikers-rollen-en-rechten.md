# Gebruikers, rollen en rechten

Toegang is in dit ISMS geen verzameling `if`-jes in de code, maar **data**: een
rij in `rol_permissies` die zegt "rol X mag op blok Y niveau Z". Wie de
rechtenmatrix wil veranderen, verandert rijen — geen PHP. Dat is de kern; de
rest van dit artikel is uitwerking en de eerlijke lijst van wat het model *niet*
doet.

## Het model in één zin

> Een **gebruiker** heeft een of meer **rollen**; een rol heeft per **blok** een
> **niveau**; de generieke autorisatiecheck `heeft-niveau` toetst (blok, niveau)
> tegen die rijen.

Er zijn geen Policy-classes per model en geen rolnamen in views. Overal in de
applicatie staat dezelfde vraag: *heeft deze gebruiker op dit blok minstens dit
niveau?*

## De vier begrippen

### 1. Rol

Vijf rollen, referentiedata (geseed, niet aan te maken via het scherm):

| Rol | Bedoeld voor |
| --- | --- |
| **CISO** | Eigenaar van het ISMS: risico's, maatregelen, beleid, gebruikers |
| **Medewerker** | Voert eigen taken uit, meldt incidenten, bevestigt beleid |
| **Auditor** | Interne of externe auditor, read-only inzage voor auditbewijs |
| **Management** | Directie: stelt vast wat de CISO opstelt. Géén beheerrechten |
| **Administrator** | Technisch beheer van de installatie. Géén toegang tot het ISMS |

Een gebruiker kan meerdere rollen hebben; de rechten zijn dan de **vereniging**
ervan (de gunstigste wint). De combinatie gebruiker × rol is uniek.

**Op één uitzondering: de Administrator gaat met geen enkele andere rol samen.**
Die rol staat buiten het ISMS — hij mag toetsbestanden plaatsen en verder niets,
en heeft op geen enkel ISMS-blok een rij. Zou één account beide petten dragen,
dan kan dezelfde persoon een bestand plaatsen én het als ISMS-gebruiker openen,
en dat is precies wat de scheiding moet voorkomen. Beheert u zelf de installatie,
maak daar dan een tweede account voor aan. Het systeem weigert de combinatie.

Dat is de enige harde onverenigbaarheid in dit model. Voor de vier ISMS-rollen
geldt onverminderd dat functiescheiding een organisatorische keuze is die het
systeem faciliteert en niet afdwingt: bij een kleine organisatie draagt iemand
soms twee petten, en het gebruikersoverzicht maakt dat zichtbaar zodat een
auditor het kan wegen.

### 2. Blok

Een blok is een functiegebied van het ISMS, niet een scherm. `identity-access`,
`risico-soa`, `beleid-maatregelbeheer`, `auditmanagement`, … — de codes komen
één-op-één uit de deelproducten. Rechten zitten dus op **domein**detailniveau, niet
per pagina en niet per record.

### 3. Niveau — een ladder

`lezen` → `uitvoeren` → `muteren`

Oplopend: een hoger niveau impliceert alle lagere. Daarom heeft de CISO aan één
rij `muteren` genoeg en hoeft er geen aparte `lezen`-rij bij.

`uitvoeren` is het interessante niveau: het betekent "mag hier schrijven, maar
alleen aan het eigene" — eigen taak afwerken, eigen incident melden, eigen
leesbevestiging afgeven, eigen bewijs uploaden.

### 4. Twee niveaus bewust buiten de ladder

`exporteren` en `goedkeuren` staan **naast** de ladder, niet erboven. Allebei
impliceren ze alleen `lezen`.

**`exporteren`** is géén "meer dan muteren", maar een andere soort bevoegdheid:
data naar buiten brengen. Zou het in de ladder staan, dan kreeg de Auditor — de
rol die per definitie onafhankelijk moet zijn — muteerrechten cadeau. Wie mag
exporteren mag per definitie inzien.

**`goedkeuren`** is vaststellen, en dat is een andere sóórt bevoegdheid dan
bewerken — geen grotere hoeveelheid ervan.
Omdat het losstaat, is functiescheiding
mogelijk: de CISO stelt op (`muteren`), Management stelt vast (`goedkeuren`).

Vijf acties toetsen erop: beleid publiceren, een scope-versie activeren, een
restrisico boven de acceptatiedrempel accepteren, de risicocriteria vaststellen
(de risk appetite, de acceptatiedrempel en de beoordelingsschaal — zie
*Issues en risico's*), en de directiebeoordeling als gehouden vastleggen.

Bij de risicocriteria loopt de functiescheiding het verst door: daar ligt ook
het *afwijzen* — een ingediende versie terugsturen naar concept — bij
Management. De CISO houdt zijn weg terug via een nieuw concept.

**Let op wat `exporteren` vandaag echt doet.** Er is nog geen exportknop in de
applicatie, en dus geen scherm dat op dit niveau toetst. De enige plek die het
uitleest is `Recordscope::magAllesZien()` — het functioneert nu als
**rolmarkering**: "deze rol ziet alle rijen, niet alleen de eigene". De naam
beschrijft de bedoelde bevoegdheid, niet de huidige functie. Zodra er een
exportfunctie in de applicatie komt, is dit het niveau waar die achter hoort.
Om diezelfde reden krijgt ook
Management het niveau op bewijs en incidenten: zonder die markering zou een
directeur er alleen zijn eigen rijen zien, en dat is nu juist de input voor de
directiebeoordeling.

De export die er wél is, loopt buiten dit model om: `php artisan isms:exporteer`
schrijft het ISMS als Markdown-boom weg (persoonsgegevens standaard
geanonimiseerd). Dat commando kent geen autorisatiecheck — shelltoegang is daar
de autorisatie. En het dichtstbijzijnde "data naar buiten" in de UI, de download
of preview van een bewijsstuk, zit achter `lezen` op
`bewijsrepository-audit-trail`, niet achter `exporteren`.

## Het ontbreken van een rij is de weigering

Er is geen niveau `geen`. Staat er voor (rol, blok) niets, dan is er geen
toegang: het menu-item verdwijnt, de route geeft 403. Zo heeft de Medewerker
bijvoorbeeld géén rij op `risico-soa` — geen risicoregister, geen SoA. Dat is een
bewuste keuze, geen omissie.

## Drie lagen toegang

De autorisatiecheck op blokniveau alleen is te grof. Er liggen drie lagen over
elkaar heen:

### Laag 1 — de autorisatiecheck op blokniveau (`heeft-niveau`)

Zit op de route én nog eens in het component. Die herhaling is opzet: de pagina
is meestal bereikbaar met `lezen`, maar de knoppen erop eisen `muteren`. Elke
actiemethode toetst daarom zelf opnieuw. Een Livewire-actie is een HTTP-request —
"de knop staat er niet" is geen beveiliging.

### Laag 2 — record-scoping (`Recordscope::magAllesZien`)

Op blokken waar de Medewerker `uitvoeren` heeft, volstaat de ladder niet:
`uitvoeren` impliceert `lezen`, dus een lees-check zou hem andermans gegevens
tonen. De scope draait het daarom **positief** om: wie `muteren` (CISO),
`goedkeuren` (Management) of `exporteren` (Auditor) heeft, ziet alles; de rest
ziet alleen de eigen rijen.

Precies hiervoor krijgt de Auditor `exporteren` op elk blok met record-scoping.
Zonder dat zou het onderscheid alleen negatief te maken zijn ("heeft lezen maar
niet uitvoeren") — en dat klapt stilzwijgend om zodra de rechtenmatrix wijzigt.

### Laag 3 — record-guards op het model

Waar de norm eigenaarschap of onafhankelijkheid eist, beslist het record zelf:

- **Auditronde** — de bevindingen van een interne ronde legt *de toegewezen
  auditor* vast, en alleen zolang de ronde in uitvoering is. Na afronding
  niemand meer, ook de CISO niet. Dat is onafhankelijkheid *afdwingen* in plaats
  van documenteren.
- **Corrigerende maatregel** — een Medewerker die eigenaar is, mag zijn eigen
  maatregel afmelden, terwijl de rest van de CAPA-cyclus `muteren` vraagt.

Deze guards staan bewust in het model, niet in de Gate: ze hangen van de *rij*
af, niet van de rol.

## De rechtenmatrix in vogelvlucht

| Blok | CISO | Medewerker | Auditor | Management |
| --- | --- | --- | --- | --- |
| Identity, access & rollen | muteren | lezen | lezen | **—** |
| Context & scope | muteren | lezen | lezen | **goedkeuren** |
| Assets & classificatie | muteren | lezen | lezen | lezen |
| Risico & SoA | muteren | — | lezen | **goedkeuren** |
| Bewijs & audit trail | muteren | uitvoeren | lezen + exporteren | uitvoeren + exporteren |
| Taken & workflow | muteren | uitvoeren | lezen + exporteren | uitvoeren |
| Beleid & maatregelbeheer | muteren | uitvoeren | lezen + exporteren | uitvoeren + **goedkeuren** |
| Incidenten & afwijkingen | muteren | uitvoeren | lezen + exporteren | uitvoeren + exporteren |
| Leveranciers & derdenrisico | muteren | — | lezen | lezen |
| Bewustzijn & training | muteren | uitvoeren | lezen + exporteren | uitvoeren |
| Auditmanagement | muteren | — | lezen + exporteren | lezen |
| Management review | muteren | — | lezen | **goedkeuren** |
| Notificaties & integraties | muteren | — | lezen | lezen |

Drie patronen zijn hier zichtbaar. **Melden en afwerken staan laag** (`uitvoeren`
voor incidenten, taken, bewijs, leesbevestigingen): drempels op melden leveren
minder meldingen op, niet minder incidenten. **`exporteren` verschijnt precies
daar waar record-scoping speelt** — het is er niet alleen om te exporteren, maar
om wie alles mag zien te onderscheiden van wie alleen het eigene ziet. En
**Management heeft nergens `muteren`**: dat is geen omissie maar de reden dat de
rol bestaat. Op taken en training staat hij als gewone medewerker in de rij —
een directeur die zijn eigen e-learning niet hoeft te doen, is geen goed
voorbeeld. Op `identity-access` staat hij helemaal niet: geen gebruikersbeheer.

De matrix is geseed vanuit `RolPermissieSeeder`; de canonieke bron is
`deelproducten/01-identity-access.md` §4.

## Twee routes staan bewust buiten het model

De **kennisbank** en het **eigen profiel** (`/settings`) kennen geen
blok-permissie: naslag en je eigen wachtwoord zijn er voor elke ingelogde
gebruiker. Alles daarbuiten loopt door een autorisatiecheck.

## De levensloop van een account

```
              uitnodigen              wachtwoord instellen
   (niets)  ─────────────►  uitgenodigd  ─────────────►  actief
                                                          │  │
   5 mislukte pogingen / 15 min, of CISO blokkeert  ◄─────┘  │
                     geblokkeerd ──── CISO heft op ──────────┤
                                                             │
            CISO deactiveert, of vervaldatum bereikt         │
                    gedeactiveerd  ◄────────────────────────-┘
```

- **Uitnodigen** (CISO): naam, e-mail, rol, optioneel afdeling en vervaldatum.
  Het account krijgt een onbruikbaar random wachtwoord en status *uitgenodigd*.
  De uitgenodigde stelt via de link zijn wachtwoord in en koppelt **in datzelfde
  scherm** meteen zijn authenticator-app; hij is toch al achter zijn scherm bezig.
  Lukt dat niet — telefoon niet bij de hand — dan is het account gewoon actief en
  valt hij terug op de respijtperiode hieronder.
- **De uitnodigingslink** is een signed URL, 7 dagen geldig, met een token dat
  is afgeleid van de wachtwoordhash. Zodra de uitgenodigde een eigen wachtwoord
  instelt verandert die hash en is de link vanzelf verbruikt — geen aparte
  tokentabel met opruimlogica. Mislukt de mail, dan blijft het account bestaan en
  is er een knop *Uitnodiging opnieuw versturen*.
- **Een typefout in het adres** herstelt u met *Uitnodiging corrigeren*, naast
  *opnieuw versturen*. U past naam en e-mailadres aan, en het systeem doet er in
  dezelfde beweging iets bij dat u niet zou moeten hoeven onthouden: **de oude
  link sterft**. Dat is geen extra voorzichtigheid maar noodzaak — het token
  hangt aan de wachtwoordhash, dus zonder die rotatie houdt degene die de mail op
  het foute adres kreeg zeven dagen lang een werkende link naar het account, en
  zou *opnieuw versturen* daarna diezelfde link naar het juiste adres sturen.
  Na een correctie gaat er altijd meteen een nieuwe uitnodiging uit.

  De knop staat er **alleen bij status *uitgenodigd***, en dat is de kern van de
  regeling. Was het adres fout, dan heeft de bedoelde persoon nooit kunnen
  accepteren, dus staat het account per definitie nog open. Staat het op
  *actief*, dan heeft iemand met dát adres een wachtwoord ingesteld, en gelden
  andere regels — zie het volgende punt.

  Hoort het nieuwe adres al bij een ander account, dan zegt de melding bij wie.
  Dat adres wordt niet vrijgemaakt, ook niet als dat account gedeactiveerd is:
  de audit trail van dat account hangt aan die identiteit, en het adres is vaak
  nog een echte mailbox.
- **Het adres van een actief account wijzigt u met *E-mailadres wijzigen*.** Een
  medewerker die trouwt, een domein dat migreert, een adres dat pas na maanden
  fout blijkt: dat zijn geen fouten waarvoor u een account hoeft weg te gooien.
  Dat weggooien zou ook duur zijn — aan een account hangen taken, bewijsstukken,
  leesbevestigingen, trainingsresultaten en het personeelsdossier, en een nieuw
  account erft daar niets van.

  Deze knop werkt **tegenovergesteld aan *Uitnodiging corrigeren***, en dat
  verschil is het hele punt:

  | | *Uitnodiging corrigeren* | *E-mailadres wijzigen* |
  | --- | --- | --- |
  | bij welke status | uitgenodigd | actief |
  | wanneer geldt het nieuwe adres | meteen | pas na bevestiging op het nieuwe adres |
  | wachtwoord | wordt vervangen (de oude link moet sterven) | blijft ongewijzigd |
  | tweede factor, lopende sessies | n.v.t. | blijven ongewijzigd |

  Bij een ongebruikt account kost een typefout niets. Bij een account dat in
  gebruik is zou diezelfde typefout de gebruiker buitensluiten: geen inlog, geen
  wachtwoordherstel, geen notificaties. Daarom verandert er niets tot er op het
  nieuwe adres is bevestigd. De link is 7 dagen geldig; zolang er niets gebeurt,
  werkt het huidige adres gewoon door.

  **Het huidige adres krijgt bericht** zodra u de wijziging aanvraagt, met het
  nieuwe adres half zichtbaar — genoeg om te zien of het domein klopt. Dat is de
  controle op de andere fout: iemand die u een adreswijziging aanpraat. De
  bevestiging op het nieuwe adres vangt uw typefouten, dit bericht vangt de
  gevallen waarin het verzoek zelf niet deugde.

  Zolang de wijziging loopt staat dat in de lijst onder de naam, met een knop
  *Wijziging intrekken*. Blijkt achteraf dat een vreemde erachter zat, dan is
  intrekken niet genoeg en is **Blokkeren** het antwoord.
- **Uitnodigingen die niets opleveren melden zichzelf.** Bij een uitgenodigd
  account staat in de lijst wanneer de uitnodiging is verstuurd, en of de link
  inmiddels verlopen is; boven de tabel staat het aantal. Dat vangt de typefouten
  waarover niemand belt — een adres dat netjes bounct levert nooit een
  telefoontje op, en het account blijft anders eindeloos open staan.
- **Bij een adres dat lijkt op een bekend domein** verschijnt onder het veld:
  *Bedoelde u @fruitbv.nl?* Alleen bij een domein dat één of twee tekens afwijkt
  van een domein dat al bij minstens twee accounts in gebruik is. Geen blokkade
  en geen bevestiging — u negeert het als u een externe uitnodigt. Er wordt
  bewust **niet** gewaarschuwd voor een onbekend domein: een auditor of
  leverancier heeft er legitiem een, en een melding die de helft van de tijd
  onterecht is wordt weggeklikt zonder gelezen te worden.
- **Vervaldatum** — de enige statusovergang zónder CISO-handeling. Een dagelijkse
  taak (`isms:verval-gebruikersaccounts`, 01:00) deactiveert actieve accounts
  waarvan `vervalt_op` bereikt is. Bedoeld voor precies dat tijdelijke
  auditor-account dat anders onopgemerkt actief blijft.
- **Het wachtwoord** moet minimaal **12 tekens** zijn. Verder niets: geen
  verplichte hoofdletters, cijfers of symbolen. Lengte doet meer tegen raden dan
  verplichte tekensoorten, en die laatste leveren vooral `Wachtwoord2026!` op —
  één patroon dat elke aanvaller kent. Er is geen verplichte periodieke
  wijziging; die dwingt vooral kleine varianten op een oud wachtwoord af.
- **Tweefactorauthenticatie is verplicht**, voor elke rol. Na uw wachtwoord
  vraagt het systeem om een code van zes cijfers uit een authenticator-app. U
  krijgt bij het instellen acht **herstelcodes** — elk één keer bruikbaar, voor
  als u uw telefoon niet bij de hand heeft. Bent u die ook kwijt, dan zet de
  CISO uw tweede factor terug; dat komt in de audit trail te staan.

  Wie de app niet meteen bij de uitnodiging koppelt, krijgt veertien dagen
  respijt, geteld vanaf zijn eerste aanmelding. In die periode staat er een
  melding op elke pagina en gaan er twee e-mails uit: één een paar dagen vóór de
  termijn en één zodra hij verstreken is. Daarna komt u niet verder dan het
  instelscherm — maar u helpt uzelf daar, er is geen beheerder voor nodig. Er is bewust geen uitschakelknop: bij een
  nieuwe telefoon kiest u *ander apparaat koppelen*, en het oude apparaat blijft
  werken tot de nieuwe koppeling bevestigd is.
- **Een foute verificatiecode blokkeert uw account niet.** Vijf pogingen per
  kwartier, daarna moet u opnieuw inloggen. Blokkeren op een typefout of een
  telefoon die een halve minuut voorloopt, levert alleen werk op voor de CISO en
  geen enkele veiligheidswinst — raden op zes cijfers komt met die limiet toch
  nergens. De poging zelf wordt wél vastgelegd, en apart van een verkeerd
  wachtwoord: *wachtwoord goed, tweede factor fout* is het signaal dat een
  wachtwoord gelekt is.
- **Blokkade, automatisch** — 5 mislukte pogingen binnen 15 minuten blokkeert een
  *actief* account. Alleen de CISO heft dat op; er is geen automatische
  ontgrendeling na afkoeling. Let op de keerzijde: de teller loopt op het
  **ingevoerde e-mailadres**, dus wie een adres kent kan iemand eruit werken. Dat
  is de bewuste ruil voor een harde grens tegen wachtwoord-raden; de weg terug is
  een telefoontje naar de CISO.
- **Blokkade, door de CISO** — de knop *Blokkeren* op `/gebruikers`, voor het
  geval waarin u een account per direct dicht wilt hebben: een vermoeden van
  gedeelde of gelekte inloggegevens, een lopend onderzoek. Er hoort een **reden**
  bij, en die is verplicht — de vraag die later gesteld wordt is niet *of* maar
  *waarom*. De blokkade werkt meteen: lopende sessies worden beëindigd, en
  iemand die op dat moment aan het werk is, is er bij zijn volgende klik uit.

  Een blokkade heeft **geen einddatum** en is omkeerbaar: u heft hem zelf op
  zodra de aanleiding weg is. Een blokkade die vanzelf afloopt zou de maatregel
  opheffen op een moment dat niemand heeft beoordeeld of dat kan. Gaat iemand uit
  dienst, kies dan *Deactiveren* — dat is de status die niet terugkomt.

  De statusregel in de lijst laat zien waar een blokkade vandaan komt: *sinds
  wanneer, door wie, met welke reden*, of *automatisch, na te veel mislukte
  inlogpogingen*. Dat is wat u nodig heeft op het moment dat u overweegt hem op
  te heffen. De reden krijgt de betrokkene níet te zien: op het inlogscherm staat
  alleen dat het account geblokkeerd is en dat hij contact opneemt met de CISO.
- **Een vreemde heeft de uitnodiging geaccepteerd.** Ging de uitnodiging naar een
  verkeerd adres en heeft die ontvanger hem geaccepteerd, dan is het account van
  hém: zijn wachtwoord, zijn tweede factor, zijn herstelcodes, mogelijk een
  lopende sessie. Het adres wijzigen lost dat niet op — hij zit er nog steeds op.
  Wat wél werkt is **Blokkeren**: dat zet hem er bij zijn volgende klik uit en
  beëindigt lopende sessies. Daarna maakt u een nieuw account aan met het juiste
  adres.
- **Uzelf blokkeren of deactiveren kan niet.** Zonder die check kan de laatste
  CISO zichzelf buitensluiten en kan niemand meer accounts beheren — bij een
  blokkade des te sterker, want opheffen kan alléén een CISO. Meteen ook de
  garantie dat er altijd iemand over is: wie blokkeert, blijft zelf actief. Een
  CISO mag een *andere* CISO wel blokkeren.
- **Inloggen** kan alleen met status *actief*; de andere statussen geven een
  eigen melding op het inlogscherm.

Elke inlogpoging — geslaagd of niet, ook met een onbekend e-mailadres — wordt
gelogd in `loginpogingen` met tijdstip en IP.

### Het eerste account: de kip en het ei

`/gebruikers` vereist een ingelogde CISO, dus de allereerste kan daar niet
vandaan komen. Die maak je op de commandline:

```
php artisan isms:eerste-ciso <e-mail> <wachtwoord> [naam]
```

Dat account is direct *actief* — zo is er voor deze eenmalige stap geen
mailserver nodig.

## Het personeelsdossier (A.6)

Aan elk account hangt een klein dossier: **NDA getekend op**, **screening**
(VOG of referentiecheck, met datum) en **accounts ingetrokken op** voor de
offboarding. Bewijsstukken — de getekende NDA, de VOG — koppel je aan de
gebruiker.

Dit zijn **gap-signalen, geen blokkades**. Een actief account zonder afgeronde
pre-employment blijft gewoon werken, maar telt mee in de teller bovenaan
`/gebruikers`; hetzelfde geldt voor een gedeactiveerd account waarvan de
offboarding niet is bevestigd. De reden is praktisch: toegang blokkeren op een
ontbrekend vinkje maakt van een administratieve achterstand een
productiestoring, en dan wordt het vinkje gezet zonder dat het klopt.

## Alles wat met rechten gebeurt, staat in de audit trail

`Gebruiker` en `RolToewijzing` zijn auditeerbaar in het blok `identity-access`.
Een roltoewijzing logt wie wanneer welke rol kreeg — de kern van A.5.15/5.18.

Eén detail dat een beveiligingscontrole is en geen opmaakkeuze: `wachtwoord` en
`remember_token` staan expliciet uitgesloten van de audit trail. Zonder die
uitsluiting zou de wachtwoordhash leesbaar belanden in een tabel die de Auditor
mag inzien.

Let ook op massa-updates: `Model::where(...)->update()` gaat rechtstreeks naar de
database en vuurt geen Eloquent-events — de wijziging gebeurt wél, maar komt niet
in de audit trail. Gebruik in code daarom `updateGeaudit()` / `deleteGeaudit()`.

Hetzelfde geldt voor **koppelingen** tussen records (welk beleid dekt welke
maatregel, wie zit in welke doelgroep, welke clausules vielen binnen een
auditronde). Die raken de velden van het record niet aan en bleven daardoor
buiten de trail; ze worden gelogd, als één regel per
handeling met de namen van wat erbij kwam en wat eraf ging.

## Wat dit model bewust niet doet

Eerlijk zijn hierover is nuttiger dan het verzwijgen:

- **Geen rollenbeheer in de UI.** Rollen en de rechtenmatrix zijn
  referentiedata. Een rol toevoegen of een niveau verschuiven is een
  seeder-wijziging plus deploy — een gecodereviewde, versiebeheerde handeling in
  plaats van een klik. Dat is voor een ISMS eerder een kenmerk dan een gebrek,
  maar het betekent wel dat je het niet ad hoc kunt.
- **De rol wordt bij uitnodiging gezet en heeft geen wijzigscherm.** Een
  rolwijziging voor een bestaand account is er nu niet als knop. De afdeling is
  wel vanuit de lijst te wijzigen.
- **Geen organisatie-eenheid-scoping op rechten.** De afdeling stuurt
  leesbevestigingen en doelgroepen aan, niet de autorisatie. Een Medewerker ziet
  niet "alles van zijn afdeling" — hij ziet het eigene.
- **Geen tijdelijke rechtenverhoging of delegatie.** Vakantievervanging los je
  op met een tweede rol of met een account met vervaldatum.
- **Geen vier-ogen op persoonsniveau.** De functiescheiding tussen CISO en
  Management zit op *rollen*, niet op personen. Wie beide rollen heeft, stelt op
  én stelt vast; het systeem verhindert dat niet. Bij een kleine organisatie is
  dat soms de realiteit, en het gebruikersoverzicht toont alle rollen per
  persoon zodat een auditor die combinatie zelf kan wegen. "De opsteller mag
  niet de goedkeurder zijn" zou een guard per record vragen.
- **Eén tenant.** Er is geen scheiding tussen organisaties in het rechtenmodel.

## Normkoppeling

| Onderdeel | Annex A / hoofdstuk |
| --- | --- |
| Rollen, verantwoordelijkheden en de rechtenmatrix | 5.2, 5.3 (functiescheiding), A.5.15 |
| Toekennen en intrekken van toegang, roltoewijzing gelogd | A.5.18 |
| Beheer van toegangsrechten, uitnodiging en blokkade | A.5.16, A.5.17, A.8.2 |
| NDA, screening, offboarding | A.6.1, A.6.2, A.6.5 |
| Loginpogingen en audit trail | A.8.5, A.8.15 |
| Onafhankelijkheid van de interne auditor | 9.2, A.5.3 |

## In de applicatie

Het scherm **Gebruikers** (`/gebruikers`) is bereikbaar met `lezen` op
`identity-access` — dus ook voor Medewerker en Auditor — maar alle knoppen eisen
`muteren` en zijn daarmee in de praktijk van de CISO. Je vindt er:

- de lijst met **rol(len) als badge**, afdeling, status en de A.6-signalen;
- **Gebruiker uitnodigen** (naam, e-mail, rol, afdeling, vervaldatum);
- **Uitnodiging opnieuw versturen** bij status *uitgenodigd*;
- **Blokkeren** (met verplichte reden), **Deactiveren** en **Blokkade opheffen**;
- het **personeelsdossier** per gebruiker (NDA, screening, offboarding) met het
  aantal gekoppelde bewijsstukken;
- bovenaan de tellers voor openstaande pre-employment- en offboarding-gaps.

Het **menu** is rolgevoelig: een item verschijnt alleen als dat blok gebouwd is
én de gebruiker het vereiste niveau erop heeft. Ziet iemand een item niet, dan is
dat geen weergavefout maar het rechtenmodel dat zijn werk doet — de bijbehorende
URL geeft ook rechtstreeks een 403.

De wijzigingen op accounts en roltoewijzingen zijn terug te vinden onder
**Bewijs & audit trail → Audit log**, blok `identity-access`.
