# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die nog wordt ingehaald: het is een ontwerpkeuze die bepaalt
wat dit systeem voor je is en wat het niet is. Deze pagina legt uit wat je hier
wel en niet mag verwachten, waar de informatie vandaan komt, en waar je zelf de
norm nodig hebt.

Deze installatie draait op het **NEN 7510-profiel**. Dat maakt het voorbehoud
hieronder op één punt strenger dan in de ISO-uitvoering van dit systeem, en dat
punt is het eerste dat je moet weten.

## Wij leveren geen maatregelteksten

**Bij geen enkele maatregel staat een omschrijving.** Niet bij de acht
zorgspecifieke maatregelen, en ook niet bij de 93 die uit ISO 27001 komen. Je
ziet nummer, titel en thema — de rest is leeg tot je hem zelf vult.

Dat is een bewuste keuze en geen omissie, en ze geldt in beide uitvoeringen van
dit systeem: een eigen omschrijving van wat een maatregel beoogt is een
interpretatie van de norm, op precies de plek waar een auditor de toepasselijkheid
beoordeelt. Elk verschil met de normtekst is een discussie die jouw organisatie
niet hoeft te voeren.

Voor deze uitvoering komt daar een tweede reden bij, en die is zwaarder. NEN 7510
legt op veertien van die 93 maatregelen een **zwaardere eis** dan ISO 27001 doet.
Wie een ISO-gerichte tekst hier zou overnemen, zou de eis onderschatten op precies
de plek waar jij hem beoordeelt — en dat is erger dan een leeg veld.

Een leeg veld is eerlijk. Het zegt: kijk in de norm.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van NEN 7510 en geen samenvatting ervan.** Je kunt je
organisatie er niet mee certificeren zonder de norm te bezitten en te lezen. Het
systeem ondersteunt de administratie van een managementsysteem — registers,
beoordelingen, taken, bewijs, audits, rapportage — maar het vertelt je niet
gezaghebbend wat de norm van je eist.

Concreet betekent dat:

- **De verwijzingen zijn een hulpmiddel, geen bewijs.** Als hier "§9.3" of
  "A.8.16" staat, is dat een aanwijzing waar je in je eigen exemplaar moet
  kijken. Het is geen garantie dat de tekst daar zegt wat je hier leest.
- **"Moeten" en "behoren te" zijn niet altijd hard te maken.** Het onderscheid
  tussen een eis en een aanbeveling bepaalt of een auditor een afwijking schrijft.
  Dat onderscheid staat in de norm en nergens anders betrouwbaar. Ga er niet van
  uit dat de bewoording hier dat verschil correct weergeeft.
- **De zorgspecifieke aanvullingen ontbreken.** Zie hieronder — het veld bestaat,
  de tekst niet.

## De zorgspecifieke aanvulling: het veld zonder de tekst

NEN 7510 markeert bij een deel van de maatregelen een **zorgspecifieke
beheersmaatregel**: een aanvulling bovenop wat ISO 27001 vraagt. Dit systeem
heeft daar een eigen veld voor, met een eigen kopje in de SoA, los van de
omschrijving — want bron, licentiestatus en voorbehoud verschillen per blok en
dat hoort zichtbaar te blijven.

Wat je ziet hangt af van wat er is ingelezen:

| Wat er staat | Wat het betekent |
|---|---|
| Geen blok | Déze maatregel heeft geen zorgspecifieke beheersmaatregel. |
| "Dit ISMS levert bij deze maatregel geen zorgspecifieke maatregel mee." | NEN 7510 heeft hier wél een zorgspecifieke beheersmaatregel. Wat die inhoudt, leest u in de norm zelf. |
| "Niet ingelezen…" | Er is helemaal niets ingelezen, dus het is niet eens bekend óf deze maatregel er een heeft. Dat is een installatiefout: de lijst hoort meegeleverd te zijn. |

Dat onderscheid is er met opzet. Zou de derde stand ontbreken, dan zou een
installatie waar de lijst niet is geladen er precies zo uitzien als een waarin
geen enkele maatregel een aanvulling heeft — en dat is een verschil dat u wilt
zien.

