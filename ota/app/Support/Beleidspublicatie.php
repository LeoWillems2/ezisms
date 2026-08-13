<?php

namespace App\Support;

use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * De enige plek die een beleidsversie op `actief` zet (implementatie/05 §4).
 *
 * Dat is geen stijlvoorkeur maar de manier waarop de invariant "hooguit één
 * actieve versie per document" wordt afgedwongen. MySQL kent geen partiële
 * unique index, en de trucs die dat nabootsen (gegenereerde kolom die NULL is
 * behalve bij 'actief') maken het schema minder leesbaar dan de invariant waard
 * is. Zet elders dus nooit rechtstreeks `status = 'actief'`.
 *
 * Dezelfde soort afspraak als de append-only audit trail: geldig in het model,
 * niet in de database.
 */
final class Beleidspublicatie
{
    /**
     * Publiceert een versie: vervangt de vorige en legt de goedkeuring vast.
     * De drie stappen horen bij elkaar — half uitgevoerd levert twee actieve
     * versies of geen enkele op, en dat is erger dan niets doen.
     *
     * @throws ValidationException
     */
    public static function publiceer(Beleidsversie $versie, Gebruiker $goedkeurder): void
    {
        if ($versie->status !== 'ter_goedkeuring') {
            throw ValidationException::withMessages([
                'publicatie' => 'Alleen een versie die ter goedkeuring ligt kan worden gepubliceerd.',
            ]);
        }

        // Publiceren zonder bestand is geen beleid: er is dan niets om te
        // lezen, en de leesbevestiging wordt een lege handeling.
        if ($versie->bewijsstuk_id === null) {
            throw ValidationException::withMessages([
                'publicatie' => 'Koppel eerst het documentbestand: een versie zonder bestand kan niet worden gepubliceerd.',
            ]);
        }

        DB::transaction(function () use ($versie, $goedkeurder) {
            // Hooguit één rij, dus ->save() per model en geen massa-update op de
            // query builder: die vuurt geen Eloquent-events en de
            // statuswijziging zou buiten de audit trail vallen. Wordt het er
            // ooit meer, dan updateGeaudit().
            $versie->document->versies()
                ->where('status', 'actief')
                ->where('id', '!=', $versie->id)
                ->get()
                ->each->update(['status' => 'vervangen']);

            $versie->update([
                'status' => 'actief',
                'gepubliceerd_op' => now(),
                'goedgekeurd_door_id' => $goedkeurder->id,
                'goedgekeurd_op' => now(),
            ]);
        });
    }
}
