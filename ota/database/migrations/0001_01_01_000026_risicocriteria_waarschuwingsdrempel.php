<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Maakt de risicocriteria volledig instelbaar: naast de rode acceptatiedrempel
// (bestond al als data) komt de amber-waarschuwingsgrens ook uit de database in
// plaats van een codeconstante (implementatie/04b + risk-appetite-scherm).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risicoacceptatiecriteria', function (Blueprint $table) {
            // Standaard 10, gelijk aan de voormalige Risico::WAARSCHUWINGSDREMPEL,
            // zodat bestaande rijen hun gedrag behouden.
            $table->unsignedTinyInteger('waarschuwingsdrempel_score')
                ->default(10)
                ->after('drempelwaarde_score');
        });
    }

    public function down(): void
    {
        Schema::table('risicoacceptatiecriteria', function (Blueprint $table) {
            $table->dropColumn('waarschuwingsdrempel_score');
        });
    }
};
