# Gebruikers, rollen en rechten

Toegang is in dit ISMS geen verzameling `if`-statements in de code, maar
**data**. Een rij in `rol_permissies` legt vast dat rol X op blok Y niveau Z
heeft. Een wijziging van de rechtenmatrix is dus een wijziging van rijen en geen
wijziging van PHP-code. Dat is de kern van het model. De rest van dit artikel
werkt die kern uit en somt eerlijk op wat het model niet doet.

## Het model in één zin

> Een **gebruiker** heeft een of meer **rollen**, een rol heeft per **blok** een
> **niveau**, en de generieke autorisatiecheck `heeft-niveau` toetst de
> combinatie van blok en niveau tegen die rijen.

Er zijn geen Policy-classes per model en er staan geen rolnamen in views. Overal
in de applicatie wordt dezelfde vraag gesteld: heeft deze gebruiker op dit blok
minstens dit niveau?

## De vier begrippen

### 1. Rol

Er zijn vijf rollen. De rollen zijn referentiedata: ze worden geseed en zijn niet
via het scherm aan te maken.

| Rol | Bedoeld voor |
| --- | --- |
| **CISO** | Eigenaar van het ISMS: risico's, maatregelen, beleid, gebruikers |
| **Medewerker** | Voert eigen taken uit, meldt incidenten, bevestigt beleid |
| **Auditor** | Interne of externe auditor, read-only inzage voor auditbewijs |
| **Management** | Directie: stelt vast wat de CISO opstelt. Geen beheerrechten |
| **Administrator** | Technisch beheer van de installatie. Geen toegang tot het ISMS |

Een gebruiker kan meerdere rollen hebben. De rechten zijn dan de **vereniging**
van die rollen, wat betekent dat de gunstigste rol wint. Elke combinatie van
gebruiker en rol is uniek.

**Er is één uitzondering: de Administrator is met geen enkele andere rol te
combineren.** Die rol staat buiten het ISMS. Een Administrator mag toetsbestanden
plaatsen en verder niets, en de rol heeft op geen enkel ISMS-blok een rij. Als
één account beide rollen zou dragen, kan dezelfde persoon een bestand plaatsen en
het daarna als ISMS-gebruiker openen. Dat is precies wat de scheiding moet
voorkomen. Een persoon die zelf de installatie beheert, heeft daarom een tweede
account nodig. Het systeem weigert de combinatie.

Dit is de enige harde onverenigbaarheid in het model. Voor de vier ISMS-rollen
blijft functiescheiding een organisatorische keuze die het systeem faciliteert
maar niet afdwingt. In een kleine organisatie heeft één persoon soms twee rollen.
Het gebruikersoverzicht maakt dat zichtbaar, zodat een auditor de combinatie kan
wegen.

### 2. Blok

Een blok is een functiegebied van het ISMS en geen scherm. Voorbeelden zijn
`identity-access`, `risico-soa`, `beleid-maatregelbeheer` en `auditmanagement`.
De codes komen één-op-één uit de deelproducten. Rechten gelden dus op het
detailniveau van een **domein**, niet per pagina en niet per record.

### 3. Niveau: een ladder

`lezen` → `uitvoeren` → `muteren`

De niveaus zijn oplopend: een hoger niveau omvat alle lagere niveaus. Daarom
heeft de CISO genoeg aan één rij `muteren` en is er geen aparte `lezen`-rij nodig.

`uitvoeren` is het niveau dat de meeste uitleg vraagt. Het betekent dat de
gebruiker in het blok mag schrijven, maar alleen aan de eigen gegevens:
een eigen taak afwerken, een eigen incident melden, een eigen leesbevestiging
afgeven of een eigen bewijsstuk uploaden.

### 4. Twee niveaus bewust buiten de ladder

`exporteren` en `goedkeuren` staan **naast** de ladder en niet erboven. Beide
niveaus omvatten alleen `lezen`.

**`exporteren`** is geen niveau boven muteren, maar een andere soort
bevoegdheid: gegevens naar buiten brengen. Als het niveau in de ladder zou
staan, zou de Auditor muteerrechten krijgen, terwijl die rol per definitie
onafhankelijk moet zijn. Wie mag exporteren, mag per definitie ook inzien.