De derde stand draagt bewust geen normtekst, ook niet in een installatie waar de
norm gekocht is. Dat is dezelfde keuze als bij de maatregelomschrijvingen: het
ISMS zegt *dát* er iets staat en waar u het vindt, in plaats van de tekst te
verspreiden. Dat scheelt bovendien een risico dat u anders zelf loopt — een
export, een schermkopie of een demonstratie neemt de tekst mee zodra hij in het
systeem staat.

**De lijst "welke maatregelen hebben een aanvulling" wordt wél meegeleverd.**
Dát een maatregel een zorgspecifieke beheersmaatregel heeft, is openbaar bekend;
alleen wat er staat, is dat niet. U ziet dus meteen bij welke 22 van de 101
maatregelen u de norm erbij moet pakken — zonder dat dit systeem iets uit die
norm doorgeeft.

## Wat wél uit openbare bronnen komt

Veel van wat een ISMS nodig heeft, is publiek en onomstreden. De
hoofdstukindeling H4–H10 — de Harmonized Structure die NEN 7510-1 deelt met ISO
27001 en met elke andere moderne managementsysteemnorm — het bestaan van de
maatregelen met hun nummers, titels en vier thema's, de verplichting van een
Verklaring van Toepasselijkheid, de onderwerpen die in een directiebeoordeling
aan bod komen, de cyclus van afwijking naar corrigerende maatregel: dat staat in
talloze openbare bronnen.

Ook de opbouw van de norm zelf is openbaar: **NEN 7510-1 bevat de eisen** (het
managementsysteem, waartegen je certificeert) en **NEN 7510-2 de
beheersmaatregelen** met hun toelichting. Voor de vocabulaire is er ISO/IEC
27000, dat gratis verkrijgbaar is — maar let op dat die de zorgspecifieke
begrippen niet dekt.

Ook de driejarige certificeringscyclus — initiële audit, twee opvolgingsaudits,
hercertificering — komt niet uit de norm zelf maar uit de accreditatieregels voor
certificerende instellingen, en die zijn openbaar.

De **architectuur** van dit systeem is helemaal niet norm-afgeleid: de indeling in
blokken, het rechtenmodel, de audit trail, de manier waarop bewijs aan records
hangt. Dat is gewoon softwareontwerp.

## Caveat emptor

Er zijn plekken waar openbare bronnen structureel tekortschieten. Wij hebben ze
liever benoemd dan stilzwijgend ingevuld.

**De attribuuttabellen.** Elke maatregel heeft vijf attribuutdimensies. Dit
systeem levert er vier mee als *uitgangspunt*, herleid uit openbare gegevens en
uitdrukkelijk bedoeld om door jou vastgesteld te worden. Leg je zelf een
classificatie vast, dan komt die naast het uitgangspunt te staan en niet
eroverheen — zo is altijd te zien wat wij meegaven en wat jullie hebben bepaald.
De vijfde, beveiligingscapaciteiten, ontbreekt bewust: die toewijzing is niet uit
openbare bronnen te herleiden en staat alleen in de norm. Zie [Maatregelclassificatie](/kennisbank/maatregelclassificatie) voor het
volledige verhaal en voor hoe je hem vult als je de norm wél bezit.

**De acht zorgspecifieke maatregelen zijn beoordeeld op hun titel.** Voor de 93
maatregelen uit Bijlage A is over hun strekking veel openbaar beschreven; voor
deze acht, die alleen NEN 7510 kent, is dat veel dunner. Hun classificatie leunt
dus zwaarder op de titel. Loop ze na.

**Exacte sub-lettering.** Verwijzingen tot op letterniveau ("6.1.3 d") zijn
cosmetisch, maar het is wel het eerste wat een auditor natrekt. Controleer ze
tegen je eigen exemplaar.

## Het eigenlijke risico: verifieerbaarheid

Het probleem is niet dat er een deel ontbreekt. Het probleem is dat **een juiste
en een verzonnen bewering er even stellig uitzien**. Een systeem dat de norm niet
kan raadplegen, kan ook niet zichtbaar maken wélk deel onzeker is.

