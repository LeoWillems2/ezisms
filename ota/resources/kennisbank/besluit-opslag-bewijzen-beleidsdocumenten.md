# Besluit t.a.v. opslag bewijzen en beleidsdocumenten

Dit besluit gaat over de vraag wat het effect is als bij bewijzen en
beleidsdocumenten ook een URL naar een document elders mag worden opgenomen. In
dat geval bevindt het bewijs zich niet in het ISMS zelf.

## Opslag in het ISMS zelf:

De bewijskracht van dit ISMS steunt op vier eigenschappen die alleen bestaan
omdat het bestand binnen het systeem staat:

1. **Integriteit.** Bij het uploaden legt het systeem een SHA-256-hash vast. Met
   `integriteitIsIntact()` is "onveranderlijke opslag" aantoonbaar, in plaats van
   alleen een belofte.
2. **Beschikbaarheid en bewaartermijn.** Het veld `bewaren_tot` staat op een
   bestand dat de organisatie zelf beheert. De bewaartermijn is daardoor niet
   afhankelijk van het opschoonbeleid van een andere partij.
3. **Toegang loopt via de applicatie.** Zowel de download als de preview loopt via
   de autorisatiecheck en de record-scoping (`Bewijstoegang::magLezen`), en nooit
   via een direct schijfpad.
4. **Elke raadpleging wordt geregistreerd.** Na een geslaagde download of preview
   schrijft de applicatie een append-only `Raadpleging`. Op die registratie draait
   een inhoudelijke controle: "bevestigd zonder het document ooit opgehaald te
   hebben." De previewcontroller vermeldt letterlijk dat het openen van de preview
   dat signaal ontkracht.

Een belangrijk detail is dat **beleidsdocumenten ook bewijsstukken zijn**: een
`Beleidsversie` verwijst naar een `bewijsstuk_id`. Een besluit over URL's raakt
daardoor direct ook de goedgekeurde beleidsversies.

## Overwegingen

**Voordeel van URL's.** Gebruikers willen documenten niet dubbel opslaan.

**Nadeel: het document staat niet in het systeem, en een dode link maakt een
slechte indruk.** Dit nadeel wordt nog onderschat, want het is niet alleen
cosmetisch. Bij een externe URL gaat de **hash** verloren. Daardoor is niet meer
aan te tonen dat het document achter de link hetzelfde is als het document dat is
goedgekeurd of bevestigd. De inhoud kan ongemerkt veranderen, verplaatsen of
achter een tweede login verdwijnen. Een dode link tijdens de audit is het
zichtbare symptoom. Het werkelijke verlies is dat het record niet langer
onweerlegbaar is. A.5.33 gaat over het beschermen van records tegen
verlies en vervalsing en over het opvraagbaar houden ervan. Een losse link doet
geen van beide.

**Registratie van de klik is technisch gedeeltelijk mogelijk, maar zwakker dan
bij preview en download.** Nu loopt de gebruiker via de controller van het ISMS,
zodat de applicatie de raadpleging server-side na authenticatie registreert. Bij
een externe URL navigeert de browser rechtstreeks naar SharePoint, en dat verzoek
bereikt het ISMS nooit. De enige mogelijkheid is een **doorstuur-endpoint**
(`/bewijs/{id}/ga-naar` -> log -> 302 naar de URL). Dat endpoint is te
registreren, maar het bewijst alleen dat de applicatie de gebruiker naar het
document heeft doorgestuurd. Het bewijst niet dat de gebruiker het document heeft
geopend en gelezen. Als de link daarna een 404 oplevert, vermeldt de log toch
"geraadpleegd". Daarbij komt dat SharePoint geen 404 geeft bij een niet-bestaand
document. De `Raadpleging` degradeert daarmee van *toegangsbewijs* naar
*kliklog*, en juist de controle "bevestigd zonder gelezen" verliest daardoor haar
betekenis. Voor een leesbewijs bij A.5.1 is dat precies de verkeerde plek om
bewijskracht in te leveren.

## Besluit

Het ISMS slaat documenten lokaal op. Voor beleidsdocumenten en bewijzen worden geen URL's gebruikt.
