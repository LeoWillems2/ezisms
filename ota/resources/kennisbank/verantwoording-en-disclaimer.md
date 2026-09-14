# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die later nog wordt ingehaald. Het is een ontwerpkeuze die
bepaalt wat dit systeem voor een organisatie is en wat het niet is. Deze pagina
beschrijft wat een gebruiker van dit systeem wel en niet mag verwachten, waar de
informatie vandaan komt en op welke punten de norm zelf nodig is.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van ISO/IEC 27001 en geen samenvatting ervan.** Een
organisatie kan zich met dit systeem niet laten certificeren zonder de norm te
bezitten en te lezen. Het systeem ondersteunt de administratie van een
managementsysteem: registers, beoordelingen, taken, bewijs, audits en
rapportage. Het systeem geeft echter geen gezaghebbend antwoord op de vraag wat
de norm van de organisatie eist.

Concreet betekent dat het volgende:

- **De verwijzingen zijn een hulpmiddel en geen bewijs.** Een verwijzing als
  "§9.3" of "A.8.16" geeft aan waar in het eigen exemplaar van de norm te kijken.
  De verwijzing garandeert niet dat de normtekst op die plek zegt wat hier staat.
- **Dit systeem levert geen maatregelteksten.** Bij elke maatregel staan de
  officiële titel en een mededeling dat de omschrijving ontbreekt. Dat is een
  bewuste keuze. De sectie *Waarom er geen omschrijving bij de maatregelen staat*
  beschrijft de reden.
- **Het verschil tussen "moeten" en "behoren te" is niet altijd vast te
  stellen.** Het onderscheid tussen een eis en een aanbeveling bepaalt of een
  auditor een afwijking noteert. Alleen de norm geeft dat onderscheid betrouwbaar
  weer. De bewoording in dit systeem geeft dat verschil niet noodzakelijk correct
  weer.

## Wat wel uit openbare bronnen komt

Veel van wat een ISMS nodig heeft, is openbaar en onomstreden. Dat geldt voor de
hoofdstukindeling H4–H10, het bestaan van de 93 maatregelen met hun nummers,
titels en vier thema's, de verplichting van een Verklaring van Toepasselijkheid,
de onderwerpen van een directiebeoordeling en de cyclus van afwijking naar
corrigerende maatregel. Deze onderwerpen staan in talloze openbare bronnen. De
vocabulairenorm ISO/IEC 27000 is bovendien gratis verkrijgbaar.

De driejarige certificeringscyclus bestaat uit een initiële audit, twee
opvolgingsaudits en een hercertificering. Die cyclus komt niet uit ISO 27001
zelf, maar uit de accreditatieregels voor certificerende instellingen. Die regels
zijn openbaar.

De **architectuur** van dit systeem is op geen enkel punt van de norm afgeleid.
De indeling in blokken, het rechtenmodel, de audit trail en de manier waarop
bewijs aan records is gekoppeld, zijn gewoon softwareontwerp.

## Waarom er geen omschrijving bij de maatregelen staat

Wie deze pagina opent via de link in de Verklaring van Toepasselijkheid, vindt
hier de reden.

**Bij geen enkele maatregel staat een omschrijving.** Bij elke maatregel staan
het nummer, de titel en het thema. Het veld voor de omschrijving blijft leeg
totdat de organisatie het zelf vult.

Tot augustus 2026 stond bij de 93 ISO-maatregelen een omschrijving in eigen
woorden, met een voorbehoud eronder. Die omschrijvingen zijn verwijderd. De reden
was niet dat er iets mis mee was. Het waren eigen formuleringen, geschreven
vanuit de bedoeling van de maatregel, en ze mochten worden verspreid.

**De omschrijvingen zijn verwijderd omdat een eigen omschrijving een
interpretatie van de norm is, op precies de plek waar een auditor de
toepasselijkheid beoordeelt.** Elk verschil tussen die tekst en de normtekst
leidt tot een discussie die de organisatie niet hoeft te voeren. Het voorbehoud
nam dat probleem niet weg. Het voorbehoud meldde alleen dat er iets te
bediscussiëren viel.

Nu staan bij elke maatregel de officiële titel en een mededeling met een link
naar deze pagina. Een organisatie die de norm bezit, kan de echte tekst zelf
invoeren; zie [De normteksten invoeren](/kennisbank/normteksten-invoeren). Wat de
organisatie zo invoert, is de normtekst zelf, en daar hoort geen voorbehoud bij.

