# Issues en risico's: wat hoort waar?

Vroeg of laat stelt iemand de vraag: *zijn die issues niet gewoon risico's?* Het
is een terechte vraag. Ze lijken op elkaar, ze gaan allebei over dingen die
misgaan, en in veel ISMS'en staan ze half door elkaar. Toch zijn het twee
verschillende dingen, uit twee verschillende hoofdstukken van de norm, met een
verschillend doel.

## Twee normclausules, twee vragen

| | Issues (§4.1) | Risico's (§6.1) |
|---|---|---|
| **De vraag** | Wat voor organisatie zijn wij en in wat voor omgeving opereren we? | Wat kan er concreet misgaan, hoe erg, en wat doen we eraan? |
| **Aard** | Een blijvende conditie | Een gebeurtenis die zich kan voordoen |
| **Toon** | Kan positief of negatief zijn | Altijd negatief |
| **Meetbaar** | Nee — geen kans, geen impact, geen score | Ja — kans × impact, eigenaar, behandeling, restrisico |
| **Rol** | Invoer voor de risicobeoordeling en de scope | Uitkomst van de risicobeoordeling |
| **Ritme** | Periodiek herzien (klopt dit beeld nog?) | Per stuk beoordeeld en behandeld |

Dat verschil zie je terug in wat het systeem van je vraagt. Een issue heeft
alleen aard, categorie, omschrijving en een datum "laatst beoordeeld". Er is geen
scoreveld, geen eigenaar en geen behandelplan — niet omdat dat vergeten is, maar
omdat een conditie zich niet laat scoren. Een risico heeft dat allemaal wél.

## De toets in één zin

> Kun je er een dreiging, een kwetsbaarheid en een getroffen asset bij noemen en
> het scoren? Dan is het een **risico**. Is het een blijvende eigenschap van je
> organisatie of omgeving die stuurt wélke risico's je moet gaan zoeken? Dan is
> het een **issue**.

## Drie verschillen die in de praktijk uitmaken

**Een issue kan gunstig zijn.** "We stappen over op een gecertificeerde
hostingpartij" of "de directie heeft security tot speerpunt gemaakt" zijn issues.
Ze horen in §4.1 omdat ze je context bepalen. Als risico zijn ze onzin — er valt
geen dreiging en kwetsbaarheid bij te bedenken. De norm spreekt bij §6.1 dan ook
bewust over risico's *en kansen*.

**Een issue is invoer, geen uitvoer.** Daarom kun je in dit systeem issues aan een
scopeverklaring koppelen: §4.3 eist dat je bij het bepalen van de ISMS-scope de
§4.1-kwesties meeweegt. Een issue onderbouwt dus waar je grens loopt. Een risico
doet dat niet — dat zit per definitie binnen die grens.

**Het detailniveau verschilt.** Eén issue levert doorgaans meerdere risico's op.
"Verscherpt toezicht op de meldplicht datalekken" is één kwestie, maar leidt tot
risico's rond detectie, rond meldtermijnen en rond dossiervorming.

## Voorbeelden

| Wat je opschrijft | Waar het hoort | Waarom |
|---|---|---|
| "De volledige omzet loopt via één platform" | Issue (intern) | Een structurele eigenschap. Niet scoorbaar, wel bepalend. |
| "Ransomware versleutelt productie en back-ups en legt de handel stil" | Risico | Dreiging, kwetsbaarheid, asset, scoorbaar. |
| "Productiebeheer rust op twee externe krachten" | Issue (intern) | Een conditie van de organisatie. |
| "Uitval van de enige beheerder maakt herstel onmogelijk binnen de hersteltijd" | Risico | Concrete gebeurtenis met impact. |
| "Klanten vragen steeds vaker om certificering" | Issue (extern) | Marktdruk. Landt eerder in beleid en directiebesluit dan in een risico. |
| "Toezichthouder handhaaft strenger op meldtermijnen" | Issue (extern) | Omgevingsfactor. |
| "Een datalek wordt te laat gemeld, met een boete tot gevolg" | Risico | Gebeurtenis, met kans en impact. |

