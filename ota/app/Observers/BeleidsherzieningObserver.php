<?php

namespace App\Observers;

use App\Models\Beleidsherziening;
use App\Support\TaakPlanner;

/**
 * Een vastgelegde herziening sluit de openstaande herzieningstaak en zet de
 * termijn door naar de versie. Hier en niet in het scherm, om dezelfde reden als
 * bij de leesbevestiging: het scherm is maar één van de wegen.
 */
class BeleidsherzieningObserver
{
    public function created(Beleidsherziening $herziening): void
    {
        $versie = $herziening->versie;

        if ($versie === null) {
            return;
        }

        // Eerst sluiten, dan de datum verzetten. Andersom verzet
        // `planHerziening` de deadline van de bestaande taak naar de nieuwe
        // datum, en blijft er één taak open die nu pas over een jaar verloopt —
        // zonder dat zichtbaar is dat deze ronde afgerond is.
        TaakPlanner::voltooiVoorEntiteit($versie, 'beleid-herziening');

        $versie->update(['volgende_herziening_gepland' => $herziening->volgende_herziening_gepland]);
    }
}
