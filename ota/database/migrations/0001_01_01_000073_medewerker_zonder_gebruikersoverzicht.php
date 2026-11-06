<?php

use App\Models\Blok;
use App\Models\Rol;
use App\Models\RolPermissie;
use Illuminate\Database\Migrations\Migration;

/**
 * De Medewerker verliest `lezen` op `identity-access` (Chrometoets 24-09-2026,
 * B9): die rij ontsloot alleen het volledige gebruikersoverzicht, en het eigen
 * profiel staat onder /settings zonder blokrecht.
 *
 * Een migratie en niet alleen het seeder: `RolPermissieSeeder` voegt toe met
 * `updateOrCreate` en haalt nooit iets weg, dus op een bestaande installatie
 * zou de rij blijven staan.
 *
 * Via het model en niet via `DB::table`: `RolPermissie::deleted` wist de
 * autorisatiecache. Met een kale delete bleef de oude toegang staan tot die
 * cache vanzelf verliep.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rol = Rol::where('naam', 'Medewerker')->value('id');
        $blok = Blok::where('code', 'identity-access')->value('id');

        if ($rol === null || $blok === null) {
            return;
        }

        RolPermissie::where('rol_id', $rol)
            ->where('blok_id', $blok)
            ->where('niveau', 'lezen')
            ->get()
            ->each->delete();
    }

    public function down(): void
    {
        // Bewust leeg: de rij terugzetten is het gat heropenen.
    }
};
