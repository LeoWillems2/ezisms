<?php

namespace App\Console\Commands;

use App\Models\Auditobject;
use App\Models\SoaRegel;
use Illuminate\Console\Command;

/**
 * Synchroniseert de maatregel-objecten in de audit-universe met de SoA (plan 11b
 * §3). Elke maatregel die *van toepassing* is krijgt/behoudt een actief
 * auditobject (soort=maatregel); een control die niet (meer) van toepassing is
 * wordt inactief. Bewust geen kopie van de normtekst — alleen een verwijzing.
 *
 * Idempotent. Meldt hoeveel objecten nieuw zijn: dat zijn de mid-cyclus alsnog
 * van-toepassing geworden controls die nog in geen enkel programma zitten
 * (drift zichtbaar maken).
 */
class SyncAuditobjecten extends Command
{
    protected $signature = 'isms:sync-auditobjecten';

    protected $description = 'Synchroniseert de maatregel-objecten van de audit-universe met de van-toepassing-verklaarde SoA';

    public function handle(): int
    {
        $vanToepassing = SoaRegel::with('maatregel')
            ->where('van_toepassing', true)
            ->get()
            ->pluck('maatregel')
            ->filter();

        $nieuw = 0;
        $gehouden = 0;
        $actieveIds = [];

        foreach ($vanToepassing as $maatregel) {
            $bestond = Auditobject::where('soort', 'maatregel')
                ->where('maatregel_id', $maatregel->id)
                ->exists();

            // Zelfde afleiding (groep/volgorde) als de live SoaRegel-observer.
            Auditobject::synchroniseerMaatregel($maatregel, true);

            $actieveIds[] = Auditobject::where('soort', 'maatregel')
                ->where('maatregel_id', $maatregel->id)
                ->value('id');
            $bestond ? $gehouden++ : $nieuw++;
        }

        // Alles wat niet (meer) van toepassing is → inactief, niet verwijderd:
        // een gekoppelde ronde/historie blijft zo intact.
        $gedeactiveerd = Auditobject::where('soort', 'maatregel')
            ->where('actief', true)
            ->when($actieveIds !== [], fn ($q) => $q->whereNotIn('id', $actieveIds))
            ->update(['actief' => false]);

        $this->info("Audit-universe gesynchroniseerd: {$nieuw} nieuw, {$gehouden} ongewijzigd, {$gedeactiveerd} gedeactiveerd.");

        if ($nieuw > 0) {
            $this->warn("{$nieuw} control(s) zijn nieuw van toepassing en zitten nog in geen enkel auditprogramma.");
        }

        return self::SUCCESS;
    }
}
