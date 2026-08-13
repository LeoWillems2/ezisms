# Een "ja" in de SoA onderbouwen

De "ja" is verraderlijk: mensen denken dat een opgenomen control zichzelf
verklaart, en schrijven dan "best practice" of "is geïmplementeerd" — precies het
soort motivatie waar een auditor doorheen prikt. Een sterke "ja"-motivatie
beantwoordt drie dingen: **waarom van toepassing, hoe ingevuld, en waaruit dat
blijkt.**

## De kern: traceerbaarheid, geen proza

Het slimste "ja"-antwoord is er één dat je niet zelf hoeft te verzinnen, omdat het
uit je eigen registers rolt. De sterkste rechtvaardiging voor opname is bijna
altijd:

> "Behandelt risico R-xx (en/of: vereist door wet/contract Y)."

Dat maakt de keten **risico → maatregel → SoA → bewijs** zichtbaar, en dát is
exact wat een auditor natrekt. Als een control níet aan een risico of eis te
hangen is, is "ja" eigenlijk verdacht — dan neem je 'm op "omdat het hoort", en
dat is een zwakke onderbouwing.

## De vier bouwstenen van een goede "ja"

Denk aan de motivatie als een klein vast patroon, niet als vrij essay:

1. **Driver — waarom van toepassing.** Eén van: behandelt een geïdentificeerd
   risico / wettelijke eis (de AVG, of sectorwetgeving die op jou van toepassing
   is) / contractuele eis van een klant / eis van
   een belanghebbende / expliciete directiekeuze. Dit is de eigenlijke
   "justification for inclusion" die de norm vraagt.
2. **Realisatie — hoe ingevuld.** Verwijs naar de procedure, het beleid of de
   technische maatregel die het waarmaakt — niet de controltekst herhalen, maar
   jóuw invulling.
3. **Bewijs — waaruit het blijkt.** Het bewijsstuk/de koppeling die laat zien dat
   het werkt (de app koppelt bewijsstukken al aan SoA-maatregelen — dáár verwijs
   je naar).
4. **Status & eigenaar.** Geïmplementeerd / deels / gepland, wie eigenaar is, en
   wanneer het herzien wordt.

## Welke gegevens je aanlevert

Concreet, en veelal al aanwezig in de app:

- **Risico-referentie(s)** — de R-nummers uit de risicolijst (bv. R-7) van de
  risico's die deze control behandelt. Dit is je belangrijkste data.
- **Bron van verplichting** — wet/norm/contract/interne beleidskeuze (kort, met
  vindplaats).
- **Implementatie-aanwijzing** — welk beleidsdocument of welke procedure de
  control realiseert (versie erbij).
- **Bewijskoppeling** — het gekoppelde bewijsstuk (rapport, configuratie-export,
  screenshot van een instelling, logbewijs).
- **Eigenaar + status + herzieningsdatum.**

## Onderscheid dat de moeite waard is

- **"Ja, en geïmplementeerd"** → nadruk op bewijs (het draait aantoonbaar).
- **"Ja, maar nog niet volledig"** → nadruk op de behandelplanning: wat, wie,
  wanneer. Een eerlijke "deels" met datum is auditbestendiger dan een geflatteerde
  "ja".

## Valkuilen bij de "ja"

- **Controltekst overschrijven** ≠ motivatie. Zegt niets over jóuw situatie.
- **Generiek** ("industry standard", "best practice") — leeg zonder driver.
- **Motivatie zonder bewijs** — dan is het een bewering, geen onderbouwing.
- **Los van het risico** — de meest gemiste kans; zonder risicolink mist de rode
  draad.

## Slimme greep binnen deze app

Omdat de risicomodule én de bewijskoppelingen er al zijn, kun je de
"ja"-motivatie grotendeels **afleiden** in plaats van typen: uit de gekoppelde
risico's rolt de driver, uit de gekoppelde bewijsstukken rolt de onderbouwing. De
motivatie wordt dan een korte, consistente samenvatting van die koppelingen in
plaats van vrij tekstwerk — minder werk, en per definitie traceerbaar.

## Voorbeeld — één zin per bouwsteen

> "Van toepassing: behandelt R-7 (onbevoegde toegang tot klantdata) en volgt uit
> AVG art. 32. Ingevuld via toegangsbeleid v2.1 (rollen/gates). Bewijs:
> toegangsreview Q2-2026. Eigenaar: CISO, status geïmplementeerd, herziening
> jaarlijks."

Het R-nummer is de korte verwijzing die in de risicolijst en op het risicodetail
staat (afgeleid van het id, dus stabiel maar met mogelijke gaten: R-7, R-8, R-12).

## Eén control, meerdere deelsystemen: de veel-op-veel-realiteit

In de praktijk vallen er vaak meerdere deelsystemen onder één control. Dat is geen
denkfout — het ís de structuur. De maatregelen uit de bijlage zijn generiek en
organisatiebreed geformuleerd:

- één control → meerdere risico's/drivers → meerdere assets → meerdere bewijzen,
- én één risico → meerdere controls.

Wie een control als 1:1 met "een systeem" ziet, hanteert een te smal model. De
relatie is veel-op-veel, in beide richtingen.

### Het "te veel werk"-bezwaar

Dat bezwaar klopt *als* je de SoA-motivatie het plek maakt waar je álles opnieuw
uitschrijft — elk deelsysteem in proza in dat ene veld. Dan ontploft het. Maar dat
is een methodefout, geen reden om de werkelijkheid plat te slaan naar 1:1. De
oplossing is **verwijzen in plaats van dupliceren**:

