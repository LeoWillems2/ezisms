# Inloggen via een identiteitsprovider

Een installatie kan worden gekoppeld aan de identiteitsprovider van de
organisatie, zoals Microsoft Entra ID of Google Workspace. Gebruikers melden zich
dan daar aan in plaats van met een wachtwoord dat dit systeem beheert. Dit
artikel beschrijft wat daarvoor nodig is en hoe de koppeling wordt ingericht. Wat
er daarna in de schermen verandert, staat in
[Gebruikers, rollen en rechten](/kennisbank/gebruikers-rollen-en-rechten).

Zonder deze koppeling verandert er niets: het loginscherm toont geen extra knop
en het uitnodigen verloopt zoals het altijd deed.

## Wat de koppeling wel en niet doet

Het systeem gebruikt OpenID Connect, de authenticatielaag boven OAuth 2.0. De
aanmelding verhuist naar de identiteitsprovider; de rest van het toegangsmodel
blijft in dit systeem:

| Onderwerp | Waar het na de koppeling ligt |
| --- | --- |
| Het bewijs dat iemand is wie hij zegt | Bij de identiteitsprovider |
| Wie een account krijgt | In dit systeem: een account ontstaat alleen door een uitnodiging van de CISO |
| Welke rol en rechten dat account heeft | In dit systeem; groepen en rollen van de identiteitsprovider worden niet gelezen |
| Blokkeren en deactiveren | In dit systeem, en dat blijft doorslaggevend |
| Accounts intrekken bij uitdiensttreding | Bij beide: de provider sluit de aanmelding af, de CISO deactiveert het account |

De inlogmethode wordt per account gekozen. Dat is nodig, omdat niet iedereen een
account bij de identiteitsprovider heeft: een ingehuurde auditor, het eerste
CISO-account uit `isms:eerste-ciso` en soms de Administrator loggen in met een
wachtwoord.

## Wat er vooraf geregeld moet worden

1. **Een app-registratie** bij de identiteitsprovider, met de redirect-URI van
   deze installatie. Die URI is `https://<adres van het ISMS>/auth/extern/callback`
   en wordt afgedrukt door het controlecommando hieronder. Het adres moet exact
   overeenkomen, inclusief `https`.
2. **Een client-id en een client secret** uit die registratie. Een secret in
   Entra ID verloopt na maximaal 24 maanden; noteer die datum.
3. **Uitgaand HTTPS-verkeer** vanaf de server naar de identiteitsprovider. Het
   systeem haalt daar het discovery-document, de sleutelset en het token op. Een
   installatie in een afgeschermd netwerk zonder uitgaand verkeer kan deze
   koppeling niet gebruiken.

Dat derde punt is de meest voorkomende oorzaak als het controlecommando hieronder
meldt dat de identiteitsprovider niet bereikbaar is. Het ISMS kan daar niets aan
doen: het ligt aan het netwerk, de firewall, een verplichte proxy of het DNS van
de omgeving waarin de installatie draait. Draait de installatie met Docker, dan
gaat het om uitgaand verkeer vanuit de container, niet vanaf de hostmachine.

De scopes die het systeem vraagt zijn `openid`, `email` en `profile`. Meer is
niet nodig: het systeem wil weten wie zich aanmeldt en vraagt geen toegang tot
gegevens bij de provider. Het accesstoken wordt weggegooid; er wordt niets van de
provider opgeslagen behalve de vaste identifier van het account.

## De instellingen

De koppeling staat in de omgeving van de installatie en niet in een scherm. Bij
een installatie op een eigen server is dat `.env`; bij een installatie met Docker
het `.env`-bestand naast het compose-bestand.

| Variabele | Betekenis |
| --- | --- |
| `OIDC_ISSUER` | Het adres van de identiteitsprovider. |
| `OIDC_CLIENT_ID` | Het client-id uit de app-registratie. |
| `OIDC_CLIENT_SECRET` | Het bijbehorende secret. |
| `OIDC_WEERGAVENAAM` | De naam op de knop, bijvoorbeeld `Microsoft`. Het loginscherm toont *Inloggen met* gevolgd door deze naam. |
| `OIDC_SUBJECT_CLAIM` | De claim met de vaste identiteit. Standaard `sub`; bij Entra ID hoort hier `oid`. |
| `OIDC_DOMEIN` | Het domein van de organisatie, vergeleken met de claim `hd`. Verplicht bij Google Workspace. |
| `OIDC_CLIENT_SECRET_VERLOOPT_OP` | Optioneel, als datum in de vorm `2027-03-01`. Het controlecommando waarschuwt 30 dagen van tevoren. |
| `ISMS_IDP_DWINGT_MFA` | De verklaring dat de identiteitsprovider zelf een tweede factor afdwingt. Zie hieronder. |

Zolang de eerste drie niet alle drie gevuld zijn, staat de koppeling uit.

### Microsoft Entra ID

De issuer is het adres van het eigen tenant:

```
OIDC_ISSUER=https://login.microsoftonline.com/<tenant-id>/v2.0
OIDC_SUBJECT_CLAIM=oid
OIDC_WEERGAVENAAM=Microsoft
```