Merk op dat de bovenste twee rijen over hetzelfde onderwerp gaan. Dat is normaal
en geen fout: het issue beschrijft waarom je dáár moest gaan kijken, het risico
beschrijft wat je vervolgens vond.

## De twee manieren waarop het misgaat

**Je vult §4.1 met risico's.** Dan krijg je een risicoregister in tweevoud — één
keer zonder scores. Herkenbaar aan omschrijvingen die beginnen met "het gevaar
dat…" of "de kans op…". Je contextanalyse zegt dan niets meer over je omgeving,
en de auditor ziet dat meteen.

**Je vult §4.1 met loze algemeenheden.** "De wereld digitaliseert", "cybercrime
neemt toe". Waar dat op stukloopt: er valt niets mee te doen. Een bruikbaar issue
is specifiek genoeg dat je kunt nagaan of het nog klopt en welke risico's eruit
volgen. Een goede test is of de zin ook voor je buurman geldt — dan is hij te
algemeen.

## Waar de auditor naar vraagt

Verwacht deze vraag, want hij is standaard:

> "U noemt een verscherpt dreigingsbeeld als externe kwestie. Waar zie ik dat
> terug in uw risicobeoordeling?"

Het antwoord hoort te zijn: hier, in deze risico's — en dat is precies wat de
kolom **Risico's** op het issue-register laat zien. Dat is de hele reden dat §4.1
in de norm staat — het is geen invuloefening maar een denkstap die bepaalt waar je
gaat kijken voordat je begint met beoordelen.

Dezelfde vraag komt terug bij de scope: *welke kwesties hebben ertoe geleid dat u
deze grens hebt getrokken?* Die kant is in dit systeem wél vastgelegd, via de
koppeling tussen een scopeverklaring en de issues waarmee rekening is gehouden.

## Zo werkt het nu in dit systeem

Op **`/issues`** registreer je de kwesties: intern of extern, een categorie
(juridisch, technologisch, markt, personeel, bedrijfsvoering…), de omschrijving en
de datum waarop je hem voor het laatst hebt beoordeeld.

Op **`/scope`** koppel je issues aan de scopeverklaring. Bij een nieuwe
scopeversie wordt die koppeling meegenomen, zodat de onderbouwing niet verdwijnt.

De doorvertaling naar risico's leg je vast **op het risico zelf**, bij de
basisgegevens onder *Aanleiding*. Daar vink je aan uit welke kwestie(s) het
risico is voortgekomen. Dat het daar zit en niet bij het behandelplan is geen
willekeur: een maatregel is een *antwoord*, een kwestie is een *aanleiding*. De
SoA-koppeling hangt daarom aan de behandeling, en de aanleiding aan het risico.

Op het issue-register zie je vervolgens per kwestie hoeveel risico's eruit zijn
voortgekomen, met een link naar die risico's. Staat er een streepje, dan is de
kwestie nergens doorvertaald.

**Leeg laten mag.** Lang niet elk risico komt uit een §4.1-kwestie — de meeste
komen uit je assets, incidenten, leveranciersbeoordelingen of audits. Een risico
zonder aanleiding is dus geen tekortkoming, en het systeem zal er ook nooit over
klagen. Het signaal loopt bewust maar één kant op: het waarschuwt over *issues
zonder risico's*, niet over risico's zonder issue.

De kolom en het signaal zijn alleen zichtbaar voor wie ook de risicomodule mag
inzien. Een medewerker met leesrecht op de context ziet het issue-register wél,
maar de doorvertaling niet.

## Wat een 3 of een 5 betekent

Een risico scoor je op kans en impact, allebei van 1 tot 5. Die cijfers zijn
alleen iets waard als voor iedereen vastligt wát ze betekenen — anders scoort de
ene beoordelaar een 3 waar de ander een 5 geeft, en dan is de score geen
criterium maar een gevoel.

