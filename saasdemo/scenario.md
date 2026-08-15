# Demo-ISMS FruitBV — scenario

Stap 1 van de aanpak uit `saasdemo/v1.md`: het verhaal, vóór er code komt.
Dit document is de bron voor de fixtures (`saasdemo/data/*.json`) en voor de
simulatiemotor. Wat hier niet in staat, komt er ook niet in.

Antwoorden uit `v1.md` die hier verwerkt zijn: relatieve tijdlijn (1c),
simulatiemotor (2), directie krijgt CISO-rol (3b), beide `Demo*Seeder`s weg (4),
demo draait in de OTA-database en begint met een volledige wipe (4/5),
diep/dun-verdeling (6), tegenslag inbouwen (7), certificering met open minor (8),
ZZP'ers alleen als gebruiker (9a), gegenereerde wachtwoorden (10),
`.md`-bewijsstukken (11).

---

## 0. De opdracht

Hier stond een verwijzing naar `saasdemo/p1`, het bestand met de oorspronkelijke
opdracht. Dat bestand is verwijderd; wat eruit nog geldt, staat hieronder. Alles
wat p1 verder beschreef (organisatie, scope, personen) is uitgewerkt in §2 en §3
en daar de bron.

**Doel.** Een gevuld demo-ISMS, zodat PDCA, KPI's en andere meetwaarden te
bouwen en te tonen zijn zonder te wachten tot er echte gebruiksdata is. Zonder
zulke data valt deze ISMS-software niet uit te leveren.

**Uitgangspunten die nog gelden:**

- Alles wat bij de demo hoort — scripts, fixtures, hulpdata — staat in
  `saasdemo/`.
- Het onderwerp is een zelfontwikkeld SaaS-handelsplatform voor groenten en
  fruit; OTA en productie zijn gescheiden.
- De scope beperkt zich tot dat platform in productie. Ontwikkeling valt
  buiten scope; alleen de beheerders met productietoegang zijn in scope, en dat
  zijn ZZP'ers. Het management bestaat uit twee directieleden.
- De benodigde documenten en bewijsstukken mogen leeg zijn, maar dragen wel een
  titel of onderwerpaanduiding.
- KPI's en risico's zijn voor de hand liggende scenario's, geen exotische.

**Vervallen uit de opdracht:** de absolute data (driejaarlijkse cyclus vanaf
1 maart 2026, eindstand 2 januari 2028). Die zijn vervangen door een relatieve
tijdlijn — zie §1.

---

## 1. Tijdmodel

De tijdlijn is **relatief**, niet absoluut (antwoord 1c). Alles wordt uitgedrukt
in maandoffsets:

| Symbool | Betekenis |
|---|---|
| **M0** | de eerste dag van de maand, 22 maanden vóór de dag waarop de demo gevuld wordt |
| **M22** | "nu" — de dag van vullen; dit is de stand die de demo toont |

Draai je de demo op 28-07-2026, dan is M0 = 01-10-2024 en M22 = 01-08-2026.
De data uit de opdracht (1 maart 2026 → 2 januari 2028) vervallen daarmee als
*absolute* data; wat overblijft is hun **afstand**: 22 maanden ISMS-historie
eindigend op vandaag. Het voordeel is dat de demo op elke dag klopt: taken zijn
echt te laat, herbeoordelingen zijn echt verlopen, en de Check-fase is gevuld.

**Jaargrenzen.** De simulatie draait `isms:leg-restrisico-vast` op elke
31 december die de tijdlijn passeert. Bij 22 maanden zijn dat er twee, dus de
restrisico-trend krijgt twee peiljaren. Welke kalenderjaren dat zijn hangt af
van de datum van vullen — dat is inherent aan een relatieve tijdlijn.

**Twee soorten "jaar".** De kopjes "Jaar 1" en "Jaar 2" in de tijdlijn zijn
*scenariojaren* (M0–M11 en M12–M22): puur een indeling van het verhaal. De
**programmajaren** van het auditprogramma zijn iets anders — die beginnen pas op
de certificaatdatum in M12. Zie §9, "Het auditprogramma".

---

## 2. De organisatie

**FruitBV** — Nederlandse groothandel in groenten en fruit, 20 medewerkers
(deeltijd, samen 16 FTE), kantoor in Barendrecht.