- De SoA is een **organisatieniveau-verklaring**: één regel per control. De
  motivatie daar is een **samenvatting/rollup** ("van toepassing wegens R-3,
  R-7, R-12 en AVG art. 32") — geen verhandeling per deelsysteem.
- Het **detail per deelsysteem** hoort thuis in het risico-/behandelregister en de
  assetkoppelingen, wáár het al staat. De SoA is een *lens* over die data, geen
  tweede kopie ervan.

Zo blijft de veel-op-veel-realiteit intact terwijl het invulwerk klein blijft. Het
"te veel werk"-gevoel komt bijna altijd voort uit auteuren in het SoA-veld in
plaats van refereren.

### De echte adder: de status per deelsysteem

Een control kan op systeem A geïmplementeerd zijn en op systeem B deels. Wat zet
je dan in de SoA?

- Op SoA-niveau houd je **één status** aan — pragmatisch de *zwakste* ("deels"),
  met een korte noot.
- De **per-deelsysteem-verschillen** leg je vast in het behandelplan (wat, wie,
  wanneer per systeem).

Een auditor accepteert een geaggregeerde SoA-status prima, zolang het detail
eronder traceerbaar is. Wat 'm níet bevalt is een control die overal
"geïmplementeerd" claimt terwijl één deelsysteem het nog niet doet.

## Het restrisico per control meetbaar maken

De logische volgende stap: maak de SoA meetbaar in plaats van tekstueel door per
control een risicogetal te tonen, en dat per jaar te volgen. Drie keuzes bepalen
of dit een echt stuurmiddel wordt of een mooi ogende grafiek die niets zegt.

### 1. Netto, niet bruto

Neem het **restrisico (na behandeling)**, niet het inherente/bruto risico.

- **Bruto** (kans × impact vóór maatregelen) beweegt nauwelijks — dat is het
  dreigingslandschap. Daar zie je geen progressie in.
- **Netto** is precies wat omlaag hoort te gaan als je maatregelen werken. Daar
  leeft het verhaal van §10 continue verbetering.

Dus: **max netto-restrisico** van de risico's die onder de control hangen. De
kolom toont dan "hoe blootgesteld ben ik nog steeds op dit control-gebied, ondanks
wat ik gedaan heb".

### 2. "Grootste" is verdedigbaar, maar verbergt de verdeling

Max is de meest audit-bestendige keuze ("je bent zo sterk als je zwakst behandelde
risico"). Maar het poetst de verdeling weg: een control die 1 hoog + 20 middens
behandelt krijgt hetzelfde getal als één die alleen dat ene hoge risico dekt.
Toon daarom **max naast een teller** ("hoogste restrisico: 12; aantal gekoppelde
risico's: 21"). Dan zie je zowel de piek als de breedte. Het getal is een rollup
over de koppelingen, geen nieuw scoresysteem.

### 3. De jaartabel is goud — met één bekende valkuil

Progressie per jaar per control maakt §9.1-meting en §10-verbetering in één beeld
zichtbaar. Maar je loopt recht in de twee KPI-valkuilen:

- **Stuurbare daling.** Als het getal daalt omdat je een risico *opnieuw scoorde*
  of *herclassificeerde* — niet omdat je 'm *mitigeerde* — is de vooruitgang
  cosmetisch.
- **Monotone daling is verdacht.** Een lijntje dat elk jaar netjes zakt roept
  eerder twijfel op dan vertrouwen.

De oplossing is dezelfde als bij de bestaande Meting-laag: **snapshot per jaar,
onveranderlijk, met een korte toelichting waaróm het bewoog** (gemitigeerd /
herscoord / nieuw risico erbij / risico afgevoerd). Nooit de historische rijen
herberekenen met de score van vandaag — dan sloop je juist het bewijs dat de trend
echt is.

### Waar het niet werkt (en dat is oké)

Niet elke control hangt aan een gekwantificeerd risico. Sommige staan er om
wettelijke/contractuele redenen zonder zinvolle kans × impact. Die rijen hebben
simpelweg **geen getal** — forceer daar geen cijfer, want dan verzin je risico om
de kolom te vullen.

## In de applicatie

Het scherm **Statement of Applicability** (`/soa`) toont per control een kolom
**Restrisico** in de vorm **`getal(n)`**:

- het **getal** is het hoogste netto-restrisico onder deze control (de piek), met
  dezelfde semafoorkleur als de risico's zelf;
- **`(n)`** is het aantal distinct risico's dat eronder hangt.

Dus **`2(1)`** = de zwaarst overgebleven blootstelling is 2, over 1 gekoppeld
risico; **`12(3)`** = piek 12 over 3 risico's. Leeg (**—**) betekent geen risico
gekoppeld; **"onbepaald"** betekent wel gekoppeld maar restrisico niet ingevuld —
bewust niet 0.

De ontwikkeling over de jaren staat op het tabblad **Restrisico-trend** (naast de
SoA): per control een jaartabel met peiljaar, restrisico (piek, gekleurd), aantal
risico's en een toelichting op de beweging. Die tabel leest onveranderlijke
jaarsnapshots — historische jaren worden nooit herrekend, zodat een dalende lijn
aantoonbaar echt is.

De kolom **Beleid** toont een amber badge **"Geen beleid"** als een control *van
toepassing = Ja* is maar er **geen actief beleidsdocument** aan gekoppeld is — een
gap-signaal, geen vrij tekstveld. Je haalt het weg door vanaf **/beleid** het
betreffende document aan de control te koppelen én het op *actief* te zetten
(concept- of ingetrokken beleid telt niet mee). Staat de control eigenlijk niet
van toepassing, dan is de juiste actie 'm op *Nee* zetten met motivatie.
