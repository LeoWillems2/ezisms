<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 12e stap 7 — implementatie/12e §9: een meegeleverde streefwaarde is een
// voorstel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            // Leeg = de streefwaarde is een voorstel en geen vastgesteld beleid.
            // Zolang die leeg is telt de streefwaarde nergens mee: de status
            // blijft `onbepaald` (nooit groen) en ze gaat niet mee de meetrij in.
            //
            // De reden is 12d §2c: een meegeleverde streefwaarde wordt bij de
            // eerste audit als vastgesteld beleid gelezen, en "die stond er al"
            // is geen antwoord op de vraag wie hem heeft vastgesteld. Andersom is
            // een installatie zonder enig voorstel er ook niet mee geholpen: dan
            // staat het hele dashboard grijs en vult niemand iets in.
            //
            // Niet `norm_vastgesteld_op`: "de norm" betekent in dit systeem al
            // ISO 27001 / NEN 7510 — zie "normtekst" op /soa en "Normatieve
            // scope" op de auditronde. Dezelfde soort botsing als
            // `drempelwaarde` in 12d §2.
            $table->date('streefwaarde_vastgesteld_op')->nullable()->after('signaalwaarde');
        });

        // Bewust géén backfill. De streefwaarden die er nu staan komen uit de
        // seeder en zijn door niemand vastgesteld; ze met terugwerkende kracht
        // tot beleid verklaren is precies wat deze kolom moet voorkomen.
        //
        // Wie hem wél als vastgesteld beschouwt, doet dat met één klik per KPI
        // in het beheerscherm — een handeling die in de audit trail belandt.
    }

    public function down(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->dropColumn('streefwaarde_vastgesteld_op');
        });
    }
};
