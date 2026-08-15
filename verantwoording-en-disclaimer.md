# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die nog wordt ingehaald: het is een ontwerpkeuze die bepaalt
wat dit systeem voor je is en wat het niet is. Deze pagina legt uit wat je hier
wel en niet mag verwachten, waar de informatie vandaan komt, en waar je zelf de
norm nodig hebt.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van ISO/IEC 27001 en geen samenvatting ervan.** Je kunt je
organisatie er niet mee certificeren zonder de norm te bezitten en te lezen. Het
systeem ondersteunt de administratie van een managementsysteem — registers,
beoordelingen, taken, bewijs, audits, rapportage — maar het vertelt je niet
gezaghebbend wat de norm van je eist.

Concreet betekent dat:

- **De verwijzingen zijn een hulpmiddel, geen bewijs.** Als hier "§9.3" of
  "A.8.16" staat, is dat een aanwijzing waar je in je eigen exemplaar moet
  kijken. Het is geen garantie dat de tekst daar zegt wat je hier leest.
- **Wij leveren geen maatregelteksten.** Bij elke maatregel staat de officiële
  titel en verder de mededeling dat de omschrijving ontbreekt. Dat is met opzet;
  de volgende sectie legt uit waarom.
- **"Moeten" en "behoren te" zijn niet altijd hard te maken.** Het onderscheid
  tussen een eis en een aanbeveling bepaalt of een auditor een afwijking schrijft.
  Dat onderscheid staat in de norm en nergens anders betrouwbaar. Ga er niet van
  uit dat de bewoording hier dat verschil correct weergeeft.

## Wat wél uit openbare bronnen komt

Veel van wat een ISMS nodig heeft, is publiek en onomstreden. De hoofdstukindeling
H4–H10, het bestaan van de 93 maatregelen met hun nummers, titels en vier thema's,
de verplichting van een Verklaring van Toepasselijkheid, de onderwerpen die in een
directiebeoordeling aan bod komen, de cyclus van afwijking naar corrigerende
maatregel: dat staat in talloze openbare bronnen, en de vocabulairenorm
ISO/IEC 27000 is zelfs gratis verkrijgbaar.

Ook de driejarige certificeringscyclus — initiële audit, twee opvolgingsaudits,
hercertificering — komt niet uit 27001 zelf maar uit de accreditatieregels voor
certificerende instellingen, en die zijn openbaar.

De **architectuur** van dit systeem is helemaal niet norm-afgeleid: de indeling in
blokken, het rechtenmodel, de audit trail, de manier waarop bewijs aan records
hangt. Dat is gewoon softwareontwerp.

## Waarom er geen omschrijving bij de maatregelen staat

Kom je hier vandaan via de Verklaring van Toepasselijkheid, dan is dit de reden.

**Bij geen enkele maatregel staat een omschrijving.** Je ziet nummer, titel en
thema — de rest is leeg tot je hem zelf vult.

Tot augustus 2026 stond er bij de 93 ISO-maatregelen een omschrijving in eigen
woorden, met een voorbehoud eronder. Die zijn weggehaald, en niet omdat er iets
mis mee was: het waren eigen formuleringen, geschreven vanuit de bedoeling van de
maatregel, en ze mochten verspreid worden.

**Ze zijn weg omdat een eigen omschrijving een interpretatie van de norm is, op
precies de plek waar een auditor de toepasselijkheid beoordeelt.** Elk verschil
tussen die tekst en de normtekst is een discussie die jouw organisatie niet hoeft
te voeren. Het voorbehoud eronder haalde dat niet weg — het meldde alleen dat er
iets te bediscussiëren viel.

Wat je nu ziet is de officiële titel, en verder een mededeling met een link naar
deze pagina. Heb je de norm, dan zet je de echte tekst er zelf in; zie [De
normteksten invoeren](/kennisbank/normteksten-invoeren). Wat jij invoert ís de
normtekst, en daar hoort geen voorbehoud bij.

## Caveat emptor

Er zijn plekken waar openbare bronnen structureel tekortschieten. Wij hebben ze
liever benoemd dan stilzwijgend ingevuld.

**De attribuuttabellen van ISO 27002.** Elke maatregel heeft daar vijf
attribuutdimensies. Dit systeem levert er vier mee als *uitgangspunt*, herleid
uit openbare gegevens en uitdrukkelijk bedoeld om door jou vastgesteld te worden.
Leg je zelf een classificatie vast, dan komt die naast het uitgangspunt te staan
en niet eroverheen — zo is altijd te zien wat wij meegaven en wat jullie hebben
bepaald. De vijfde, beveiligingscapaciteiten, ontbreekt bewust: die toewijzing is
niet uit openbare bronnen te herleiden en staat alleen in de norm. Zie [Maatregelclassificatie](/kennisbank/maatregelclassificatie) voor het
volledige verhaal en voor hoe je hem vult als je de norm wél bezit.

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

Heb je de norm aangeschaft, dan kun je de exacte maatregelteksten uit de norm
overnemen; [De normteksten invoeren](/kennisbank/normteksten-invoeren) beschrijft
hoe. Dat mag voor eigen gebruik, maar **dat bestand mag je niet distribueren.**

## En wat het systeem niet kan weten

Een kanttekening in de andere richting, want die is minstens zo belangrijk.

De inrichting van dit systeem is op meerdere punten bijgestuurd door
praktijkkennis die **nergens in de norm staat**. Dat de interne auditcyclus pas
echt begint ná de certificeringsaudit. Dat er in de aanloop meerdere auditrondes
in één jaar vallen. Dat de eerste ronde een nulmeting is en niet een oordeel.

Een systeem dat de norm perfect zou naspreken maar dat soort dingen niet weet,
levert een ISMS op dat formeel klopt en in de praktijk niet werkt. De norm is de
ondergrens van wat je moet regelen, niet de handleiding voor hoe je het regelt.

## Auteursrecht en verspreiding

ISO- en NEN-normen zijn auteursrechtelijk beschermd en worden per exemplaar in
licentie gegeven. Dat betekent voor dit systeem:

- **Er staat geen normtekst in.** Niet in de code, niet in de gegevens, niet in
  de kennisbank. Bij de maatregelen staat geen omschrijving; zie hierboven.
- **Referenties, nummers en titels zijn wél gelijk gehouden** aan de norm. Zonder
  dat werkt de koppeling met een audit niet: een auditor die naar A.5.15 vraagt,
  moet A.5.15 kunnen vinden.
- **Norm-eigen gegevens die je zelf toevoegt, blijven van jou en blijven lokaal.**
  Vul je de capaciteitendimensie aan, dan komt jouw toewijzing in een bestand dat
  niet in versiebeheer terechtkomt.

## Aansprakelijkheid

Dit systeem wordt geleverd zoals het is. Het geeft geen juridisch advies, geen
certificeringsgarantie en geen uitspraak over de vraag of jouw organisatie aan
enige norm voldoet. Die beoordeling is aan jou, je adviseur en uiteindelijk aan
je certificerende instelling.
