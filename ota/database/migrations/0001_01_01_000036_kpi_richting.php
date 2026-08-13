<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Blok 12d fase 1 — implementatie/12d §1: de richting als eigen vlag.
return new class extends Migration
{
    public function up(): void
    {
        // "Welke kant op is goed." Tot nu toe werd dat uit de eenheid afgeleid
        // ('dagen' => omlaag is goed). Dat is een proxy en geen vlag: het
        // koppelt de betekenis van een beweging aan de meeteenheid, en het
        // breekt bij de eerste ratio-KPI waarbij omlaag goed is (12d §4,
        // `bevindingen_open`).
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->enum('richting', ['omhoog', 'omlaag'])->nullable()->after('eenheid');
        });

        // Backfill die het huidige gedrag exact reproduceert, zodat er bij het
        // uitrollen niets zichtbaar verandert. Een richting die afwijkt van wat
        // er gisteren op het scherm stond, laat de historie met terugwerkende
        // kracht anders lezen — precies wat 12 §2c verbiedt.
        DB::table('kpi_definities')->where('eenheid', 'dagen')->update(['richting' => 'omlaag']);
        DB::table('kpi_definities')->whereNull('richting')->update(['richting' => 'omhoog']);

        // Pas ná de backfill verplicht: er bestaat geen KPI waarvan de richting
        // onbekend is, en een stille standaardwaarde zou de eerste omlaag-KPI
        // als achteruitgang laten rapporteren.
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->enum('richting', ['omhoog', 'omlaag'])->nullable(false)->change();
        });

        // `definitie_versie` gaat hier bewust níét omhoog: de berekening
        // verandert niet, alleen de interpretatie van het teken wordt expliciet.
        // Dat veld markeert breuken in de vergelijkbaarheid van de reeks, en die
        // zijn er niet.
    }

    public function down(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->dropColumn('richting');
        });
    }
};
