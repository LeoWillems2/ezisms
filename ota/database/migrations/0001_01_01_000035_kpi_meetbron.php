<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Blok 12e (KPI-beheer) — implementatie/12e §3: de meetbron los van de sleutel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            // null = handmatige KPI: de applicatie rekent niets uit, de CISO
            // voert per periode teller en noemer in. "Berekend zonder meetbron"
            // bestaat niet, dus één kolom volstaat.
            $table->string('meetbron')->nullable()->after('sleutel');
        });

        // Vandaag ís de sleutel de meetbron — `MeetKpis::meet()` schakelde erop.
        // De backfill is dus een rechte kopie en verandert geen enkel bestaand
        // gedrag; dat is precies waarom deze ontkoppeling nú goedkoop is.
        DB::table('kpi_definities')->update(['meetbron' => DB::raw('sleutel')]);
    }

    public function down(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->dropColumn('meetbron');
        });
    }
};
