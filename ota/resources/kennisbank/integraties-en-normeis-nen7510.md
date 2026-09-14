# Integraties: welke norm-eis onderbouwt het register?

Op `/integraties` wordt bijgehouden welke externe koppelingen er zijn
(identiteitsbron, ticketing, scanning, overig), of ze actief zijn en of de laatste
synchronisatie is gelukt. Een veelgestelde vraag is of dat register een
**norm-eis** is.

## Kort antwoord

**NEN 7510 stelt het register niet als losstaande eis, maar in de zorg ligt het
genuanceerder dan bij ISO 27001.** In de hoofdtekst (clausules 4–10) staat nergens
"houd een register van integraties bij". Het register is een **beheersmaatregel**
die de organisatie kiest om een aantal maatregelen uit Bijlage A aantoonbaar te
maken.

Het verschil met ISO zit in de omliggende normen. In de zorg bestaan
**zelfstandige normen die wel specifiek over koppelvlakken gaan**. NEN 7512 stelt
eisen aan het vertrouwensniveau van elektronische gegevensuitwisseling tussen
zorgpartijen, en NEN 7513 stelt eisen aan het loggen van toegang tot
cliëntdossiers. Een zorgorganisatie met gegevensuitwisseling heeft dus vaak wel
een harde eis. Die eis komt alleen niet uit NEN 7510 en niet uit dit register.

De redenering die een auditor wil horen, is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Bijlage A) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid en een asset. Zonder overzicht is de inventaris incompleet. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in- en uitgangen die de organisatie moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Ook hier geldt dat alleen een bekende koppeling te beheersen en te monitoren is. |
| **A.5.14** Informatietransport | Een koppeling is een transportkanaal. De organisatie moet weten welke data via welke interface stroomt. Dit is een van de maatregelen met een zorgspecifieke aanvulling. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier. De koppeling maakt die datastroom zichtbaar. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen onder deze control. |
| **A.5.42** Communicatie in noodsituaties | Dit is een zorgspecifieke maatregel. Als de reguliere kanalen uitvallen, moet de organisatie weten welke koppelingen er zijn en wat het alternatief is. Een actueel register is daarvoor de voorwaarde. |

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

## Wat dit register niet is: een zorgkoppelvlak

Dit is de belangrijkste afbakening in dit artikel. Een vergissing op dit punt
heeft de grootste gevolgen.

**Dit ISMS is zelf geen schakel in de zorgketen.** Het systeem wisselt geen
patiëntgegevens uit, is geen XIS en maakt geen deel uit van een
uitwisselingssysteem. De koppelingen in dit register zijn koppelingen van het
*managementsysteem*: een identiteitsbron, een ticketsysteem, een scanner.

Daaruit volgt:

- **NEN 7512** gaat over de vertrouwensbasis van gegevensuitwisseling tussen
  zorgaanbieders: welk betrouwbaarheidsniveau een uitwisseling nodig heeft en hoe
  partijen elkaar authenticeren. Die norm is van toepassing op het EPD en het
  uitwisselingsplatform van de organisatie, en niet op dit register. Een
  organisatie met zo'n uitwisseling heeft daarvoor een **eigen normtraject**, dat
  in dit systeem hoogstens als asset en als SoA-motivatie terugkomt.
- **NEN 7513** gaat over het logboek van toegang tot **cliëntdossiers**. De audit
  trail in dit systeem legt ISMS-mutaties vast, zoals wie een risico wijzigde en
  wie een incident sloot. Die audit trail is nadrukkelijk geen 7513-logging. Zie
  [De audit trail](/kennisbank/de-audit-trail) voor wat er wel in staat.

Wie deze twee normen op de SoA afdoet met een verwijzing naar dit register,
onderbouwt een zorgeis met bewijs uit een ander domein. Dat is precies het soort
gat dat bij een audit opvalt.

Zie ook [Wat NEN 7510 toevoegt bovenop ISO
27001](/kennisbank/wat-nen-7510-toevoegt) voor de volledige afbakening tussen dit
platform en de zorgsystemen van de organisatie.

## Wat de onderbouwing sterker maakt

Een losse lijst is zwakker bewijs dan een lijst die met andere gegevens verbonden
is:

- **Koppeling aan een leverancier.** Een integratie die aan een leverancier is
  gekoppeld, staat niet los, maar is onderdeel van het leveranciersdossier. Zo'n
  integratie versterkt A.5.19–A.5.22 in plaats van alleen A.5.9. *(Deze koppeling
  zit nog niet in de module. Het is de logische volgende stap als het register als
  serieus bewijs moet dienen.)*
- **Koppeling aan het asset- en systeemregister.** Die koppeling laat zien welke
  integratie welk systeem raakt, en daarmee welke classificatie van toepassing is.
  Als een systeem persoonlijke gezondheidsinformatie raakt, is dat op het asset
  vastgelegd. Dat gegeven werkt via de koppeling door naar de integratie.
- **Actualiteit.** Een auditor let vooral op **volledigheid en actualiteit**. Een
  register dat achterloopt op de werkelijkheid, is op zichzelf een bevinding. Een
  `fout`-sync die maanden blijft staan, is een signaal dat om actie vraagt.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar een goed te
verantwoorden maatregel onder A.5.9, A.8.20/21, A.5.14, A.5.42 en (mits gekoppeld
aan leveranciers) A.5.19–A.5.23. De waarde van het register zit in **volledigheid,
actualiteit en de audit trail**. Daarmee toont de organisatie aan dat zij weet
welke externe koppelingen er zijn en wie ze beheert. Het register is geen bewijs
onder NEN 7512 of NEN 7513.
