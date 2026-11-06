# Assistenttoegang via MCP

Een gebruiker kan een AI-assistent aan het ISMS koppelen, zoals Claude Code,
claude.ai of een andere assistent die het Model Context Protocol (MCP) spreekt. De
assistent kan daarna namens die gebruiker vragen stellen aan het ISMS. Samen met
koppelingen naar andere bronnen, zoals een ticketsysteem, een IAM-omgeving of een
kwetsbaarheidsscanner, kan de assistent cijfers uit het ISMS naast cijfers uit de
praktijk leggen. Dit artikel beschrijft wat de assistent kan, wie mag koppelen, hoe
een koppeling tot stand komt en wat er wordt vastgelegd.

De functie staat standaard uit. Zolang de beheerder hem niet aanzet, bestaat het
adres voor assistenten niet en verandert er in de schermen niets.

## Wat de assistent wel en niet kan

| Onderwerp | Hoe het werkt |
| --- | --- |
| Lezen | De assistent ziet precies wat de gebruiker zelf in het ISMS ziet, inclusief namen, bijlagen en bewijsstukken. |
| Wijzigen | Niet mogelijk. Elke opvraging is alleen-lezen. |
| Rechten | Dezelfde autorisatiecheck als in de schermen, bij elke opvraging opnieuw. Een gewijzigde rol geldt bij de volgende vraag. |
| Inloggen | Via het gewone loginscherm, met tweefactor of de identiteitsprovider. Daarna geeft de gebruiker toestemming. |
| Het AI-model | Draait bij de aanbieder die de gebruiker met de assistent gebruikt. Het ISMS roept zelf geen AI-dienst aan en bewaart geen sleutel voor een AI-dienst. |
| Vastleggen | Elke opvraging komt in een eigen aanroeplog, met gebruiker, assistent en tijdstip. |

Omdat de assistent alleen leest, blijft elk besluit in het ISMS bij een mens. De
assistent kan een analyse of een voorstel maken. Het vastleggen daarvan gebeurt in
de schermen, door iemand met de rechten daarvoor.

## Wie mag koppelen

Na het aanzetten mag nog geen enkele rol een assistent koppelen, ook de CISO niet.
De CISO bepaalt per rol wie het mag, op **Notificatie & Integratie →
Assistenttoegang** (`/assistenttoegang`). Dat is een besluit over een gegevensstroom
naar een externe dienst en daarmee een besluit binnen het ISMS. Het staat daarom
bij de CISO en niet bij de Administrator. De Administrator staat niet in de lijst,
omdat die rol geen ISMS-rechten heeft.

Elke wijziging in de rechtenmatrix komt in de audit trail. Wordt een rol
uitgezet, dan trekt het systeem de bestaande koppelingen in van iedereen die
daardoor geen recht meer heeft. Een auditor kan het scherm lezen, maar niets
wijzigen.

## Een assistent koppelen

Het adres om te koppelen staat bij de instellingen van de gebruiker onder
**Assistenten** en heeft de vorm `https://<adres van het ISMS>/mcp`.

- **Claude Code.** Het commando is
  `claude mcp add --transport http isms https://<adres van het ISMS>/mcp`. Daarna
  start `/mcp` in Claude Code de aanmelding in de browser.
- **claude.ai en Claude Desktop.** Onder Instellingen → Connectoren wordt een
  aangepaste connector toegevoegd met hetzelfde adres.
- **Andere assistenten.** Elke MCP-client die OAuth met dynamische
  clientregistratie ondersteunt, werkt op dezelfde manier.

Bij het koppelen opent de browser het loginscherm van het ISMS. Na het inloggen,
met de tweede factor als die verplicht is, volgt het scherm **Assistent koppelen**.
Dat scherm toont de naam van de assistent, het adres waarnaar de code teruggaat en
het account waarmee de gebruiker is ingelogd. De naam kiest de assistent zelf bij
het registreren, dus die zegt weinig. Het adres zegt meer: bij Claude Code is dat
`localhost`, bij claude.ai is dat `claude.ai`.

