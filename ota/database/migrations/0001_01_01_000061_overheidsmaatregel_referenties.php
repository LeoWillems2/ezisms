<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twee referentievelden per BIO-verplichting
 * (deelproducten/04c-bio-verplichtingen-zichtbaar.md §2).
 *
 * Dezelfde namen, hetzelfde type en dezelfde lengte als op `soa_regels`
 * (migratie 000024). Dat is geen kopieerwerk maar het punt: het is dezelfde
 * handeling, één niveau lager. "Hoofdstuk 4.2 van het wachtwoordbeleid" is het
 * antwoord op dezelfde vraag, alleen bij 5.24.03 in plaats van bij 5.24. Twee
 * verschillende woorden voor één veld valt pas op bij de export.
 *
 * Vrije tekst en geen koppeling naar een beleidsdocument: die pivot bestaat één
 * niveau hoger, op de SoA-regel, en daar hoort hij ook — beleid dekt zelden
 * precies één verplichting. Wat per verplichting verschilt is de vindplaats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overheidsmaatregel_beoordelingen', function (Blueprint $table) {
            $table->string('beleidreferentie')->nullable()->after('motivatie');
            $table->string('procesreferentie')->nullable()->after('beleidreferentie');
        });
    }

    public function down(): void
    {
        Schema::table('overheidsmaatregel_beoordelingen', function (Blueprint $table) {
            $table->dropColumn(['beleidreferentie', 'procesreferentie']);
        });
    }
};
