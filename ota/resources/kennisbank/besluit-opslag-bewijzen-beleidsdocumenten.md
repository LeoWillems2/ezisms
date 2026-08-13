# Besluit t.a.v. opslag bewijzen en beleidsdocumenten

Wat is het effect als bij bewijzen en beleidsdocumenten ook een
URL mag worden opgenomen naar een document elders, zodat het bewijs zich niet in
het ISMS zelf bevindt.

## Opslag in het ISMS zelf:

De bewijskracht van dit ISMS leunt op vier dingen die je *alleen* hebt omdat het
bestand binnen het systeem zit:

1. **Integriteit** — bij upload wordt een SHA-256-hash vastgelegd;
   `integriteitIsIntact()` maakt "onveranderlijke opslag" aantoonbaar in plaats
   van een belofte.
2. **Beschikbaarheid & bewaartermijn** — `bewaren_tot` op een bestand dat jíj
   beheert; niet afhankelijk van andermans opschoonbeleid.
3. **Toegang loopt door de applicatie** — download én preview gaan via de Gate +
   record-scoping (`Bewijstoegang::magLezen`), nooit via een direct schijfpad.
4. **Elke raadpleging is geregistreerd** — na een *geslaagde* download/preview
   schrijft de app een append-only `Raadpleging`. Daarop draait een echte
   controle: "bevestigd zonder het document ooit opgehaald te hebben." De
   previewcontroller zegt het letterlijk — de preview openen *ontkracht* dat
   signaal.

Belangrijk detail: **beleidsdocumenten zíjn bewijsstukken** (een `Beleidsversie`
wijst naar een `bewijsstuk_id`). Eén beslissing over URL's raakt dus meteen je
goedgekeurde beleidsversies.

## Overwegingen

**Voordeel van URL's** Mensen willen niet dubbel opslaan.

**Nadeel "niet contained, dode link = slechte indruk" — nog onderschat.** Het is
niet alleen cosmetisch. Bij een externe URL verlies je de **hash**: je kunt niet
meer bewijzen dat het document áchter die link hetzelfde is als wat is
goedgekeurd/bevestigd. De inhoud kan stil veranderen, verplaatsen of achter een
tweede login verdwijnen. Een dode link tijdens de audit is het zichtbare
symptoom; het echte verlies is dat het record niet langer *onweerlegbaar* is
(A.5.33 / A.8.10: records beschermen tegen verlies en vervalsing, en
retrieveerbaar houden — een losse link doet niets daarvan).

**"Kan de klik geregistreerd worden zoals preview/download?" — technisch:
gedeeltelijk, en zwakker.** Nu klikt de gebruiker door *onze* controller, dus we
loggen server-side ná auth. Bij een externe URL navigeert de browser rechtstreeks
naar SharePoint; die request zien wij nooit. Het enige wat kan is een
**doorstuur-endpoint** (`/bewijs/{id}/ga-naar` -> log -> 302 naar de URL). Dat is
registreerbaar, maar het bewijst alleen "wij hebben de gebruiker naar de deur
gestuurd" — niet dat hij naar binnen ging en het las. Als de link daarna 404't,
zegt je log nog steeds "geraadpleegd". Erger: Sharepoint geeft geen 404 bij een niet bestaand document.
Je `Raadpleging` degradeert van
*toegangsbewijs* naar *kliklog*, en juist de controle "bevestigd zonder gelezen"
wordt daarmee betekenisloos. Voor een leesbewijs bij A.5.1 is dat precies de
verkeerde plek om in te leveren.

## Besluit

Het ISMS slaat documenten lokaal op. Er worden geen URL's voor beleidsdocumenten en bewijzen gebruikt.
