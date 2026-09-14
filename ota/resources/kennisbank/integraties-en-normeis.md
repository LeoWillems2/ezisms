# Integraties: welke norm-eis onderbouwt het register?

Op `/integraties` wordt bijgehouden welke externe koppelingen er zijn
(identiteitsbron, ticketing, scanning, overig), of ze actief zijn en of de laatste
synchronisatie is gelukt. Een veelgestelde vraag is of dat register een
**norm-eis** is.

## Kort antwoord

**Het register is geen losstaande eis.** In de hoofdtekst van ISO/IEC 27001
(clausules 4–10) staat nergens "houd een register van integraties bij". Het
register is een **beheersmaatregel** die de organisatie kiest om een aantal
Annex A-controls aantoonbaar te maken. Het register is dus geen doel op zich, maar
**bewijs** onder controls die de organisatie al van toepassing heeft verklaard.

De redenering die een auditor wil horen, is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Annex A, 2022) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid en een asset. Zonder overzicht is de inventaris incompleet. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in- en uitgangen die de organisatie moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Ook hier geldt dat alleen een bekende koppeling te beheersen en te monitoren is. |
| **A.5.14** Informatietransport | Een koppeling is een transportkanaal. De organisatie moet weten welke data via welke interface stroomt. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier. De koppeling maakt die datastroom zichtbaar. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen onder deze control. |

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
- **Actualiteit.** Een auditor let vooral op **volledigheid en actualiteit**. Een
  register dat achterloopt op de werkelijkheid, is op zichzelf een bevinding. Een
  `fout`-sync die maanden blijft staan, is een signaal dat om actie vraagt.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar een goed te
verantwoorden maatregel onder A.5.9, A.8.20/21, A.5.14 en (mits gekoppeld aan
leveranciers) A.5.19–A.5.23. De waarde van het register zit in **volledigheid,
actualiteit en de audit trail**. Daarmee toont de organisatie aan dat zij weet
welke externe koppelingen er zijn en wie ze beheert.
