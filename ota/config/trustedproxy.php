<?php

/*
 * De proxy's waarvan `X-Forwarded-For` wordt geloofd (implementatie/01l §2).
 *
 * Achter HAProxy is REMOTE_ADDR de TLS-terminator en niet de bezoeker. Zonder
 * deze lijst staat in elke loginpoging hetzelfde adres, en is "een inlogpoging
 * van een ongebruikelijke plek" (BIO2 5.17.01) niet te zien. Hij telt ook voor
 * de throttling van de login en de 2FA-challenge: die sleutelen op het
 * IP-adres, en deelden tot dit plan dus één teller voor iedereen.
 *
 * De naam van dit bestand is die van Laravel zelf: `TrustProxies` valt hierop
 * terug, en leest hem per verzoek — dus ook na `config:cache`, anders dan een
 * `env()` in `bootstrap/app.php`, dat draait voordat `.env` geladen is.
 *
 * `*` en `**` worden hier weggefilterd. Laravel leest die als "vertrouw wie er
 * ook belt", en dan zet iedere bezoeker zijn eigen X-Forwarded-For en kiest hij
 * zelf waar hij vandaan lijkt te komen. Leeg = niemand vertrouwen, het gedrag
 * van vóór dit plan.
 */
$proxies = array_values(array_filter(
    array_map('trim', explode(',', (string) env('ISMS_VERTROUWDE_PROXIES', ''))),
    fn (string $proxy) => $proxy !== '' && ! in_array($proxy, ['*', '**'], true),
));

return [

    'proxies' => $proxies === [] ? null : $proxies,

];
