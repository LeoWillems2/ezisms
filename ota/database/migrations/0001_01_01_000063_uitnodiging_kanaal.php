<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Via welk kanaal de uitnodiging is uitgereikt (implementatie/01i §2).
 *
 * Waarom een eigen kolom en geen afleiding uit de configuratie: die kan na een
 * verhuizing anders staan dan op het moment dat de uitnodiging de deur uit
 * ging. En zonder de kolom meldt de lijst "Verstuurd op 29-08" over een
 * uitnodiging die met de hand op een USB-stick is meegegeven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->string('uitnodiging_kanaal')->nullable()->after('uitnodiging_verstuurd_op');
        });

        // Alles wat tot nu toe is uitgereikt, ging over de mail: de handmatige
        // weg bestond niet. Rijen zonder datum blijven leeg — daar is niets
        // uitgereikt, en een derde waarde "onbekend" zou alleen ruis zijn.
        DB::table('gebruikers')
            ->whereNotNull('uitnodiging_verstuurd_op')
            ->update(['uitnodiging_kanaal' => 'mail']);
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->dropColumn('uitnodiging_kanaal');
        });
    }
};
