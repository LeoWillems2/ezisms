<?php

namespace App\Observers;

use App\Models\Leesbevestiging;
use App\Support\TaakPlanner;

/**
 * Sluit de leesbevestigingstaak zodra de bevestiging binnen is
 * (implementatie/05 §8). Een taak die blijft staan nadat de handeling verricht
 * is, leert mensen taken negeren.
 */
class LeesbevestigingObserver
{
    public function created(Leesbevestiging $bevestiging): void
    {
        if ($bevestiging->versie === null) {
            return;
        }

        TaakPlanner::voltooiVoorEntiteit(
            $bevestiging->versie,
            'beleid-leesbevestiging',
            $bevestiging->gebruiker_id,
        );
    }
}
