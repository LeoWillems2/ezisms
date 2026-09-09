<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 11d (naschrift) — implementatie/11d-behandeling-per-auditobject.md §12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bevindingen', function (Blueprint $table) {
            // "Gesproken met" is verplicht geworden, en dan moet "er is niet
            // gesproken" een antwoord zijn en geen leeg veld. Zonder deze vlag is
            // een lege `gesproken_met_id` dubbelzinnig: niet vastgelegd, of
            // bewust geen gesprek? Een auditor die iets in de logbestanden ziet
            // heeft geen gesprekspartner, en die mag geen naam hoeven verzinnen.
            $table->boolean('eigen_waarneming')->default(false)->after('gesproken_met_id');
        });
    }

    public function down(): void
    {
        Schema::table('bevindingen', function (Blueprint $table) {
            $table->dropColumn('eigen_waarneming');
        });
    }
};
