<?php

namespace App\Http\Controllers;

use App\Support\ToetsBestanden;
use App\Support\Toetsrespons;
use Illuminate\Http\Response;

/**
 * Een toets bekijken zonder token, voor wie toetsen uitzet (implementatie/01e
 * §1.3).
 *
 * Tot 01e ging dat vanzelf: het bestand stond in `public/toetsen/` en was zonder
 * token op te vragen — een vastgelegd besluit, want *"de token doet het
 * beveiligingswerk, niet de onvindbaarheid van het bestand"*
 * (kennisbank/open-punten.md). Met een route zou "alleen voor ingelogde
 * gebruikers" er gratis bij komen, en dat kost het vrijblijvend testen van een
 * toets. Vandaar deze route, achter dezelfde autorisatiecheck als de bouwhulp.
 *
 * Zonder `?callback=` registreert `onQuizVoltooid` niets, dus een voorbeeld
 * raakt geen enkele opdracht.
 */
class ToonToetsVoorbeeld extends Controller
{
    public function __invoke(string $bestand): Response
    {
        // basename() in ToetsBestanden vangt een pad al af; hier weigeren we het
        // meteen, want een verzoek met een pad erin is geen vergissing.
        abort_unless($bestand === basename($bestand), 404);

        $html = ToetsBestanden::inhoud($bestand);

        abort_if($html === null, 404);

        return Toetsrespons::voor($html);
    }
}
