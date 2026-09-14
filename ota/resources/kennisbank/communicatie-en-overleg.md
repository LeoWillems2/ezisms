Clausule 7.4 vraagt de organisatie te bepalen **wat** zij over
informatiebeveiliging communiceert, **wanneer**, **met wie** en **hoe**, zowel
intern als extern. Deze pagina beschrijft hoe dat in dit systeem wordt vastgelegd
zonder er een apart project van te maken. Daarvoor is geen nieuw scherm nodig. De
bouwstenen zijn al aanwezig, maar ze staan verspreid.

## Wat de norm vraagt, en wat niet

De clausule verplicht tot een besluit en niet tot een document. Er is geen
voorgeschreven "communicatieplan" en geen verplichte vorm. Een auditor vraagt
naar het besluit en bekijkt vervolgens de sporen: verstuurde berichten, notulen,
trainingsregistraties en bevestigingen van ontvangst. Die sporen legt dit systeem
al vast.

Dat heeft twee gevolgen. Er is geen zware inrichting nodig om aan 7.4 te voldoen.
De organisatie moet wel kunnen uitleggen wie wat met wie deelt, want "dat gaat
vanzelf" is geen antwoord op de vraag van de auditor.

> **Let op het onderscheid met overleggen.** Clausule 7.4 zegt niets over
> vergaderingen, overlegstructuren of notulen. Een overzicht van terugkerende
> overleggen is nuttig en hoort bij leiderschap (clausule 5.1) en bij de
> managementreview (9.3). Het overzicht hoort echter niet als verplichting onder
> 7.4 te worden gepresenteerd. Een organisatie die dat wel doet, documenteert meer
> dan de norm vraagt en zit daar de rest van de certificeringscyclus aan vast.

## Het overzicht: leg het plan vast als procedure

Het communicatieplan wordt vastgelegd als document onder **Beleid**, met type
`procedure`. Daarmee krijgt het plan wat een los bestand op een netwerkschijf niet
heeft: een versie, een eigenaar, een goedkeuringsstatus en een publicatiemoment,
allemaal vastgelegd in de audit trail.

Als het plan iedereen aangaat, hoort **leesbevestiging vereist** aan te staan. Het
systeem registreert dan per gebruiker een bevestiging met datum. Dat is het
bewijsmateriaal waar een auditor het meeste aan heeft. Het toont niet alleen dat
het plan is rondgestuurd, maar ook welke personen het op welke dag hebben gezien.
Bij type `beleid` staat die plicht standaard aan. Bij een procedure moet de
plicht handmatig worden aangezet.

De inhoud is een tabel met vijf kolommen. De onderstaande rijen zijn een
**voorbeeld en geen voorschrift**. Rijen die niet bij de organisatie passen,
horen te worden geschrapt, en ontbrekende rijen horen te worden aangevuld:

| Wat | Wanneer | Met wie | Door wie | Hoe |
|---|---|---|---|---|
| Wijziging in het beveiligingsbeleid | bij elke nieuwe versie | alle medewerkers | CISO | dit systeem, met leesbevestiging |
| Uitkomsten van de managementreview | na elke review | management, proceseigenaren | voorzitter van de review | reviewdossier + terugkoppeling in het lijnoverleg |
| Meldingsplichtig incident | binnen de wettelijke termijn | toezichthouder | CISO | het officiële meldkanaal |
| Beveiligingsincident met impact | zodra bekend | getroffen afdeling, directie | incidentbehandelaar | e-mail, telefonisch bij spoed |
| Bewustzijnscampagne | per kwartaal | de betreffende doelgroep | CISO | trainingsmodule in dit systeem |
| Beveiligingseisen aan een leverancier | bij contract en bij wijziging | de leverancier | contracteigenaar | contractclausule |

De kolom "met wie" hoeft niet opnieuw te worden bedacht. Intern zijn de
**doelgroepen** al vastgelegd, en extern de **belanghebbenden** uit de scope met
hun eisen. Het plan hoort naar die registers te verwijzen in plaats van de lijst
over te nemen. Zo blijven plan en registers met elkaar in overeenstemming.

## Het ritme: terugkerende overleggen als taaksjabloon

