<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annex A.8.14 (Redundantie van informatieverwerkende faciliteiten) verlangt dat
 * de organisatie "eisen identificeert voor de beschikbaarheid van zakelijke
 * diensten en informatiesystemen" en met passende redundantie aan die eisen
 * voldoet. Tot nu toe bestond beschikbaarheid alleen als classificatielabel op
 * de asset; de eis op systeemniveau — waar A.8.14 hem legt — ontbrak.
 *
 * Daarom per systeem: de beschikbaarheidseis, of er redundantie is en hoe. Beide
 * nullable: 'nog niet bepaald' is zelf een gap (A.8.14 vraagt juist om
 * identificatie), niet hetzelfde als 'niet kritiek' of 'geen redundantie'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systemen', function (Blueprint $table) {
            $table->enum('beschikbaarheidseis', ['niet_kritiek', 'normaal', 'hoog', 'bedrijfskritiek'])
                ->nullable()->after('hostingtype');
            $table->boolean('redundant')->nullable()->after('beschikbaarheidseis');
            $table->string('redundantie_toelichting')->nullable()->after('redundant');
        });
    }

    public function down(): void
    {
        Schema::table('systemen', function (Blueprint $table) {
            $table->dropColumn(['beschikbaarheidseis', 'redundant', 'redundantie_toelichting']);
        });
    }
};
