# Taken: hoe ze ontstaan en hoe ze worden afgerond

Een taak is in dit systeem een stuk werk met een eigenaar en een deadline. Het
merendeel van de taken wordt niet met de hand aangemaakt, maar door het systeem
zelf. Een risico dat opnieuw beoordeeld moet worden, een beleidsversie die op
vaststelling wacht en een training die verloopt, leveren elk een taak op. Dit
artikel beschrijft waar taken vandaan komen, wat de knoppen in de kolom *Acties*
op **`/taken`** doen, en in welke gevallen een taak op dat scherm niet te sluiten
is.

## Waar taken te zien zijn

Het takenoverzicht staat op **`/taken`**. Het tabblad *Sjablonen* ernaast
(`/taaksjablonen`) bevat de terugkerende taken. Daarnaast toont het dashboard
bovenaan het paneel *Mijn openstaande taken*, met de eigen taken die nog aandacht
vragen.

Wat een gebruiker op `/taken` ziet, hangt af van de rechten op het blok Taken &
workflow:

- Een gebruiker met `uitvoeren`, zoals de Medewerker en Management, ziet alleen de
  eigen taken. De schakelaar *Alleen mijn taken* ontbreekt dan, omdat het filter
  altijd geldt.
- Een gebruiker met `muteren`, in de standaardrollen de CISO, ziet alle taken en
  mag ze aanmaken, bewerken en heropenen.
- Het scherm vraagt minimaal `uitvoeren`. De Auditor heeft op dit blok `lezen` en
  `exporteren` en kan `/taken` daarom niet openen.

Het statusfilter staat standaard op *Openstaand*. Daaronder vallen de statussen
*open*, *in uitvoering* en *verlopen*. Een stap die op zijn beurt wacht (status
*wachtend*) telt niet als openstaand. Zo'n stap verschijnt daarom niet op het
dashboard en niet in het standaardfilter, omdat er op dat moment niets van de
eigenaar wordt gevraagd.

## Hoe taken ontstaan

Er zijn vijf routes waarlangs een taak ontstaat.

### Met de hand

Een gebruiker met `muteren` maakt met **Nieuwe taak** een losse taak aan. Zo'n
taak heeft een titel, een optionele omschrijving, een eigenaar en een deadline.
De eigenaar is verplicht, omdat een taak zonder eigenaar bij niemand op het
dashboard verschijnt. Een losse taak hoort bij geen enkel sjabloon en bij geen
enkel register.

### Uit een sjabloon

Een taaksjabloon op `/taaksjablonen` beschrijft terugkerend werk: een naam, een
herhaling (*Eenmalig*, *Maandelijks*, *Per kwartaal*, *Jaarlijks* of *Aangepast*
met een interval in dagen), een standaard-eigenaar en het aantal dagen dat de
taak vóór de deadline wordt aangemaakt.

Het commando `isms:genereer-taken` draait elke nacht om 02:00 en maakt per actief
sjabloon de volgende taak aan zodra die binnen het venster van *Dagen vooraf
aanmaken* valt. De volgende deadline wordt berekend vanaf de deadline van de
vorige taak en niet vanaf de datum van vandaag. Een gemiste uitvoering verschuift
de cyclus daardoor niet. Een eenmalig sjabloon levert precies één taak op. Een
dubbele run van het commando maakt geen tweede taak aan, omdat de combinatie van
sjabloon en deadline uniek is.

### Uit een signaal van een register

Veel registers vragen op een bepaald moment om een handeling. Het systeem zet dat
signaal om in een **beheerde taak**. Een beheerde taak hoort bij één record in
het register en heeft een vaste soort. Per record en soort staat er hoogstens één
openstaande taak. Een nieuw signaal voor hetzelfde record verzet de bestaande
taak in plaats van er een tweede naast te zetten. Bij leesbevestigingen,
beleidsgoedkeuringen, trainingen en verbeteracties geldt die regel per eigenaar.

