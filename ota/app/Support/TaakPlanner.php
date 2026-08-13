<?php

namespace App\Support;

use App\Models\Taak;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * De enige ingang waarmee andere blokken een taak plannen
 * (implementatie/07-taken-workflow-engine.md §7).
 *
 * De koppeling loopt één kant op: bronblokken roepen deze planner aan, de
 * engine kent de bronblokken niet. Zo hoeft blok 7 niet aangepast te worden
 * bij elk blok dat erbij komt.
 */
final class TaakPlanner
{
    /**
     * Plant of verplaatst de openstaande taak voor een entiteit.
     *
     * Idempotent op (entiteit, soort): een tweede aanroep verzet de bestaande
     * taak in plaats van er een tweede naast te zetten. Een `null`-deadline
     * (het datumveld is leeggemaakt) verwijdert de openstaande taak. Een reeds
     * voltooide taak blijft altijd staan — dat is historie.
     *
     * Met `$perEigenaar` verschuift de sleutel naar (entiteit, soort,
     * eigenaar), voor het geval dat blok 5 introduceert: dezelfde entiteit met
     * één openstaande taak per gebruiker (implementatie/05 §8).
     */
    public static function planVoorEntiteit(
        Model $entiteit,
        string $soort,
        string $titel,
        ?CarbonInterface $deadline,
        string $blokNaam,
        ?int $eigenaarId = null,
        bool $perEigenaar = false,
    ): ?Taak {
        $bestaande = self::openstaandeTaken(
            $entiteit,
            $soort,
            $perEigenaar ? $eigenaarId : null,
        )->first();

        if ($deadline === null) {
            $bestaande?->delete();

            return null;
        }

        if ($bestaande) {
            $bestaande->update([
                'titel' => $titel,
                'deadline' => $deadline,
                // Een verzette deadline maakt een verlopen taak weer actueel.
                'status' => $deadline->isFuture() && $bestaande->status === 'verlopen'
                    ? 'open'
                    : $bestaande->status,
            ]);

            return $bestaande;
        }

        return Taak::create([
            'titel' => $titel,
            'deadline' => $deadline,
            'eigenaar_id' => $eigenaarId,
            'soort' => $soort,
            'gekoppeld_blok_naam' => $blokNaam,
            'gekoppeld_entiteit_type' => $entiteit->getMorphClass(),
            'gekoppeld_entiteit_id' => $entiteit->getKey(),
        ]);
    }

    /**
     * Sluit de openstaande taak omdat de handeling verricht is — niet omdat de
     * deadline verviel. Gebruikt door blok 5 zodra een leesbevestiging binnen
     * is (implementatie/05 §8).
     */
    public static function voltooiVoorEntiteit(
        Model $entiteit,
        string $soort,
        ?int $eigenaarId = null,
    ): void {
        foreach (self::openstaandeTaken($entiteit, $soort, $eigenaarId)->get() as $taak) {
            $taak->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        }
    }

    /**
     * De idempotentiesleutel op één plek. `$eigenaarId` versmalt hem naar één
     * taak per gebruiker; zonder eigenaar blijft het één taak per (entiteit,
     * soort), zoals blok 2, 3 en 4 hem gebruiken.
     */
    private static function openstaandeTaken(
        Model $entiteit,
        string $soort,
        ?int $eigenaarId,
    ): EloquentBuilder {
        return Taak::query()
            ->where('gekoppeld_entiteit_type', $entiteit->getMorphClass())
            ->where('gekoppeld_entiteit_id', $entiteit->getKey())
            ->where('soort', $soort)
            ->when($eigenaarId !== null, fn (EloquentBuilder $q) => $q->where('eigenaar_id', $eigenaarId))
            ->whereIn('status', Taak::OPENSTAAND);
    }
}
