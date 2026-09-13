# Oefening: zet een auditcyclus op

Het artikel [Een interne audit opzetten (§9.2)](interne-audit-opzetten) geeft de
volgorde: SoA af, cyclus opzetten, auditor toewijzen, uitvoeren en afronden. Die
volgorde is kort op te schrijven en verraderlijk om te lopen, want de fouten
kosten pas veel later iets. Een nulmeting die als gewone ronde is geregistreerd,
kleurt de matrix groen op het moment dat er nog niets gedekt ís. Een cyclus die op
1 januari begint in plaats van op de certificaatdatum, loopt drie jaar lang net
uit de pas met de audits van de certificerende instelling. En een object dat in de
scope stond maar waar niemand aan toekwam, ziet er in de planning uit als
afgedekt.

In deze oefening zet je die cyclus zelf op voor een verzonnen organisatie die nog
nooit intern heeft geaudit. Afwijken van de aanbevolen keuze mag: de oefening
spoelt dan door naar het moment waarop het gevolg zichtbaar wordt — meestal het
scherm dat een externe auditor voorgelegd krijgt — en zet je daarna terug naar het
punt waar je afweek. Veertien beslispunten, en anderhalf jaar aan verhaal. Reken
op ruim een uur; bij beslispunt 9 zit een natuurlijk rustpunt.

De oefening loopt via de schermen en niet via `isms:bereid-auditcyclus-voor`. Dat
commando zet dezelfde cyclus in één opdracht neer, maar het vraagt toegang tot de
server, en het neemt precies de beslissingen over die je hier wilt oefenen.

## Wat je nodig hebt

De oefening is een **opdracht voor een AI-assistent**, en niet een scherm in
EzISMS. Dat is een bewuste keuze: EzISMS stuurt zelf niets naar een AI-dienst, en
dat willen we zo houden. Je gebruikt dus de assistent die je organisatie al
toestaat. Download de opdracht onderaan dit artikel en plak de inhoud als eerste
bericht in een nieuw gesprek; de assistent begint dan zelf met de casus en de
eerste vraag.

Je hoeft in EzISMS niets te doen: de oefening speelt zich helemaal in het gesprek
af. Wil je de schermen erbij openen, doe dat dan in een testinstallatie. Een
afgeronde ronde is niet te heropenen en een gesloten bevinding niet — precies de
strengheid die oefenen in productie onverstandig maakt.

## Wat je oefent

Veertien beslispunten, van de vraag waarom er überhaupt intern geaudit wordt tot
het gesprek met de certificerende instelling:

1. wat een interne audit toetst — en wat niet;
2. waarom de SoA eerst af moet;
3. het voorbereidingsprogramma naast de driejarige cyclus;
4. de nulmeting als ronde: type, scope en waarom hij niet meetelt voor de dekking;
5. wie hem uitvoert, en wat de applicatie aan onafhankelijkheid wél en niet
   afdwingt;
6. de behandeling per object: bron, "geen opmerkingen", en waarom je geen
   bevinding per control maakt;
7. afronden met gaten erin;
8. de opvolging van een non-conformiteit;
9. het certificaat: de voorbereiding afsluiten, de cyclus starten op de
   certificaatdatum;
10. de frequentie per object, risicogebaseerd;
11. de spreiding over de programmajaren;
12. een maatregel die midden in de cyclus van toepassing wordt;
13. scope is geen dekking — en waarom je een gat niet wegplant;
14. wat je de auditor laat zien als hij vraagt hoe je weet dat alles is bekeken.

Tijdens het gesprek kun je `hint`, `terug`, `doorzetten`, `stand`, `ga naar 10`,
`cheat` en `stop` typen. Met `cheat` geeft de assistent het antwoord dat de
oefening verwacht en gaat hij door naar het volgende beslispunt — handig om er snel
doorheen te stappen, maar dan oefen je niets.

## Vier dingen om te weten

**Gebruik de verzonnen casus.** De opdracht gaat over een verzonnen groothandel.
Plak er geen gegevens van je eigen organisatie in: het gesprek loopt bij een
partij buiten EzISMS, onder de voorwaarden die jouw organisatie met die partij
heeft. Over EzISMS zelf staat er niets in wat niet ook in deze kennisbank staat.

**De assistent kan gedrag verzinnen.** Daarom staat in de opdracht een paragraaf
met feiten over EzISMS, en de instructie om daarbuiten "dat weet ik niet" te
zeggen. Toch blijft het een taalmodel: klopt iets niet met wat je in het scherm
ziet, dan heeft het scherm gelijk. Meld zo'n verschil, dan wordt de opdracht
bijgewerkt.

**Dit is geen training met bewijswaarde.** Er is geen score en er wordt niets
geregistreerd. Wil je bekwaamheid aantoonbaar maken (§7.2), dan is dat een
trainingsmodule met een toets, en geen oefening in een chatvenster.

**Geen AI-assistent beschikbaar?** Lees dan
[Een interne audit opzetten (§9.2)](interne-audit-opzetten) en zet de cyclus na in
een testinstallatie. De valkuilen staan er allemaal in; wat je mist is het
doorspoelen en het terugzetten.

Dezelfde opzet bestaat voor de meetkant: [Oefening: zet zelf een KPI
op](kpi-oefening).

## De opdracht

**[Download de opdracht](/kennisbank/audit-oefening/opdracht)** — een tekstbestand,
`audit-oefening-opdracht.md`.

Open het bestand, kopieer de hele inhoud en plak die als eerste bericht in een
nieuw gesprek met je assistent. Een assistent die bestanden als bijlage aanneemt,
kan het ook zo krijgen; zet er dan in je bericht bij dat hij de opdracht in de
bijlage moet uitvoeren.

De opdracht wordt bijgewerkt als EzISMS verandert. Haal hem daarom opnieuw op
wanneer je de oefening later nog eens doet, in plaats van een oude kopie te
gebruiken.