Een deel van de signalen ontstaat direct bij het opslaan van een record, een
ander deel in de nachtelijke run van `isms:genereer-taken`.

| Titel begint met | Ontstaat wanneer | Moment |
|---|---|---|
| *Risico herbeoordelen:* | Een risico heeft een datum bij *Volgende beoordeling gepland*. De risico-eigenaar wordt eigenaar van de taak. | Bij opslaan |
| *Herbeoordelen na aangescherpte risicocriteria:* | Een nieuwe versie van de risicocriteria wordt geactiveerd en plaatst een risico in een zwaardere band. De deadline ligt 30 dagen later. | Bij activeren |
| *Risicocriteria v… herzien* | De actieve versie van de risicocriteria heeft een geplande herziening. | Bij opslaan |
| *Scope-verklaring v… herzien* | De actieve scopeverklaring heeft een geplande herziening. | Bij opslaan |
| *Beleid herzien:* | De actieve versie van een beleidsdocument heeft een geplande herziening. De documenteigenaar wordt eigenaar van de taak. | Bij opslaan |
| *Beleid vaststellen:* | Een beleidsversie wordt ter goedkeuring aangeboden. Elke actieve gebruiker met `goedkeuren` op beleid krijgt een eigen taak, met een deadline van 14 dagen na aanbieden. Is er geen enkele goedkeurder, dan ontstaat één taak zonder eigenaar. | Bij aanbieden en elke nacht |
| *Lezen en bevestigen:* | Een actieve beleidsversie vraagt om een leesbevestiging. Elk lid van de doelgroep dat nog niet heeft bevestigd, krijgt een taak. De deadline ligt 30 dagen na publicatie. | Elke nacht |
| *Training afronden:* | Een lid van de doelgroep van een actieve trainingsmodule heeft de training nooit afgerond, of de afronding verloopt. | Elke nacht |
| *Retourcontrole:* | Een bedrijfsmiddel staat langer dan 12 maanden uitgereikt zonder retour. De deadline ligt 14 dagen later. | Elke nacht |
| *SoA herbeoordelen:* | Een toepasselijke maatregel is langer dan 12 maanden niet beoordeeld. De deadline ligt 14 dagen later. | Elke nacht |
| *Corrigerende maatregel uitvoeren:* | Een corrigerende maatregel heeft een deadline en is nog niet voltooid. | Bij opslaan |
| *Effectiviteit toetsen:* | Een corrigerende maatregel is voltooid en heeft nog geen effectiviteitstoets. De deadline ligt 30 dagen na voltooiing. | Bij opslaan |
| *Leverancier herbeoordelen:* | De nieuwste beoordeling van een leverancier heeft een datum voor de volgende beoordeling. | Bij opslaan |
| *Verbeteractie afronden:* | Een openstaande verbeteractie uit de management review heeft een deadline. | Bij opslaan |
| *Meetpunt ontbreekt:* of *Bewaking onderbroken:* | `isms:controleer-hartslag` stelt vast dat een geplande meting of vastlegging is gemist en niet vanzelf wordt ingehaald. De taak gaat naar de CISO. | Elke nacht |
| *Controleer de classificatie van* | Het commando `isms:kenmerken` wijzigt de uitgangsclassificatie van maatregelen waarvoor de organisatie een eigen classificatie heeft vastgelegd. De taak gaat naar de CISO. | Bij het commando |

Een aantal van deze taken krijgt geen eigenaar, bijvoorbeeld *SoA herbeoordelen*
en *Leverancier herbeoordelen*. Zo'n taak toont in de kolom *Eigenaar* de badge
*Geen eigenaar*. De CISO wijst de taak met **Bewerken** aan een eigenaar toe.

### Als stap in een reeks