Het systeem weigert een issuer met `common`, `organizations` of `consumers`. Met
zo'n adres kan elk Microsoft-account zich aanmelden, ook een account uit een
ander tenant. Het tenant hoort daarom in de issuer te staan: de controle op de
uitgever van het token is dan meteen de controle op het tenant.

`OIDC_SUBJECT_CLAIM=oid` is geen detail. De claim `sub` verschilt bij Entra ID
per app-registratie. Wordt de registratie opnieuw aangemaakt, dan komen alle
bestaande koppelingen te vervallen. De claim `oid` blijft binnen het tenant
gelijk.

### Google Workspace

```
OIDC_ISSUER=https://accounts.google.com
OIDC_DOMEIN=voorbeeld.nl
OIDC_WEERGAVENAAM=Google
```

`OIDC_DOMEIN` is hier verplicht en het systeem weigert de koppeling zonder die
waarde. Iedereen kan een persoonlijk Google-account aanmaken; de claim `hd` is
wat een account van de organisatie onderscheidt van zo'n persoonlijk account.

### Andere providers

Elke provider met een discovery-document op
`<issuer>/.well-known/openid-configuration` werkt, bijvoorbeeld Keycloak of
Authentik. `OIDC_DOMEIN` blijft dan leeg, tenzij de provider een claim `hd`
levert.

## Controleren dat het klopt

```
php artisan isms:extern-inloggen-controleren
```

Het commando leest de instellingen, haalt het discovery-document en de sleutelset
op, en drukt de redirect-URI af die in de app-registratie moet staan. Dat laatste
is de controle die het vaakst iets oplevert: bij een installatie achter een
TLS-terminatie wijkt die URI af zodra het webadres niet goed doorkomt.

Het client secret is alleen met een echte aanmelding te controleren. Het is
daarom verstandig eerst één account over te zetten en daarmee aan te melden,
voordat de rest van de organisatie overgaat.

## De tweede factor

Standaard geldt de tweefactorplicht van dit systeem ook voor accounts die via de
identiteitsprovider inloggen. Die gebruikers koppelen dan een authenticator-app,
bovenop de controle die de provider al doet.

Met `ISMS_IDP_DWINGT_MFA=true` vervalt die plicht voor die accounts.
Wachtwoordaccounts op dezelfde installatie houden hem. In het gebruikersoverzicht
staat bij zo'n account *Via* de naam van de provider in de kolom Tweefactor.

Deze instelling is een verklaring van de organisatie en geen controle door het
systeem. Het ISMS kan niet zien of de provider werkelijk een tweede factor
vraagt. Wie de instelling aanzet, hoort de onderbouwing als beheersmaatregel vast
te leggen: een Conditional Access-regel in Entra ID of verplichte verificatie in
twee stappen in Google Workspace, met bewijs bij de maatregel voor veilige
authenticatie (A.8.5). Een wijziging van de instelling komt niet in de audit
trail, omdat ze buiten de applicatie staat. Daarom legt elke aanmelding via de
identiteitsprovider vast wat de instelling op dat moment was.

## Onderhoud

- **Het client secret verloopt.** Dan komt geen enkel account meer binnen dat via
  de provider inlogt. Vul `OIDC_CLIENT_SECRET_VERLOOPT_OP` in en draai het
  controlecommando periodiek, bijvoorbeeld bij elke uitrol.
- **De identiteitsprovider valt weg.** De weg terug is
  `isms:inlogmethode-wachtwoord <e-mailadres>` op de commandoregel. Dat commando
  zet één account terug op een wachtwoord en drukt een herstellink af. Het werkt
  ook zonder mailserver, en het is de reden dat deze handeling geen knop in een
  scherm heeft: op het moment dat de provider wegvalt, kan de CISO vaak zelf niet
  meer inloggen.
- **De app-registratie wordt opnieuw aangemaakt.** Bij Entra ID blijven de
  koppelingen werken zolang `OIDC_SUBJECT_CLAIM=oid` is ingesteld. Anders reikt
  de CISO per account een nieuwe koppeling uit.
- **Het ISMS verhuist naar een ander webadres.** De redirect-URI in de
  app-registratie verandert mee; het controlecommando toont de nieuwe.

## Wat er in de audit trail terechtkomt

Het koppelen en het verbreken van een koppeling staan in de audit trail, op naam
van degene die zich aanmeldde, met het account bij de provider erbij. Elke
inlogpoging vermeldt de weg: met een wachtwoord of via de identiteitsprovider.
Een aanmelding die het systeem weigert, bijvoorbeeld omdat het token niet klopt
of het domein afwijkt, wordt vastgelegd als mislukte poging. De reden staat in
het technische log en niet op het scherm van de bezoeker.

Het verdient aanbeveling de koppeling ook op **/integraties** vast te leggen, als
koppeling van het type *identiteit*. Dan staat in het ISMS zelf dat de
authenticatie van een deel van de accounts bij een externe partij ligt.
