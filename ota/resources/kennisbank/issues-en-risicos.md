# Issues en risico's: wat hoort waar?

Bij het opzetten van een ISMS komt vroeg of laat de vraag op of issues niet
gewoon risico's zijn. Die vraag is terecht. Issues en risico's lijken op elkaar,
omdat beide gaan over zaken die mis kunnen gaan, en in veel ISMS'en staan ze
deels door elkaar. Toch zijn het twee verschillende begrippen. Ze komen uit
verschillende hoofdstukken van de norm en hebben elk een eigen doel.

## Twee normclausules, twee vragen

| | Issues (§4.1) | Risico's (§6.1) |
|---|---|---|
| **De vraag** | Wat voor organisatie is dit, en in welke omgeving opereert zij? | Wat kan er concreet misgaan, hoe ernstig is dat, en welke maatregelen zijn nodig? |
| **Aard** | Een blijvende conditie | Een gebeurtenis die zich kan voordoen |
| **Toon** | Positief of negatief | Altijd negatief |
| **Meetbaar** | Nee: een issue heeft geen kans, geen impact en geen score | Ja: een risico heeft kans × impact, een eigenaar, een behandeling en een restrisico |
| **Rol** | Invoer voor de risicobeoordeling en de scope | Uitkomst van de risicobeoordeling |
| **Ritme** | Het beeld wordt periodiek herzien | Elk risico wordt afzonderlijk beoordeeld en behandeld |

Het systeem weerspiegelt dit verschil in de gegevens die het vastlegt. Een issue
heeft alleen een aard, een categorie, een omschrijving en een datum "laatst
beoordeeld". Een issue heeft geen scoreveld, geen eigenaar en geen behandelplan.
Dat is geen omissie, want een conditie laat zich niet scoren. Een risico heeft
al deze velden wel.

## De toets in één zin

> Een onderwerp is een **risico** als er een dreiging, een kwetsbaarheid en een
> getroffen asset bij te benoemen zijn en het te scoren is, en een **issue** als
> het een blijvende eigenschap van de organisatie of haar omgeving is die
> bepaalt naar welke risico's gezocht moet worden.

## Drie verschillen die in de praktijk uitmaken

**Een issue kan gunstig zijn.** De overstap naar een gecertificeerde
hostingpartij is een issue. Een directie die informatiebeveiliging tot speerpunt
heeft gemaakt, is ook een issue. Zulke onderwerpen horen in §4.1, omdat ze de
context van de organisatie bepalen. Als risico zijn ze zinloos, omdat er geen
dreiging of kwetsbaarheid bij te benoemen is. Om die reden spreekt de norm in
§6.1 over risico's en kansen.

**Een issue is invoer en geen uitvoer.** Het systeem biedt daarom de mogelijkheid
om issues aan een scopeverklaring te koppelen. §4.3 eist dat de organisatie bij
het bepalen van de ISMS-scope rekening houdt met de kwesties uit §4.1. Een issue
onderbouwt dus waar de grens van het ISMS ligt. Een risico doet dat niet, omdat
een risico per definitie binnen die grens valt.

**Het detailniveau verschilt.** Eén issue leidt doorgaans tot meerdere risico's.
Verscherpt toezicht op de meldplicht datalekken is één kwestie, maar die kwestie
leidt tot risico's op het gebied van detectie, meldtermijnen en dossiervorming.

## Voorbeelden

| Omschrijving | Registreren als | Reden |
|---|---|---|
| "De volledige omzet loopt via één platform" | Issue (intern) | Dit is een structurele eigenschap. Het is niet te scoren, maar het bepaalt wel waar risico's liggen. |
| "Ransomware versleutelt productie en back-ups en legt de handel stil" | Risico | Er zijn een dreiging, een kwetsbaarheid en een asset te benoemen, en het is te scoren. |
| "Productiebeheer rust op twee externe krachten" | Issue (intern) | Dit is een conditie van de organisatie. |
| "Uitval van de enige beheerder maakt herstel binnen de hersteltijd onmogelijk" | Risico | Dit is een concrete gebeurtenis met impact. |
| "Klanten vragen steeds vaker om certificering" | Issue (extern) | Dit is marktdruk. Marktdruk leidt eerder tot beleid en een directiebesluit dan tot een risico. |
| "De toezichthouder handhaaft strenger op meldtermijnen" | Issue (extern) | Dit is een factor in de omgeving. |
| "Een datalek wordt te laat gemeld, met een boete tot gevolg" | Risico | Dit is een gebeurtenis met een kans en een impact. |

