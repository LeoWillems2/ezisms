<?php

namespace App\Observers;

use App\Models\CorrigerendeMaatregel;
use App\Support\TaakPlanner;

/**
 * Twee gevolgen van een maatregelwijziging (implementatie/08 §5 en §8): de
 * status van de afwijking, en de twee taken.
 *
 * Beide signalen zijn veldgestuurd — er wijzigt een datum of een status — en
 * horen daarom hier, net als de herzieningstaak in blok 5. Tijdgestuurde
 * signalen zitten juist in `isms:genereer-taken`.
 */
class CorrigerendeMaatregelObserver extends AfwijkingstatusObserver
{
    /**
     * Termijn waarbinnen de effectiviteit van een afgeronde maatregel getoetst
     * hoort te zijn. Staat NIET in de norm: §10.2 vraagt om een toets, niet om
     * een termijn. Gekozen omdat een toets zonder deadline er nooit komt.
     */
    public const TOETSTERMIJN_DAGEN = 30;

    public function saved(CorrigerendeMaatregel $maatregel): void
    {
        $this->werkAfwijkingstatusBij($maatregel);
        $this->planUitvoering($maatregel);
        $this->planToets($maatregel);
    }

    public function deleted(CorrigerendeMaatregel $maatregel): void
    {
        $this->werkAfwijkingstatusBij($maatregel);
    }

    /** De maatregel zelf afronden. Verdwijnt zodra hij voltooid is. */
    private function planUitvoering(CorrigerendeMaatregel $maatregel): void
    {
        TaakPlanner::planVoorEntiteit(
            $maatregel,
            'corrigerende-maatregel',
            'Corrigerende maatregel uitvoeren: '.$maatregel->auditOmschrijving(),
            $maatregel->status === 'voltooid' ? null : $maatregel->deadline,
            'incident-afwijkingenbeheer',
            $maatregel->eigenaar_id,
        );
    }

    /**
     * Het belangrijkste signaal van dit blok. Zonder taak blijft "we hebben een
     * maatregel genomen" hangen en komt de toets er nooit — en juist die toets
     * is wat §10.2 vraagt.
     */
    private function planToets(CorrigerendeMaatregel $maatregel): void
    {
        $moetGetoetst = $maatregel->status === 'voltooid'
            && $maatregel->voltooid_op !== null
            && ! $maatregel->toetsen()->exists();

        TaakPlanner::planVoorEntiteit(
            $maatregel,
            'effectiviteitstoets',
            'Effectiviteit toetsen: '.$maatregel->auditOmschrijving(),
            $moetGetoetst ? $maatregel->voltooid_op->copy()->addDays(self::TOETSTERMIJN_DAGEN) : null,
            'incident-afwijkingenbeheer',
            $maatregel->eigenaar_id,
        );
    }
}