Een wijziging die in behandeling wordt genomen, krijgt de stappen uit het gekozen
wijzigingssjabloon. Elke stap is een taak. Alle stappen worden in één keer
aangemaakt met de status *wachtend*, en alleen de eerste groep staat meteen
*open*. Zodra een groep klaar is, gaat de volgende groep open en krijgt de
eigenaar bericht. Stappen met hetzelfde volgnummer vormen één groep en lopen
parallel. In de kolom *Titel* staat bij zo'n taak "stap 2 van 4". Het artikel
[Wijzigingsbeheer: van aankondiging tot evaluatie](/kennisbank/wijzigingsbeheer)
beschrijft de reeks in detail.

### Als toetsopdracht

Op `/toetsen/uitzetten` zet de CISO een toets uit voor een selectie gebruikers.
Elke deelnemer krijgt een taak *Toets maken:* met een deadline die standaard vier
weken later ligt. Een deelnemer die al een openstaande taak voor dezelfde toets
heeft, wordt overgeslagen. Een toetstaak is geen beheerde taak: hij sluit alleen
bij een geslaagde toets of met de hand.

## Hoe beheerde taken verdwijnen of sluiten

Een beheerde taak volgt het register waar hij bij hoort. Er zijn drie manieren
waarop zo'n taak zonder tussenkomst van een gebruiker verandert:

- **De taak sluit vanzelf** zodra de handeling is verricht. Dat gebeurt bij een
  gegeven leesbevestiging, bij een beleidsversie die is vastgesteld of waarvan het
  document is ingetrokken en bij een verbeteractie die op voltooid staat. De taak
  krijgt dan de status *voltooid* en blijft als historie bewaard.
- **De taak verdwijnt** zodra de aanleiding vervalt. Voorbeelden zijn een
  leeggemaakt datumveld, een beleids- of scopeversie die door een nieuwe versie is
  vervangen, een corrigerende maatregel die voltooid is (de uitvoeringstaak) of
  waarvan de effectiviteitstoets is vastgelegd (de taak *Effectiviteit toetsen*), en een gebruiker
  die niet meer tot de doelgroep of de goedkeurders behoort. Alleen openstaande
  taken worden verwijderd. Voltooide taken blijven staan.
- **De taak verschuift** als de datum in het register verandert. Een nieuwe datum
  bij *Volgende beoordeling gepland* geeft de openstaande taak een nieuwe deadline.
  Een verlopen taak waarvan de nieuwe deadline in de toekomst ligt, gaat daarbij
  terug naar *open*. Bij een terugkerende training verschuift de taak na afronding
  naar de verloopdatum van die afronding.

In alle andere gevallen wordt een taak met de hand afgerond, met de knoppen in de
kolom *Acties*.

## Verlopen en escaleren

Het commando `isms:verloop-taken` draait elke nacht om 02:15. Een taak met status
*open* of *in uitvoering* waarvan de deadline is verstreken, krijgt de status
*verlopen* en escalatieniveau 1. Een taak die 14 dagen of langer over de deadline
is, gaat naar niveau 2. Op dat moment verstuurt het systeem een escalatiebericht,
en in de kolom *Deadline* verschijnt de badge *Escalatie*. Escaleren betekent hier
zichtbaarheid en geen overdracht aan een andere persoon, omdat er geen rol boven
de CISO bestaat.

Het scherm kleurt de deadline rood zodra de datum voorbij is, ook als het
nachtelijke commando nog niet heeft gedraaid. Een deadline binnen zeven dagen
wordt oranje getoond.

## De knoppen in de kolom Acties

Welke knoppen er bij een taak staan, hangt af van de status van de taak, het soort
taak en de rechten van de gebruiker. De eigenaar van de taak en een gebruiker met
`muteren` krijgen knoppen te zien. Voor alle andere gebruikers staat er een
streepje.

