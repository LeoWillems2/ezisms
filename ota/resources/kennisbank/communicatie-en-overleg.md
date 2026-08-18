Clausule 7.4 vraagt je te bepalen **wat** je over informatiebeveiliging
communiceert, **wanneer**, **met wie** en **hoe** — intern én extern. Deze pagina
laat zien hoe je dat in dit systeem vastlegt zonder er een apart project van te
maken. Er komt geen nieuw scherm aan te pas: de bouwstenen staan er al, ze staan
alleen verspreid.

## Wat de norm vraagt, en wat niet

De clausule verplicht je tot een besluit, niet tot een document. Er is geen
voorgeschreven "communicatieplan" en geen verplichte vorm; wat een auditor doet,
is ernaar vragen en vervolgens de sporen bekijken — verstuurde berichten,
notulen, trainingsregistraties, bevestigingen van ontvangst. Juist die sporen
legt dit systeem al vast.

Dat betekent twee dingen. Je hoeft geen zware inrichting te bouwen om aan 7.4 te
voldoen. En je moet wél kunnen uitleggen wie wat met wie deelt, want "dat gaat
vanzelf" is geen antwoord op de vraag van de auditor.

> **Let op het onderscheid met overleggen.** Clausule 7.4 zegt niets over
> vergaderingen, overlegstructuren of notulen. Een overzicht van terugkerende
> overleggen is nuttig en hoort bij leiderschap (clausule 5.1) en bij de
> managementreview (9.3) — maar presenteer het niet als 7.4-verplichting. Doe je
> dat wel, dan documenteer je meer dan de norm vraagt en zit je er de rest van de
> certificeringscyclus aan vast.

## Het overzicht: leg het plan vast als procedure

Zet het communicatieplan neer als document onder **Beleid**, met type
`procedure`. Daarmee krijgt het wat een los bestand op een netwerkschijf niet
heeft: een versie, een eigenaar, een goedkeuringsstatus en een publicatiemoment,
allemaal in de audit trail.

Gaat het plan iedereen aan, zet dan **leesbevestiging vereist** aan. Je krijgt
dan per gebruiker een bevestiging met datum, en dat is het bewijsmateriaal waar
een auditor het meeste aan heeft: niet "wij hebben het rondgestuurd" maar "deze
mensen hebben het gezien, op deze dag". Bij type `beleid` staat die plicht
standaard aan, bij een procedure zet je hem zelf om.

De inhoud is een tabel met vijf kolommen. Onderstaande rijen zijn een
**voorbeeld en geen voorschrift** — schrap wat niet bij je organisatie past en
vul aan wat er mist:

| Wat | Wanneer | Met wie | Door wie | Hoe |
|---|---|---|---|---|
| Wijziging in het beveiligingsbeleid | bij elke nieuwe versie | alle medewerkers | CISO | dit systeem, met leesbevestiging |
| Uitkomsten van de managementreview | na elke review | management, proceseigenaren | voorzitter van de review | reviewdossier + terugkoppeling in het lijnoverleg |
| Meldingsplichtig incident | binnen de wettelijke termijn | toezichthouder | CISO | het officiële meldkanaal |
| Beveiligingsincident met impact | zodra bekend | getroffen afdeling, directie | incidentbehandelaar | e-mail, telefonisch bij spoed |
| Bewustzijnscampagne | per kwartaal | de betreffende doelgroep | CISO | trainingsmodule in dit systeem |
| Beveiligingseisen aan een leverancier | bij contract en bij wijziging | de leverancier | contracteigenaar | contractclausule |

De kolom "met wie" hoeft niet uit de lucht te komen vallen: intern heb je de
**doelgroepen** al staan, en extern de **belanghebbenden** uit je scope met hun
eisen. Verwijs daarnaar in plaats van de lijst over te schrijven, dan loopt hij
niet uit de pas.

## Het ritme: terugkerende overleggen als taaksjabloon

