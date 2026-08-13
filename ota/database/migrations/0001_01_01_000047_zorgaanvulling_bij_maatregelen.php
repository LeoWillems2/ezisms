<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De zorgspecifieke aanvulling per maatregel — implementatie/04e §3.
 *
 * NEN 7510-1:2024 Bijlage A telt 101 maatregelen (de 93 van ISO plus 8), en 22
 * daarvan dragen een zorgspecifieke beheersmaatregel. Die aanvullende tekst
 * krijgt een eigen kolom en gaat niet bij de omschrijving in: bron,
 * licentiestatus en voorbehoud verschillen per blok en dat moet zichtbaar
 * blijven.
 *
 * Op `maatregelen` en niet op `soa_regels`: dit is normtekst en dus
 * referentiedata, geen uitspraak van de organisatie. Vergelijk migratie `000033`
 * (04d), waar de eigen classificatie juist wél naar de SoA-regel ging.
 *
 * Drie toestanden, en het onderscheid tussen de eerste twee is de hele reden dat
 * de kolom nullable is:
 *
 *  - `null` — niets ingelezen; dan is niet eens bekend wélke maatregelen er een
 *    hebben. Het scherm meldt dat het seedbestand ontbreekt.
 *  - `DO NOT TOUCH` — ingelezen, deze maatregel heeft geen aanvulling. Geen
 *    blok. (Was een lege string tot 05-08-2026; die telt nog steeds mee.)
 *  - tekst  — deze maatregel heeft er wél een. Sinds 05-08-2026 is dat altijd
 *    de vaste mededeling: het ISMS levert de normtekst niet mee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maatregelen', function (Blueprint $table) {
            $table->text('zorgaanvulling')->nullable()->after('omschrijving');
        });
    }

    public function down(): void
    {
        Schema::table('maatregelen', function (Blueprint $table) {
            $table->dropColumn('zorgaanvulling');
        });
    }
};