| Knop | Zichtbaar bij | Voor wie |
|---|---|---|
| **Start toets** | Een toetstaak die nog niet voltooid is | Alleen de eigenaar |
| **Oppakken** | Status *open* of *verlopen* | Eigenaar of `muteren` |
| **Voltooien** | Status *open*, *in uitvoering* of *verlopen*, bij een taak die geen uitkomst vraagt | Eigenaar of `muteren` |
| **Goedkeuren** en **Afkeuren** | Dezelfde statussen, bij een goedkeuringsstap | Eigenaar of `muteren` |
| **Heropenen** | Status *voltooid*, bij een losse taak of een taak uit een sjabloon | Alleen `muteren` |
| **Bewerken** | Elke status | Alleen `muteren` |

### Start toets

Deze knop opent de toets in een nieuw tabblad. De toets meldt het resultaat zelf
terug aan het systeem. Bij een geslaagde toets wordt de taak voltooid, komen de
score en het totaal in de omschrijving te staan en wordt, als de toets bij een
trainingsmodule hoort, de training als afgerond geregistreerd. Bij een gezakte
toets blijft de taak open, zodat de deelnemer het opnieuw kan proberen.

### Oppakken

Oppakken zet de taak op *in uitvoering*. Daarmee geeft de eigenaar aan dat het
werk is begonnen. De knop verandert niets aan de deadline. Een verlopen taak die
wordt opgepakt, staat na de eerstvolgende nachtelijke run weer op *verlopen*
zolang de deadline in het verleden ligt.

### Voltooien

Voltooien zet de taak op *voltooid* en legt de datum van afronding vast. Ligt die
datum na de deadline, dan toont het systeem het aantal dagen vertraging in de
melding en later in de kolom *Status*. Die vertraging blijft bewaard, omdat de
deadline van een voltooide taak niet meer verandert.

Bij een stap uit een reeks schuift de reeks door zodra de hele groep voltooid is.
Het maakt daarbij niet uit of de stap op `/taken` of op het dossierscherm is
afgerond.

### Goedkeuren en Afkeuren

Een goedkeuringsstap wordt niet afgerond met *Voltooien*, omdat "voltooid" niets
zegt over de uitkomst. In plaats daarvan staan er twee knoppen. Beide leggen de
uitkomst vast en voltooien de stap in één handeling. De kolom *Status* toont daarna
de badge *Goedgekeurd* of *Afgekeurd*.

Na **Goedkeuren** schuift de reeks door zodra de hele groep klaar is. Na
**Afkeuren** staat de reeks stil. Het
systeem kiest op `/taken` geen vervolg: de terugsprong naar een eerdere stap of het
afwijzen van de wijziging wordt op het dossierscherm besloten.

### Heropenen

Heropenen zet een voltooide taak terug op *open* en wist de datum van afronding.
Dit is een toezichtshandeling voor de CISO, bijvoorbeeld wanneer een afmelding
niet klopt. De eerdere voltooiing blijft in de audit trail herleidbaar. Ligt de
deadline in het verleden, dan staat de taak na de eerstvolgende nachtelijke run
weer op *verlopen*.

### Bewerken

Bewerken opent een formulier met de titel, de omschrijving, de eigenaar en de
deadline. Een nieuwe eigenaar moet een actief account zijn. Een eigenaar die
inmiddels niet meer actief is, mag bij het bewerken wel blijven staan. Boven de
tabel verschijnt een waarschuwing zodra er openstaande taken zijn bij een eigenaar
die niet meer actief is.

Bij een beheerde taak en bij een stap is de deadline alleen-lezen. De deadline
komt dan uit het register of uit de planning van het dossier, en een wijziging op
`/taken` zou bij de volgende planning worden overschreven. Om dezelfde reden kan
de titel van een beheerde taak bij een volgende planning terugspringen naar de
titel die het register opgeeft.

### Nieuwe taak

Deze knop staat rechtsboven het overzicht en niet in de kolom *Acties*. Hij is
alleen zichtbaar voor een gebruiker met `muteren` en maakt een losse taak aan, zoals
beschreven onder *Met de hand*.

## Wanneer een taak niet via /taken te sluiten is

In de volgende gevallen is een taak op `/taken` niet af te ronden, of heeft het
afronden daar niet het beoogde effect.

