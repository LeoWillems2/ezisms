# Een "ja" in de SoA onderbouwen

Een "ja" in de SoA is verraderlijk. Veel mensen denken dat een opgenomen control
zichzelf verklaart, en schrijven dan "best practice" of "is geïmplementeerd". Dat
is precies het soort motivatie dat een auditor niet accepteert. Een sterke
"ja"-motivatie beantwoordt drie vragen: **waarom de control van toepassing is, hoe
die is ingevuld, en waaruit dat blijkt.**

## De kern: traceerbaarheid, geen proza

Het beste "ja"-antwoord hoeft niet bedacht te worden, omdat het volgt uit de
registers van de organisatie. De sterkste rechtvaardiging voor opname is bijna
altijd:

> "Behandelt risico R-xx (en/of: vereist door wet/contract Y)."

Die formulering maakt de keten **risico → maatregel → SoA → bewijs** zichtbaar, en
die keten is precies wat een auditor natrekt. Als een control niet aan een risico
of eis te koppelen is, is een "ja" eigenlijk verdacht. De control is dan opgenomen
"omdat het hoort", en dat is een zwakke onderbouwing.

## De vier bouwstenen van een goede "ja"

De motivatie volgt het beste een klein vast patroon en is geen vrij essay:

1. **Driver: waarom van toepassing.** De driver is één van de volgende: een
   geïdentificeerd risico dat de control behandelt, een wettelijke eis (de AVG, of
   sectorwetgeving die op de organisatie van toepassing is), een contractuele eis
   van een klant, een eis van een belanghebbende of een expliciete keuze van de
   directie. Dit is de eigenlijke "justification for inclusion" die de norm vraagt.
2. **Realisatie: hoe ingevuld.** De motivatie verwijst naar de procedure, het
   beleid of de technische maatregel die de control waarmaakt. De motivatie
   herhaalt niet de controltekst, maar beschrijft de invulling door de
   organisatie.
3. **Bewijs: waaruit het blijkt.** Het bewijsstuk of de koppeling laat zien dat de
   control werkt. De applicatie koppelt bewijsstukken al aan SoA-maatregelen, en de
   motivatie verwijst naar die koppeling.
4. **Status en eigenaar.** De motivatie noemt de status (geïmplementeerd, deels of
   gepland), de eigenaar en het moment van herziening.

## Welke gegevens nodig zijn

De benodigde gegevens zijn concreet en staan meestal al in de applicatie:

- **Risico-referentie(s).** Dit zijn de R-nummers uit de risicolijst (bijvoorbeeld
  R-7) van de risico's die deze control behandelt. Dit zijn de belangrijkste
  gegevens.
- **Bron van de verplichting.** Dit is de wet, de norm, het contract of de interne
  beleidskeuze, kort beschreven en met vindplaats.
- **Implementatie-aanwijzing.** Dit is het beleidsdocument of de procedure die de
  control realiseert, met de versie erbij.
- **Bewijskoppeling.** Dit is het gekoppelde bewijsstuk, zoals een rapport, een
  configuratie-export, een screenshot van een instelling of logbewijs.
- **Eigenaar, status en herzieningsdatum.**

## Onderscheid dat de moeite waard is

- **"Ja, en geïmplementeerd"** legt de nadruk op bewijs: de control werkt
  aantoonbaar.
- **"Ja, maar nog niet volledig"** legt de nadruk op de behandelplanning: wat, wie
  en wanneer. Een eerlijke "deels" met een datum is auditbestendiger dan een
  geflatteerde "ja".

## Valkuilen bij de "ja"

- **De controltekst overschrijven** is geen motivatie. De tekst zegt dan niets over
  de situatie van de organisatie.
- **Een generieke motivatie** ("industry standard", "best practice") is leeg
  zonder driver.
- **Een motivatie zonder bewijs** is een bewering en geen onderbouwing.
- **Een motivatie los van het risico** is de meest gemiste kans. Zonder koppeling
  aan een risico ontbreekt de rode draad.

## De motivatie afleiden uit bestaande koppelingen