**FruitCloud** — het handelsplatform waarop telers, groothandel en retail
partijen groenten in- en verkopen. In eigen huis ontwikkeld. OTA en productie
zijn gescheiden omgevingen. Productie draait bij **WortelNet**, een
ISO 27001-gecertificeerde Nederlandse hostingprovider.

**Persoonsgegevens** in het platform: NAW-gegevens, e-mailadressen en
**bankrekeningnummers** van handelspartners. Dat laatste is de reden dat
integriteit hier zwaarder weegt dan bij een doorsnee SaaS: een gewijzigd
rekeningnummer is direct geld.

### Scope

**Binnen scope:** FruitCloud in productie, de gegevens die het platform
verwerkt, de hostingdienst van WortelNet, en de beheerders die toegang hebben
tot productie.

**Buiten scope:** de ontwikkelstraat en de OTA-omgeving, en de algemene
kantoorautomatisering. Beide als expliciete uitsluiting mét motivatie
vastgelegd — de scope-uitsluiting is een van de eerste dingen waar een auditor
naar kijkt.

**Raakvlakken** (`interfaces`, met risico-implicatie):

| Raakvlak | Risico-implicatie |
|---|---|
| Ontwikkelstraat → productie (release) | code van buiten de scope belandt binnen de scope; vraagt om changecontrole op de grens |
| WortelNet (hosting, beheer onderliggende infrastructuur) | beveiliging deels uitbesteed; aantoonbaarheid loopt via leveranciersbeheer |
| Bankkoppeling / PSP | uitwisseling van rekeninggegevens; integriteit en vertrouwelijkheid |
| Microsoft 365 (mail, documenten) | ISMS-documenten en communicatie buiten het platform |

### Organisatie-eenheden

| Type | Eenheden |
|---|---|
| afdeling | Directie · Handel · Platformbeheer · Administratie |
| locatie | Kantoor Barendrecht · Datacenter WortelNet (Amsterdam) |
| proces | Klantonboarding · Orderafhandeling · Facturatie & betalingen · Platformbeheer & release |

---

## 3. Personen en rollen

| Persoon | Functie | ISMS-rol | Opmerking |
|---|---|---|---|
| Ciske Willems (ciso@acme.example) | CISO | CISO | eigenaar van het ISMS, doet het meeste werk |
| Bobo Spruitje (bobo@acme.example) | Directeur | Management | zie hieronder |
| Baas Prei (baas@acme.example) | Directeur | Management | zie hieronder |
| Jantien Wortel (jantien@acme.example) | Beheerder productie (ZZP) | Medewerker | in scope wegens productietoegang |
| Kees Karot (kees@acme.example) | Beheerder productie (ZZP) | Medewerker | idem |
| Aurelius Aardappel (aurelius@acme.example) | Interne auditor | Auditor | voert de interne audits uit |
| Piet Peer (piet@acme.example) | Medewerker Handel | Medewerker | geen ISMS-taken; doelgroep training en leesbevestiging |
| Keesje Kers (keesje@acme.example) | Medewerker Administratie | Medewerker | idem |

**Het e-maildomein.** `acme.example`, en niet een bestaand domein. `.example` is
door RFC 2606 gereserveerd en zal nooit van iemand zijn: wat de demo ook aan
notificaties opwekt, het kan geen echte postbus bereiken. Eerder stond hier
`lewi.nl` — een domein dat wél bestaat, en dat op een demo-installatie waar de
mailer níet op `array` staat post naar de eigenaar ervan stuurt.

**De wachtwoorden.** Gegenereerd (antwoord 10) en **niet afgedrukt**. Ze gaan
naar `storage/app/private/demo-inloggegevens.txt` (0600); het commando toont
alleen dat pad. Reden: `deploy.sh` draait `isms:demo-vul` onbeheerd mee in een
uitrol, en alles wat het commando afdrukt belandt in het uitrollog dat bewaard
blijft in `shared/installatie/`. Verzonnen mensen of niet — werkende
inloggegevens horen niet in een logbestand dat rondgaat.

**De Management-rol.** Antwoord 3b in `v1.md` was een compromis: er bestond geen
directierol, dus zouden Bobo en Baas als CISO inloggen. Dat compromis is
ingehaald door `implementatie/01c-rol-management.md` — **gebouwd op 29-07-2026,
fase 1 én fase 2**. Er is een vierde rol **Management** met goedkeurrechten op
scope, beleid, risico's en de directiebeoordeling, en géén toegang tot
gebruikersbeheer.