De betekenis van de vijf niveaus staat daarom op **`/risicos/criteria`**, onder
*Beoordelingsschaal*, samen met de leidraad per as en de acceptatiedrempel. Die
schaal hoort bij de norm die deze installatie volgt: wat een gebeurtenis erg
maakt, hangt af van wat de organisatie doet en voor wie. Lees hem vóórdat je
scoort, en noteer bij het risico waaróm je op dat niveau uitkomt — dat is wat een
auditor vraagt, niet het cijfer zelf.

### De schaal en de drempels zijn vastgesteld, niet ingesteld

Alles op die pagina — de risk appetite, de acceptatiedrempel, de
waarschuwingsgrens, de leidraden en de tien niveaudefinities — vormt samen één
**versie**. Die versie wordt niet bijgewerkt maar vervangen: de CISO stelt een
nieuwe conceptversie op, dient hem in, en de directie stelt hem vast. Pas op dat
laatste moment verandert er iets aan de semafoor.

Dat is met opzet omslachtiger dan een invulveld. Een acceptatiedrempel is een
bestuurlijke grens; hij hoort niet te verschuiven omdat één persoon een ander
getal invulde. Bij elk beoordeeld risico staat daarom onder welke versie het
beoordeeld is, en vervangen versies blijven bewaard — zo is achteraf vast te
stellen tegen welk criterium een risico destijds aanvaardbaar heette.

Bij het vaststellen laat het scherm zien wat er met het bestaande register
gebeurt: welke risico's zwaarder gaan wegen, en hoeveel er boven de
acceptatiedrempel uitkomen. Die krijgen automatisch een herbeoordelingstaak bij
hun eigenaar. Dat is precies de vraag die een auditor stelt na een aangescherpte
drempel — niet "waar staat het", maar "wat heeft u toen met die risico's
gedaan".

### De kwantitatieve band

Sommige auditors sturen op cijfers: "wat kost impact 4 ons?". Elk niveau heeft
daarvoor een apart veld, *kwantitatieve band*, waarin u bijvoorbeeld "1 tot 5%
van de jaaromzet, of meer dan 3 dagen uitval van een kernproces" kwijt kunt.
Het veld wordt leeg uitgeleverd: wat 2% van úw omzet is, hoort niet uit dit
systeem te komen.

Let op waar zo'n bedrag op slaat. Aan de **score** hangt er geen: 10 is zowel
2 × 5 (zeldzaam, catastrofaal) als 5 × 2 (maandelijks, klein), en die twee kosten
niet hetzelfde. Kwantificeren kan alleen per impactniveau.

De meegeleverde teksten zijn een uitgangspunt, geen normtabel. U mag ze
herschrijven — dat is dan een nieuwe versie, met goedkeuring en al.

## De jaarlijkse contextherziening

Het veld "laatst beoordeeld" is er niet voor de sier. Loop je issues periodiek
langs — bij voorkeur als vaste stap vóór de risicoherbeoordeling en vóór de
directiebeoordeling — en stel per kwestie drie vragen:

1. **Klopt dit nog?** Een opgelost issue haal je weg of markeer je als vervallen.
   Een register dat alleen maar groeit, wordt niet gelezen.
2. **Is er iets bij gekomen?** Nieuwe wetgeving, een overname, een verschuiving in
   je klantenbestand, een technologische wissel.
3. **Waar landt dit in mijn risico's?** Het register zet die vraag zelf boven de
   lijst zodra er kwesties zonder risico staan. Kun je hem voor een kwestie niet
   beantwoorden, dan is er één van twee dingen aan de hand: het issue is niet
   relevant genoeg om te registreren, óf er zit een gat in je risicobeoordeling.
   Het systeem dwingt niets af — het stelt de vraag.

Die derde vraag is de nuttigste van de drie, en meteen de enige reden waarom het
onderscheid tussen issues en risico's de moeite waard is. Zonder die vraag is
§4.1 inderdaad dubbele administratie.
