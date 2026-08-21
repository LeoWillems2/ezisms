# TLS zonder HAProxy

Deze installatie gaat er standaard van uit dat een HAProxy ervóór de TLS
termineert en `X-Forwarded-Proto: https` meestuurt. Dit document beschrijft wat
er verandert als die proxy er niet is en nginx zelf het certificaat draagt (of
als er helemaal geen TLS komt).

Het is een beschrijving van een **omschakeling**, met de hand. Wie de keuze
instelbaar wil maken — één sleutel `ISMS_TLS` met drie standen — vindt de
bouwinstructie in `implementatie/00r-tls-instelbaar.md`. Dat plan is niet
gebouwd; er is nog niets in de applicatie dat deze keuze kent.

**Uitgangspunt nu:** de applicatie kent geen `TrustProxies`-configuratie; het
HTTPS-besef komt puur uit de nginx-constructie

    set $my_https "";
    if ($http_x_forwarded_proto = "https") { set $my_https "on"; }
    fastcgi_param HTTPS $my_https;

Die kop is client-stuurbaar. Met een proxy ervóór is dat veilig — HAProxy
overschrijft hem — maar zonder proxy niet: een bezoeker stuurt hem dan zelf over
kaal HTTP mee en de applicatie denkt dat de verbinding beveiligd is. Die
constructie moet dus wég, niet blijven staan.

En "met een proxy ervóór is dat veilig" geldt alleen zolang de webserver ook
niet buiten die proxy om bereikbaar is. `ontwikkelmachine/nginx-ota-isms.conf`
dwingt dat af met `allow 192.168.100.220; allow 127.0.0.1; deny all;`. De
container doet dat níét: die publiceert `HTTP_POORT` op alle interfaces, dus wie
de hostpoort kan bereiken kan de kop vandaag al zelf sturen. Dat is een bestaand
punt — het staat als openstaand punt over het client-IP ook in `README.md` — maar
het hoort bij deze verbouwing thuis, want beide gevallen komen uit dezelfde
constructie.

## 1. nginx — nu zelf TLS termineren

In `ontwikkelmachine/nginx-ota-isms.conf` en `docker/ezisms/nginx.conf`:

- Serverblok op `listen 443 ssl;` plus `http2 on;`, met `ssl_certificate` en
  `ssl_certificate_key` (Let's Encrypt of een eigen certificaat).
- Poort 80 wordt een redirectblok: `return 301 https://$host$request_uri;` —
  behalve `/.well-known/acme-challenge/` als u certbot gebruikt.
- In het `location ~ \.php$`-blok de hele `$my_https`-constructie vervangen door

      fastcgi_param HTTPS $https;      # leeg bij http, "on" bij TLS

  of, in een blok dat alleen op 443 luistert, hardweg `fastcgi_param HTTPS on;`.
  Zo blijven `request()->secure()` en de URL-generatie kloppen, en kan niemand
  het meer faken met een zelfgestuurde kop.
- Passend bij een ISMS, en pas nadat de redirect werkt:

      add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

## 2. Docker

- `compose.yml`: naast (of in plaats van) `"${HTTP_POORT:-81}:80"` ook
  `"443:443"` publiceren, en een read-only mount voor de certificaten,
  bijvoorbeeld `- ${ISMS_TLS:-./tls}:/etc/ezisms/tls:ro`. De container luistert
  binnenin dan op 80 én 443.
- `env.voorbeeld`: de toelichting bij `HTTP_POORT` en `APP_URL` beschrijft nu
  HAProxy en moet mee.
- **Vernieuwing.** Er zit geen certbot in het image en nginx draait onder
  supervisord, dus na een renewal op de host moet er nog een `nginx -s reload`
  de container in: `docker compose exec app nginx -s reload` in de deploy-hook
  van certbot. Zonder dat serveert de container een verlopen certificaat tot de
  eerstvolgende `up -d`. Bij de webroot-route kan het ACME-verzoek gewoon door
  het huidige serverblok heen — `location ~ /\.(?!well-known).*` laat
  `/.well-known/` al staan.
- Alternatief dat minder verandert: laat de container HTTP-only op poort 81 en
  zet een nginx op de **host** ervoor als TLS-terminerende reverse proxy. Dan
  blijft de containerconfiguratie zoals ze is — maar dan hebt u wél weer een
  proxy, moet u daar `proxy_set_header X-Forwarded-Proto $scheme;` zetten, en is
  de huidige `$http_x_forwarded_proto`-constructie juist correct.

## 3. Laravel

- `APP_URL` moet exact het schema en de host van buiten dragen:
  `https://isms.voorbeeld.nl`. Dat is gelijk aan de HAProxy-situatie, maar wordt
  nu door nginx zelf waargemaakt.
- **Het sessiecookie regelt zichzelf.** De entrypoint leidt
  `SESSION_SECURE_COOKIE` af uit het schema van `APP_URL` (stap 4c): `https://`
  ⇒ `true`, anders `false` met een waarschuwing in het log. Zet `APP_URL` goed
  en dit klopt vanzelf, in alle drie de opstellingen.

  Dat was op 18-08-2026, toen dit document werd geschreven, nog niet zo — en de
  regel die hier stond klopte ook niet. Er stond dat `SESSION_SECURE_COOKIE` in
  geen van beide env-bestanden stond; hij stond in beide, op `true`. Maar níét in
  de `environment:` van `compose.yml`, en die lijst is uitputtend: de sleutel
  bereikte de container nooit.

  Het gevolg viel mee, maar niet weg. `config/session.php` las `null`, en dan
  laat Laravel het cookie de aard van het verzoek volgen — Symfony's
  `Cookie::isSecure()` valt terug op `secureDefault`, en `Response::prepare()`
  zet die op `true` zodra `$request->isSecure()`. Achter HAProxy wás het cookie
  dus secure. Twee dingen deugden niet: de sleutel was in het geheel niet
  instelbaar, en de uitkomst hing aan `$my_https` — dus aan de kop die op de open
  containerpoort door de client zelf te sturen is. Dat laatste is precies de
  reden om het aan `APP_URL` op te hangen en niet aan de webserver.

  Gerepareerd op 19-08-2026, samen met `SESSION_ENCRYPT`, die om dezelfde reden
  dood was. Daar was het gevolg wél echt: de sessie-payload stond ondanks
  `SESSION_ENCRYPT=true` in het voorbeeldbestand onversleuteld in de database.
  Die sleutel verschilt niet per installatie en staat nu in
  `docker/env.statisch`.
- **Geen `TrustProxies` aanzetten.** Zonder proxy zou het vertrouwen van
  `X-Forwarded-*` juist een spoofing-ingang zijn; de huidige afwezigheid ervan is
  hier de goede stand.
- `URL::forceScheme('https')` in `AppServiceProvider` is niet nodig zolang nginx
  `HTTPS` als fastcgi-param zet. Alleen als u de webserverkant niet kunt
  aanpassen is dat de terugvaloptie.
- Bijvangst: `request()->ip()` in `RegistreerGeslaagdeLoginpoging` en
  `RegistreerMislukteLoginpoging` logt zonder proxy meteen het echte client-IP.
  Achter HAProxy was dat het adres van de proxy, want de applicatie vertrouwt
  `X-Forwarded-For` niet.

## 4. Als er helemaal geen TLS komt (kaal HTTP)

Alleen `APP_URL=http://…` en de `$my_https`-constructie schrappen; het
sessiecookie volgt `APP_URL` en gaat vanzelf op `false`, met een waarschuwing bij
elke start. Wel expliciet vermelden in het ISMS:
2FA-codes, sessiecookies en bewijsstukken gaan dan onversleuteld over de lijn.
Dat is voor een productie-ISMS niet verdedigbaar tegenover A.8.24 en A.5.14.

## 5. Documentatie die meemoet

`CLAUDE.md` (de regel over haproxy), `docker/ezisms/LEESMIJ.md` (de regels over
`APP_URL` en poort 81, en de passage over het webtoegangslog),
`docker/ezisms/env.voorbeeld`, en de vermeldingen in
`implementatie/00l-docker-stack.md`, `implementatie/00n-docker-deploy.md`,
`implementatie/00p-docker-logging.md`, `implementatie/01e-administrator.md` en
`ota/README.md`.

Ook `SBOM.md` (HAProxy staat er als component in, en wordt door
`scripts/genereer-sbom.php` opnieuw geschreven — daar staat de tekst) en het
commentaar bij `App\Support\Toetsrespons::BRONNEN`, dat de HAProxy noemt om uit
te leggen waarom `config('app.url')` en de werkelijke host uiteen kunnen lopen.

En, het zwaarst: de **compliance-set**. Zowel de dienstbeschrijving (document
02) als bijlage 2 (document 04) noemt de HAProxy als feit van de dienst, en
bijlage 2 draagt als restrisico 6 "verkeer tussen HAProxy en de applicatie is
onversleuteld". Termineert nginx zelf, dan **vervalt dat restrisico** — dat is
de winst van deze verbouwing, maar het betekent wel dat bijlage 2 twee varianten
moet beschrijven of per installatie ingevuld moet worden.
`compliance/01-intake-verwerkersovereenkomst.md` belegt de "DNS,
TLS-certificaten, HAProxy-laag" bij een partij; die regel verschuift mee.