Een gebruiker wiens rol niet mag koppelen, ziet het scherm **Geen
assistenttoegang** en krijgt geen code.

Een toegangstoken is 60 minuten geldig. De assistent vernieuwt het zelf met een
verversingstoken dat 30 dagen geldig is. Na 30 dagen zonder gebruik moet de
gebruiker opnieuw inloggen en toestemming geven.

## Wat de assistent kan opvragen

De assistent ziet alleen de onderdelen waar de gebruiker recht op heeft. Een
onderdeel zonder leesrecht staat niet in de lijst. Waar een opvraging meerdere
onderdelen raakt, meldt de uitvoer onder `weggelaten` wat ontbreekt en waarom.
Daardoor leest een model een ontbrekend onderdeel niet als "er is niets".

| Opvraging | Wat het teruggeeft |
| --- | --- |
| `isms-overzicht` | De stand van zaken: normprofiel, risico's, implementatie van maatregelen, KPI's met hun status, openstaande incidenten en eigen taken. |
| `zoek` | Treffers op vrije tekst in de registers, met type, titel, status en een link naar het scherm. |
| `maatregelstatus` | De stand in de Verklaring van Toepasselijkheid per maatregel, met gekoppeld bewijs en de ouderdom daarvan. |
| `risicos` | Het risicoregister met kans, impact, score en behandeling. |
| `kpi-reeks` | KPI-definities en hun meetpunten over een periode, met streef- en signaalwaarde en de definitieversie. |
| `incidenten` | Incidenten en afwijkingen met termijnen, meldplicht en corrigerende maatregelen. |
| `open-acties` | Taken, corrigerende maatregelen, verbeteracties en herzieningen die openstaan of over termijn zijn. |
| `leveranciers` | Leveranciers met risiconiveau, certificering, beoordelingen en contractclausules. |
| `audit-dekking` | Het auditprogramma, de dekkingsmatrix en de bevindingen per auditronde. |
| `training-en-bewustzijn` | Trainingsmodules, voltooiing en leesbevestigingen, zoals de schermen die aan de gebruiker tonen. |
| `bewijs` | De metadata van bewijsstukken: naam, type, datum, ouderdom en koppelingen. |
| `beleid` | Beleidsdocumenten met de geldende versie en de herzieningsdatum. |

Elk resultaat bevat een link naar het scherm in het ISMS. Een antwoord van de
assistent hoort naar dat scherm te verwijzen in plaats van de inhoud te
parafraseren.

De inhoud van bestanden is apart op te halen: een bewijsstuk via
`isms://bewijs/<id>` en een beleidsversie via `isms://beleid/<document>/versie/<nummer>`.
Daarvoor gelden dezelfde regels als voor een download in de schermen. Twee
controles komen erbij:

- het bestand moet nog overeenkomen met de hash die bij het uploaden is
  vastgelegd, anders levert het systeem het niet uit;
- een bestand groter dan 10 MB gaat niet mee; de assistent krijgt dan de link om
  het in het ISMS te openen.

Een bestand ophalen telt als raadpleging, met het kanaal *assistent*. Bij de
leesbevestiging van beleid telt die raadpleging niet als zelf gelezen. Een
medewerker die een beleidsstuk alleen via een samenvatting van een assistent kent,
blijft in het overzicht staan als iemand die heeft bevestigd zonder het document
te openen.

Normteksten van ISO, NEN of de BIO komen niet mee, behalve wat de installatie zelf
al bevat.

## Waar de gegevens heen gaan

Wat de assistent opvraagt, gaat van het ISMS naar de assistent en van daar naar
het AI-model bij de aanbieder die de gebruiker met die assistent gebruikt. Die
laatste stap valt buiten het ISMS. De aanbieder is daarmee een verwerker van de
organisatie, niet van de leverancier van het ISMS.

