# Van lege installatie naar draaiend ISMS

De uitgangssituatie is een draaiende installatie met één CISO-account, gemaakt met
`isms:eerste-ciso`. Elk register in het menu staat open, en dat is precies het
probleem, want de registers hangen van elkaar af. Wie bij de risico's begint,
mist het kader waartegen die risico's beoordeeld worden. Wie bij de gebruikers
begint, heeft nog geen afdelingen om ze aan toe te wijzen. Deze pagina beschrijft
de volgorde die deze problemen voorkomt.

<div class="vulvolgorde" style="margin:1.4rem 0;">
<!-- De naam staat in aria-label en niet in een title-element: de
     GFM-converter escapet `title` (samen met script, style, iframe en textarea),
     en een ge-escapete opening zou als platte tekst boven het schema belanden.
     Een desc-element staat niet op die lijst en mag dus wel. -->
<svg role="img" aria-label="Van lege installatie naar draaiend ISMS" aria-describedby="vulvolgorde-d" viewBox="0 108 1000 548" style="width:100%;height:auto;font-family:inherit;" xmlns="http://www.w3.org/2000/svg">
<desc id="vulvolgorde-d">Vier fasen. Fase 1 Fundament: organisatie-eenheden, dan gebruikers en rollen met hun afdeling. Fase 2 Kader: context, scope-verklaring versie 1, risicocriteria, classificatieschema. Fase 3 Inhoud: systemen en assets, risico's, de Verklaring van Toepasselijkheid (SoA), beleid. Fase 4 Ritme: taken en KPI's, auditcyclus. Scope, risicocriteria en beleid worden door Management vastgesteld.</desc>
<g fill="none" stroke="currentColor" stroke-opacity="0.35" stroke-width="1.5">
<circle cx="26" cy="150" r="24"/>
<circle cx="26" cy="280" r="24"/>
<circle cx="26" cy="410" r="24"/>
<circle cx="26" cy="540" r="24"/>
</g>
<g fill="none" stroke="currentColor" stroke-opacity="0.3" stroke-width="1.5">
<line x1="26" y1="176" x2="26" y2="256"/>
<line x1="26" y1="306" x2="26" y2="386"/>
<line x1="26" y1="436" x2="26" y2="516"/>
</g>
<g fill="rgba(128,128,128,0.07)" stroke="rgba(128,128,128,0.28)" stroke-width="1">
<rect x="150" y="118" width="195" height="64"/>
<rect x="367" y="118" width="195" height="64"/>
<rect x="150" y="248" width="195" height="64"/>
<rect x="367" y="248" width="195" height="64"/>
<rect x="584" y="248" width="195" height="64"/>
<rect x="801" y="248" width="195" height="64"/>
<rect x="150" y="378" width="195" height="64"/>
<rect x="367" y="378" width="195" height="64"/>
<rect x="584" y="378" width="195" height="64"/>
<rect x="801" y="378" width="195" height="64"/>
<rect x="150" y="508" width="195" height="64"/>
<rect x="367" y="508" width="195" height="64"/>
</g>
<g fill="none" stroke="currentColor" stroke-opacity="0.45" stroke-width="1.25">
<path d="M349 150 H 357"/>
<path d="M349 280 H 357"/>
<path d="M566 280 H 574"/>
<path d="M783 280 H 791"/>
<path d="M349 410 H 357"/>
<path d="M566 410 H 574"/>
<path d="M783 410 H 791"/>
<path d="M349 540 H 357"/>
</g>
<g fill="currentColor" fill-opacity="0.5">
<polygon points="357,145 367,150 357,155"/>
<polygon points="357,275 367,280 357,285"/>
<polygon points="574,275 584,280 574,285"/>
<polygon points="791,275 801,280 791,285"/>
<polygon points="357,405 367,410 357,415"/>
<polygon points="574,405 584,410 574,415"/>
<polygon points="791,405 801,410 791,415"/>
<polygon points="357,535 367,540 357,545"/>
</g>
<g fill="var(--color-accent, #5980a6)" font-size="21" font-weight="700" text-anchor="middle">
<text x="26" y="157">1</text>
<text x="26" y="287">2</text>
<text x="26" y="417">3</text>
<text x="26" y="547">4</text>
</g>
<g fill="currentColor" font-size="18" font-weight="700">
<text x="62" y="163">Fundament</text>
<text x="62" y="293">Kader</text>
<text x="62" y="423">Inhoud</text>
<text x="62" y="553">Ritme</text>
</g>
<g fill="currentColor" fill-opacity="0.9" font-size="15" font-weight="600">
<text x="164" y="146">Organisatie-eenheden</text>
<text x="381" y="146">Gebruikers &amp; rollen</text>
<text x="164" y="276">Context</text>
<text x="381" y="276">Scope-verklaring v1</text>
<text x="598" y="276">Risicocriteria</text>
<text x="815" y="276">Classificatieschema</text>
<text x="164" y="406">Systemen &amp; assets</text>
<text x="381" y="406">Risico's</text>
<text x="598" y="406">SoA</text>
<text x="815" y="406">Beleid</text>
<text x="164" y="536">Taken &amp; KPI's</text>
<text x="381" y="536">Auditcyclus</text>
</g>
<g fill="currentColor" fill-opacity="0.6" font-size="13">
<text x="164" y="166">afdelingen en locaties</text>
<text x="381" y="166">met hun afdeling</text>
<text x="164" y="296">issues, belanghebbenden</text>
<text x="381" y="296">Management activeert</text>
<text x="598" y="296">drempels, tien niveaus</text>
<text x="815" y="296">betekenis van BIV hier</text>
<text x="164" y="426">classificeren op BIV</text>
<text x="381" y="426">beoordelen tegen kader</text>
<text x="598" y="426">beslissen + motiveren</text>
<text x="815" y="426">bestand, doelgroep</text>
<text x="164" y="556">eigenaar, streefwaarde</text>
<text x="381" y="556">vereist een besliste SoA</text>
</g>
<g fill="var(--color-accent, #5980a6)">
<circle cx="546" cy="262" r="4"/>
<circle cx="763" cy="262" r="4"/>
<circle cx="980" cy="392" r="4"/>
</g>
<line x1="0" y1="606" x2="1000" y2="606" stroke="currentColor" stroke-opacity="0.3" stroke-width="1.5"/>
<circle cx="6" cy="632" r="4" fill="var(--color-accent, #5980a6)"/>
<text x="20" y="636" fill="currentColor" fill-opacity="0.6" font-size="12">Management stelt vast; de CISO kan dit niet zelf. Een management-gebruiker is daarom direct nodig.</text>
</svg>
</div>

