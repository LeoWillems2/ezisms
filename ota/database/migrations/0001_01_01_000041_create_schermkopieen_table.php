<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wat er als schermkopie is meegegeven aan een auditor (implementatie/12h §9).
 *
 * Bewust een eigen tabel en niet `audit_logregels`: die gaat over wijzigingen
 * aan het ISMS, en een kopie wijzigt niets. Dezelfde afweging als bij
 * `raadplegingen`, en om dezelfde reden — beide leesbaar houden.
 *
 * Het nut zit in de optelsom. Op een auditdag gaan er makkelijk tien schermen
 * mee; deze lijst ís het overdrachtsdossier, en beantwoordt achteraf de vraag
 * "wat hebben wij die auditor gegeven" zonder dat er ergens een pakket bewaard
 * hoeft te worden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schermkopieen', function (Blueprint $table) {
            $table->id();
            $table->string('scherm');
            // De actieve filters als label => waarde. Null is niet hetzelfde als
            // een lege lijst: null betekent "dit scherm kent geen filters".
            $table->json('filters')->nullable();
            $table->unsignedInteger('aantal_rijen');
            $table->unsignedInteger('totaal_rijen');
            // Niet welke persoonsgegevens, alleen dát ze erin zaten: de kopie
            // zelf wordt niet bewaard (12h §8), en de vraag die beantwoordbaar
            // moet blijven is of er persoonsgegevens de deur uit zijn gegaan.
            $table->boolean('met_persoonsgegevens')->default(false);
            $table->foreignId('gebruiker_id')->constrained('gebruikers');
            $table->timestamp('gemaakt_op');

            $table->index('gemaakt_op');

            // Geen created_at/updated_at: `gemaakt_op` ís het tijdstip, en een
            // updated_at suggereert dat een vastlegging te wijzigen valt.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schermkopieen');
    }
};
