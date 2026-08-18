# TLS zonder HAProxy

Deze installatie gaat er standaard van uit dat een HAProxy ervóór de TLS
termineert en `X-Forwarded-Proto: https` meestuurt. Dit document beschrijft wat
er verandert als die proxy er niet is en nginx zelf het certificaat draagt (of
als er helemaal geen TLS komt).

**Uitgangspunt nu:** de applicatie kent geen `TrustProxies`-configuratie; het
HTTPS-besef komt puur uit de nginx-constructie

    set $my_https "";
    if ($http_x_forwarded_proto = "https") { set $my_https "on"; }
    fastcgi_param HTTPS $my_https;

Die kop is client-stuurbaar. Met een proxy ervóór is dat veilig — HAProxy
overschrijft hem — maar zonder proxy niet: een bezoeker stuurt hem dan zelf over
kaal HTTP mee en de applicatie denkt dat de verbinding beveiligd is. Die
constructie moet dus wég, niet blijven staan.

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
- Alternatief dat minder verandert: laat de container HTTP-only op poort 81 en
  zet een nginx op de **host** ervoor als TLS-terminerende reverse proxy. Dan
  blijft de containerconfiguratie zoals ze is — maar dan hebt u wél weer een
  proxy, moet u daar `proxy_set_header X-Forwarded-Proto $scheme;` zetten, en is
  de huidige `$http_x_forwarded_proto`-constructie juist correct.

## 3. Laravel

- `APP_URL` moet exact het schema en de host van buiten dragen:
  `https://isms.voorbeeld.nl`. Dat is gelijk aan de HAProxy-situatie, maar wordt
  nu door nginx zelf waargemaakt.
- **`SESSION_SECURE_COOKIE=true` toevoegen.** Die sleutel staat nu in geen van
  beide env-bestanden, terwijl `config/session.php` hem leest
  (`'secure' => env('SESSION_SECURE_COOKIE')`). Zonder die regel gaat het
  sessiecookie ook over een onbeveiligde verbinding mee.
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

Alleen `APP_URL=http://…`, `SESSION_SECURE_COOKIE` weglaten, en de
`$my_https`-constructie schrappen. Wel expliciet vermelden in het ISMS:
2FA-codes, sessiecookies en bewijsstukken gaan dan onversleuteld over de lijn.
Dat is voor een productie-ISMS niet verdedigbaar tegenover A.8.24 en A.5.14.

## 5. Documentatie die meemoet

`CLAUDE.md` (de regel over haproxy), `docker/ezisms/LEESMIJ.md` (de regels over
`APP_URL` en poort 81, en de passage over het webtoegangslog),
`docker/ezisms/env.voorbeeld`, en de vermeldingen in
`implementatie/00l-docker-stack.md`, `implementatie/00n-docker-deploy.md`,
`implementatie/00p-docker-logging.md`, `implementatie/01e-administrator.md` en
`ota/README.md`.
