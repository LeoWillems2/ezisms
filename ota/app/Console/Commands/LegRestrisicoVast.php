<?php

namespace App\Console\Commands;

use App\Models\RestrisicoSnapshot;
use App\Models\RisicocriteriaVersie;
use App\Models\SoaRegel;
use Illuminate\Console\Command;

/**
 * Legt de jaarlijkse restrisico-snapshot per control vast (plan 04c §2). Neemt de
 * fase-1-rollup (max netto-restrisico + aantal distinct risico's) en bevriest die
 * per peiljaar. Onveranderlijk: nooit een bestaand jaar herschrijven — de trend
 * moet aantoonbaar echt zijn, niet met de score van vandaag herrekend.
 *
 * Alleen controls met minstens één gekoppeld risico krijgen een rij: zonder
 * risico valt er niets te trenden (geen getal forceren).
 */
class LegRestrisicoVast extends Command
{
    protected $signature = 'isms:leg-restrisico-vast {--jaar= : Peiljaar om vast te leggen (standaard het huidige jaar)}';

    protected $description = 'Legt de jaarlijkse restrisico-snapshot per control vast (onveranderlijk)';

    /** Versie van de rollup-definitie: max netto-restrisico + distinct risico's. */
    public const DEFINITIE_VERSIE = 1;

    public function handle(): int
    {
        $jaar = (int) ($this->option('jaar') ?: now()->year);

        // Uniek per jaar: een tweede vastlegging zou de bevroren cijfers over-
        // schrijven. Weigeren, niet stilzwijgend aanvullen.
        if (RestrisicoSnapshot::where('peiljaar', $jaar)->exists()) {
            $this->warn("Er bestaat al een restrisico-snapshot voor {$jaar}; niets vastgelegd.");

            return self::FAILURE;
        }

        $aantal = 0;

        foreach (SoaRegel::with('risicobehandelingen')->get() as $regel) {
            if ($regel->aantalGekoppeldeRisicos() === 0) {
                continue;
            }

            RestrisicoSnapshot::create([
                'soa_regel_id' => $regel->id,
                'peiljaar' => $jaar,
                'max_restrisico' => $regel->piekRestrisico(),
                'aantal_risicos' => $regel->aantalGekoppeldeRisicos(),
                'definitie_versie' => self::DEFINITIE_VERSIE,
                // Onder welk criteriakader dit peiljaar gemeten is. Zonder deze
                // stempel suggereert de trendgrafiek vergelijkbaarheid tussen
                // jaren waarin een 4 iets anders betekende (04g §8.3).
                'risicocriteria_versie_id' => RisicocriteriaVersie::actief()?->id,
            ]);

            $aantal++;
        }

        $this->info("{$aantal} restrisico-snapshot(s) vastgelegd voor {$jaar}.");

        return self::SUCCESS;
    }
}
