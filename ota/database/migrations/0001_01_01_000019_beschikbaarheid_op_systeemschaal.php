<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De classificatie-triade gebruikte voor álle drie dimensies dezelfde schaal
 * (openbaar/intern/vertrouwelijk/geheim). Dat is een vertrouwelijkheids-
 * vocabulaire; "hoog beschikbaar" laat zich er niet in uitdrukken. De
 * beschikbaarheids-dimensie krijgt daarom dezelfde schaal als de A.8.14-
 * beschikbaarheidseis op een systeem (niet_kritiek/normaal/hoog/bedrijfskritiek),
 * zodat de eis op de asset en die op het systeem één taal spreken.
 *
 * Bestaande beschikbaarheidsclassificaties worden op null gezet: er is geen
 * zuivere vertaling van de oude naar de nieuwe schaal, en opnieuw laten
 * vaststellen is eerlijker dan een gokvertaling. (De ontwikkeldatabase bevat
 * geen handmatig geclassificeerde assets meer.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Reset bestaande waarden vóór de kolomwijziging (geen mapping).
        DB::table('assets')->update(['beschikbaarheidsniveau' => null]);

        // 2. beschikbaarheidsniveau wordt een string (app-gevalideerd), net als
        //    classificatieschemas.niveau hieronder — de schaal kan per dimensie
        //    verschillen, dus een gedeelde enum past niet meer.
        Schema::table('assets', function (Blueprint $table) {
            $table->string('beschikbaarheidsniveau')->nullable()->change();
        });

        // 3. niveau in het schema is niet langer één enum over alle dimensies.
        Schema::table('classificatieschemas', function (Blueprint $table) {
            $table->string('niveau')->change();
        });

        // 4. De oude beschikbaarheid-rijen weg; de seeder maakt ze opnieuw aan in
        //    de nieuwe schaal (updateOrCreate op dimensie+niveau).
        DB::table('classificatieschemas')->where('dimensie', 'beschikbaarheid')->delete();
    }

    public function down(): void
    {
        DB::table('assets')->update(['beschikbaarheidsniveau' => null]);

        Schema::table('assets', function (Blueprint $table) {
            $table->enum('beschikbaarheidsniveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim'])->nullable()->change();
        });

        Schema::table('classificatieschemas', function (Blueprint $table) {
            $table->enum('niveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim'])->change();
        });

        DB::table('classificatieschemas')->where('dimensie', 'beschikbaarheid')->delete();
    }
};
