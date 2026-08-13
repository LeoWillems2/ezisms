<?php

namespace App\Http\Controllers;

use App\Models\Toetsopdracht;
use App\Support\ToetsBestanden;
use App\Support\Toetsrespons;
use Illuminate\Http\Response;

/**
 * De deelnemerkant van een toets (implementatie/01e §1.3). Publiek, zonder
 * sessie: de token is het bewijs, net als bij het callbackkanaal ernaast.
 *
 * Tot 01e was dit geen route maar een bestand in `public/toetsen/`, dat nginx
 * uitserveerde vanaf de origin van het ISMS. Nu gaat het door de applicatie
 * heen, en dat is de enige reden dat er een sandbox omheen te zetten is.
 */
class ToonToets extends Controller
{
    public function __invoke(string $token): Response
    {
        // Onbekende token => 404, zonder onderscheid tussen "bestaat niet" en
        // "ingetrokken" — zelfde lijn als ToetsCallbackController.
        $opdracht = Toetsopdracht::where('token', $token)->firstOrFail();

        $html = ToetsBestanden::inhoud($opdracht->toets_bestand);

        // Het bestand is weg terwijl de opdracht er nog is. Dat is een
        // beheerfout, geen deelnemersfout: 404 met de titel die in de opdracht
        // is vastgelegd, zodat de melding tenminste noemt wélke toets ontbreekt.
        abort_if($html === null, 404, "Het bestand van de toets '{$opdracht->toets_titel}' is niet gevonden.");

        return Toetsrespons::voor($html);
    }
}