## Caveat emptor

Op enkele plekken schieten openbare bronnen structureel tekort. Die plekken zijn
hieronder benoemd in plaats van stilzwijgend ingevuld.

**De attribuuttabellen van ISO 27002.** ISO 27002 kent per maatregel vijf
attribuutdimensies. Dit systeem levert er vier mee als *uitgangspunt*. Die vier
zijn herleid uit openbare gegevens en zijn uitdrukkelijk bedoeld om door de
organisatie te worden vastgesteld. Een classificatie die de organisatie zelf
vastlegt, komt naast het uitgangspunt te staan en overschrijft het niet. Zo
blijft altijd zichtbaar wat het systeem meeleverde en wat de organisatie heeft
bepaald. De vijfde dimensie, beveiligingscapaciteiten, ontbreekt bewust. Die
toewijzing is niet uit openbare bronnen te herleiden en staat alleen in de norm.
[Maatregelclassificatie](/kennisbank/maatregelclassificatie) beschrijft het
volledige verhaal en legt uit hoe een organisatie die de norm bezit, de vijfde
dimensie vult.

**Exacte sub-lettering.** Verwijzingen tot op letterniveau ("6.1.3 d") zijn
cosmetisch, maar een auditor controleert juist die verwijzingen als eerste. Deze
verwijzingen horen te worden gecontroleerd tegen het eigen exemplaar van de norm.

## Het eigenlijke risico: verifieerbaarheid

Het probleem is niet dat er een deel ontbreekt. Het probleem is dat **een juiste
en een verzonnen bewering er even stellig uitzien**. Een systeem dat de norm niet
kan raadplegen, kan ook niet zichtbaar maken welk deel onzeker is.

Daarom was de leidende regel bij het bouwen van dit ISMS: liever een gat dan een
plausibele gok. Een lege dimensie is eerlijk. Een verzonnen dimensie verspreidt
zich door SoA-onderbouwingen en is achteraf niet meer op te sporen.

Voor de organisatie betekent dat: **elke van de norm afgeleide bewering in dit
systeem is een aanwijzing die de organisatie zelf verifieert.** Voor een
certificeringstraject is dat verificatiewerk hoe dan ook nodig, met of zonder dit
systeem.

Een organisatie die de norm heeft aangeschaft, kan de exacte maatregelteksten uit
de norm overnemen; [De normteksten invoeren](/kennisbank/normteksten-invoeren)
beschrijft hoe. Dat is toegestaan voor eigen gebruik, maar **het bestand met die
teksten mag niet worden verspreid.**

## En wat het systeem niet kan weten

Er is ook een kanttekening in de andere richting, en die is minstens zo
belangrijk.

De inrichting van dit systeem is op meerdere punten bijgestuurd door
praktijkkennis die **nergens in de norm staat**. De interne auditcyclus begint
pas echt na de certificeringsaudit. In de aanloop naar certificering vallen
meerdere auditrondes in één jaar. De eerste ronde is een nulmeting en geen
oordeel.

Een systeem dat de norm perfect navolgt maar zulke praktijkkennis mist, levert
een ISMS op dat formeel klopt en in de praktijk niet werkt. De norm is de
ondergrens van wat een organisatie moet regelen, en geen handleiding voor de
manier waarop.

## Auteursrecht en verspreiding

ISO- en NEN-normen zijn auteursrechtelijk beschermd en worden per exemplaar in
licentie gegeven. Voor dit systeem betekent dat het volgende:

- **Er staat geen normtekst in.** De code, de gegevens en de kennisbank bevatten
  geen normtekst. Bij de maatregelen staat geen omschrijving; zie hierboven.
- **Referenties, nummers en titels zijn wel gelijk gehouden** aan de norm. Zonder
  die gelijkheid werkt de koppeling met een audit niet: een auditor die naar
  A.5.15 vraagt, moet A.5.15 kunnen vinden.
- **Norm-eigen gegevens die de organisatie zelf toevoegt, blijven van de
  organisatie en blijven lokaal.** Een zelf ingevulde capaciteitendimensie komt
  in een bestand dat niet in versiebeheer terechtkomt.

## Aansprakelijkheid

Dit systeem wordt geleverd zoals het is. Het systeem geeft geen juridisch advies,
geen certificeringsgarantie en geen uitspraak over de vraag of de organisatie aan
enige norm voldoet. Die beoordeling ligt bij de organisatie zelf, bij haar
adviseur en uiteindelijk bij haar certificerende instelling.