## Waarom de volgorde uitmaakt

Er zijn drie soorten afhankelijkheden, en ze gedragen zich verschillend.

De eerste soort is **hard**: een record verwijst naar een ander record, en zonder
dat andere record is het niet op te slaan. Een gebruiker draagt een
organisatie-eenheid, en een risico draagt de versie van de risicocriteria
waaronder het beoordeeld is. Deze afhankelijkheden blijken vanzelf, omdat het
opslaan anders mislukt.

De tweede soort is **inhoudelijk**. Deze soort is gevaarlijker, omdat het systeem
het werk gewoon laat doorgaan. Het is mogelijk om 50 assets te classificeren
terwijl er nog geen enkele regel in het classificatieschema staat. Dat lukt, maar
het resultaat is waardeloos. "Vertrouwelijk" betekent dan nog niets, en als die
betekenis een maand later alsnog wordt vastgelegd, blijkt de helft van de
classificaties niet te kloppen.

De derde soort is **procedureel**: Management moet iets vaststellen voordat het
geldt. Dat kost doorlooptijd die de CISO niet zelf in de hand heeft. De laatste
paragraaf van deze pagina gaat daarop in.

## Fase 1 — Fundament

**Organisatie-eenheden eerst.** Afdelingen en locaties vormen het skelet waar
verderop veel aan hangt: de reikwijdte van de scope-verklaring, de eigenaren van
assets en risico's, en de doelgroepen voor beleid en trainingen.

