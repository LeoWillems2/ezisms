# Verantwoording en disclaimer

Dit ISMS is gebouwd **zonder dat de normtekst zelf wordt meegeleverd**. Dat is
geen tekortkoming die later nog wordt ingehaald. Het is een ontwerpkeuze die
bepaalt wat dit systeem voor een organisatie is en wat het niet is. Deze pagina
beschrijft wat een gebruiker van dit systeem wel en niet mag verwachten, waar de
informatie vandaan komt en op welke punten de norm zelf nodig is.

Deze installatie draait op het **NEN 7510-profiel**. Daardoor is het voorbehoud
hieronder op één punt strenger dan in de ISO-uitvoering van dit systeem. Dat punt
is het belangrijkste om te weten en komt daarom als eerste aan bod.

## Geen meegeleverde maatregelteksten

**Bij geen enkele maatregel staat een omschrijving.** Dat geldt voor de acht
zorgspecifieke maatregelen en ook voor de 93 maatregelen die uit ISO 27001 komen.
Bij elke maatregel staan het nummer, de titel en het thema. Het veld voor de
omschrijving blijft leeg totdat de organisatie het zelf vult.

Dat is een bewuste keuze en geen omissie, en die keuze geldt in elke uitvoering
van dit systeem. Een eigen omschrijving van het doel van een maatregel is een
interpretatie van de norm, op precies de plek waar een auditor de
toepasselijkheid beoordeelt. Elk verschil met de normtekst leidt tot een
discussie die de organisatie niet hoeft te voeren.

Voor deze uitvoering komt daar een tweede, zwaardere reden bij. NEN 7510 stelt
bij 14 van die 93 maatregelen een **zwaardere eis** dan ISO 27001. Een
ISO-gerichte tekst zou op die plekken de eis onderschatten, precies waar de
organisatie de maatregel beoordeelt. Dat is erger dan een leeg veld.

Een leeg veld is eerlijk. Het geeft aan dat de norm geraadpleegd moet worden.

## Waar dit systeem geen vervanging voor is

**Dit is geen kopie van NEN 7510 en geen samenvatting ervan.** Een organisatie
kan zich met dit systeem niet laten certificeren zonder de norm te bezitten en te
lezen. Het systeem ondersteunt de administratie van een managementsysteem:
registers, beoordelingen, taken, bewijs, audits en rapportage. Het systeem geeft
echter geen gezaghebbend antwoord op de vraag wat de norm van de organisatie
eist.

Concreet betekent dat het volgende:

- **De verwijzingen zijn een hulpmiddel en geen bewijs.** Een verwijzing als
  "§9.3" of "A.8.16" geeft aan waar in het eigen exemplaar van de norm te kijken.
  De verwijzing garandeert niet dat de normtekst op die plek zegt wat hier staat.
- **Het verschil tussen "moeten" en "behoren te" is niet altijd vast te
  stellen.** Het onderscheid tussen een eis en een aanbeveling bepaalt of een
  auditor een afwijking noteert. Alleen de norm geeft dat onderscheid betrouwbaar
  weer. De bewoording in dit systeem geeft dat verschil niet noodzakelijk correct
  weer.
- **De zorgspecifieke aanvullingen ontbreken.** Het veld voor de aanvulling
  bestaat, maar de tekst ontbreekt; zie de volgende sectie.

## De zorgspecifieke aanvulling: het veld zonder de tekst

NEN 7510 markeert bij een deel van de maatregelen een **zorgspecifieke
beheersmaatregel**: een aanvulling bovenop wat ISO 27001 vraagt. Dit systeem
heeft daarvoor een eigen veld, met een eigen kopje in de SoA, los van de
omschrijving. Die scheiding is nodig omdat bron, licentiestatus en voorbehoud per
blok verschillen, en dat verschil hoort zichtbaar te blijven.

Wat de SoA toont, hangt af van wat er is ingelezen:

