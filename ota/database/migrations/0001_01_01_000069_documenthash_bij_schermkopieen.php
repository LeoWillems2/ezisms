<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 12h — implementatie/12h-schermkopie-voor-de-auditor.md §9.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schermkopieen', function (Blueprint $table) {
            // De sha256 van het meegegeven document. De kopie zelf wordt niet
            // bewaard; deze hash maakt van "er is een document meegegeven" iets
            // dat je kunt náslaan: het bestand dat iemand later voorlegt is dit
            // document, of het is het niet. Nullable, want de kopieën van vóór
            // deze kolom hebben er geen — en er wordt er geen verzonnen.
            $table->char('documenthash', 64)->nullable()->after('met_persoonsgegevens');
        });
    }

    public function down(): void
    {
        Schema::table('schermkopieen', function (Blueprint $table) {
            $table->dropColumn('documenthash');
        });
    }
};
