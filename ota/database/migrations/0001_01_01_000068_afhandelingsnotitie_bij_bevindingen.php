<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 11d (naschrift) — implementatie/11d-behandeling-per-auditobject.md §13.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bevindingen', function (Blueprint $table) {
            // Waaróm deze bevinding dicht kon. `gesloten_op` en `gesloten_door_id`
            // zeggen wie en wanneer; bij de surveillance-audit een jaar later is
            // de vraag wat ermee is gebeurd. Bij een non-conformiteit staat dat
            // antwoord in de afwijking, maar een observatie of verbeterkans heeft
            // geen CAPA-dossier en liet dus geen enkel spoor na.
            $table->text('afhandelingsnotitie')->nullable()->after('gesloten_door_id');
        });
    }

    public function down(): void
    {
        Schema::table('bevindingen', function (Blueprint $table) {
            $table->dropColumn('afhandelingsnotitie');
        });
    }
};