Omdat ook fase 2 er is, moet de simulatiemotor vier handelingen dóór een
directeur laten uitvoeren in plaats van door de CISO:

| Handeling | Wie | Waar in de tijdlijn |
|---|---|---|
| scope-versie activeren | Bobo of Baas | M1, en bij elke herziening |
| beleidsversie publiceren | Bobo of Baas | M2–M5 en bij elke herziening |
| restrisico **boven de drempel** accepteren | Bobo of Baas | M3 e.v., per risico |
| directiebeoordeling als gehouden vastleggen | Bobo of Baas | M8 en M19 |
| risicocriteria vaststellen | Bobo of Baas | M20 |

Een restrisico ónder de acceptatiedrempel zet de CISO zelf op geaccepteerd; dat
valt binnen zijn mandaat. Boven de drempel blijft het risico op
`behandelplan_opgesteld` staan tot de directie tekent — een aardig detail voor
de demo, want een risico dat daar blijft hangen laat meteen zien waarom de
drempel bestaat.

**Wachtwoorden** worden bij het vullen gegenereerd en één keer op de console
getoond (antwoord 10). Ze staan niet in versiebeheer en niet in dit document.

**Buiten het ISMS:** de overige ~12 medewerkers hebben geen ISMS-account. Pier en
Keesje staan model voor die groep: zij hebben geen enkele beheertaak, maar maken
de bewustzijns- en leesbevestigingscijfers geloofwaardiger dan een populatie van
zes waarin iedereen ook nog CISO of auditor is. Doelgroepen worden daarmee:
*Alle gebruikers* (acht), *Beheerders* (Jantien, Kees), *Directie* (Bobo, Baas).

Van de acht accounts is er in de eindstand één **gedeactiveerd**: Keesje gaat in
M19 uit dienst, met de bijbehorende intrekking van toegang. Zonder dat staat de
gebruikersbeheerpagina op acht keer "actief" en laat de accountlevenscyclus uit
blok 1 zich niet zien.

---

## 4. Assets, systemen en classificatie

**Systemen** (alle extern gehost):

| Systeem | Leverancier | Beschikbaarheidseis |
|---|---|---|
| FruitCloud productie | WortelNet | hoog |
| FruitCloud database | WortelNet | hoog |
| Back-upomgeving | WortelNet | midden |
| Microsoft 365 | Microsoft | midden |
| Betaalkoppeling PeenPay | PeenPay | midden |

**Assets** (C/I/B op de schaal openbaar → intern → vertrouwelijk → geheim):

| Asset | Type | V | I | B | Scope |
|---|---|---|---|---|---|
| Klantgegevens handelspartners (NAW, e-mail) | informatie | vertrouwelijk | vertrouwelijk | intern | binnen |
| Bankrekeninggegevens handelspartners | informatie | geheim | geheim | intern | binnen |
| Order- en transactiegegevens | informatie | vertrouwelijk | geheim | vertrouwelijk | binnen |
| FruitCloud productieomgeving | systeem_of_dienst | vertrouwelijk | geheim | vertrouwelijk | binnen |
| Back-ups FruitCloud | informatie | vertrouwelijk | geheim | intern | binnen |
| Productiesecrets en sleutels | informatie | geheim | geheim | vertrouwelijk | binnen |
| Beheerlaptops ZZP'ers | hardware | vertrouwelijk | intern | intern | binnen |
| Broncode FruitCloud | informatie | vertrouwelijk | vertrouwelijk | intern | **buiten** |

Die laatste regel is expres opgenomen: een asset met `binnen_scope = false` laat
zien dat het register breder is dan de scope, en dat de grens een geregistreerde
keuze is.

---

## 5. Leveranciers

| Leverancier | Dienst | Risiconiveau | Bijzonderheid |
|---|---|---|---|
| WortelNet | hosting productie | hoog | ISO 27001-gecertificeerd; verwerkersovereenkomst; alle vier clausuletypen |
| Microsoft | M365 | midden | standaardvoorwaarden, verwerkersovereenkomst |
| PeenPay | betaalkoppeling | hoog | financiële gegevens; SLA en incidentmeldplicht |
| SnijBoon Support | helpdesktool | laag → **hoog** | de leverancier die zakt, zie tijdlijn M15 |

