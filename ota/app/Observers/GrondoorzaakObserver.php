<?php

namespace App\Observers;

use App\Models\Grondoorzaak;

/** De eerste grondoorzaak brengt de afwijking van `open` naar `analyse` (§5). */
class GrondoorzaakObserver extends AfwijkingstatusObserver
{
    public function saved(Grondoorzaak $grondoorzaak): void
    {
        $this->werkAfwijkingstatusBij($grondoorzaak);
    }

    public function deleted(Grondoorzaak $grondoorzaak): void
    {
        $this->werkAfwijkingstatusBij($grondoorzaak);
    }
}
