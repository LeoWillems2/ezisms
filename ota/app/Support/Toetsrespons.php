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
     */
    public const SANDBOX = 'sandbox allow-scripts allow-forms allow-modals allow-top-navigation';

    /**
     * De bronbeperking (implementatie/10b). Herziet het besluit van 30-07-2026
     * dat toetsen externe assets mochten laden: dat gold toen de toetsen alleen
     * intern werden gebruikt. Bij een gehoste dienst zijn de deelnemers
     * medewerkers van een klant, en dan is elke CDN een ontvanger van hun
     * IP-adres die in bijlage 3 van de verwerkersovereenkomst genoemd moet
     * worden. Dit is de enige plek waar dat af te dwingen is: de klant levert de
     * toetsen zelf aan én plaatst ze zelf, dus keuren aan de deur kan niet.
     *
     * `'unsafe-inline'` is hier geen concessie maar een voorwaarde: een toets ís
     * één bestand met inline <script> en <style>. Het doel van deze regel is niet
     * XSS tegenhouden — dat doet de sandbox hierboven, die het document al uit
     * onze origin heeft gehaald — maar uitgaande verbindingen tegenhouden. Wie
     * 'unsafe-inline' hier "voor de veiligheid" weghaalt, breekt elke toets.
     *
     * `data:` staat toe bij img en font, want dat is de route die overblijft nu
     * externe bronnen dicht gaan: een plaatje bak je in het bestand.
     *
     * `connect-src 'self'` moet de POST naar /toetsen/callback/<token> toelaten.
     * Het document heeft een opake origin (vandaar CORS met Origin: null), maar
     * `'self'` wordt afgeleid uit de URL van de respons en niet uit de origin van
     * het document, dus dat komt uit op deze host. Blijkt dat in een browser toch
     * anders te liggen, dan is de uitweg de host expliciet noemen — let dan op dat
     * config('app.url') en de werkelijke host uiteen kunnen lopen, want de TLS
     * termineert op een HAProxy ervóór.
     */
    public const BRONNEN = "default-src 'self'; img-src 'self' data:; font-src 'self' data:; "
        ."style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'";

    public static function voor(string $html): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::SANDBOX.'; '.self::BRONNEN,
            // Geen MIME-gokwerk op materiaal dat van buiten komt.
            'X-Content-Type-Options' => 'nosniff',
            // Een toets hoort nergens in een cache te blijven hangen: de URL
            // draagt de token van één deelnemer.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