Beoordelingen vinden jaarlijks plaats (M4 en M15). De ZZP-beheerders zijn
**geen** leverancier in het register (antwoord 9a) — ze staan als gebruiker in
het personeelsdossier van blok 1.

---

## 6. Risico's

Veertien risico's, allemaal voor de hand liggend voor een handelsplatform.
Kans en impact op 1–5, score = kans × impact; drempel 15 (rood), waarschuwing 10
(amber).

| # | Risico | Start (K×I) | Eind (K×I) |
|---|---|---|---|
| 1 | Ransomware legt FruitCloud plat | 3×5 = 15 | 2×5 = 10 |
| 2 | Datalek klantgegevens via kwetsbaarheid in de webapplicatie | 3×5 = 15 | 2×4 = 8 |
| 3 | Misbruik of te ruime rechten van een beheeraccount | 3×4 = 12 | 2×4 = 8 |
| 4 | Uitval WortelNet (afhankelijkheid van één hostingpartij) | 2×5 = 10 | 2×5 = 10 |
| 5 | Back-up blijkt niet herstelbaar | 3×5 = 15 | 1×5 = 5 |
| 6 | Frauduleuze wijziging van een bankrekeningnummer | 3×5 = 15 | 2×5 = 10 |
| 7 | Phishing leidt tot accountovername bij een handelspartner | 4×3 = 12 | 3×3 = 9 |
| 8 | Kwetsbaarheden in third-party libraries (patchachterstand) | 3×4 = 12 | **4×4 = 16** → 2×4 = 8 |
| 9 | Ongeautoriseerde wijziging in productie zonder changecontrole | 3×4 = 12 | 2×4 = 8 |
| 10 | Uitval of vertrek van een van de twee beheerders | 3×4 = 12 | 3×3 = 9 |
| 11 | Onvoldoende logging: incident niet reconstrueerbaar | 4×3 = 12 | 2×3 = 6 |
| 12 | Te lange bewaartermijn klantgegevens (AVG) | 3×3 = 9 | 2×2 = 4 |
| 13 | DDoS tijdens het hoogseizoen | 2×4 = 8 | 2×3 = 6 |
| 14 | Productiedata in de OTA-omgeving | 3×4 = 12 | 1×4 = 4 |

Risico's **15 en 16** ontstaan pas in M17 (zie tijdlijn) — nieuw geïdentificeerde
risico's zijn bewijs dát er gekeken wordt. Hun cijfers stonden hier niet en zijn
bij het schrijven van de fixtures ingevuld: **risico 15 op 4×4 = 16 met
behandeloptie `accepteren`**. Dat is bewust, want `Risico::boventDrempel()`
toetst `score > 15` op de score van het risico zélf. Met uitsluitend de scores
uit de tabel hierboven komt geen enkel risico boven de drempel, en dan wordt de
goedkeuractie "restrisico accepteren" van de rol Management nooit gebruikt —
terwijl §3 die wel als een van de vier directiehandelingen noemt. Zie
`saasdemo/data/risicos.json`.

Risico 4 blijft op 10 staan: niet elk risico daalt, en een geaccepteerd risico
met een expliciete acceptatie door de directie is precies wat §6.1.3 wil zien.
Risico 8 gaat **omhoog** voordat het omlaag gaat (M14).

---

## 7. Statement of Applicability

Alle 93 Annex A-maatregelen worden beoordeeld. Streefverdeling in de eindstand:

| | Aantal |
|---|---|
| Van toepassing | 88 |
| Niet van toepassing (met motivatie) | 5 |
| Van de toepasselijke: geïmplementeerd | ~80 |
| Van de toepasselijke: in uitvoering | ~8 |

De vijf niet-toepasselijke, allemaal met dezelfde grondslag (geen eigen
serverruimte, geen uitbestede ontwikkeling):

- **A.7.11** Nutsvoorzieningen
- **A.7.12** Beveiliging van bekabeling
- **A.7.13** Onderhoud van apparatuur
- **A.7.14** Veilig verwijderen of hergebruiken van apparatuur
- **A.8.30** Uitbestede ontwikkeling

De SoA wordt **niet in één keer** gevuld: in M3 staat ongeveer de helft, in M4
de rest. Dat maakt de Plan-KPI in de eerste maanden zichtbaar oplopend in plaats
van meteen 100%.

---

## 8. Beleid en procedures