De risicomodule en de bewijskoppelingen bestaan al. Daardoor is de
"ja"-motivatie grotendeels **af te leiden** in plaats van te typen. De driver
volgt uit de gekoppelde risico's, en de onderbouwing volgt uit de gekoppelde
bewijsstukken. De motivatie wordt dan een korte, consistente samenvatting van die
koppelingen in plaats van vrij tekstwerk. Dat kost minder werk en is per definitie
traceerbaar.

## Voorbeeld: één zin per bouwsteen

> "Van toepassing: behandelt R-7 (onbevoegde toegang tot klantdata) en volgt uit
> AVG art. 32. Ingevuld via toegangsbeleid v2.1 (rollen/gates). Bewijs:
> toegangsreview Q2-2026. Eigenaar: CISO, status geïmplementeerd, herziening
> jaarlijks."

Het R-nummer is de korte verwijzing die in de risicolijst en op het risicodetail
staat. Het nummer is afgeleid van het id. Het is dus stabiel, maar de reeks kan
gaten bevatten: R-7, R-8, R-12.

## Eén control, meerdere deelsystemen: de veel-op-veel-realiteit

In de praktijk vallen er vaak meerdere deelsystemen onder één control. Dat is geen
denkfout, maar de werkelijke structuur. De maatregelen uit de bijlage zijn
generiek en organisatiebreed geformuleerd:

- één control hoort bij meerdere risico's of drivers, meerdere assets en meerdere
  bewijzen,
- en één risico hoort bij meerdere controls.

Een model waarin een control één-op-één bij "een systeem" hoort, is te smal. De
relatie is veel-op-veel, in beide richtingen.

### Het "te veel werk"-bezwaar

Dat bezwaar klopt als de SoA-motivatie de plek wordt waar alles opnieuw wordt
uitgeschreven, met elk deelsysteem in proza in dat ene veld. Dan wordt de
hoeveelheid werk onbeheersbaar. Dat is echter een methodefout en geen reden om de
werkelijkheid terug te brengen tot één-op-één. De oplossing is **verwijzen in
plaats van dupliceren**:

- De SoA is een **verklaring op organisatieniveau**, met één regel per control. De
  motivatie daar is een **samenvatting** ("van toepassing wegens R-3, R-7, R-12 en
  AVG art. 32") en geen verhandeling per deelsysteem.
- Het **detail per deelsysteem** hoort in het risico- en behandelregister en in de
  assetkoppelingen, waar het al staat. De SoA is een *lens* over die gegevens en
  geen tweede kopie ervan.

Zo blijft de veel-op-veel-realiteit intact, terwijl het invulwerk klein blijft.
Het gevoel van "te veel werk" ontstaat bijna altijd doordat auteurs tekst in het
SoA-veld schrijven in plaats van ernaar te verwijzen.

### Het lastigste punt: de status per deelsysteem

Een control kan op systeem A geïmplementeerd zijn en op systeem B deels. De vraag
is dan welke status in de SoA hoort:

- Op SoA-niveau geldt **één status**. Pragmatisch is dat de *zwakste* status
  ("deels"), met een korte noot.
- De **verschillen per deelsysteem** worden vastgelegd in het behandelplan: wat,
  wie en wanneer per systeem.

Een auditor accepteert een geaggregeerde SoA-status, zolang het detail eronder
traceerbaar is. Een auditor accepteert geen control die overal "geïmplementeerd"
claimt terwijl één deelsysteem de control nog niet uitvoert.

## Het restrisico per control meetbaar maken

De logische volgende stap is de SoA meetbaar te maken in plaats van tekstueel. Dat
gebeurt door per control een risicogetal te tonen en dat getal per jaar te volgen.
Drie keuzes bepalen of dit een echt stuurmiddel wordt of een aantrekkelijke
grafiek zonder betekenis.

### 1. Netto, niet bruto

Het getal is het **restrisico (na behandeling)** en niet het inherente of bruto
risico.

- **Bruto** (kans × impact vóór maatregelen) beweegt nauwelijks, omdat het het
  dreigingslandschap weergeeft. Het laat geen voortgang zien.
- **Netto** is precies het getal dat hoort te dalen als de maatregelen werken. Het
  netto-restrisico laat de continue verbetering uit §10 zien.

Het getal is dus het
**max netto-restrisico** van de risico's die onder de control hangen. De kolom
toont dan hoeveel blootstelling er op het gebied van deze control nog bestaat,
ondanks de genomen maatregelen.

### 2. "Grootste" is verdedigbaar, maar verbergt de verdeling

Het maximum is de meest auditbestendige keuze, omdat een organisatie zo sterk is
als haar zwakst behandelde risico. Het maximum verbergt echter de verdeling. Een
control die één hoog en 20 middelgrote risico's behandelt, krijgt hetzelfde getal
als een control die alleen dat ene hoge risico dekt. Het scherm toont daarom **het
maximum naast een teller** ("hoogste restrisico: 12; aantal gekoppelde risico's:
21"). Daarmee zijn zowel de piek als de breedte zichtbaar. Het getal is een
samenvatting over de koppelingen en geen nieuw scoresysteem.

### 3. De jaartabel is waardevol, met één bekende valkuil

Voortgang per jaar per control maakt de meting uit §9.1 en de verbetering uit §10
in één beeld zichtbaar. Een jaartabel heeft echter dezelfde twee valkuilen als
een KPI:

- **Stuurbare daling.** Als het getal daalt omdat een risico opnieuw is gescoord of
  anders is geclassificeerd, en niet omdat het risico is gemitigeerd, is de
  vooruitgang cosmetisch.
- **Monotone daling is verdacht.** Een lijn die elk jaar regelmatig daalt, wekt
  eerder twijfel dan vertrouwen.

De oplossing is dezelfde als bij de bestaande Meting-laag: **een onveranderlijke
snapshot per jaar, met een korte toelichting op de reden van de beweging**
(gemitigeerd, opnieuw gescoord, nieuw risico toegevoegd, risico afgevoerd). De
historische rijen worden nooit herberekend met de score van vandaag. Herberekening
zou juist het bewijs vernietigen dat de trend echt is.

### Waar het niet werkt (en dat is oké)

Niet elke control hangt aan een gekwantificeerd risico. Sommige controls zijn om
wettelijke of contractuele redenen opgenomen, zonder zinvolle kans × impact. Die
rijen hebben **geen getal**. Een geforceerd cijfer op die rijen zou betekenen dat
er een risico wordt verzonnen om de kolom te vullen.

## In de applicatie

Het scherm **Statement of Applicability** (`/soa`) toont per control een kolom
**Restrisico** in de vorm **`getal(n)`**:

- het **getal** is het hoogste netto-restrisico onder deze control (de piek), met
  dezelfde semafoorkleur als de risico's zelf;
- **`(n)`** is het aantal afzonderlijke risico's dat onder de control hangt.

**`2(1)`** betekent dus dat de zwaarste overgebleven blootstelling 2 is, over één
gekoppeld risico. **`12(3)`** betekent een piek van 12 over drie risico's. Leeg
(**—**) betekent dat er geen risico is gekoppeld. **"onbepaald"** betekent dat er
wel een risico is gekoppeld, maar dat het restrisico niet is ingevuld. Die waarde
is bewust niet 0.

De ontwikkeling over de jaren staat op het tabblad **Restrisico-trend** (naast de
SoA). Dat tabblad toont per control een jaartabel met peiljaar, restrisico (piek,
gekleurd), aantal risico's en een toelichting op de beweging. De tabel leest
onveranderlijke jaarsnapshots. Historische jaren worden nooit herrekend, zodat een
dalende lijn aantoonbaar echt is.

De kolom **Beleid** toont een amberkleurige badge **"Geen beleid"** als een control
*van toepassing = Ja* is, maar er **geen actief beleidsdocument** aan gekoppeld
is. Die badge is een signaal voor een gat en geen vrij tekstveld. De badge
verdwijnt als het betreffende document vanaf **/beleid** aan de control wordt
gekoppeld en op *actief* wordt gezet. Beleid met de status concept of ingetrokken
telt niet mee. Als de control eigenlijk niet van toepassing is, is de juiste actie
de control op *Nee* te zetten, met een motivatie.