| Wat er staat | Wat het betekent |
|---|---|
| Geen blok | Deze maatregel heeft geen zorgspecifieke beheersmaatregel. |
| "Dit ISMS levert bij deze maatregel geen zorgspecifieke maatregel mee." | NEN 7510 heeft hier wel een zorgspecifieke beheersmaatregel. De inhoud daarvan staat in de norm zelf. |
| "Niet ingelezen…" | Er is helemaal niets ingelezen. Daardoor is zelfs niet bekend of deze maatregel een zorgspecifieke beheersmaatregel heeft. Dat is een installatiefout, omdat de lijst hoort te zijn meegeleverd. |

Dat onderscheid is bewust aangebracht. Zonder de derde stand zou een installatie
waarin de lijst niet is geladen, er precies zo uitzien als een installatie waarin
geen enkele maatregel een aanvulling heeft. Dat verschil hoort zichtbaar te zijn.

De tweede stand bevat bewust geen normtekst, ook niet in een installatie waarvoor
de norm is gekocht. Dat is dezelfde keuze als bij de maatregelomschrijvingen: het
ISMS meldt dat er iets staat en waar het te vinden is, in plaats van de tekst te
verspreiden. Die keuze voorkomt bovendien een risico dat de organisatie anders
zelf loopt. Zodra de tekst in het systeem staat, gaat die mee in elke export,
schermkopie of demonstratie.

**De lijst met maatregelen die een aanvulling hebben, wordt wel meegeleverd.**
Het is openbaar bekend dat een maatregel een zorgspecifieke beheersmaatregel
heeft. Alleen de inhoud van die beheersmaatregel is niet openbaar. De SoA toont
daardoor direct bij welke 22 van de 101 maatregelen de norm geraadpleegd moet
worden, zonder dat dit systeem inhoud uit de norm doorgeeft.

## Wat wel uit openbare bronnen komt

Veel van wat een ISMS nodig heeft, is openbaar en onomstreden. Dat geldt voor de
hoofdstukindeling H4–H10, het bestaan van de maatregelen met hun nummers, titels
en vier thema's, de verplichting van een Verklaring van Toepasselijkheid, de
onderwerpen van een directiebeoordeling en de cyclus van afwijking naar
corrigerende maatregel. Deze onderwerpen staan in talloze openbare bronnen. De
hoofdstukindeling H4–H10 is de Harmonized Structure die NEN 7510-1 deelt met ISO
27001 en met elke andere moderne managementsysteemnorm.

Ook de opbouw van de norm zelf is openbaar. **NEN 7510-1 bevat de eisen** aan het
managementsysteem, waartegen een organisatie wordt gecertificeerd. **NEN 7510-2
bevat de beheersmaatregelen** met hun toelichting. Voor de vocabulaire is ISO/IEC
27000 gratis verkrijgbaar. Die norm dekt de zorgspecifieke begrippen echter niet.

De driejarige certificeringscyclus bestaat uit een initiële audit, twee
opvolgingsaudits en een hercertificering. Die cyclus komt niet uit de norm zelf,
maar uit de accreditatieregels voor certificerende instellingen. Die regels zijn
openbaar.

De **architectuur** van dit systeem is op geen enkel punt van de norm afgeleid.
De indeling in blokken, het rechtenmodel, de audit trail en de manier waarop
bewijs aan records is gekoppeld, zijn gewoon softwareontwerp.

## Caveat emptor

Op enkele plekken schieten openbare bronnen structureel tekort. Die plekken zijn
hieronder benoemd in plaats van stilzwijgend ingevuld.

**De attribuuttabellen.** Elke maatregel heeft vijf attribuutdimensies. Dit
systeem levert er vier mee als *uitgangspunt*. Die vier zijn herleid uit openbare
gegevens en zijn uitdrukkelijk bedoeld om door de organisatie te worden
vastgesteld. Een classificatie die de organisatie zelf vastlegt, komt naast het
uitgangspunt te staan en overschrijft het niet. Zo blijft altijd zichtbaar wat
het systeem meeleverde en wat de organisatie heeft bepaald. De vijfde dimensie,
beveiligingscapaciteiten, ontbreekt bewust. Die toewijzing is niet uit openbare
bronnen te herleiden en staat alleen in de norm.
[Maatregelclassificatie](/kennisbank/maatregelclassificatie) beschrijft het
volledige verhaal en legt uit hoe een organisatie die de norm bezit, de vijfde
dimensie vult.

