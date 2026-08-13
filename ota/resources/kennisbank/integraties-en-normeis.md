# Integraties: welke norm-eis onderbouw je ermee?

In `/integraties` houd je bij wélke externe koppelingen er zijn (identiteitsbron,
ticketing, scanning, overig), of ze actief zijn en of de laatste synchronisatie
lukte. Veelgestelde vraag: **is dat een norm-eis?**

## Kort antwoord

**Nee, niet als losstaande eis.** In de hoofdtekst van ISO/IEC 27001 (clausules
4–10) staat nergens "houd een register van integraties bij". Het is een
**beheersmaatregel** die je kiest om een aantal Annex A-controls aantoonbaar te
maken. Het register is dus geen doel op zich — het is **bewijs** onder controls
die je toch al van toepassing verklaart.

De redenering die een auditor wil horen is dezelfde als bij elke "ja" in de SoA
(zie *De SoA onderbouwen: van 'ja' tot restrisico*):

> driver (de control) → realisatie (het integratieregister) → bewijs (de vulling)

## Welke controls het onderbouwt

| Control (Annex A, 2022) | Waarom het register helpt |
|---|---|
| **A.5.9** Inventarisatie van informatie en bijbehorende assets | Een koppeling naar een externe partij is een afhankelijkheid/asset; zonder overzicht is je inventaris incompleet. |
| **A.8.21** Beveiliging van netwerkdiensten | Externe koppelingen zijn de in-/uitgangen die je moet kennen en beheersen. |
| **A.8.20** Netwerkbeveiliging | Idem: je kunt alleen beheersen en monitoren wat je kent. |
| **A.5.14** Informatietransport | Een koppeling ís een transportkanaal; je moet weten via welke interface welke data stroomt. |
| **A.5.19–A.5.22** Leveranciersrelaties | Een integratie hangt vrijwel altijd aan een leverancier; de koppeling maakt die datastroom zichtbaar. |
| **A.5.23** Beveiliging bij gebruik van clouddiensten | Koppelingen naar SaaS vallen hieronder. |

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

## Wat de onderbouwing sterker maakt

Een kale lijst is zwakker bewijs dan een lijst die ergens aan hangt:

- **Koppel de integratie aan een leverancier** (blok 9, `/leveranciers`). Dan is
  een koppeling niet los, maar onderdeel van het leveranciersdossier — en versterkt
  hij A.5.19–A.5.22 in plaats van alleen A.5.9. *(Deze koppeling zit nog niet in de
  module; het is de logische volgende stap als je dit als serieus bewijs wilt
  inzetten.)*
- **Koppel aan het asset-/systeemregister** (blok 3): welke koppeling raakt welk
  systeem, en dus welke classificatie.
- **Houd het actueel.** Een auditor let vooral op **volledigheid en actualiteit**:
  een register dat achterloopt op de werkelijkheid is een bevinding op zichzelf.
  Een `fout`-sync die maanden blijft staan is een signaal, geen decoratie.

## Samengevat

Het integratieregister is **geen zelfstandige norm-eis**, maar een goed te
verantwoorden maatregel onder A.5.9, A.8.20/21, A.5.14 en (mits gekoppeld aan
leveranciers) A.5.19–A.5.23. De waarde zit in **volledigheid, actualiteit en de
audit trail** — dat je aantoonbaar wéét welke externe koppelingen er zijn en wie
ze beheert.