**Daarna pas gebruikers en rollen, met hun afdeling.** De afdeling is de reden dat
deze twee stappen in deze volgorde staan. Een gebruiker zonder
organisatie-eenheid valt buiten elke doelgroep die op afdeling is gebaseerd, en
dat is niet direct zichtbaar. Die gebruiker krijgt geen leesbevestiging en geen
training toegewezen, en het systeem meldt niet dat er iemand is overgeslagen. De
afdelingen worden daarom eerst aangemaakt, en de gebruikers daarna.

**In deze fase wordt ook een management-gebruiker aangemaakt.** Dat hoort niet
later te gebeuren. De rol *Management* heeft op geen enkel blok muteerrecht, maar
heeft wel goedkeuringsrecht op context en scope, op risico's en SoA, en op beleid.
De CISO heeft dat recht juist niet. Dat is de functiescheiding waarvoor de rol
bestaat. Zonder zo'n account loopt fase 2 vast op de eerste stap die vastgesteld
moet worden. Zie [Gebruikers, rollen en rechten](/kennisbank/gebruikers-rollen-en-rechten).

## Fase 2 — Kader

Deze fase wordt het vaakst overgeslagen, maar levert het meeste op. In deze fase
wordt vastgelegd waartegen later alles wordt afgemeten.

**Context: issues en belanghebbenden.** Dit zijn de interne en externe onderwerpen
die het managementsysteem raken, en de partijen die er belang bij hebben. Ze zijn
geen doel op zich, maar worden gekoppeld aan de scope-verklaring die hierna wordt
gemaakt. Wat wel en niet in dit register hoort, staat in [Issues (§4.1) en risico's
(§6.1)](/kennisbank/issues-en-risicos).

**Scope-verklaring versie 1.** Deze verklaring bevat de organisatie-eenheden uit
fase 1, de issues en belanghebbenden die zojuist zijn vastgelegd, en de
uitsluitingen en koppelvlakken met de buitenwereld. Een verklaring is bewerkbaar
zolang die de status *concept* heeft. Daarna gaat de verklaring naar *ter
goedkeuring* en activeert Management haar. Versie 1 hoeft niet perfect te zijn en
mag ruw zijn. De statusgang `concept → ter goedkeuring → actief → vervangen` gaat
er juist van uit dat er een versie 2 komt. Een vervangen versie blijft bewaard als
bewijs van wat er destijds gold.

**Risicocriteria.** Eén versie bevat het hele kader: de risk-appetite-verklaring,
de rode acceptatiedrempel, de amber waarschuwingsgrens, de leidraad per as en de
10 niveaudefinities, namelijk vijf voor kans en vijf voor impact. De CISO stelt
de versie op en dient die in, en Management activeert haar. Deze stap moet vóór
de risico's uit fase 3 plaatsvinden. De reden is niet alleen netheid: elk risico
legt vast onder welke versie het beoordeeld is. Als de risico's eerst worden
beoordeeld en het kader pas daarna wordt vastgesteld, hebben de scores geen
grondslag.

**Classificatieschema.** Het schema bevat al 12 regels: drie dimensies
(vertrouwelijkheid, integriteit, beschikbaarheid) maal vier niveaus.
Vertrouwelijkheid en integriteit lopen van *openbaar* tot *geheim*,
beschikbaarheid van *niet kritiek* tot *bedrijfskritiek*. Het leeg gelaten deel
is precies het deel dat per organisatie verschilt: de omschrijving en de
omgangsregels per niveau. Die velden horen ingevuld te zijn voordat het
classificeren begint. Anders is "vertrouwelijk" een woord zonder afspraak.

## Fase 3 — Inhoud

Pas in deze fase gaat het over de werkelijke situatie van de organisatie. Dat is
nu ook mogelijk, omdat er een meetlat ligt.