Heb je vaste overleggen — een maandelijks beveiligingsoverleg, een
kwartaalrisicobeoordeling — zet ze dan onder **Taaksjablonen** neer in plaats van
in een tekst. Een sjabloon draagt een naam, een omschrijving, een eigenaar en een
herhaling: eenmalig, maandelijks, per kwartaal, jaarlijks, of een eigen interval
in dagen. Het systeem maakt er vanzelf een taak van met een deadline, en die
verschijnt bij de eigenaar in het takenoverzicht.

Dat is het verschil tussen een afspraak op papier en een afspraak die zich meldt.
Slaat een kwartaal over, dan staat er een verlopen taak, en verlopen taken zijn
zichtbaar — een zin in een procedure die niemand naleeft, is dat niet.

**De managementreview hoort hier niet bij.** Die heeft een eigen dossier met
deelnemers, de negen verplichte agendapunten uit §9.3 en de besluiten die eruit
volgen. Zet hem niet daarnaast nog eens als taaksjabloon neer; dan heb je twee
plaatsen waar hetzelfde overleg staat en gaat er één achterlopen.

## Waar de notulen blijven

Voor de managementreview: in het reviewdossier zelf. De samenvatting per
agendapunt en de vastgelegde besluiten *zijn* de notulen, en ze hangen aan de
sessie waar ze bij horen.

Voor de overige overleggen kun je het verslag als bewijsstuk uploaden in het
**bewijsregister**. Koppel het dan wel aan datgene waar het overleg over besliste
— een risico, een maatregel, een auditronde. Een bewijsstuk zonder koppeling
blijft staan als signaal in het overzicht van ongekoppelde bewijzen, en terecht:
een verslag dat nergens aan hangt, onderbouwt ook niets.

Wil je de notulen liever in je gewone documentbeheer houden, dan is dat prima.
Zet in het communicatieplan dan één regel waar ze staan. De norm vraagt niet dat
alles in één systeem zit; ze vraagt dat je het kunt vinden.

## Waar het bewijs vandaan komt

Het meeste van wat 7.4 aantoonbaar maakt, ontstaat al als bijproduct van gewoon
werken:

| Wat je wilt aantonen | Waar het staat |
|---|---|
| Wie de externe partijen zijn en wat ze verwachten | de belanghebbenden bij je scope, met hun eisen |
| Dat beleid de mensen bereikt heeft | de leesbevestigingen per beleidsversie, met datum |
| Dat bewustzijn periodiek terugkomt | trainingsmodules per doelgroep, met voltooiingen |
| Dat er intern gesignaleerd wordt | de notificatieregels: welke gebeurtenis naar welke rol gaat |
| Dat er extern gemeld is toen het moest | de meldverplichtingen bij het incident, met datum en termijn |
| Dat de directie meepraat | het reviewdossier met deelnemers en besluiten |
| Dat er niets stilletjes is aangepast | de audit trail |

Wil je weten of dit geheel standhoudt, plan er dan een interne audit op: **7.4
Communicatie** bestaat als auditobject, dus je kunt de clausule als
onderzoeksvraag in een auditronde opnemen en de bevindingen langs de gewone weg
opvolgen.

## Wat dit systeem niet doet

- **Geen agendabeheer of notuleneditor.** Je plant hier geen vergaderingen en
  schrijft hier geen verslagen. Het systeem bewaakt het ritme en bewaart de
  uitkomst; het overleg zelf voer je in de agenda en de vergaderzaal die je al
  gebruikt.
- **Geen afdwinging dat een verslag bestaat.** Een taak uit een sjabloon kun je
  afvinken zonder bewijsstuk. Dat is bewust: voor een overleg dat "niets te
  melden" opleverde is een verplicht document een papieren ritueel, en de norm
  vraagt het niet.
- **Geen verzendmodule.** Behalve de notificaties die het systeem zelf stuurt,
  gaat communicatie langs je eigen kanalen. Wat hier staat is het besluit wie wat
  wanneer deelt — niet het bericht.
