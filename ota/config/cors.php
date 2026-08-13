<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| Eén route, en die staat er om één reden (implementatie/01e §1.5).
|
| Sinds 01e wordt een toets uitgeserveerd met `Content-Security-Policy: sandbox`.
| Dat zet het document in een opake origin, en die stuurt `Origin: null` mee.
| `onQuizVoltooid` post bovendien `Content-Type: application/json`, wat een
| preflight uitlokt. Zonder deze configuratie faalt het terugmelden van elke
| uitslag — zichtbaar voor de deelnemer als "je uitslag kon niet worden
| opgeslagen", maar pas nadat hij de toets gemaakt heeft.
|
| `*` mag hier omdat de callback geen sessie gebruikt: de token is het bewijs, de
| route staat buiten `auth`, is CSRF-uitgezonderd en zet de actor server-side.
| Er valt met een meegelezen antwoord niets te winnen wat de token niet al gaf.
| `supports_credentials` blijft daarom uit — met `true` zou `*` bovendien
| geweigerd worden door de browser.
|
| Alleen dit pad. Wie hier een tweede regel bij zet, opent een route van het ISMS
| voor elke willekeurige pagina op internet.
|
*/

return [

    'paths' => ['toetsen/callback/*'],

    'allowed_methods' => ['POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