### De gebruiker heeft geen zeggenschap over de taak

Alleen de eigenaar en een gebruiker met `muteren` mogen een taak oppakken en
afronden. Een gebruiker die wel alle taken mag inzien maar geen `muteren` heeft,
ziet bij de taken van anderen een streepje. Het systeem controleert dit ook bij
de handeling zelf en niet alleen bij het tonen van de knop.

### De stap is nog niet aan de beurt

Een stap met de status *wachtend* heeft geen knoppen om af te ronden. De stap gaat
pas open wanneer alle stappen in de voorgaande groep zijn voltooid. De CISO kan
een wachtende stap wel bewerken, bijvoorbeeld om vooraf de eigenaar te corrigeren.

### De stap vraagt om een uitkomst

Een goedkeuringsstap heeft geen knop *Voltooien*. De stap wordt alleen afgerond
met *Goedkeuren* of *Afkeuren*.

### Het register houdt de taak tegen

Bij een aantal taken vraagt het systeem vóór het afronden aan het bijbehorende
register of de handeling werkelijk is verricht. Is dat niet zo, dan wordt de taak
niet opgeslagen en verschijnt boven de tabel de melding *Nog niet af te ronden*
met de reden. Deze controle zit in de taak zelf en niet in het scherm. Hij geldt
daardoor op `/taken` en op het dossierscherm, en ook voor *Goedkeuren* en
*Afkeuren*.

| Taak | Tegengehouden zolang | Wat er in plaats daarvan moet gebeuren |
|---|---|---|
| *Beleid vaststellen:* | De versie nog op vaststelling wacht. | De versie wordt vastgesteld met *Publiceren* bij Beleid & procedures. De taak sluit dan vanzelf, ook bij de andere goedkeurders. |
| *Lezen en bevestigen:* | De eigenaar de leesbevestiging nog niet heeft gegeven. | De eigenaar opent het document bij Beleid & procedures en bevestigt daar het lezen. De taak sluit dan vanzelf. |
| Uitvoerstap van een wijziging | Het veld *Terugvalplan* van de wijziging leeg is. | Het terugvalplan wordt op het dossier vastgelegd, zoals A.8.32 vraagt. |
| Stap met *bewijs verplicht* | Er nog geen bewijsstuk aan de wijziging is gekoppeld. | Er wordt een bewijsstuk aan de wijziging gekoppeld. |

### De taak is al voltooid

Een voltooide taak heeft geen knoppen om af te ronden. Alleen losse taken en
taken uit een sjabloon zijn te heropenen. Bij de andere soorten ontbreekt
*Heropenen* om de volgende redenen:

- Bij een beheerde taak is de status een afgeleide van het register. Het register
  bepaalt of er opnieuw een taak nodig is.
- Bij een stap uit een reeks zou heropenen de volgorde van de reeks ongeldig
  maken. Een stap gaat alleen terug via een afkeuring met terugsprong op het
  dossierscherm.
- Bij een toetstaak blijft de uitslag van een geslaagde toets bestaan. Heropenen
  zou de indruk wekken dat die uitslag ongedaan is gemaakt.

### Afronden sluit het signaal niet

Een beheerde taak zonder controle, zoals *SoA herbeoordelen* of *Retourcontrole*,
is met *Voltooien* af te ronden. Als de handeling in het register niet is verricht,
blijft het signaal bestaan. Bij de eerstvolgende planning ontstaat dan een nieuwe
taak voor hetzelfde record. Bij de nachtelijke signalen gebeurt dat de volgende
nacht, bij de signalen die bij het opslaan ontstaan de eerstvolgende keer dat het
record wordt opgeslagen. Het
afronden van zo'n taak is dus alleen zinvol nadat het werk in het register is
vastgelegd.

Een toetstaak is ook met *Voltooien* af te ronden zonder dat de toets is gemaakt.
In dat geval registreert het systeem geen afgeronde training. Die registratie
ontstaat alleen bij een geslaagde toets.
