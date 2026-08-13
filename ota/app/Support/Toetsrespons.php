<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * De ene plek waar een toets het systeem uit gaat, met de afscherming eromheen
 * (implementatie/01e §1.4).
 *
 * Een toets is door een mens geleverde HTML mét JavaScript. Werd hij vanaf de
 * origin van het ISMS geserveerd — zoals tot 01e uit `public/toetsen/` — dan
 * draaide dat script in de sessie van wie hem opende. De sandbox-richtlijn zet
 * het document in een eigen, lege origin: geen sessiecookie, geen DOM van het
 * ISMS, geen opslag.
 */
final class Toetsrespons
{
    /**
     * Vier vlaggen, en drie ervan staan er omdat `resources/toetsen/onQuizVoltooid.js`
     * ze nodig heeft — die helper zit in élk bestaand toetsbestand:
     *
     *   allow-scripts             een toets ís JavaScript;
     *   allow-forms               toetsen met een <form>;
     *   allow-modals              de helper meldt de uitslag met alert() (`:61`,
     *                             `:71`). Zonder deze vlag worden die stil
     *                             geblokkeerd en weet de deelnemer niet of zijn
     *                             uitslag is aangekomen — precies wat die tweede
     *                             alert() moest voorkomen;
     *   allow-top-navigation      de helper stuurt na een geslaagde toets door
     *                             naar de takenlijst. De toets opent met
     *                             target="_blank" en is dus zijn eigen tabblad:
     *                             hij navigeert alleen zichzelf.
     *
     * `allow-same-origin` staat er NIET bij en mag er nooit bij: dat geeft de
     * toets zijn origin terug en maakt de hele ingreep zinloos.
     *
     * Geen `default-src` of andere bronbeperking: op 30-07-2026 is vastgelegd dat
     * toetsen externe assets mogen laden. De sandbox-richtlijn zegt niets over
     * waar bronnen vandaan komen, dus dat besluit blijft staan. Wie hier "voor de
     * netheid" een bronbeperking bij zet, breekt bestaande toetsen.
     */
    public const SANDBOX = 'sandbox allow-scripts allow-forms allow-modals allow-top-navigation';

    public static function voor(string $html): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::SANDBOX,
            // Geen MIME-gokwerk op materiaal dat van buiten komt.
            'X-Content-Type-Options' => 'nosniff',
            // Een toets hoort nergens in een cache te blijven hangen: de URL
            // draagt de token van één deelnemer.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
