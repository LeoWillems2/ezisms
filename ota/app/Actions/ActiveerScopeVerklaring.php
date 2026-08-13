<?php

namespace App\Actions;

use App\Models\ScopeVerklaring;
use Illuminate\Support\Facades\DB;

/**
 * Zet één scope-versie op 'actief' en de vorige actieve op 'vervangen'.
 *
 * Bewust een expliciete invokable action en geen Eloquent-observer: het effect
 * ("de vorige actieve versie wordt vervangen") blijft zo zichtbaar op de plek
 * waar het gebeurt (implementatie/02-context-scope.md §4).
 */
class ActiveerScopeVerklaring
{
    public function __invoke(ScopeVerklaring $nieuweVersie, string $goedgekeurdDoor): void
    {
        DB::transaction(function () use ($nieuweVersie, $goedgekeurdDoor) {
            // updateGeaudit: het vervangen van de vorige actieve versie is
            // juist de overgang die auditbewijs vormt, en een massa-update zou
            // hem buiten de audit trail houden.
            ScopeVerklaring::where('status', 'actief')->updateGeaudit(['status' => 'vervangen']);

            $nieuweVersie->update([
                'status' => 'actief',
                'geldig_vanaf' => now(),
                'goedgekeurd_door' => $goedgekeurdDoor,
                // Default: jaarlijkse herziening (deelproduct 2 §7).
                'volgende_herziening_gepland' => $nieuweVersie->volgende_herziening_gepland
                    ?? now()->addYear(),
            ]);
        });
    }
}
