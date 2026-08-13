<?php

namespace App\Observers;

use App\Models\Afwijking;
use Illuminate\Database\Eloquent\Model;

/**
 * Gedeelde afleiding van de afwijkingstatus (implementatie/08 §5).
 *
 * Grondoorzaken en maatregelen sturen dezelfde regel aan, dus staat hij één
 * keer hier in plaats van twee keer in twee observers.
 */
abstract class AfwijkingstatusObserver
{
    /**
     * @param  Model  $model  met een `afwijking()`-relatie
     */
    protected function werkAfwijkingstatusBij(Model $model): void
    {
        // `afwijking()->first()` en niet `$model->afwijking`: de gecachte
        // relatie is verouderd zodra er in dezelfde transactie meer dan één
        // onderliggende rij wijzigt. Eloquent ziet dan geen wijziging
        // (isDirty() false) en slaat de update stilzwijgend over — precies de
        // bug die in blok 5 de documentstatus liet achterlopen
        // (implementatie/05 §14, implementatie/08 §5).
        $afwijking = $model->afwijking()->first();

        if (! $afwijking instanceof Afwijking) {
            return;
        }

        $model->setRelation('afwijking', $afwijking);

        $afwijking->update(['status' => $afwijking->afgeleideStatus()]);
    }
}