Omdat de assistent alles ziet wat de gebruiker ziet, kunnen er persoonsgegevens
meegaan: namen, e-mailadressen en rollen, trainingsresultaten, betrokkenen bij
incidenten en de inhoud van bewijsstukken. De organisatie neemt dat op in haar
verwerkingsregister en, waar nodig, in haar DPIA. De volgende tekst kan daarvoor
als uitgangspunt dienen:

> Gebruikers met een daartoe aangewezen rol kunnen een AI-assistent aan het ISMS
> koppelen. De assistent leest namens de gebruiker wat die in het ISMS mag zien,
> waaronder namen en e-mailadressen van medewerkers, trainingsresultaten,
> betrokkenen bij incidenten en de inhoud van bewijsstukken. De gegevens worden
> verwerkt door de aanbieder van het AI-model die de organisatie voor deze
> assistent heeft goedgekeurd. De CISO bepaalt per rol wie mag koppelen. Elke
> opvraging wordt met gebruiker, assistent en tijdstip vastgelegd.

Organisaties die gegevens niet naar een aanbieder buiten de EU mogen sturen,
kunnen een assistent met een model binnen de eigen omgeving gebruiken. MCP is een
open protocol en de koppeling is niet gebonden aan één aanbieder.

## Prompt-injectie en de combinatie met andere koppelingen

Een geüpload bestand of een omschrijving kan tekst bevatten die een model als
opdracht leest, bijvoorbeeld een regel die vraagt om het risicoregister naar een
extern adres te sturen. Het ISMS kan dat niet voorkomen, maar beperkt het effect:

- het ISMS zelf is alleen-lezen, dus via deze koppeling verandert er niets en gaat
  er niets naar buiten;
- de instructies van de server en een kop boven elk tekstbestand melden dat
  inhoud uit het ISMS gegevens zijn en geen opdracht.

Het risico ontstaat in de combinatie. Een assistent met toegang tot vertrouwelijke
gegevens, die ook onbetrouwbare tekst leest en daarnaast een koppeling heeft die
kan schrijven of versturen, kan gegevens naar buiten brengen zonder dat de
gebruiker dat wil. Het advies is daarom om de ISMS-koppeling niet te combineren
met koppelingen die zonder bevestiging e-mail versturen, bestanden delen of naar
een openbare plek schrijven. De organisatie legt dit risico vast in haar eigen
risicoregister, met de maatregelen uit dit artikel als beheersing.

## Intrekken

Een koppeling kan op drie manieren worden ingetrokken:

- **door de gebruiker zelf**, bij de instellingen onder **Assistenten**;
- **door de CISO**, op `/assistenttoegang` onder *Gekoppelde assistenten*;
- **automatisch**, als het account wordt geblokkeerd of gedeactiveerd, of als de
  rol het recht verliest.

Na het intrekken werkt het toegangstoken niet meer en kan de assistent ook geen
nieuw token ophalen. De assistent moet opnieuw toestemming vragen. Los daarvan
toetst het systeem bij elke opvraging of het account actief is en de rol mag
koppelen. Een geblokkeerd account komt dus ook niet binnen met een token dat nog
niet is ingetrokken.

## Wat er wordt vastgelegd

| Wat | Waar |
| --- | --- |
| Elke opvraging van een tool of bestand | De aanroeplog op `/assistenttoegang`, met gebruiker, assistent, argumenten, aantal resultaten en uitkomst. |
| Het ophalen van een bestand | Daarnaast als raadpleging bij het bewijsstuk, met het kanaal *assistent*. |
| Een rol aan- of uitzetten | De audit trail, als wijziging in de rechtenmatrix. |
| Het intrekken van een koppeling | De audit trail, bij de gebruiker. |