Vaste overleggen, zoals een maandelijks beveiligingsoverleg of een
kwartaalrisicobeoordeling, horen onder **Taaksjablonen** te staan en niet in een
tekst. Een sjabloon heeft een naam, een omschrijving, een eigenaar en een
herhaling: eenmalig, maandelijks, per kwartaal, jaarlijks, of een eigen interval
in dagen. Het systeem maakt daaruit automatisch een taak met een deadline, en die
taak verschijnt bij de eigenaar in het takenoverzicht.

Dat is het verschil tussen een afspraak op papier en een afspraak die zichzelf
meldt. Als een kwartaal wordt overgeslagen, staat er een verlopen taak, en
verlopen taken zijn zichtbaar. Een zin in een procedure die niemand naleeft, is
niet zichtbaar.

**De managementreview hoort hier niet bij.** De managementreview heeft een eigen
dossier met deelnemers, de negen verplichte agendapunten uit §9.3 en de besluiten
die eruit volgen. De review hoort daarom niet ook nog als taaksjabloon te worden
vastgelegd. Anders staat hetzelfde overleg op twee plaatsen en raakt één daarvan
achter.

## Waar de notulen blijven

Voor de managementreview staan de notulen in het reviewdossier zelf. De
samenvatting per agendapunt en de vastgelegde besluiten vormen de notulen, en ze
hangen aan de sessie waar ze bij horen.

Voor de overige overleggen kan het verslag als bewijsstuk worden geüpload in het
**bewijsregister**. Het verslag hoort dan wel te worden gekoppeld aan het onderwerp
waarover het overleg besliste, zoals een risico, een maatregel of een auditronde.
Een bewijsstuk zonder koppeling blijft als signaal staan in het overzicht van
ongekoppelde bewijzen. Dat is terecht, want een verslag dat nergens aan hangt,
onderbouwt ook niets.

Het is ook mogelijk om de notulen in het gewone documentbeheer van de organisatie
te bewaren. Het communicatieplan vermeldt dan in één regel waar ze staan. De norm
vraagt niet dat alles in één systeem staat. De norm vraagt dat het te vinden is.

## Waar het bewijs vandaan komt

Het meeste van wat 7.4 aantoonbaar maakt, ontstaat al als bijproduct van het
normale werk:

| Aan te tonen | Vindplaats |
|---|---|
| Wie de externe partijen zijn en wat ze verwachten | de belanghebbenden bij de scope, met hun eisen |
| Dat beleid de mensen bereikt heeft | de leesbevestigingen per beleidsversie, met datum |
| Dat bewustzijn periodiek terugkomt | trainingsmodules per doelgroep, met voltooiingen |
| Dat er intern gesignaleerd wordt | de notificatieregels: welke gebeurtenis naar welke rol gaat |
| Dat er extern gemeld is toen het moest | de meldverplichtingen bij het incident, met datum en termijn |
| Dat de directie meepraat | het reviewdossier met deelnemers en besluiten |
| Dat er niets stilletjes is aangepast | de audit trail |

Om te toetsen of dit geheel standhoudt, kan de organisatie er een interne audit op
plannen. **7.4 Communicatie** bestaat als auditobject. De clausule is daardoor als
onderzoeksvraag in een auditronde op te nemen, en de bevindingen worden via de
gewone route opgevolgd.

## Wat dit systeem niet doet

- **Geen agendabeheer of notuleneditor.** Het systeem plant geen vergaderingen en
  bevat geen editor voor verslagen. Het systeem bewaakt het ritme en bewaart de
  uitkomst. Het overleg zelf vindt plaats met de agenda en de vergaderruimte die
  de organisatie al gebruikt.
- **Geen afdwinging dat een verslag bestaat.** Een taak uit een sjabloon is af te
  vinken zonder bewijsstuk. Dat is bewust. Voor een overleg zonder relevante
  uitkomst is een verplicht document een papieren ritueel, en de norm vraagt
  dat niet.
- **Geen verzendmodule.** Met uitzondering van de notificaties die het systeem zelf
  verstuurt, verloopt communicatie via de eigen kanalen van de organisatie. Het
  systeem legt het besluit vast wie wat wanneer deelt, maar niet het bericht zelf.
