<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wanneer een versie ter goedkeuring is aangeboden (implementatie/05b §2).
 *
 * Waarom een eigen kolom en niet `updated_at`: de goedkeuringstaak krijgt zijn
 * deadline hiervandaan, en `TaakPlanner` verzet die deadline bij elke aanroep.
 * Zou hij op `updated_at` staan, dan schuift de termijn mee met elke opslag van
 * de versie en verloopt de taak nooit — dezelfde valkuil die de leestermijn aan
 * `gepubliceerd_op` knoopt in plaats van aan vandaag (05 §8).
 *
 * Het is bovendien het antwoord op "sinds wanneer ligt dit er": de datum die
 * een auditor bij een trage vaststelling als eerste vraagt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beleidsversies', function (Blueprint $tabel) {
            $tabel->timestamp('aangeboden_op')->nullable()->after('status');
        });

        // Terugvulling voor wat nu al ter goedkeuring ligt: `updated_at` is de
        // best beschikbare waarheid — de laatste opslag ván die versie was in
        // de praktijk de statuswijziging. Bij de overige versies blijft de
        // kolom leeg; daar is de aanbieding ofwel nooit gebeurd, ofwel allang
        // afgehandeld en staat het antwoord in `goedgekeurd_op`.
        DB::table('beleidsversies')
            ->where('status', 'ter_goedkeuring')
            ->update(['aangeboden_op' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('beleidsversies', function (Blueprint $tabel) {
            $tabel->dropColumn('aangeboden_op');
        });
    }
};