De eerste twee rijen gaan over hetzelfde onderwerp. Dat is normaal en geen fout.
Het issue beschrijft waarom de organisatie op die plek moest kijken, en het
risico beschrijft wat daar is gevonden.

## De twee manieren waarop het misgaat

**§4.1 wordt gevuld met risico's.** Het resultaat is een tweede risicoregister
zonder scores. Deze fout is herkenbaar aan omschrijvingen die beginnen met "het
gevaar dat…" of "de kans op…". De contextanalyse zegt dan niets meer over de
omgeving van de organisatie, en een auditor ziet dat direct.

**§4.1 wordt gevuld met algemeenheden.** Voorbeelden zijn "de wereld
digitaliseert" en "cybercrime neemt toe". Het probleem van zulke issues is dat er
geen actie uit volgt. Een bruikbaar issue is zo specifiek dat na te gaan is of
het nog klopt en welke risico's eruit volgen. Een praktische toets is of de zin
ook voor een willekeurige andere organisatie geldt. Als dat zo is, is het issue
te algemeen.

## Waar de auditor naar vraagt

Een auditor stelt vrijwel altijd de volgende vraag:

> "Het verscherpte dreigingsbeeld staat genoemd als externe kwestie. Waar is dat
> terug te zien in de risicobeoordeling?"

Het juiste antwoord verwijst naar de risico's die uit die kwestie zijn
voortgekomen. Die risico's zijn te zien in de kolom **Risico's** van het
issue-register. Dit verband is de reden dat §4.1 in de norm staat. De paragraaf
is geen invuloefening, maar een denkstap die vóór de beoordeling bepaalt waar de
organisatie naar risico's zoekt.

De auditor stelt een vergelijkbare vraag over de scope: welke kwesties hebben
geleid tot de gekozen grens? Het systeem legt dat vast via de koppeling tussen
een scopeverklaring en de issues die bij het bepalen van de scope zijn
meegewogen.

## Zo werkt het nu in dit systeem

Op **`/issues`** worden de kwesties geregistreerd. Per kwestie legt het systeem
vast of die intern of extern is, tot welke categorie die behoort (juridisch,
technologisch, markt, personeel, bedrijfsvoering en dergelijke), wat de
omschrijving is en wanneer de kwestie voor het laatst is beoordeeld.

Op **`/scope`** worden issues aan de scopeverklaring gekoppeld. Bij een nieuwe
scopeversie neemt het systeem die koppeling over, zodat de onderbouwing behouden
blijft.

De doorvertaling naar risico's wordt vastgelegd **op het risico zelf**, bij de
basisgegevens onder *Aanleiding*. Daar wordt aangevinkt uit welke kwestie of
kwesties het risico is voortgekomen. Deze plaats is bewust gekozen. Een maatregel
is een antwoord op een risico, en een kwestie is de aanleiding voor een risico.
Daarom hangt de SoA-koppeling aan de behandeling en hangt de aanleiding aan het
risico.

Het issue-register toont vervolgens per kwestie hoeveel risico's eruit zijn
voortgekomen, met een link naar die risico's. Een streepje betekent dat de
kwestie niet in een risico is doorvertaald.

**Het veld Aanleiding mag leeg blijven.** De meeste risico's komen niet uit een
§4.1-kwestie, maar uit assets, incidenten, leveranciersbeoordelingen of audits.
Een risico zonder aanleiding is daarom geen tekortkoming, en het systeem geeft
daar ook geen melding over. Het signaal werkt bewust in één richting: het systeem
waarschuwt voor issues zonder risico's, maar niet voor risico's zonder issue.

De kolom en het signaal zijn alleen zichtbaar voor gebruikers die de
risicomodule mogen inzien. Een medewerker met leesrecht op de context ziet het
issue-register wel, maar ziet de doorvertaling niet.

## Wat een 3 of een 5 betekent

Een risico wordt gescoord op kans en impact, elk op een schaal van 1 tot 5. Die
cijfers hebben alleen waarde als voor alle beoordelaars vastligt wat ze
betekenen. Zonder die afspraak geeft de ene beoordelaar een 3 waar een andere
beoordelaar een 5 geeft. De score is dan geen criterium, maar een gevoel.

