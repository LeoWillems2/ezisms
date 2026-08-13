<?php

namespace App\Observers;

use App\Models\Leveranciersbeoordeling;
use App\Support\TaakPlanner;

/**
 * Twee afleidingen uit een beoordeling, allebei cross-entity (ze raken de
 * bovenliggende `Leverancier`, niet de beoordeling zelf) — vgl. `RisicoObserver`
 * in blok 7 §7, maar dan met de datum op het kind en de taak op de ouder.
 */
class LeveranciersbeoordelingObserver
{
    public function saved(Leveranciersbeoordeling $beoordeling): void
    {
        // Laad de ouder VERS: bij een tweede beoordeling in één transactie zou
        // een gecachete relatie op verouderde status/beoordelingen werken (de
        // terugkerende stale-relatie-les uit blok 8, §6).
        $leverancier = $beoordeling->leverancier()->first();

        if ($leverancier === null) {
            return;
        }

        // kandidaat → actief: zodra er een eerste beoordeling is, mag de
        // leverancier actief — geen los knopje dat je kunt vergeten (§5). Een
        // beëindigde leverancier blijft beëindigd.
        if ($leverancier->status === 'kandidaat') {
            $leverancier->update(['status' => 'actief']);
        }

        // De herbeoordelingstaak hangt aan de leverancier, met als deadline de
        // volgende-datum van de nieuwste beoordeling. Idempotent per (entiteit,
        // soort): een nieuwe beoordeling verzet dezelfde taak, een leeggemaakte
        // datum ruimt hem op (dat handelt de planner af).
        $nieuwste = $leverancier->nieuwsteBeoordeling()->first();

        TaakPlanner::planVoorEntiteit(
            $leverancier,
            'leverancier-herbeoordeling',
            'Leverancier herbeoordelen: '.$leverancier->naam,
            $nieuwste?->volgende_beoordeling_gepland,
            'leveranciers-derdenrisico',
        );
    }
}
