# Integraties: welke norm-eis onderbouw je ermee?

In `/integraties` houd je bij wélke externe koppelingen er zijn (identiteitsbron,
ticketing, scanning, overig), of ze actief zijn en of de laatste synchronisatie
lukte. Veelgestelde vraag: **is dat een norm-eis?**

## Kort antwoord

**Onder de BIO harder dan onder ISO 27001, en dat komt door twee dingen: de keten
en de verplichte beveiligingsstandaarden.**

In de hoofdtekst van de norm (clausules 4–10) staat nergens "houd een register van
integraties bij". Het register is een **beheersmaatregel** die je kiest om een
aantal maatregelen uit Bijlage A aantoonbaar te maken. Zo ver is het gelijk aan de
ISO-uitvoering.

Wat de BIO erbij zet, is concreet. Overheidsmaatregel 5.01.01 vraagt om het
vastleggen van *"de verantwoordelijkheden en samenhang van informatiebeveiliging
voor ketens van informatiesystemen"* — en dat is niet vast te leggen als je niet
weet welke koppelingen er zijn. En bij beheersmaatregel 5.14 (informatietransport)
verplicht de BIO de standaarden van het **Forum Standaardisatie** voor
internetfacing systemen en e-mail, met de metingen van **internet.nl** als
stuurmiddel.

De redenering die een auditor wil horen is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Bijlage A) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid/asset; zonder overzicht is je inventaris incompleet. |
| **A.5.1** Beleidsregels voor informatiebeveiliging | De BIO vraagt hier expliciet de ketenverantwoordelijkheid te beleggen; zonder ketenoverzicht is dat een lege belofte. |
| **A.5.14** Informatietransport | Een koppeling ís een transportkanaal. Dit is de maatregel waar de verplichte beveiligingsstandaarden aan hangen. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in- en uitgangen die je moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Idem: je kunt alleen beheersen en monitoren wat je kent. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier; de koppeling maakt die datastroom zichtbaar. Deel 1 §13 stelt hier bovendien inkoopeisen. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen hieronder. |
| **A.5.30** ICT-gereedheid voor bedrijfscontinuïteit | De BIO vraagt hier om identificatie van kritieke systemen. Een keten die je niet kent, kun je niet als kritiek aanmerken. |

Geen van deze zegt letterlijk "integratieregister" — maar samen maken ze het tot
een verdedigbare, nuttige maatregel.

## Wat de module vastlegt

Per koppeling registreer je:

- **`naam`** — welke koppeling het is.
- **`type`** — `identiteit` (bijv. een SSO-/gebruikersbron), `ticketing`,
  `scanning` (bijv. kwetsbaarheidsscans) of `overig`.
- **`status`** — `niet_geconfigureerd → actief → inactief`. Een adapter springt op
  `actief` zodra de eerste synchronisatie slaagt.
- **synchronisatie-resultaten** — per sync leg je `succes`/`fout` en het aantal
  verwerkte records vast; de adapter onthoudt het tijdstip van de **laatste sync**.

Het aanmaken of aan-/uitzetten van een koppeling is een **bestuurlijke daad** en
staat daarom in de **audit trail** (de adapter is `Auditeerbaar`). Dat is precies
wat je bij een audit wilt kunnen tonen: wie zette wanneer welke koppeling aan.

> **Let op — dit register vervangt de sync-motor niet.** Er draait geen echte
> koppeling; de CISO legt adapters en sync-resultaten **handmatig** vast. Het
> register bewijst dus dat je de koppelingen *kent en beheerst*, niet dat er
> technisch data stroomt.

## Wat dit register niet is: de meting van je standaarden

Dit is de belangrijkste afbakening op deze pagina, en de duurste om verkeerd te
hebben.

De BIO verplicht bij beheersmaatregel 5.14 dat internetfacing systemen en
e-mailverkeer blijven voldoen aan de standaarden van het Forum Standaardisatie, en
noemt internet.nl als stuurmiddel. Dat is een **meetbare, externe eis** — en dit
register meet niets.

Daaruit volgt:

- **Een internet.nl-score is bewijs, dit register niet.** Hang de meting als
  bewijsstuk aan de SoA-regel voor A.5.14, of leg hem als KPI vast. Verwijs je
  daar naar dit register, dan onderbouw je een meetbare eis met een inventaris —
  en dat gat valt bij een audit meteen op.
- **Het gaat over jóuw publieke systemen, niet over dit ISMS.** De koppelingen die
  je hier registreert zijn die van je *managementsysteem*: een identiteitsbron, een
  ticketsysteem, een scanner. Je burgerportaal, je e-mailomgeving en je
  DNS-configuratie staan hier niet en horen hier niet.
- **De keten is breder dan dit register.** Overheidsmaatregel 5.01.01 gaat over
  ketens van informatiesystemen in je hele organisatie. Dit register dekt daar een
  hoek van; het asset- en systeemregister (blok 2) is de plek waar de keten echt
  hoort te staan.

Zie ook [Wat de BIO toevoegt bovenop ISO 27001](/kennisbank/wat-de-bio-toevoegt)
voor de bredere afbakening tussen dit platform en je eigen systemen.

## Wat de onderbouwing sterker maakt

Een kale lijst is zwakker bewijs dan een lijst die ergens aan hangt:

- **Koppel de integratie aan een leverancier.** Dan is een koppeling niet los, maar
  onderdeel van het leveranciersdossier — en versterkt hij A.5.19–A.5.22 in plaats
  van alleen A.5.9. Onder de BIO telt dat dubbel, want deel 1 §13 vraagt om
  inkoopeisen op precies die relatie. *(Deze koppeling zit nog niet in de module;
  het is de logische volgende stap als je dit als serieus bewijs wilt inzetten.)*
- **Koppel aan het asset-/systeemregister**: welke koppeling raakt welk systeem, en
  dus welke classificatie. Is een systeem als kritiek aangemerkt onder A.5.30, dan
  straalt dat via de koppeling door.
- **Houd het actueel.** Een auditor let vooral op **volledigheid en actualiteit**:
  een register dat achterloopt op de werkelijkheid is een bevinding op zichzelf.
  Een `fout`-sync die maanden blijft staan is een signaal, geen decoratie.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar onder de BIO wel een
sterker te verantwoorden maatregel dan onder ISO: het onderbouwt A.5.9, A.5.1
(ketenverantwoordelijkheid), A.5.14, A.8.20/21, A.5.30 en — mits gekoppeld aan
leveranciers — A.5.19–A.5.23. De waarde zit in **volledigheid, actualiteit en de
audit trail**. Wat het níét is: bewijs dat je aan de verplichte
beveiligingsstandaarden voldoet. Dat bewijs komt van internet.nl.