Dertien documenten. Alle inhoud is leeg op titel en datum na (uitgangspunt uit
de opdracht, §0); wat telt is de status, de versiehistorie en de koppeling naar
SoA-regels.

| Document | Type | Actief vanaf | Herziening |
|---|---|---|---|
| Informatiebeveiligingsbeleid | beleid | M1 | v2 in M12 |
| Toegangsbeleid | beleid | M2 | — |
| Beleid aanvaardbaar gebruik | beleid | M2 | — |
| Cryptografiebeleid | beleid | M4 | — |
| Leveranciersbeleid | beleid | M4 | — |
| Bewaartermijnenbeleid | beleid | M5 | — |
| Procedure incidentbeheer | procedure | M2 | v2 in M15 |
| Procedure back-up en herstel | procedure | M3 | v2 in M8 |
| Change- en releaseprocedure | procedure | M3 | — |
| Procedure kwetsbaarheden- en patchbeheer | procedure | M4 | v2 in M14 |
| Datalekprocedure (AVG) | procedure | M5 | — |
| Procedure interne audit | procedure | M6 | — |
| Procedure toegangsrechten-review | procedure | M7 | — |

Leesbevestigingen worden gevraagd bij v1 en bij elke nieuwe actieve versie, per
doelgroep. Ook hier bewust niet 100%: een enkele bevestiging blijft openstaan.

---

## 9. De tijdlijn

### Jaar 1 — opbouw (M0–M11)

| Maand | Wat er gebeurt |
|---|---|
| **M0** | Directiebesluit ISMS; Ciske benoemd tot CISO; accounts aangemaakt; context vastgelegd (6 issues, 8 belanghebbenden met eisen); scope-verklaring in concept |
| **M1** | Scope-verklaring v1 **actief**, goedgekeurd door de directie; informatiebeveiligingsbeleid v1; assetregister ingericht en geclassificeerd |
| **M2** | Risicoregister ronde 1: risico's 1–10 geïdentificeerd en beoordeeld; leveranciers opgevoerd met clausules; toegangs- en gebruiksbeleid actief |
| **M3** | SoA eerste slag: ~45 van 93 regels beoordeeld; risico's 11–14 erbij; back-up- en changeprocedure actief |
| **M4** | SoA afgerond (88 van toepassing); behandelplannen bij alle risico's; leveranciersbeoordeling ronde 1; cryptografie- en leveranciersbeleid actief. **Nulmeting** (`intern_nulmeting`): één ronde over álle auditobjecten, uitgevoerd door Aurelius. Uitkomst: 1 minor NC, 9 observaties, 6 verbeterkansen. Telt niet mee voor dekking |
| **M5** | Implementatie loopt op naar ~35 geïmplementeerde regels. **Incident 1**: phishingmail bij Handel, ernst midden, opgelost binnen twee dagen |
| **M6** | Bewustzijnstraining ronde 1 met leesbevestigingen. **Tegenslag 1**: de kwartaaltaak "Controle toegangsrechten" wordt 23 dagen te laat afgerond |
| **M7** | **Interne audit voorbereidingsfase**: volwaardige interne audit over de hele norm, gericht op de gaten uit de nulmeting. Bevindingen: 1 minor NC (geen bewijs van een uitgevoerde herstelttest), 2 observaties, 1 verbeterkans. De minor wordt een afwijking met grondoorzaak en corrigerende maatregel. Telt evenmin mee voor dekking — de cyclus loopt nog niet |
| **M8** | Herstelttest uitgevoerd en als bewijsstuk vastgelegd; risico 5 daalt naar 1×5. **Directiebeoordeling 1**: alle negen §9.3-inputs, 3 besluiten, 2 verbeteracties. Effectiviteitstoets op de minor: effectief → afwijking gesloten |
| **M9** | **Certificeringsaudit fase 1** (documentbeoordeling): 3 observaties, geen NC's |
| **M10** | Gaten dichten: implementatie naar ~70 van 88; procedure toegangsrechten-review actief |
| **M11** | **Certificeringsaudit fase 2**: 2 minor NC's — (a) toegangsrechten-review niet aantoonbaar elk kwartaal uitgevoerd (A.5.18), (b) monitoring en logging onvoldoende om een incident te reconstrueren (A.8.16). Verder 3 observaties. Certificaat toegekend onder voorbehoud van een corrigerend actieplan |

### Jaar 2 — bijsturen (M12–M22)

