<?php

namespace App\Models\Concerns;

use App\Models\AuditLogregel;
use App\Support\Enumwaarden;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Weigert een waarde die niet in de enum van de kolom past
 * (implementatie/00s §10.3).
 *
 * Op MySQL doet de database dit zelf; op SQLite is dezelfde kolom een kale
 * `varchar` en gebeurt er zonder deze trait niets. Dat verschil is voor de
 * statusmachines het gevaarlijkst: daar hangen belemmeringen, rapportages en
 * KPI's aan een handvol afgesproken woorden, en een typefout in code of seed zou
 * op de ene installatie afketsen en op de andere een record opleveren dat
 * nergens meer in beeld komt.
 *
 * Dezelfde vorm als de bewaking die {@see AuditLogregel} al voor
 * `actie` had — deze trait maakt er een gedeeld mechanisme van dat zijn lijst
 * uit {@see Enumwaarden} haalt.
 *
 * De trait bewaakt alleen wat er in het register staat, en alleen bij een
 * gewijzigde waarde: bestaande rijen met een oude waarde blijven te bewerken,
 * anders zou een verruiming die later teruggedraaid wordt de hele tabel op slot
 * zetten. `null` gaat er ook langs — of een kolom leeg mag, is een
 * NOT NULL-vraag, en die bewaken beide drivers wél.
 */
trait Waardenbewaking
{
    public static function bootWaardenbewaking(): void
    {
        static::saving(function (Model $model) {
            foreach (Enumwaarden::vanTabel($model->getTable()) as $kolom => $toegestaan) {
                if (! $model->isDirty($kolom)) {
                    continue;
                }

                $waarde = $model->getAttribute($kolom);

                if ($waarde === null || in_array($waarde, $toegestaan, true)) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    "Onbekende waarde '%s' voor %s.%s. Toegestaan: %s. Een nieuwe waarde vraagt "
                    .'ook een migratie die de enum verruimt — op sqlite valt dat niet op, op MySQL wel.',
                    is_scalar($waarde) ? (string) $waarde : gettype($waarde),
                    $model->getTable(),
                    $kolom,
                    implode(', ', $toegestaan),
                ));
            }
        });
    }
}
