<?php

/*
 * Tijdweergave (implementatie/00o).
 *
 * **Opslag blijft UTC** (`config/app.php` → `timezone`). Dit is uitsluitend de
 * zone waarin een tijdstip aan een mens getoond wordt.
 *
 * Die scheiding is geen smaak maar een randvoorwaarde: `tijdstip` is het eerste
 * veld in `Ketenhash::VELDEN`, dus de audit trail is over de opgeslagen waarden
 * gehasht. `app.timezone` omzetten en de bestaande rijen meeschuiven maakt de
 * hele keten ongeldig; omzetten zónder de rijen te schuiven levert een kolom op
 * waarin oude regels UTC zijn en nieuwe lokaal, zonder dat iets dat verraadt.
 *
 * Omzetten gebeurt daarom pas bij het tonen, via de macro `->lokaal()` uit
 * `AppServiceProvider`.
 */
return [

    /*
     * De zone waarin schermen, mails en exports een tijdstip tonen. Instelbaar
     * omdat een installatie buiten Nederland kan draaien; de opslag verandert
     * er niet door, dus wijzigen is veilig en met terugwerkende kracht juist.
     */
    'weergave' => env('ISMS_TIJDZONE', 'Europe/Amsterdam'),

];