| Maand | Wat er gebeurt |
|---|---|
| **M12** | Certificaat ontvangen (bewijsstuk). Het **voorbereidingsprogramma wordt afgesloten** en de **driejarige certificeringscyclus start op de certificaatdatum**; programmajaar 1 loopt M12–M23. Informatiebeveiligingsbeleid v2 actief |
| **M13** | Corrigerende maatregelen op beide minors afgerond; logging uitgebreid; risico 11 daalt |
| **M14** | **Tegenslag 2 — incident 2**: een kwetsbaarheid in een third-party library wordt actief misbruikt op de OTA-omgeving. Geen productie-impact, wel een datalekanalyse (er stond productiedata in OTA — risico 14). Ernst hoog. Wordt een afwijking met bron `incident`. Risico 8 gaat **omhoog** naar 4×4 = 16, boven de acceptatiedrempel, dus mitigeren is verplicht. Patchbeheerprocedure v2 |
| **M15** | Leveranciersbeoordeling ronde 2. **Tegenslag 3**: SnijBoon Support zakt van laag naar hoog — geen incidentmeldplicht in het contract, geen aantoonbare beveiligingsmaatregelen. Actie: clausule toevoegen of dienst uitfaseren. Procedure incidentbeheer v2 |
| **M16** | **Tegenslag 4**: de kwartaaltaak "Herbeoordeling risicoregister" wordt niet opgepakt en **verloopt**. De Check-KPI voor risicoherbeoordeling zakt zichtbaar |
| **M17** | Inhaalslag: alle risico's herbeoordeeld, risico 8 terug naar 2×4 na patchronde; **risico 15** (afhankelijkheid van één betaaldienstverlener) en **risico 16** (AI-plugin in de klantportal verwerkt ordergegevens) nieuw geïdentificeerd |
| **M18** | **Interne audit programmajaar 1** (H4 Context, H5 Leiderschap, H6 Planning + bijbehorende maatregelen — de eerste schijf van de driejarige dekking). Bevindingen: 1 minor NC (leveranciersbeoordelingen niet volgens de eigen frequentie), 3 observaties, 2 verbeterkansen. Dit is de eerste ronde die de dekkingsmatrix kleurt |
| **M19** | **Directiebeoordeling 2**: negen inputs, besluit tot investering in monitoring, 3 verbeteracties waarvan er één blijft lopen. Keesje Kers uit dienst: account gedeactiveerd, toegang ingetrokken. Ciske stelt **versie 2 van de risicocriteria** op en dient hem in: acceptatiedrempel van 15 naar 12, en een kwantitatieve band per impactstap |
| **M20** | Bobo **stelt de risicocriteria vast**; de risico's die daardoor boven de acceptatiedrempel uitkomen krijgen een herbeoordelingstaak bij hun eigenaar. Jaarlijkse SoA-herziening: implementatie naar ~80 van 88; twee regels van "in uitvoering" naar "geïmplementeerd" |
| **M21** | **Eerste opvolgingsaudit** (`extern_surveillance`): 1 minor NC — het continuïteitsplan is nooit getest (A.5.30) — plus 2 observaties |
| **M22 = nu** | De open minor uit M21 staat op **`non_conformiteit_gestart`**; de corrigerende maatregel is `in_uitvoering` met een deadline over zes weken. Programmajaar 1 loopt nog (t/m M23); jaar 2 en 3 staan gepland met rondes op `gepland`. Restrisico-snapshots vastgelegd voor beide gepasseerde jaargrenzen |

### Het auditprogramma

Er zijn **twee programma's**, zoals uitgewerkt in
`implementatie/11c-auditcyclus-en-nulmeting.md`.

**Het voorbereidingsprogramma** (M0–M12, `aard = voorbereiding`) draagt de
nulmeting van M4 en de interne audit van M7. Beide rondes tellen niet mee voor
dekking: er is nog geen cyclus om te dekken. Het programma wordt in M12
afgesloten — die statuswijziging staat in de audit trail en is de zichtbare
overgang naar de gecertificeerde situatie.

**De driejarige certificeringscyclus** start op de certificaatdatum in M12 en
loopt tot M47. De dekking van H4–H10 en Bijlage A is over drie programmajaren
verdeeld, opgezet met het bestaande `isms:bereid-auditcyclus-voor` (dat commando
blijft, antwoord 4). Clausule 9.2 zit in elk jaar — de showcase voor
risicogebaseerde frequentie.