Een geweigerde opvraging wordt ook vastgelegd. De gebruiker is dan al aangemeld,
en een weigering zegt iets over wat een assistent probeerde. De aanroeplog wordt
365 dagen bewaard. Het nachtelijke commando `isms:assistent-opruimen` verwijdert
oudere regels en legt in de audit trail vast dat de bewaartermijn is gehandhaafd.

## Voor de beheerder

De functie staat in de omgeving van de installatie en niet in een scherm.

| Variabele | Betekenis |
| --- | --- |
| `ISMS_ASSISTENT` | `true` zet de assistenttoegang aan. Standaard `false`. |
| `ISMS_ASSISTENT_REDIRECTS` | De adressen (schema en host) waarnaar een assistent na het inloggen mag terugsturen, gescheiden door komma's. Standaard `https://claude.ai`. Lokale assistenten (`localhost`) mogen altijd. |
| `ISMS_ASSISTENT_LIMIET` | Het maximale aantal opvragingen per gebruiker per minuut. Standaard 120. |

Na het wijzigen van `ISMS_ASSISTENT` is een herstart nodig, omdat de routes bij
het starten worden vastgelegd. Bij Docker is dat `docker compose up -d`.

**Sleutels.** De tokens worden getekend met een sleutelpaar. In de Docker-stack
maakt de container dat paar bij de eerste start aan in `storage/oauth` op de
gegevensmap. Bij een installatie op een eigen server maakt
`php artisan passport:keys` het paar aan in `storage/`. Het sleutelpaar hoort bij
de back-up. Een nieuw paar maakt alle bestaande koppelingen in één keer ongeldig.

**Uitzetten.** Na het uitzetten bestaan de adressen niet meer. Oude tokens staan
dan nog wel in de database en zouden bij opnieuw aanzetten weer werken. Daarom
hoort bij het uitzetten:

```bash
php artisan isms:assistent-opruimen --alles
```

Dat commando trekt alle koppelingen in en verwijdert alle geregistreerde
assistenten.

**Bereikbaarheid.** Claude Code verbindt vanaf de computer van de gebruiker.
Connectoren van claude.ai en Claude Desktop verbinden vanuit de infrastructuur van
de aanbieder. Een installatie die alleen bereikbaar is vanaf het kantoornetwerk of
via een VPN, werkt dus wel met Claude Code, maar niet met zo'n connector, tenzij de
adresbereiken van de aanbieder worden toegelaten. De limiet op het aantal
opvragingen geldt per gebruiker en niet per adres, omdat zo'n connector zijn
adressen deelt met andere klanten van de aanbieder.

**Het integratieregister.** Bij elke uitrol werkt het systeem de regel
*Assistenttoegang (MCP)* in het integratieregister bij: actief als de functie aan
staat, inactief als hij weer uit is gezet.

## Voorbeeldvragen

Met alleen het ISMS:

- Welke KPI's staan op aandacht of slecht, en wat is de trend over het laatste jaar?
- Welke maatregelen zijn geïmplementeerd verklaard maar hebben al meer dan een jaar
  geen nieuw bewijs?
- Welke bevindingen uit de laatste interne audit zijn nog niet afgehandeld?
- Stel de input voor de directiebeoordeling samen uit incidenten, auditbevindingen
  en KPI-trends, met links naar de schermen.

In combinatie met andere bronnen:

- Liggen de patchtermijnen uit het ticketsysteem in lijn met wat de Verklaring van
  Toepasselijkheid bij 8.8 beweert?
- Welke accounts in de identiteitsprovider hebben geen afgeronde
  bewustzijnstraining in het ISMS?
- Komt het aantal kritieke bevindingen uit de kwetsbaarheidsscanner overeen met
  het risiconiveau in het risicoregister?

Een KPI die de assistent zo opbouwt, staat nog niet in het ISMS. De meethistorie
ontstaat pas als iemand de waarde in het ISMS vastlegt, met de herkomst erbij.