**`goedkeuren`** is vaststellen. Vaststellen is een andere soort bevoegdheid dan
bewerken en geen grotere hoeveelheid ervan. Omdat het niveau los staat, is
functiescheiding mogelijk: de CISO stelt op (`muteren`) en Management stelt vast
(`goedkeuren`).

Vijf acties toetsen op dit niveau: beleid publiceren, een scope-versie
activeren, een restrisico boven de acceptatiedrempel accepteren, de
risicocriteria vaststellen (de risk appetite, de acceptatiedrempel en de
beoordelingsschaal, zie *Issues en risico's*) en de directiebeoordeling als
gehouden vastleggen.

Bij de risicocriteria gaat de functiescheiding het verst. Daar ligt ook het
*afwijzen* bij Management, wat betekent dat Management een ingediende versie
terugstuurt naar concept. De CISO kan daarna een nieuw concept opstellen.

**De huidige functie van `exporteren` wijkt af van de naam.** De applicatie heeft
nog geen exportknop, en er is dus geen scherm dat op dit niveau toetst. De enige
plek die het niveau uitleest, is `Recordscope::magAllesZien()`. Het niveau werkt
nu als **rolmarkering**: deze rol ziet alle rijen en niet alleen de eigen rijen.
De naam beschrijft de bedoelde bevoegdheid en niet de huidige functie. Zodra de
applicatie een exportfunctie krijgt, hoort die functie achter dit niveau. Om
dezelfde reden krijgt ook Management dit niveau op bewijs en incidenten. Zonder
die markering zou een directeur daar alleen de eigen rijen zien, terwijl juist
het volledige beeld de input is voor de directiebeoordeling.

De export die wel bestaat, valt buiten dit model. `php artisan isms:exporteer`
schrijft het ISMS weg als een boom van Markdown-bestanden, met persoonsgegevens
standaard geanonimiseerd. Dat commando kent geen autorisatiecheck, omdat
shelltoegang daar de autorisatie is. De download of preview van een bewijsstuk
komt in de UI het dichtst bij gegevens naar buiten brengen, maar die functie
vereist `lezen` op `bewijsrepository-audit-trail` en niet `exporteren`.

## Het ontbreken van een rij is de weigering

Er is geen niveau `geen`. Als er voor een combinatie van rol en blok geen rij
bestaat, is er geen toegang: het menu-item verdwijnt en de route geeft een 403.
De Medewerker heeft bijvoorbeeld geen rij op `risico-soa` en ziet daardoor geen
risicoregister en geen SoA. Dat is een bewuste keuze en geen omissie.

## Drie lagen toegang

De autorisatiecheck op blokniveau is op zichzelf te grof. Daarom liggen er drie
lagen over elkaar heen.

### Laag 1: de autorisatiecheck op blokniveau (`heeft-niveau`)

De check zit op de route en nog een keer in het component. Die herhaling is
bewust. Een pagina is meestal bereikbaar met `lezen`, terwijl de knoppen op die
pagina `muteren` vereisen. Elke actiemethode toetst daarom zelf opnieuw. Een
Livewire-actie is een HTTP-request, dus het ontbreken van een knop is geen
beveiliging.

### Laag 2: record-scoping (`Recordscope::magAllesZien`)

Op blokken waar de Medewerker `uitvoeren` heeft, volstaat de ladder niet.
`uitvoeren` omvat `lezen`, dus een leescheck zou de Medewerker ook de gegevens
van anderen tonen. De scope is daarom **positief** geformuleerd: wie `muteren`
(CISO), `goedkeuren` (Management) of `exporteren` (Auditor) heeft, ziet alles.
Alle andere gebruikers zien alleen de eigen rijen.

Om precies deze reden krijgt de Auditor `exporteren` op elk blok met
record-scoping. Zonder dat niveau zou het onderscheid alleen negatief te maken
zijn, namelijk als "heeft lezen maar niet uitvoeren". Zo'n negatieve regel slaat
ongemerkt om zodra de rechtenmatrix wijzigt.

### Laag 3: record-guards op het model

Waar de norm eigenaarschap of onafhankelijkheid eist, beslist het record zelf:

- **Auditronde:** de bevindingen van een interne ronde worden vastgelegd door
  *de toegewezen auditor*, en alleen zolang de ronde in uitvoering is. Na
  afronding kan niemand de bevindingen nog wijzigen, ook de CISO niet. Zo wordt
  onafhankelijkheid afgedwongen in plaats van alleen gedocumenteerd.
- **Corrigerende maatregel:** een Medewerker die eigenaar is van een maatregel,
  mag die maatregel afmelden. De rest van de CAPA-cyclus vereist `muteren`.

Deze guards staan bewust in het model en niet in de Gate, omdat ze afhangen van
de *rij* en niet van de rol.

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

In de matrix zijn drie patronen zichtbaar.

**Melden en afwerken hebben een laag niveau.** Incidenten, taken, bewijs en
leesbevestigingen vereisen `uitvoeren`. Een hoge drempel voor melden levert
minder meldingen op, maar niet minder incidenten.

**`exporteren` komt precies voor op de blokken met record-scoping.** Het niveau
dient daar niet alleen voor exporteren, maar vooral om gebruikers die alles mogen
zien te onderscheiden van gebruikers die alleen de eigen gegevens zien.

**Management heeft nergens `muteren`.** Dat is geen omissie, maar de reden dat de
rol bestaat. Op taken en training staat Management gelijk aan een gewone
medewerker, omdat een directeur die de eigen e-learning niet hoeft te doen geen
goed voorbeeld geeft. Op `identity-access` heeft Management geen rij en dus geen
gebruikersbeheer.

De matrix wordt geseed vanuit `RolPermissieSeeder`. De canonieke bron is
`deelproducten/01-identity-access.md` §4.

## Twee routes staan bewust buiten het model

De **kennisbank** en het **eigen profiel** (`/settings`) kennen geen
blok-permissie. Naslag en het beheer van het eigen wachtwoord zijn beschikbaar
voor elke ingelogde gebruiker. Alle andere routes lopen door een
autorisatiecheck.

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

- **Uitnodigen** (CISO): de CISO vult naam, e-mail en rol in, en optioneel een
  afdeling en een vervaldatum. Het account krijgt een onbruikbaar willekeurig
  wachtwoord en de status *uitgenodigd*. De uitgenodigde gebruiker stelt via de
  link een wachtwoord in en koppelt **in hetzelfde scherm** direct een
  authenticator-app, omdat de gebruiker op dat moment toch al met het account
  bezig is. Als dat niet lukt, bijvoorbeeld omdat de telefoon niet bij de hand
  is, is het account toch actief en geldt de respijtperiode die hieronder is
  beschreven.
- **De uitnodigingslink** is een signed URL die 7 dagen geldig is, met een token
  dat is afgeleid van de wachtwoordhash. Zodra de uitgenodigde gebruiker een eigen
  wachtwoord instelt, verandert die hash en is de link automatisch verbruikt.
  Daardoor is er geen aparte tokentabel met opruimlogica nodig. Als het versturen
  van de mail mislukt, blijft het account bestaan en is er een knop *Uitnodiging
  opnieuw versturen*.
- **Een typefout in het adres** wordt hersteld met *Uitnodiging corrigeren*, naast
  *opnieuw versturen*. De CISO past naam en e-mailadres aan, en het systeem voert
  in dezelfde handeling een extra stap uit die niemand hoeft te onthouden:
  **de oude link wordt ongeldig**. Die stap is noodzakelijk en geen extra
  voorzichtigheid. Het token hangt aan de wachtwoordhash. Zonder die rotatie zou
  de ontvanger op het foute adres zeven dagen lang een werkende link naar het
  account hebben, en zou *opnieuw versturen* diezelfde link daarna naar het
  juiste adres sturen. Na een correctie verstuurt het systeem altijd direct een
  nieuwe uitnodiging.

  De knop staat er **alleen bij status *uitgenodigd***, en dat is de kern van de
  regeling. Als het adres fout was, heeft de bedoelde persoon de uitnodiging nooit
  kunnen accepteren, en staat het account per definitie nog open. Als het account
  op *actief* staat, heeft iemand met dat adres een wachtwoord ingesteld, en
  gelden andere regels. Die regels staan in het volgende punt.

  Als het nieuwe adres al bij een ander account hoort, noemt de melding dat
  account. Het adres wordt niet vrijgemaakt, ook niet als het andere account
  gedeactiveerd is. De audit trail van dat account hangt aan die identiteit, en
  het adres is vaak nog een bestaande mailbox.
- **Het adres van een actief account wordt gewijzigd met *E-mailadres
  wijzigen*.** Een medewerker die trouwt, een domein dat migreert of een adres dat
  pas na maanden fout blijkt: dat zijn geen redenen om een account te verwijderen.
  Verwijderen zou ook kostbaar zijn. Aan een account hangen taken, bewijsstukken,
  leesbevestigingen, trainingsresultaten en het personeelsdossier, en een nieuw
  account neemt daar niets van over.

  Deze knop werkt **tegenovergesteld aan *Uitnodiging corrigeren***, en dat
  verschil is de kern:

  | | *Uitnodiging corrigeren* | *E-mailadres wijzigen* |
  | --- | --- | --- |
  | bij welke status | uitgenodigd | actief |
  | wanneer geldt het nieuwe adres | meteen | pas na bevestiging op het nieuwe adres |
  | wachtwoord | wordt vervangen (de oude link moet ongeldig worden) | blijft ongewijzigd |
  | tweede factor, lopende sessies | n.v.t. | blijven ongewijzigd |

  Bij een ongebruikt account heeft een typefout geen gevolgen. Bij een account
  dat in gebruik is, zou dezelfde typefout de gebruiker buitensluiten: geen
  inlog, geen wachtwoordherstel en geen notificaties. Daarom verandert er niets
  totdat het nieuwe adres is bevestigd. De link is 7 dagen geldig. Zolang er niet
  is bevestigd, blijft het huidige adres gewoon werken.

  **Het huidige adres krijgt een bericht** zodra de wijziging is aangevraagd. In
  dat bericht is het nieuwe adres gedeeltelijk zichtbaar, genoeg om te zien of
  het domein klopt. Dit bericht is de controle op een ander soort fout: iemand
  die de CISO een adreswijziging aanpraat. De bevestiging op het nieuwe adres
  vangt typefouten, en het bericht aan het huidige adres vangt verzoeken die zelf
  niet deugen.

  Zolang de wijziging loopt, staat dat in de lijst onder de naam, met een knop
  *Wijziging intrekken*. Als achteraf blijkt dat een onbekende partij achter het
  verzoek zat, is intrekken niet genoeg en is **Blokkeren** de juiste handeling.
- **Uitnodigingen zonder resultaat worden gesignaleerd.** Bij een uitgenodigd
  account toont de lijst wanneer de uitnodiging is verstuurd en of de link
  inmiddels verlopen is. Boven de tabel staat het aantal. Zo worden typefouten
  zichtbaar waarover niemand contact opneemt. Een adres dat een bounce oplevert,
  leidt nooit tot een telefoontje, en zonder dit signaal blijft het account
  onbeperkt open staan.
- **Bij een adres dat lijkt op een bekend domein** verschijnt onder het veld de
  tekst *Bedoelde u @fruitbv.nl?* Die tekst verschijnt alleen bij een domein dat
  één of twee tekens afwijkt van een domein dat al bij minstens twee accounts in
  gebruik is. Het is geen blokkade en vraagt geen bevestiging. Bij het uitnodigen
  van een externe gebruiker kan de tekst worden genegeerd. Het systeem waarschuwt
  bewust **niet** voor een onbekend domein. Een auditor of leverancier heeft
  legitiem een ander domein, en een melding die in de helft van de gevallen
  onterecht is, wordt weggeklikt zonder te worden gelezen.
- **Vervaldatum:** dit is de enige statusovergang zonder handeling van de CISO.
  Een dagelijkse taak (`isms:verval-gebruikersaccounts`, 01:00) deactiveert
  actieve accounts waarvan `vervalt_op` is bereikt. De functie is bedoeld voor
  het tijdelijke auditoraccount dat anders onopgemerkt actief blijft.
- **Het wachtwoord** moet minimaal **12 tekens** lang zijn. Er gelden geen andere
  eisen: geen verplichte hoofdletters, cijfers of symbolen. Lengte beschermt
  beter tegen raden dan verplichte tekensoorten. Verplichte tekensoorten leveren
  vooral wachtwoorden als `Wachtwoord2026!` op, en dat patroon kent elke
  aanvaller. Er is geen verplichte periodieke wijziging, omdat die vooral kleine
  varianten op een oud wachtwoord oplevert.
- **Tweefactorauthenticatie is verplicht** voor elke rol. Na het wachtwoord vraagt
  het systeem om een code van zes cijfers uit een authenticator-app. Bij het
  instellen krijgt de gebruiker acht **herstelcodes**. Elke code is één keer
  bruikbaar en is bedoeld voor situaties waarin de telefoon niet beschikbaar is.
  Als de gebruiker ook de herstelcodes kwijt is, zet de CISO de tweede factor
  terug. Die handeling komt in de audit trail.

  Een gebruiker die de app niet direct bij de uitnodiging koppelt, krijgt veertien
  dagen respijt, gerekend vanaf de eerste aanmelding. In die periode staat er een
  melding op elke pagina en verstuurt het systeem twee e-mails: één een paar
  dagen vóór het einde van de termijn en één zodra de termijn is verstreken.
  Daarna kan de gebruiker alleen nog het instelscherm openen. Op dat scherm kan de
  gebruiker de koppeling zelf voltooien, zonder tussenkomst van een beheerder. Er
  is bewust geen knop om tweefactorauthenticatie uit te schakelen. Bij een nieuwe
  telefoon kiest de gebruiker *ander apparaat koppelen*, en het oude apparaat
  blijft werken totdat de nieuwe koppeling is bevestigd.
- **Een foute verificatiecode blokkeert het account niet.** Er zijn vijf pogingen
  per kwartier toegestaan, en daarna moet de gebruiker opnieuw inloggen. Een
  blokkade op een typefout of op een telefoon die een halve minuut voorloopt,
  levert alleen werk op voor de CISO en geen veiligheidswinst. Met die limiet is
  het raden van zes cijfers toch niet haalbaar. De mislukte poging wordt wel
  vastgelegd, en apart van een verkeerd wachtwoord. De combinatie van een goed
  wachtwoord en een foute tweede factor is namelijk het signaal dat een
  wachtwoord is gelekt.
- **Automatische blokkade:** 5 mislukte pogingen binnen 15 minuten blokkeren een
  *actief* account. Alleen de CISO kan die blokkade opheffen. Er is geen
  automatische ontgrendeling na een wachttijd. Deze regel heeft een keerzijde:
  de teller telt per **ingevoerd e-mailadres**, dus iemand die een adres kent,
  kan de eigenaar van dat adres buitensluiten. Dat is de bewuste afweging voor een
  harde grens tegen het raden van wachtwoorden. De weg terug is contact met de
  CISO.
- **Blokkade door de CISO:** de knop *Blokkeren* op `/gebruikers` is bedoeld voor
  situaties waarin een account per direct moet worden afgesloten, zoals een
  vermoeden van gedeelde of gelekte inloggegevens of een lopend onderzoek. Een
  **reden** is verplicht, omdat later niet de vraag is of er is geblokkeerd, maar
  waarom. De blokkade werkt direct: lopende sessies worden beëindigd, en een
  gebruiker die op dat moment actief is, wordt bij de volgende klik uitgelogd.

  Een blokkade heeft **geen einddatum** en is omkeerbaar. De CISO heft de blokkade
  op zodra de aanleiding is verdwenen. Een blokkade die automatisch afloopt, zou
  de maatregel opheffen op een moment waarop niemand heeft beoordeeld of dat
  verantwoord is. Voor een medewerker die uit dienst gaat, is *Deactiveren* de
  juiste handeling, omdat die status niet terugkeert.

  De statusregel in de lijst toont de herkomst van een blokkade: *sinds wanneer,
  door wie, met welke reden*, of *automatisch, na te veel mislukte
  inlogpogingen*. Die informatie is nodig bij de afweging om een blokkade op te
  heffen. De betrokken gebruiker ziet de reden niet. Het inlogscherm meldt alleen
  dat het account geblokkeerd is en dat de gebruiker contact moet opnemen met de
  CISO.
- **Een onbekende partij heeft de uitnodiging geaccepteerd.** Als de uitnodiging
  naar een verkeerd adres is gegaan en de ontvanger die heeft geaccepteerd, dan
  beheerst die ontvanger het account: het wachtwoord, de tweede factor, de
  herstelcodes en mogelijk een lopende sessie. Het adres wijzigen lost dat niet
  op, omdat de ontvanger dan nog steeds toegang heeft. **Blokkeren** werkt wel:
  de ontvanger wordt bij de volgende klik uitgelogd en lopende sessies worden
  beëindigd. Daarna maakt de CISO een nieuw account aan met het juiste adres.
- **Een gebruiker kan het eigen account niet blokkeren of deactiveren.** Zonder die
  check zou de laatste CISO zichzelf kunnen buitensluiten, waarna niemand meer
  accounts kan beheren. Bij een blokkade weegt dat nog zwaarder, omdat alleen een
  CISO een blokkade kan opheffen. De check garandeert ook dat er altijd iemand
  overblijft: wie blokkeert, blijft zelf actief. Een CISO mag een *andere* CISO
  wel blokkeren.
- **Inloggen** kan alleen met de status *actief*. De andere statussen geven elk
  een eigen melding op het inlogscherm.

Elke inlogpoging wordt met tijdstip en IP-adres gelogd in `loginpogingen`. Dat
geldt voor geslaagde en mislukte pogingen, en ook voor pogingen met een onbekend
e-mailadres. Elke poging vermeldt ook de weg: met een wachtwoord of via de
identiteitsprovider.

### Inloggen via de identiteitsprovider

Een installatie kan worden gekoppeld aan de identiteitsprovider van de
organisatie, zoals Microsoft Entra ID of Google Workspace. Het systeem gebruikt
daarvoor OpenID Connect. Zonder die koppeling verandert er niets aan het
inlogscherm of aan het uitnodigen. De koppeling wordt ingesteld in de omgeving
van de installatie en niet in een scherm; zie
[Inloggen via een identiteitsprovider](inloggen-via-een-identiteitsprovider).

- **De inlogmethode hoort bij het account.** Bij het uitnodigen kiest de CISO
  tussen *Wachtwoord* en de identiteitsprovider. De identiteitsprovider is de
  standaard. Een wachtwoord blijft nodig voor wie geen account bij de
  identiteitsprovider heeft: een ingehuurde auditor, de eerste CISO uit
  `isms:eerste-ciso`, en soms de Administrator.
- **Accounts ontstaan nog steeds alleen via een uitnodiging.** Iemand die zich
  bij de identiteitsprovider aanmeldt zonder uitnodiging, krijgt geen account.
  De uitnodiging is het moment waarop de CISO de rol toekent, de vervaldatum
  zet en het personeelsdossier start.
- **Koppelen vervangt het instellen van een wachtwoord.** De uitgenodigde
  gebruiker opent de link, meldt zich aan bij de identiteitsprovider en is
  daarmee ingelogd. Het account staat vanaf dat moment op *actief*.
- **De koppeling hangt aan de identiteit bij de identiteitsprovider, niet aan het
  e-mailadres.** Bij Google kan iedereen een account met een willekeurig adres
  aanmaken, en bij Entra ID is het adres in het token niet gegarandeerd
  geverifieerd. Het systeem slaat het adres dat de identiteitsprovider meegeeft
  alleen op om te tonen. Wijkt dat adres af van het uitnodigingsadres, dan staat
  er een hint in de lijst. Die hint is geen blokkade, omdat een
  gebruikersnaam in Entra ID vaak legitiem afwijkt van het mailadres.
- **Een extern account heeft geen bruikbaar wachtwoord.** *Wachtwoord vergeten*
  verstuurt niets, het wachtwoordscherm onder de instellingen ontbreekt, en een
  hersteltoken van vóór het overzetten zet geen wachtwoord meer. Een wachtwoord
  zou een weg om de identiteitsprovider heen zijn: langs de tweede factor van de
  identiteitsprovider en langs de uitschakeling bij uitdiensttreding. Om dezelfde
  reden blokkeren mislukte wachtwoordpogingen een extern account niet; er valt
  geen wachtwoord te raden.
- **Blokkeren en deactiveren blijven werken zoals hierboven beschreven.** Het
  systeem controleert de status na elke aanmelding bij de identiteitsprovider.
  Een account dat in de identiteitsprovider is uitgeschakeld, komt ook niet meer
  binnen. Een lopende sessie in het ISMS loopt dan wel door tot die verloopt;
  "Aangemeld blijven" bestaat daarom niet voor een extern account.
- **Overzetten en opnieuw koppelen.** Een actief wachtwoordaccount wordt met
  *Overzetten naar* de identiteitsprovider omgezet. Het wachtwoord vervalt
  meteen, lopende sessies worden beëindigd en de gebruiker krijgt een koppellink,
  per mail of als bestand. Tot de koppeling is gelegd, kan het account niet
  inloggen. *Koppeling opnieuw uitreiken* vervangt een bestaande koppeling, bijvoorbeeld
  als die aan het verkeerde account bij de identiteitsprovider is gelegd.
  De weg terug naar een wachtwoord loopt alleen via de commandoregel
  (`isms:inlogmethode-wachtwoord`), omdat die weg nodig is wanneer de
  identiteitsprovider wegvalt en de CISO dan vaak zelf niet kan inloggen.

**De tweede factor bij de identiteitsprovider.** Standaard geldt de
tweefactorplicht ook voor een extern account. De installatie kan verklaren dat
de identiteitsprovider zelf een tweede factor afdwingt (`ISMS_IDP_DWINGT_MFA`).
Een extern account hoeft dan in het ISMS geen authenticator-app te koppelen, en
de kolom Tweefactor toont *Via* de identiteitsprovider. Wachtwoordaccounts op
dezelfde installatie houden de plicht.

Die instelling is een verklaring van de organisatie en geen controle door het
systeem. Het ISMS kan niet zien of de identiteitsprovider werkelijk een tweede
factor vraagt. De onderbouwing hoort daarom als beheersmaatregel in het ISMS:
een Conditional Access-regel in Entra ID of verplichte verificatie in twee
stappen in Google Workspace, met bewijs bij de maatregel voor veilige
authenticatie (A.8.5). Een wijziging van de instelling komt niet in de audit
trail, omdat die in de omgeving van de installatie staat. Elke externe login
legt daarom vast wat de instelling op dat moment was.

Een extern account dat de tweefactorplicht wel heeft, bevestigt wijzigingen op
het tweefactorscherm met een recente aanmelding bij de identiteitsprovider in
plaats van met een wachtwoord.

Het is verstandig de koppeling ook in het integratieregister vast te leggen, als
koppeling van het type *identiteit*. Dan staat in het ISMS zelf dat de
authenticatie van een deel van de accounts bij een externe partij ligt.

### Het eerste account

`/gebruikers` vereist een ingelogde CISO, dus het allereerste CISO-account kan
daar niet worden aangemaakt. Dat account wordt op de commandoregel aangemaakt:

```
php artisan isms:eerste-ciso <e-mail> <wachtwoord> [naam]
```

Het account is direct *actief*, zodat er voor deze eenmalige stap geen
mailserver nodig is.

## Het personeelsdossier (A.6)

Aan elk account hangt een klein dossier met drie gegevens: **NDA getekend op**,
**screening** (VOG of referentiecheck, met datum) en **accounts ingetrokken op**
voor de offboarding. Bewijsstukken, zoals de getekende NDA en de VOG, worden aan
de gebruiker gekoppeld.

Deze gegevens zijn **gap-signalen en geen blokkades**. Een actief account zonder
afgeronde pre-employment-controle blijft gewoon werken, maar telt mee in de
teller bovenaan `/gebruikers`. Hetzelfde geldt voor een gedeactiveerd account
waarvan de offboarding niet is bevestigd. De reden is praktisch. Als toegang
wordt geblokkeerd op een ontbrekend vinkje, wordt een administratieve achterstand
een productiestoring, en dan wordt het vinkje gezet zonder dat het klopt.

## Alles wat met rechten gebeurt, staat in de audit trail

`Gebruiker` en `RolToewijzing` zijn auditeerbaar in het blok `identity-access`.
Een roltoewijzing legt vast wie wanneer welke rol kreeg. Dat is de kern van
A.5.15 en A.5.18.

Eén detail is een beveiligingscontrole en geen opmaakkeuze: `wachtwoord` en
`remember_token` zijn expliciet uitgesloten van de audit trail. Zonder die
uitsluiting zou de wachtwoordhash leesbaar in een tabel staan die de Auditor mag
inzien.

Massa-updates vragen aandacht. `Model::where(...)->update()` schrijft
rechtstreeks naar de database en activeert geen Eloquent-events. De wijziging
wordt wel doorgevoerd, maar komt niet in de audit trail. Code gebruikt daarom
`updateGeaudit()` en `deleteGeaudit()`.

Hetzelfde geldt voor **koppelingen** tussen records, zoals welk beleid welke
maatregel dekt, wie in welke doelgroep zit en welke clausules binnen een
auditronde vielen. Koppelingen wijzigen de velden van het record niet en vielen
daardoor buiten de trail. Ze worden nu gelogd als één regel per handeling, met de
namen van wat is toegevoegd en wat is verwijderd.

## Wat dit model bewust niet doet

Het is nuttiger om de beperkingen te benoemen dan om ze te verzwijgen:

- **Geen rollenbeheer in de UI.** Rollen en de rechtenmatrix zijn referentiedata.
  Een rol toevoegen of een niveau verschuiven vereist een wijziging in de seeder
  en een deploy. Dat is een versiebeheerde handeling met code review in plaats
  van een klik. Voor een ISMS is dat eerder een kenmerk dan een gebrek, maar het
  betekent wel dat de matrix niet ad hoc te wijzigen is.
- **De rol wordt bij de uitnodiging ingesteld en heeft geen wijzigscherm.** Voor
  een bestaand account is er nu geen knop om de rol te wijzigen. De afdeling is
  wel vanuit de lijst te wijzigen.
- **Geen scoping van rechten op organisatie-eenheid.** De afdeling bepaalt
  leesbevestigingen en doelgroepen, maar niet de autorisatie. Een Medewerker ziet
  niet alle gegevens van de eigen afdeling, maar alleen de eigen gegevens.
- **Geen tijdelijke rechtenverhoging of delegatie.** Vakantievervanging wordt
  opgelost met een tweede rol of met een account met een vervaldatum.
- **Geen vier-ogenprincipe op persoonsniveau.** De functiescheiding tussen CISO en
  Management geldt voor *rollen* en niet voor personen. Een persoon met beide
  rollen kan zowel opstellen als vaststellen, en het systeem verhindert dat niet.
  In een kleine organisatie is dat soms onvermijdelijk. Het gebruikersoverzicht
  toont alle rollen per persoon, zodat een auditor die combinatie zelf kan wegen.
  Een regel dat de opsteller niet de goedkeurder mag zijn, zou een guard per
  record vereisen.
- **Eén tenant.** Het rechtenmodel kent geen scheiding tussen organisaties.
- **Geen rechten uit de identiteitsprovider.** Groepen en rollen uit Entra ID of
  Google Workspace worden niet gelezen, en accounts worden niet automatisch
  gedeactiveerd bij uitdiensttreding. Het toekennen van rechten blijft een
  handeling in het ISMS, zodat die in de eigen audit trail staat.

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
`identity-access`, en dus ook voor de Medewerker en de Auditor. Alle knoppen
vereisen echter `muteren` en zijn daardoor in de praktijk voorbehouden aan de
CISO. Het scherm bevat:

- de lijst met **rol(len) als badge**, afdeling, status en de A.6-signalen;
- **Gebruiker uitnodigen** (naam, e-mail, rol, afdeling, vervaldatum);
- **Uitnodiging opnieuw versturen** bij status *uitgenodigd*;
- **Blokkeren** (met verplichte reden), **Deactiveren** en **Blokkade opheffen**;
- het **personeelsdossier** per gebruiker (NDA, screening, offboarding) met het
  aantal gekoppelde bewijsstukken;
- bovenaan de tellers voor openstaande gaps in pre-employment en offboarding.

Het **menu** past zich aan de rol aan. Een item verschijnt alleen als het blok
gebouwd is en de gebruiker het vereiste niveau op dat blok heeft. Als een
gebruiker een item niet ziet, is dat geen weergavefout, maar het rechtenmodel dat
werkt zoals bedoeld. De bijbehorende URL geeft bij rechtstreeks openen ook een
403.

De wijzigingen op accounts en roltoewijzingen zijn terug te vinden onder
**Bewijs & audit trail → Audit log**, blok `identity-access`.
