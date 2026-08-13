<?php

namespace App\Support;

use App\Models\Afwijking;
use App\Models\CorrigerendeMaatregel;
use App\Models\Gebruiker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * De enige plek die een afwijking sluit (implementatie/08 §5).
 *
 * De rest van de statemachine is afgeleid — `analyse` en `actie_lopend` volgen
 * uit wat eronder hangt. `gesloten` niet: dat is een managementbesluit waarin
 * iemand vaststelt dat de afwijking is weggenomen en daar zijn naam onder zet.
 * Automatisch sluiten zodra de laatste toets op `effectief` staat maakt van dat
 * besluit een bijproduct van een formulier, en levert geen antwoord op de vraag
 * die §10.2 stelt: wie stelde dit vast, en wanneer.
 *
 * Zelfde soort afspraak als Beleidspublicatie in blok 5.
 */
final class Afwijkingafsluiting
{
    /**
     * De reden waarom sluiten (nog) niet kan, of `null` wanneer het wel kan.
     *
     * Apart van sluiten() zodat het scherm de reden kan tonen in plaats van een
     * grijze knop zonder uitleg — bij een afwijking die al maanden loopt is
     * "waarom kan dit niet" de eerste vraag.
     */
    public static function belemmering(Afwijking $afwijking): ?string
    {
        if ($afwijking->isGesloten()) {
            return 'Deze afwijking is al gesloten.';
        }

        $maatregelen = $afwijking->maatregelen()->with('laatsteToets')->get();

        if ($maatregelen->isEmpty()) {
            return 'Er is nog geen corrigerende maatregel vastgelegd. Een afwijking sluiten zonder maatregel is de constatering wegstrepen, niet de oorzaak.';
        }

        $onvoltooid = $maatregelen->where('status', '!=', 'voltooid')->count();

        if ($onvoltooid > 0) {
            return "Er zijn nog {$onvoltooid} maatregel(en) niet voltooid.";
        }

        $ongetoetst = $maatregelen
            ->reject(fn (CorrigerendeMaatregel $m) => $m->isEffectiefBevonden())
            ->count();

        if ($ongetoetst > 0) {
            return "Er zijn nog {$ongetoetst} maatregel(en) zonder effectiviteitstoets met resultaat 'effectief'.";
        }

        return null;
    }

    /** @throws ValidationException */
    public static function sluit(Afwijking $afwijking, Gebruiker $sluiter): void
    {
        $belemmering = self::belemmering($afwijking);

        if ($belemmering !== null) {
            throw ValidationException::withMessages(['afsluiting' => $belemmering]);
        }

        DB::transaction(function () use ($afwijking, $sluiter) {
            $afwijking->update([
                'gesloten_op' => now(),
                'gesloten_door_id' => $sluiter->id,
                'status' => 'gesloten',
            ]);
        });
    }
}
