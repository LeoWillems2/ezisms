<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 11d (naschrift) — implementatie/11d-behandeling-per-auditobject.md §12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditronde_auditobject', function (Blueprint $table) {
            // Dezelfde uitweg als bij een bevinding: "geen opmerkingen" kan ook
            // uit eigen onderzoek komen — een procedure nagelezen, een export
            // bekeken — en dan is er geen gesprekspartner om te noemen. De bron
            // blijft verplicht; alleen is "ik heb het zelf nagelopen" nu een
            // geldig antwoord in plaats van een verzonnen naam.
            $table->boolean('eigen_waarneming')->default(false)->after('gesproken_met_id');
        });
    }

    public function down(): void
    {
        Schema::table('auditronde_auditobject', function (Blueprint $table) {
            $table->dropColumn('eigen_waarneming');
        });
    }
};