Op de peildatum M22 is er dus **één afgeronde dekkende ronde** (M18, jaar 1) en
loopt programmajaar 1 nog tot M23. De dekkingsmatrix toont de eerste schijf
groen, de rest gepland. Dat is een magerder plaatje dan twee afgeronde jaren,
maar het is wat een organisatie 22 maanden na de kickoff werkelijk kan tonen —
en de nulmeting die eronder hangt laat zien waaróm de matrix er zo uitziet.

De programmajaren beginnen op de certificaatdatum, niet op 1 januari. Dat is
precies het geval waar de kalenderjaar-verankering uit 11b op stukliep, dus deze
demo is meteen de proef op de som voor plan 11c.

De externe certificeringsaudits (M9, M11, M21) lopen daarnaast, als
auditrondes van het type `extern_certificering` en `extern_surveillance`. Het
externe rapport blijft de bron van waarheid; in het ISMS staan de bevindingen
overgenomen, zoals het kennisartikel over de externe certificeringsaudit
voorschrijft.

---

## 10. Wat de KPI's zouden moeten doen

Dit zijn **verwachtingen**, geen ingevoerde waarden: de simulatiemotor draait
`isms:meet-kpis` op elke maandgrens en de curve rolt eruit. Klopt hij niet met
onderstaande, dan is dat een signaal dat het scenario of de motor niet deugt.

| KPI | Verwacht verloop |
|---|---|
| SoA-regels beoordeeld (plan) | 0% → ~48% (M3) → 100% (M4), daarna vlak |
| Toepasselijke regels met actief beleid (plan) | loopt op tot ~65% in M7, daarna langzaam naar ~75% |
| Risico's met eigenaar én behandelplan (plan) | trapsgewijs naar 100% in M4; **dipje in M17** wanneer risico 15 en 16 erbij komen en nog geen behandelplan hebben |
| Toepasselijke regels geïmplementeerd (do) | ~40% (M5) → ~79% (M10) → ~91% (M20) |
| SoA binnen termijn herbeoordeeld (check) | 100% tot M15, dan **zakkend** omdat de jaartermijn verstrijkt, herstel bij de herziening in M20 |
| Risico's binnen termijn herbeoordeeld (check) | hoog tot M15, **duidelijke dip in M16** (verlopen kwartaaltaak), herstel in M17 |
| Beheerde taken op tijd afgerond (check) | ~100% tot M6, **knik** bij tegenslag 1, tweede knik bij M16, eindstand ~85% |
| Gemiddelde overschrijding in dagen (check) | 0 → piek rond M6 en M16, eindstand een paar dagen |

> **Bijstelling na het vullen (30-07-2026), twee regels hierboven.** De knikken
> bij tegenslag 1 (M6) en M16 komen er níet, en dat is geen fout in de motor.
> `reviewtaken_op_tijd` en `reviewtaken_gem_overschrijding` tellen alleen taken
> met een `soort` — taken die een bronblok aan een entiteit koppelt
> (`MeetKpis`: "door een bronblok beheerde taken"). Sjabloontaken hebben dat veld
> niet, en tegenslag 1 en 4 zijn juist sjabloontaken: de te late toegangsreview
> en de verlopen kwartaaltaak. Ze staan dus wél in de takenlijst maar buiten deze
> twee KPI's.
>
> De KPI heet ook "Beheerde taken", dus de definitie lijkt juist en dit scenario
> ging uit van iets anders. De feitelijke curve loopt van 20% (M3) naar 80% (M22)
> en de overschrijding van 78 naar 18 dagen; de beweging komt van de
> risico-herbeoordelingen en de corrigerende maatregelen. **Nog te beslissen:**
> het scenario bijstellen naar wat de KPI meet, of de KPI-definitie uitbreiden
> naar sjabloontaken. Tot die keuze is dit de bekende afwijking en niet een
> bevinding over de motor.

**Bijgesteld 02-08-2026.** Hier stond dat de drie Act-KPI's leeg blijven omdat ze
in de applicatie waren uitgesteld. Ze zijn inmiddels gebouwd
(`implementatie/12g`), dus de demo meet ze nu ook. Het patroon dat hier al werd
beschreven — de score-daling van risico 8 in M17 volgt op een patchronde met
bewijs, en die van risico 5 in M8 op de hersteltest — is precies wat
`scoredaling_zonder_bewijs` meet. De tijdlijn koppelde dat bewijs alleen aan de
afwijking en niet aan het risico; dat is nu aangevuld, zodat de KPI in de demo
beweegt in plaats van vast te staan op 100%.