De betekenis van de vijf niveaus staat daarom op **`/risicos/criteria`**, onder
*Beoordelingsschaal*, samen met de leidraad per as en de acceptatiedrempel. De
schaal hoort bij de norm die deze installatie volgt, omdat de ernst van een
gebeurtenis afhangt van wat de organisatie doet en voor wie. Een beoordelaar
leest de schaal vóór het scoren en legt bij het risico vast waarom het risico op
dat niveau uitkomt. Een auditor vraagt naar die onderbouwing en niet naar het
cijfer zelf.

### De schaal en de drempels zijn vastgesteld, niet ingesteld

Alle gegevens op die pagina vormen samen één **versie**: de risk appetite, de
acceptatiedrempel, de waarschuwingsgrens, de leidraden en de 10
niveaudefinities. Een versie wordt niet bijgewerkt, maar vervangen. De CISO
stelt een nieuwe conceptversie op en dient die in, waarna de directie de versie
vaststelt. De semafoor verandert pas op het moment van vaststelling.

Dit proces is bewust omslachtiger dan een invulveld. Een acceptatiedrempel is
een bestuurlijke grens, en die grens hoort niet te verschuiven doordat één
persoon een ander getal invult. Bij elk beoordeeld risico staat daarom vermeld
onder welke versie het is beoordeeld. Vervangen versies blijven bewaard. Daardoor
is achteraf vast te stellen tegen welk criterium een risico destijds als
aanvaardbaar gold.

Bij het vaststellen toont het scherm de gevolgen voor het bestaande register:
welke risico's zwaarder gaan wegen en hoeveel risico's boven de
acceptatiedrempel uitkomen. Voor die risico's maakt het systeem automatisch een
herbeoordelingstaak aan bij de eigenaar. Een auditor stelt na een aangescherpte
drempel precies deze vraag. De auditor vraagt dan niet waar de drempel staat,
maar wat er daarna met de betrokken risico's is gedaan.

### De kwantitatieve band

Sommige auditors sturen op cijfers en vragen bijvoorbeeld wat impact 4 de
organisatie kost. Elk niveau heeft daarvoor een apart veld, *kwantitatieve
band*. Een voorbeeld van een invulling is "1 tot 5% van de jaaromzet, of meer
dan 3 dagen uitval van een kernproces". Het veld wordt leeg uitgeleverd. Wat 2%
van de omzet betekent, verschilt per organisatie en hoort niet uit dit systeem
te komen.

Een bedrag hoort bij een impactniveau en niet bij een **score**. Een score van
10 ontstaat zowel uit 2 × 5 (zeldzaam, catastrofaal) als uit 5 × 2 (maandelijks,
klein), en die twee situaties kosten niet hetzelfde. Kwantificeren is daarom
alleen per impactniveau mogelijk.

De meegeleverde teksten zijn een uitgangspunt en geen normtabel. De organisatie
mag ze herschrijven. Het resultaat is dan een nieuwe versie, die de volledige
goedkeuringsroute doorloopt.

## De jaarlijkse contextherziening

Het veld "laatst beoordeeld" heeft een functie. De issues horen periodiek te
worden doorgelopen, bij voorkeur als vaste stap vóór de risicoherbeoordeling en
vóór de directiebeoordeling. Per kwestie zijn daarbij drie vragen te
beantwoorden:

1. **Klopt dit nog?** Een opgelost issue wordt verwijderd of als vervallen
   gemarkeerd. Een register dat alleen groeit, wordt niet meer gelezen.
2. **Is er iets bijgekomen?** Voorbeelden zijn nieuwe wetgeving, een overname,
   een verschuiving in het klantenbestand en een technologische wissel.
3. **Waar komt dit terug in de risico's?** Het register toont deze vraag boven de
   lijst zodra er kwesties zonder risico zijn. Als de vraag voor een kwestie niet
   te beantwoorden is, is er één van twee dingen aan de hand: het issue is niet
   relevant genoeg om te registreren, of
   er zit een gat in de risicobeoordeling. Het systeem dwingt niets af, maar
   stelt alleen de vraag.

De derde vraag is de nuttigste van de drie. Deze vraag is ook de enige reden
waarom het onderscheid tussen issues en risico's de moeite waard is. Zonder deze
vraag is §4.1 dubbele administratie.