**Systemen en assets, geclassificeerd op BIV.** Met het schema uit fase 2 is dit
invulwerk in plaats van improvisatie.

**Risico's, beoordeeld tegen het kader.** Een risico hangt aan een asset of aan
een leverancier, draagt een kans- en impactniveau uit de actieve versie van de
criteria, en heeft een eigenaar. De score volgt uit het kader en niet uit een
gevoel dat per beoordelaar verschilt.

**De Verklaring van Toepasselijkheid: beslissen en motiveren.** Per maatregel
wordt vastgelegd of de maatregel van toepassing is en waarom. Die motivatie is
geen formaliteit, want een auditor leest haar. Hoe een regel wordt opgebouwd van
driver tot restrisico, staat in [De SoA onderbouwen](/kennisbank/soa-onderbouwen-en-restrisico).

**Beleid.** Een beleidsdocument krijgt een bestand en een doelgroep, en de
doelgroep werkt alleen als fase 1 klopt. De CISO schrijft en Management stelt
vast. Dat is dezelfde scheiding als bij de scope en de criteria.

## Fase 4 — Ritme

De eerste drie fasen vormen een project. Deze fase is dat niet. In deze fase
wordt het terugkerende werk ingericht waar de norm om vraagt.

**Taken en KPI's.** Taken krijgen een eigenaar en een deadline. Terugkerende taken
komen uit sjablonen en worden 's nachts gegenereerd. KPI's krijgen een
streefwaarde en een signaalwaarde.

De KPI's worden beter te vroeg dan te laat ingericht. De meting draait maandelijks
en legt per KPI de teller en de noemer onveranderlijk vast. Dat is de bedoeling,
maar het betekent ook dat meethistorie niet met terugwerkende kracht te maken is.
Een KPI die in juni wordt gedefinieerd, heeft over januari tot en met mei geen
gegevens, en dat gat is later niet meer te vullen. Zie
[KPI's en meetwaarden](/kennisbank/kpis-en-meetwaarden).

**Auditcyclus, en die staat bewust achteraan.** De audit-universe wordt afgeleid
uit de SoA. Elke maatregel die van toepassing is, krijgt een auditobject, en een
maatregel die niet van toepassing is, krijgt er geen. Als de cyclus wordt
opgezet voordat de SoA beslist is, worden er rondes gepland over een lege
verzameling. Het commando `isms:bereid-auditcyclus-voor` weigert daarom bij een
onvolledige SoA, tenzij `--forceer` wordt meegegeven. Die optie is alleen bedoeld
voor wie weet waarom die nodig is.

Voor de opstart is er `--voorbereiding`: één ronde die een nulmeting is en geen
oordeel. De echte meerjarencyclus begint pas na de certificeringsaudit. Zie
[Een interne audit opzetten](/kennisbank/interne-audit-opzetten).

## Wat later kan

Niet alles hoeft in deze volgorde. Leveranciers, incidenten, afwijkingen,
trainingen en wijzigingsbeheer hebben geen harde voorganger in dit schema en
kunnen worden toegevoegd wanneer ze aan de orde zijn. Daarbij geldt één
kanttekening. Een incident dat vandaag wordt gemeld, is later bewijs, en een
leverancier die vandaag wordt opgevoerd, kan later aan een risico hangen. Later
beginnen is dus geen probleem, maar niet beginnen wel.

## De drie momenten waarop Management nodig is

De accentstippen in het schema markeren deze momenten: de **scope-verklaring**, de
**risicocriteria** en het **beleid**. Op alle drie heeft de rol *Management*
goedkeuringsrecht en de CISO niet. Dat is geen omissie, maar het doel: wie een
document opstelt, stelt het niet zelf vast.

In de praktijk heeft dat twee gevolgen. Het management-account moet al in fase 1
bestaan. Daarnaast zijn de drie vaststellingen de enige stappen in dit hele
traject waarop de CISO op iemand anders wacht. Die vaststellingen worden daarom
vooruit gepland in plaats van pas op het moment zelf geregeld.