**De acht zorgspecifieke maatregelen zijn beoordeeld op hun titel.** Over de
strekking van de 93 maatregelen uit Bijlage A is veel openbaar beschreven. Over de
acht maatregelen die alleen NEN 7510 kent, is veel minder openbaar beschreven.
Hun classificatie leunt daarom zwaarder op de titel. Die classificatie hoort te
worden nagelopen.

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

Een organisatie die de norm heeft aangeschaft, kan de maatregelteksten en de
zorgspecifieke aanvullingen lokaal invoeren; [De normteksten
invoeren](/kennisbank/normteksten-invoeren) beschrijft hoe. Dat is toegestaan voor
eigen gebruik, maar **de bestanden met die teksten mogen niet worden verspreid.**

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

## Wat buiten dit systeem valt

NEN 7510 stelt eisen aan de informatiebeveiliging van de **zorgsystemen van de
organisatie**. Dit systeem is een ISMS-platform. Het administreert het
managementsysteem, is zelf geen zorgsysteem en verwerkt geen persoonlijke
gezondheidsinformatie. Maatregelen als cliëntidentificatie,
break-glass-toegang en logging volgens NEN 7513 gaan over het EPD en de
zorgapplicaties, en niet over deze software. De organisatie motiveert die
maatregelen hier in de SoA, maar implementeert ze in andere systemen.

[Wat NEN 7510 toevoegt bovenop ISO 27001](/kennisbank/wat-nen-7510-toevoegt)
beschrijft de volledige afbakening.

## Auteursrecht en verspreiding

NEN- en ISO-normen zijn auteursrechtelijk beschermd en worden per exemplaar in
licentie gegeven. Bij NEN 7510 zijn er **twee rechthebbenden**: NEN voor de
Nederlandse norm, en ISO/IEC voor de tekst die NEN 7510 uit ISO/IEC-normen
overneemt. Voor dit systeem betekent dat het volgende:

- **Er staat geen normtekst in.** De code, de gegevens en de kennisbank bevatten
  geen normtekst. De maatregelomschrijvingen zijn leeg, en de zorgspecifieke
  aanvullingen worden niet meegeleverd.
- **Het systeem levert de aanwijzing, maar niet de inhoud.** Welke maatregelen
  een zorgspecifieke beheersmaatregel hebben, is openbaar bekend en zit daarom wel
  in de uitlevering. Die lijst is een verwijzing naar de norm en geen weergave
  ervan, net als de nummers en de titels in het volgende punt.
- **Referenties, nummers, titels en thema's zijn wel gelijk gehouden** aan de
  norm. Zonder die gelijkheid werkt de koppeling met een audit niet: een auditor
  die naar A.5.43 vraagt, moet A.5.43 kunnen vinden.
- **Norm-eigen gegevens die de organisatie zelf toevoegt, blijven van de
  organisatie en blijven lokaal.** Een zelf ingevulde capaciteitendimensie of zelf
  ingevoerde aanvullingsteksten komen in bestanden die niet in versiebeheer
  terechtkomen.

## Aansprakelijkheid

Dit systeem wordt geleverd zoals het is. Het systeem geeft geen juridisch advies,
geen certificeringsgarantie en geen uitspraak over de vraag of de organisatie aan
enige norm voldoet. Die beoordeling ligt bij de organisatie zelf, bij haar
adviseur en uiteindelijk bij haar certificerende instelling. Waar het om toezicht
gaat, ligt die beoordeling bij de Inspectie Gezondheidszorg en Jeugd.
