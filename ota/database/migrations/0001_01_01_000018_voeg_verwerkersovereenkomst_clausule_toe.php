<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een verwerkersovereenkomst (AVG/art. 28 AVG) is voor een leverancier die
 * persoonsgegevens verwerkt — een HR-SaaS bijvoorbeeld — een aparte,
 * securityrelevante afspraak naast de vertrouwelijkheidsclausule. Hij hoorde nog
 * niet in de vaste set (blok 9).
 */
return new class extends Migration
{
    private const NIEUW = ['vertrouwelijkheid', 'recht_op_audit', 'sla', 'incidentmeldplicht', 'verwerkersovereenkomst'];

    private const OUD = ['vertrouwelijkheid', 'recht_op_audit', 'sla', 'incidentmeldplicht'];

    public function up(): void
    {
        Schema::table('contractclausules', function (Blueprint $table) {
            $table->enum('type', self::NIEUW)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contractclausules', function (Blueprint $table) {
            $table->enum('type', self::OUD)->change();
        });
    }
};
