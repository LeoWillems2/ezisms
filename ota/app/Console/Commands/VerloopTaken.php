<?php

namespace App\Console\Commands;

use App\Mail\TaakGeescaleerd;
use App\Models\Taak;
use App\Support\NotificatieDispatcher;
use Illuminate\Console\Command;

/**
 * Zet verstreken taken op 'verlopen' en escaleert ze (implementatie/07 §5).
 *
 * Dit is bewust géén observer: `verlopen` volgt uit het verstrijken van tijd,
 * niet uit een gewijzigd veld, dus een observer zou nooit vuren.
 *
 * Escaleren betekent hier niet "doorzetten naar iemand anders" — er is geen rol
 * boven de CISO (deelproducten/07 §7). Niveau 1 en 2 zijn puur zichtbaarheid.
 */
class VerloopTaken extends Command
{
    protected $signature = 'isms:verloop-taken';

    protected $description = 'Markeert verstreken taken als verlopen en verhoogt het escalatieniveau';

    /** Dagen over de deadline voordat een taak naar niveau 2 gaat. */
    private const ESCALATIE_NA_DAGEN = 14;

    public function handle(): int
    {
        // updateGeaudit i.p.v. update: een massa-update omzeilt de
        // Eloquent-events en dus de audit trail, terwijl juist het verlopen en
        // escaleren herleidbaar moet zijn.
        $verlopen = Taak::whereIn('status', ['open', 'in_uitvoering'])
            ->whereDate('deadline', '<', now())
            ->updateGeaudit([
                'status' => 'verlopen',
                'escalatie_niveau' => 1,
                'escalatie_op' => now(),
            ]);

        // Per taak i.p.v. één massa-update, omdat elke overgang naar niveau 2
        // ook een escalatie-mail uitzendt (implementatie/14 §5). De `update()`
        // per model raakt — net als updateGeaudit — de observers en de audit
        // trail. De filter op escalatie_niveau=1 maakt het idempotent: een taak
        // die al op niveau 2 staat matcht niet meer, dus de dagelijkse sweep
        // mailt niet opnieuw.
        $teEscaleren = Taak::where('status', 'verlopen')
            ->where('escalatie_niveau', 1)
            ->whereDate('deadline', '<=', now()->subDays(self::ESCALATIE_NA_DAGEN))
            ->get();

        foreach ($teEscaleren as $taak) {
            $taak->update(['escalatie_niveau' => 2, 'escalatie_op' => now()]);
            NotificatieDispatcher::verzend('taak_geescaleerd', new TaakGeescaleerd($taak));
        }

        $this->info("{$verlopen} taak/taken verlopen, {$teEscaleren->count()} geëscaleerd naar niveau 2.");

        return self::SUCCESS;
    }
}
