# Integraties: welke norm-eis onderbouw je ermee?

In `/integraties` houd je bij wélke externe koppelingen er zijn (identiteitsbron,
ticketing, scanning, overig), of ze actief zijn en of de laatste synchronisatie
lukte. Veelgestelde vraag: **is dat een norm-eis?**

## Kort antwoord

**Niet als losstaande eis in NEN 7510 zelf — maar in de zorg ligt het genuanceerder
dan bij ISO 27001.** In de hoofdtekst (clausules 4–10) staat nergens "houd een
register van integraties bij". Het register is een **beheersmaatregel** die je
kiest om een aantal maatregelen uit Bijlage A aantoonbaar te maken.

Het verschil met ISO zit ernaast: in de zorg bestaan er **zelfstandige normen die
wél specifiek over koppelvlakken gaan**. NEN 7512 stelt eisen aan het
vertrouwensniveau van elektronische gegevensuitwisseling tussen zorgpartijen, en
NEN 7513 aan het loggen van toegang tot cliëntdossiers. Wie in de zorg
gegevensuitwisseling heeft, heeft dus vaak wél een harde eis — alleen komt die
niet uit 7510 en niet uit dit register.

De redenering die een auditor wil horen is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Bijlage A) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid/asset; zonder overzicht is je inventaris incompleet. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in-/uitgangen die je moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Idem: je kunt alleen beheersen en monitoren wat je kent. |
| **A.5.14** Informatietransport | Een koppeling ís een transportkanaal; je moet weten via welke interface welke data stroomt. Dit is een van de maatregelen met een zorgspecifieke aanvulling. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier; de koppeling maakt die datastroom zichtbaar. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen hieronder. |
| **A.5.42** Communicatie in noodsituaties | Een zorgspecifieke maatregel: als de reguliere kanalen uitvallen, moet je weten wélke koppelingen er zijn en wat het alternatief is. Een actueel register is daar de voorwaarde voor. |

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

## Wat dit register niet is: een zorgkoppelvlak

Dit is de belangrijkste afbakening op deze pagina, en de duurste om verkeerd te
hebben.

**Dit ISMS is zelf geen schakel in de zorgketen.** Het wisselt geen
patiëntgegevens uit, het is geen XIS, en het staat niet in een
uitwisselingssysteem. De koppelingen die je hier registreert zijn koppelingen van
je *managementsysteem* — een identiteitsbron, een ticketsysteem, een scanner.

Daaruit volgt:

- **NEN 7512** gaat over de vertrouwensbasis van gegevensuitwisseling tússen
  zorgaanbieders: welk betrouwbaarheidsniveau een uitwisseling nodig heeft en hoe
  partijen elkaar authenticeren. Dat speelt bij je EPD en je uitwisselingsplatform,
  niet bij dit register. Heb je zo'n uitwisseling, dan is dat een **eigen
  norm-traject** dat je hier hoogstens als asset en als SoA-motivatie terugziet.
- **NEN 7513** gaat over het logboek van toegang tot **cliëntdossiers**. De audit
  trail in dit systeem legt ISMS-mutaties vast — wie een risico wijzigde, wie een
  incident sloot — en is nadrukkelijk geen 7513-logging. Zie
  [De audit trail](/kennisbank/de-audit-trail) voor wat er wél in staat.

Zet je die twee normen op de SoA weg met een verwijzing naar dít register, dan
onderbouw je een zorgeis met bewijs uit een ander domein. Dat is precies het soort
gat dat bij een audit opvalt.

Zie ook [Wat NEN 7510 toevoegt bovenop ISO
27001](/kennisbank/wat-nen-7510-toevoegt) voor de volledige afbakening tussen dit
platform en je zorgsystemen.

## Wat de onderbouwing sterker maakt

Een kale lijst is zwakker bewijs dan een lijst die ergens aan hangt:

- **Koppel de integratie aan een leverancier**. Dan is
  een koppeling niet los, maar onderdeel van het leveranciersdossier — en versterkt
  hij A.5.19–A.5.22 in plaats van alleen A.5.9. *(Deze koppeling zit nog niet in de
  module; het is de logische volgende stap als je dit als serieus bewijs wilt
  inzetten.)*
- **Koppel aan het asset-/systeemregister**: welke koppeling raakt welk
  systeem, en dus welke classificatie. Raakt een systeem persoonlijke
  gezondheidsinformatie, dan is dat op het asset vastgelegd en straalt het via de
  koppeling door naar de integratie.
- **Houd het actueel.** Een auditor let vooral op **volledigheid en actualiteit**:
  een register dat achterloopt op de werkelijkheid is een bevinding op zichzelf.
  Een `fout`-sync die maanden blijft staan is een signaal, geen decoratie.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar een goed te
verantwoorden maatregel onder A.5.9, A.8.20/21, A.5.14, A.5.42 en (mits gekoppeld
aan leveranciers) A.5.19–A.5.23. De waarde zit in **volledigheid, actualiteit en
de audit trail** — dat je aantoonbaar wéét welke externe koppelingen er zijn en
wie ze beheert. Wat het níét is: bewijs onder NEN 7512 of NEN 7513.
