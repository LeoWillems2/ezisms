<?php

namespace App\Support;

use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use Illuminate\Support\Facades\Gate;

/**
 * Lost het gat op dat de brug naar blok 6 anders zou hebben
 * (implementatie/05 §5).
 *
 * `DownloadBewijsstuk` staat een download toe bij volledige inzage of bij eigen
 * upload. Het beleidsbestand is door de CISO geüpload, dus een Medewerker zou
 * 403 krijgen op precies het document dat hij moet lezen en bevestigen — geen
 * randgeval, maar de hoofdstroom van blok 5.
 *
 * De afhankelijkheid loopt bewust van 5 naar 6 en niet andersom, net als bij
 * TaakPlanner: blok 6 kent blok 5 niet en roept alleen deze helper aan.
 */
final class Beleidstoegang
{
    /**
     * Bijlage bij een gepubliceerde beleidsversie: leesbaar voor iedereen met
     * leesrecht op beleid, ongeacht wie het bestand heeft geüpload.
     *
     * Alleen `actief`. Vervangen versies en concepten vallen er bewust buiten:
     * historie is auditmateriaal en blijft via de normale route bereikbaar voor
     * `muteren` (CISO) en `exporteren` (Auditor), en een concept hoort niemand
     * te lezen laat staan te bevestigen.
     */
    public static function magBestandLezen(Bewijsstuk $bewijsstuk): bool
    {
        if (! Gate::allows('heeft-niveau', ['beleid-maatregelbeheer', 'lezen'])) {
            return false;
        }

        return Beleidsversie::where('bewijsstuk_id', $bewijsstuk->id)
            ->where('status', 'actief')
            ->exists();
    }
}