---

## 11. Aannames en openstaande punten

Alle punten hieronder zijn beslist; ze blijven staan omdat ze de fixtures en de
motor sturen. Terugdraaien kan zolang er nog geen fixtures liggen.

1. **De driejarige cyclus = het interne auditprogramma, verankerd op de
   certificaatdatum.** De opstartfase (nulmeting + interne audit) hangt onder een
   apart voorbereidingsprogramma dat bij certificering wordt afgesloten. Dit
   vraagt de wijzigingen uit `implementatie/11c-auditcyclus-en-nulmeting.md`:
   zonder fase 1 en 2 daarvan kan de demo niet gevuld worden zoals hier
   beschreven. **Volgorde: eerst 11c bouwen, dan de demo.**
2. **Certificaat na ~11 maanden** (fase 2 in M11) — vastgesteld. Snel, maar
   haalbaar voor 16 FTE met één systeem en een ervaren CISO, en het is de enige
   indeling waarbij de opvolgingsaudit nog binnen de 22 maanden valt en er dus
   een open externe minor op de peildatum staat.
3. **Drie taaksjablonen erbij in de referentieseeder** — vastgesteld: ze gaan in
   `TaaksjabloonSeeder` en gelden dus voor alle klanten, niet alleen voor de
   demo.

   | Sjabloon | Herhaling | Bron-blok |
   |---|---|---|
   | Herstelttest back-up | jaarlijks | `bewijsrepository-audit-trail` |
   | Leveranciersbeoordeling | jaarlijks | `leveranciers-derdenrisico` |
   | Patch- en kwetsbaarhedenronde | maandelijks | `beleid-maatregelbeheer` |

   Dit is een wijziging aan productie-referentiedata, geen demovulling: bestaande
   installaties krijgen er drie terugkerende taken bij zodra de seeder draait.
   De maandelijkse patchronde is de enige met die frequentie en levert in 22
   maanden ~22 taken — genoeg volume om de taken-KPI's betekenis te geven.
4. **Twee extra medewerkers** — vastgesteld: Piet Peer en Keesje Kers, zonder
   ISMS-taken, alleen als doelgroep voor training en leesbevestiging. Keesje gaat
   in M19 uit dienst, zodat de accountlevenscyclus uit blok 1 zichtbaar wordt.
5. **Bewijsstukken**: ongeveer 35 `.md`-bestanden (auditrapporten, certificaat,
   herstelttest, kwartaalreviews toegang, verwerkersovereenkomsten, notulen
   directiebeoordeling, trainingsdeelname). Titel en datum, verder leeg. Ze
   worden gegenereerd bij het vullen en gaan **niet** de repository in.
6. **De wipe.** `isms:demo-vul` begint met het volledig legen van de database en
   het opnieuw draaien van de referentieseeders. De enige beveiliging is een
   **omgevingsblokkade**: het commando weigert buiten `local` en `demo`. Geen
   bevestigingsvraag en geen `--force`, zodat herhaald vullen tijdens het
   ontwikkelen niet in de weg zit.

---

## 12. Wat hierna komt

0. ~~Plan 01c bouwen~~ — **gedaan op 29-07-2026**, fase 1 en 2. De rol Management
   bestaat; de vier goedkeuracties zitten erachter.
1. **Plan 11c bouwen** (`implementatie/11c-auditcyclus-en-nulmeting.md`), minimaal
   fase 1 en 2. De demo leunt erop: zonder de nulmeting-vlag en de verankering op
   een datum is de auditgeschiedenis hierboven niet in te voeren.
2. ~~De fixtures (`saasdemo/data/*.json`)~~ — **gedaan op 29-07-2026**. Negen
   bestanden: acht domeinbestanden plus `tijdlijn.json`. Kruisverwijzingen
   gecontroleerd; één inhoudelijke keuze die hier niet vastlag staat in §6.
3. ~~Het implementatiedocument voor de simulatiemotor~~ — **gedaan op
   29-07-2026**: `saasdemo/simulatiemotor.md`.
4. Bouwen.

Stap 1 en 2 kunnen parallel — de fixtures raken de auditwijziging alleen in
`tijdlijn.json`.
