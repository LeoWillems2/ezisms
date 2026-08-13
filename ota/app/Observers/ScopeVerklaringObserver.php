<?php

namespace App\Observers;

use App\Models\ScopeVerklaring;
use App\Support\TaakPlanner;

/**
 * Zet het veldgestuurde herzieningssignaal om in een taak (implementatie/07 §7).
 *
 * De tijdgestuurde signalen (asset-retourcontrole, SoA-herbeoordeling) zitten
 * juist in `isms:genereer-taken`: daar wijzigt niets, daar verstrijkt tijd, en
 * een observer zou dus nooit vuren.
 */
class ScopeVerklaringObserver
{
    public function saved(ScopeVerklaring $versie): void
    {
        // Alleen de actieve versie is te herzien. Wordt een versie vervangen,
        // dan verdwijnt de openstaande taak (deadline null = opruimen).
        $deadline = $versie->status === 'actief'
            ? $versie->volgende_herziening_gepland
            : null;

        TaakPlanner::planVoorEntiteit(
            $versie,
            'scope-herziening',
            'Scope-verklaring v'.$versie->versienummer.' herzien',
            $deadline,
            'context-scope',
        );
    }
}
