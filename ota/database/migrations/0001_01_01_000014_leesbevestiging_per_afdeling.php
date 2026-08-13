<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De leesbevestigingsplicht was organisatiebreed: elke actieve gebruiker moest
 * elk bevestigingsplichtig document lezen. A.5.1 vraagt om erkenning door
 * "relevant personeel", en dat is preciezer te maken per afdeling.
 *
 * Daarvoor zijn twee koppelingen nodig:
 *  - een gebruiker hoort bij één afdeling (een organisatie-eenheid van type
 *    'afdeling'); nullable, want CISO/auditor/externen hebben er soms geen;
 *  - een document richt zich op één of meer afdelingen.
 *
 * De doelgroep is dan de doorsnede: actieve gebruikers wier afdeling het
 * document heeft aangevinkt (zie Beleidsdocument::doelgroepGebruikerIds()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            // nullOnDelete en niet cascade: een afdeling opheffen mag nooit een
            // gebruikersaccount verwijderen; de gebruiker raakt alleen zijn
            // afdeling kwijt.
            $table->foreignId('organisatie_eenheid_id')->nullable()->after('status')
                ->constrained('organisatie_eenheden')->nullOnDelete();
        });

        Schema::create('beleidsdocument_organisatie_eenheid', function (Blueprint $table) {
            $table->foreignId('beleidsdocument_id')->constrained('beleidsdocumenten')->cascadeOnDelete();

            // Eigen constraintnaam: de naam die Laravel zelf zou verzinnen
            // (tabel + kolom + '_foreign') wordt hier 66 tekens en MySQL staat
            // maximaal 64 toe. Op sqlite — waar de testsuite draait — geldt die
            // limiet niet, dus dit valt alleen op bij een verse migratie op
            // MySQL. Zelfde reden als de expliciete naam van de primary key.
            $table->foreignId('organisatie_eenheid_id');
            $table->foreign('organisatie_eenheid_id', 'bd_oe_organisatie_eenheid_foreign')
                ->references('id')->on('organisatie_eenheden')->cascadeOnDelete();

            $table->primary(['beleidsdocument_id', 'organisatie_eenheid_id'], 'bd_oe_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beleidsdocument_organisatie_eenheid');

        Schema::table('gebruikers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisatie_eenheid_id');
        });
    }
};