Daarom is de leidende regel bij het bouwen van dit ISMS geweest: liever een gat
dan een plausibele gok. Een lege dimensie is eerlijk; een verzonnen dimensie gaat
rondzingen in SoA-onderbouwingen en is achteraf niet meer terug te vinden.

Wat dat voor jou betekent: **behandel elke norm-afgeleide bewering in dit systeem
als een aanwijzing die je zelf verifieert.** Voor een certificeringstraject is dat
sowieso werk dat je doet — met of zonder dit systeem.

Heb je de norm aangeschaft, dan kun je de maatregelteksten en de zorgspecifieke
aanvullingen lokaal invoeren; [De normteksten
invoeren](/kennisbank/normteksten-invoeren) beschrijft hoe. Dat mag voor eigen
gebruik, maar **die bestanden mag je niet distribueren.**

## En wat het systeem niet kan weten

Een kanttekening in de andere richting, want die is minstens zo belangrijk.

De inrichting van dit systeem is op meerdere punten bijgestuurd door
praktijkkennis die **nergens in de norm staat**. Dat de interne auditcyclus pas
echt begint ná de certificeringsaudit. Dat er in de aanloop meerdere auditrondes
in één jaar vallen. Dat de eerste ronde een nulmeting is en niet een oordeel.

Een systeem dat de norm perfect zou naspreken maar dat soort dingen niet weet,
levert een ISMS op dat formeel klopt en in de praktijk niet werkt. De norm is de
ondergrens van wat je moet regelen, niet de handleiding voor hoe je het regelt.

## Wat buiten dit systeem valt

NEN 7510 stelt eisen aan de informatiebeveiliging van de **zorgsystemen van je
organisatie**. Dit is een ISMS-platform: het administreert je managementsysteem,
het is zelf geen zorgsysteem en het verwerkt geen persoonlijke
gezondheidsinformatie. Maatregelen als cliëntidentificatie, break-glass-toegang
en logging volgens NEN 7513 gaan over je EPD en je zorgapplicaties, niet over
deze software — je motiveert ze hier in de SoA, je bouwt ze elders.

Zie [Wat NEN 7510 toevoegt bovenop ISO 27001](/kennisbank/wat-nen-7510-toevoegt)
voor de volledige afbakening.

## Auteursrecht en verspreiding

NEN- en ISO-normen zijn auteursrechtelijk beschermd en worden per exemplaar in
licentie gegeven. Bij NEN 7510 spelen er **twee rechthebbenden**: NEN voor de
Nederlandse norm, en ISO/IEC voor de tekst die NEN 7510 daaruit overneemt. Dat
betekent voor dit systeem:

- **Er staat geen normtekst in.** Niet in de code, niet in de gegevens, niet in
  de kennisbank. De maatregelomschrijvingen zijn leeg, en de zorgspecifieke
  aanvullingen worden niet meegeleverd.
- **Wel de aanwijzing, niet de inhoud.** Wélke maatregelen een zorgspecifieke
  beheersmaatregel dragen, is openbaar bekend en zit daarom wél in de
  uitlevering. Het is een verwijzing naar de norm, geen weergave ervan — net als
  de nummers en de titels hieronder.
- **Referenties, nummers, titels en thema's zijn wél gelijk gehouden** aan de
  norm. Zonder dat werkt de koppeling met een audit niet: een auditor die naar
  A.5.43 vraagt, moet A.5.43 kunnen vinden.
- **Norm-eigen gegevens die je zelf toevoegt, blijven van jou en blijven lokaal.**
  Vul je de capaciteitendimensie of de aanvullingsteksten zelf aan, dan komt jouw
  invulling in bestanden die niet in versiebeheer terechtkomen.

## Aansprakelijkheid

Dit systeem wordt geleverd zoals het is. Het geeft geen juridisch advies, geen
certificeringsgarantie en geen uitspraak over de vraag of jouw organisatie aan
enige norm voldoet. Die beoordeling is aan jou, je adviseur en uiteindelijk aan
je certificerende instelling — en waar het om toezicht gaat, aan de
Inspectie Gezondheidszorg en Jeugd.
