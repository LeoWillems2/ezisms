# Oefening: zet een auditcyclus op

Het artikel [Een interne audit opzetten (§9.2)](interne-audit-opzetten) beschrijft
de volgorde: de SoA afmaken, de cyclus opzetten, een auditor toewijzen, uitvoeren
en afronden. Die volgorde is kort op te schrijven, maar lastig uit te voeren,
omdat de gevolgen van fouten pas veel later zichtbaar worden. Een nulmeting die
als gewone ronde is geregistreerd, kleurt de matrix groen op een moment dat er nog
niets is gedekt. Een cyclus die op 1 januari begint in plaats van op de
certificaatdatum, loopt drie jaar lang net niet gelijk met de audits van de
certificerende instelling. Een object dat in de scope stond maar waar niemand aan
toekwam, ziet er in de planning uit als afgedekt.

In deze oefening zet de deelnemer die cyclus zelf op, voor een verzonnen
organisatie die nog nooit intern heeft geaudit. Afwijken van de aanbevolen keuze
is toegestaan. De oefening spoelt dan door naar het moment waarop het gevolg
zichtbaar wordt, meestal het scherm dat een externe auditor te zien krijgt. Daarna
zet de oefening de deelnemer terug naar het punt van afwijken. Er zijn 14
beslispunten, en het verhaal beslaat anderhalf jaar. De oefening kost ruim een
uur. Beslispunt 9 is een natuurlijk rustpunt.

De oefening loopt via de schermen en niet via `isms:bereid-auditcyclus-voor`. Dat
commando zet dezelfde cyclus in één opdracht neer, maar het vereist toegang tot de
server en het neemt precies de beslissingen over die deze oefening traint.

## Wat nodig is

De oefening is een **opdracht voor een AI-assistent** en geen scherm in EzISMS.
Dat is een bewuste keuze: EzISMS stuurt zelf niets naar een AI-dienst, en dat
blijft zo. De deelnemer gebruikt daarom de assistent die de eigen organisatie al
toestaat. De opdracht staat onderaan dit artikel als download. De inhoud hoort als
eerste bericht in een nieuw gesprek te worden geplakt. De assistent begint dan zelf
met de casus en de eerste vraag.

In EzISMS hoeft de deelnemer niets te doen, omdat de oefening zich volledig in het
gesprek afspeelt. Wie de schermen erbij wil openen, hoort dat in een
testinstallatie te doen. Een afgeronde ronde en een gesloten bevinding zijn niet te
heropenen. Die strengheid maakt oefenen in productie onverstandig.

## Wat de oefening behandelt

Er zijn 14 beslispunten, van de vraag waarom er intern geaudit wordt tot het
gesprek met de certificerende instelling:

1. wat een interne audit toetst, en wat niet;
2. waarom de SoA eerst af moet zijn;
3. het voorbereidingsprogramma naast de driejarige cyclus;
4. de nulmeting als ronde: het type, de scope en de reden dat de nulmeting niet
   meetelt voor de dekking;
5. wie de nulmeting uitvoert, en wat de applicatie wel en niet afdwingt aan
   onafhankelijkheid;
6. de behandeling per object: de bron, "geen opmerkingen", en de reden dat er geen
   bevinding per control wordt gemaakt;
7. afronden terwijl er gaten zijn;
8. de opvolging van een non-conformiteit;
9. het certificaat: de voorbereiding afsluiten en de cyclus starten op de
   certificaatdatum;
10. de frequentie per object, op basis van risico;
11. de spreiding over de programmajaren;
12. een maatregel die midden in de cyclus van toepassing wordt;
13. het verschil tussen scope en dekking, en de reden dat een gat niet weggepland
    hoort te worden;
14. wat de organisatie de auditor laat zien als die vraagt waaruit blijkt dat alles
    is bekeken.

Tijdens het gesprek zijn de commando's `hint`, `terug`, `doorzetten`, `stand`,
`ga naar 10`, `cheat` en `stop` beschikbaar. Met `cheat` geeft de assistent het
antwoord dat de oefening verwacht en gaat de assistent door naar het volgende
beslispunt. Dat is handig om snel door de oefening te stappen, maar de deelnemer
oefent dan niets.

## Vier dingen om te weten

**Gebruik de verzonnen casus.** De opdracht gaat over een verzonnen groothandel.
Gegevens van de eigen organisatie horen niet in het gesprek. Het gesprek loopt bij
een partij buiten EzISMS, onder de voorwaarden die de organisatie met die partij
heeft afgesproken. Over EzISMS zelf bevat de opdracht niets wat niet ook in deze
kennisbank staat.

**De assistent kan gedrag verzinnen.** Daarom bevat de opdracht een paragraaf met
feiten over EzISMS, en de instructie om buiten die feiten aan te geven dat het
antwoord onbekend is. Toch blijft de assistent een taalmodel. Als iets niet
overeenkomt met wat het scherm toont, dan heeft het scherm gelijk. Een gemeld
verschil leidt tot een bijgewerkte opdracht.

**Dit is geen training met bewijswaarde.** Er is geen score en er wordt niets
geregistreerd. Bekwaamheid aantoonbaar maken (§7.2) vraagt een trainingsmodule met
een toets, en geen oefening in een chatvenster.

**Geen AI-assistent beschikbaar?** Het alternatief is het artikel
[Een interne audit opzetten (§9.2)](interne-audit-opzetten) lezen en de cyclus
nabouwen in een testinstallatie. Alle valkuilen staan in dat artikel. Wat
ontbreekt, is het doorspoelen en het terugzetten.

Dezelfde opzet bestaat voor de meetkant: [Oefening: zet zelf een KPI
op](kpi-oefening).

## De opdracht

**[Download de opdracht](/kennisbank/audit-oefening/opdracht)**: een tekstbestand
met de naam `audit-oefening-opdracht.md`.

De volledige inhoud van het bestand hoort als eerste bericht in een nieuw gesprek
met de assistent te worden geplakt. Een assistent die bijlagen accepteert, kan het
bestand ook als bijlage krijgen. Het bericht vermeldt dan dat de assistent de
opdracht in de bijlage moet uitvoeren.

De opdracht wordt bijgewerkt als EzISMS verandert. Bij een latere herhaling van de
oefening hoort daarom een nieuw gedownloade opdracht te worden gebruikt, en geen
oude kopie.
