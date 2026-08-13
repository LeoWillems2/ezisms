<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 12e stap 6 — implementatie/12e §3 en §5: handmatig ingevoerde meetpunten.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metingen', function (Blueprint $table) {
            // Null = vastgelegd door `isms:meet-kpis`. Gevuld bij een handmatig
            // ingevoerd meetpunt.
            //
            // Eén kolom in plaats van de `Auditeerbaar`-trait op `Meting`: een
            // meting wordt nooit gewijzigd, dus een trail met een oude en een
            // nieuwe waarde voegt niets toe. De enige vraag is wie hem invoerde.
            $table->foreignId('ingevoerd_door_id')->nullable()->after('toelichting')
                ->constrained('gebruikers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metingen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingevoerd_door_id');
        });
    }
};
