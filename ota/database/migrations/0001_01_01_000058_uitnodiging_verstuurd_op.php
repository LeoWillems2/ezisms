<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wanneer de uitnodiging voor het laatst is verstuurd (implementatie/01g §1).
 *
 * Waarom niet `created_at`: *Uitnodiging opnieuw versturen* en de correctie uit
 * 01g geven allebei een nieuwe link uit zonder dat `created_at` verandert.
 * Zonder eigen kolom zou een account dat gisteren nog een verse uitnodiging
 * kreeg, morgen als "verlopen" in de lijst staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->timestamp('uitnodiging_verstuurd_op')->nullable()->after('email_geverifieerd_op');
        });

        // Terugvulling voor wie nu open staat: `created_at` is de best
        // beschikbare waarheid en maakt het signaal uit §4 meteen bruikbaar.
        // Voor de rest blijft de kolom null — daar is nooit een uitnodiging
        // verstuurd, dus er hoort geen datum te staan.
        DB::table('gebruikers')
            ->where('status', 'uitgenodigd')
            ->update(['uitnodiging_verstuurd_op' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->dropColumn('uitnodiging_verstuurd_op');
        });
    }
};
