# Integraties: welke norm-eis onderbouwt het register?

Op `/integraties` wordt bijgehouden welke externe koppelingen er zijn
(identiteitsbron, ticketing, scanning, overig), of ze actief zijn en of de laatste
synchronisatie is gelukt. Een veelgestelde vraag is of dat register een
**norm-eis** is.

## Kort antwoord

**Onder de BIO weegt het register zwaarder dan onder ISO 27001. Dat komt door twee
factoren: de keten en de verplichte beveiligingsstandaarden.**

In de hoofdtekst van de norm (clausules 4–10) staat nergens "houd een register van
integraties bij". Het register is een **beheersmaatregel** die de organisatie
kiest om een aantal maatregelen uit Bijlage A aantoonbaar te maken. Tot dit punt
is de BIO-uitvoering gelijk aan de ISO-uitvoering.

De BIO voegt concrete eisen toe. Overheidsmaatregel 5.01.01 vraagt om het
vastleggen van *"de verantwoordelijkheden en samenhang van informatiebeveiliging
voor ketens van informatiesystemen"*. Die samenhang is niet vast te leggen zonder
kennis van de bestaande koppelingen. Daarnaast verplicht de BIO bij
beheersmaatregel 5.14 (informatietransport) de standaarden van het **Forum
Standaardisatie** voor internetfacing systemen en e-mail, met de metingen van
**internet.nl** als stuurmiddel.

De redenering die een auditor wil horen, is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Bijlage A) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid en een asset. Zonder overzicht is de inventaris incompleet. |
| **A.5.1** Beleidsregels voor informatiebeveiliging | De BIO vraagt hier expliciet om de ketenverantwoordelijkheid te beleggen. Zonder ketenoverzicht is die verantwoordelijkheid een lege belofte. |
| **A.5.14** Informatietransport | Een koppeling is een transportkanaal. Aan deze maatregel hangen de verplichte beveiligingsstandaarden. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in- en uitgangen die de organisatie moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Ook hier geldt dat alleen een bekende koppeling te beheersen en te monitoren is. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier. De koppeling maakt die datastroom zichtbaar. Deel 1 §13 stelt hier bovendien inkoopeisen. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen onder deze control. |
| **A.5.30** ICT-gereedheid voor bedrijfscontinuïteit | De BIO vraagt hier om de identificatie van kritieke systemen. Een keten die niet bekend is, kan niet als kritiek worden aangemerkt. |

Geen van deze controls noemt letterlijk een "integratieregister". Samen maken ze
het register wel tot een verdedigbare en nuttige maatregel.

## Wat de module vastlegt

Per koppeling worden de volgende gegevens geregistreerd:

- **`naam`**: de naam van de koppeling.
- **`type`**: `identiteit` (bijvoorbeeld een SSO- of gebruikersbron), `ticketing`,
  `scanning` (bijvoorbeeld kwetsbaarheidsscans) of `overig`.
- **`status`**: `niet_geconfigureerd → actief → inactief`. Een adapter gaat naar
  `actief` zodra de eerste synchronisatie slaagt.
- **synchronisatie-resultaten**: per synchronisatie worden `succes` of `fout` en
  het aantal verwerkte records vastgelegd. De adapter onthoudt het tijdstip van de
  **laatste sync**.

Het aanmaken, aanzetten of uitzetten van een koppeling is een **bestuurlijke daad**
en staat daarom in de **audit trail** (de adapter is `Auditeerbaar`). Bij een audit
is daardoor aan te tonen wie wanneer welke koppeling heeft aangezet.

> **Let op: dit register vervangt de sync-motor niet.** Er draait geen echte
> koppeling. De CISO legt adapters en sync-resultaten **handmatig** vast. Het
> register bewijst dus dat de organisatie de koppelingen kent en beheerst, en niet
> dat er technisch data stroomt.

## Wat dit register niet is: de meting van de standaarden

Dit is de belangrijkste afbakening in dit artikel. Een vergissing op dit punt
heeft de grootste gevolgen.

De BIO verplicht bij beheersmaatregel 5.14 dat internetfacing systemen en
e-mailverkeer blijven voldoen aan de standaarden van het Forum Standaardisatie, en
noemt internet.nl als stuurmiddel. Dat is een **meetbare, externe eis**, en dit
register meet niets.

Daaruit volgt:

- **Een internet.nl-score is bewijs, dit register niet.** De meting wordt als
  bewijsstuk aan de SoA-regel voor A.5.14 gehangen of als KPI vastgelegd. Een
  verwijzing naar dit register onderbouwt een meetbare eis met een inventaris, en
  dat gat valt bij een audit direct op.
- **Het gaat over de publieke systemen van de organisatie, niet over dit ISMS.** De
  koppelingen in dit register zijn die van het *managementsysteem*: een
  identiteitsbron, een ticketsysteem, een scanner. Het burgerportaal, de
  e-mailomgeving en de DNS-configuratie van de organisatie staan niet in dit
  register en horen er ook niet in.
- **De keten is breder dan dit register.** Overheidsmaatregel 5.01.01 gaat over
  ketens van informatiesystemen in de hele organisatie. Dit register dekt daarvan
  een deel. Het asset- en systeemregister (blok 2) is de plek waar de keten
  volledig hoort te staan.

Zie ook [Wat de BIO toevoegt bovenop ISO 27001](/kennisbank/wat-de-bio-toevoegt)
voor de bredere afbakening tussen dit platform en de eigen systemen van de
organisatie.

## Wat de onderbouwing sterker maakt

Een losse lijst is zwakker bewijs dan een lijst die met andere gegevens verbonden
is:

- **Koppeling aan een leverancier.** Een integratie die aan een leverancier is
  gekoppeld, staat niet los, maar is onderdeel van het leveranciersdossier. Zo'n
  integratie versterkt A.5.19–A.5.22 in plaats van alleen A.5.9. Onder de BIO
  weegt die koppeling dubbel, omdat deel 1 §13 inkoopeisen stelt aan precies die
  relatie. *(Deze koppeling zit nog niet in de module. Het is de logische volgende
  stap als het register als serieus bewijs moet dienen.)*
- **Koppeling aan het asset- en systeemregister.** Die koppeling laat zien welke
  integratie welk systeem raakt, en daarmee welke classificatie van toepassing is.
  Als een systeem onder A.5.30 als kritiek is aangemerkt, werkt dat via de
  koppeling door.
- **Actualiteit.** Een auditor let vooral op **volledigheid en actualiteit**. Een
  register dat achterloopt op de werkelijkheid, is op zichzelf een bevinding. Een
  `fout`-sync die maanden blijft staan, is een signaal dat om actie vraagt.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar onder de BIO wel een
sterker te verantwoorden maatregel dan onder ISO. Het register onderbouwt A.5.9,
A.5.1 (ketenverantwoordelijkheid), A.5.14, A.8.20/21, A.5.30 en, mits gekoppeld
aan leveranciers, A.5.19–A.5.23. De waarde zit in **volledigheid, actualiteit en
de audit trail**. Het register is geen bewijs dat de organisatie aan de verplichte
beveiligingsstandaarden voldoet. Dat bewijs komt van internet.nl.
