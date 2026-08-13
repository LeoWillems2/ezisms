<?php

namespace App\Observers;

use App\Models\Effectiviteitstoets;

/**
 * De terugweg uit de statemachine (implementatie/08 §5).
 *
 * `deelproducten/08` §2: een toets met `niet_effectief` sluit de maatregel niet
 * af, maar zet de cyclus terug. Dat is het hele punt van een effectiviteitstoets
 * — zonder die terugweg is het een afvinkveld.
 */
class EffectiviteitstoetsObserver
{
    public function saved(Effectiviteitstoets $toets): void
    {
        // Verse instantie, zelfde reden als in AfwijkingstatusObserver.
        $maatregel = $toets->maatregel()->first();

        if ($maatregel === null) {
            return;
        }

        if ($toets->resultaat === 'niet_effectief') {
            $maatregel->update(['status' => 'in_uitvoering', 'voltooid_op' => null]);

            // Een al gesloten afwijking gaat weer open. Dat is geen randgeval
            // maar precies waarvoor deze regel bestaat: er is achteraf
            // vastgesteld dat het probleem niet is weggenomen. Wie hem sloot
            // blijft in de audit trail staan.
            $afwijking = $maatregel->afwijking()->first();

            if ($afwijking?->isGesloten()) {
                $afwijking->update(['gesloten_op' => null, 'gesloten_door_id' => null]);
                $afwijking->update(['status' => $afwijking->afgeleideStatus()]);
            }

            return;
        }

        // Effectief: de maatregel opnieuw opslaan zodat de observer daar de
        // openstaande toetstaak opruimt (§8).
        $maatregel->save();
    }
}
