<?php

use App\Models\Beveiligingssignaal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bewaking van misbruik van inloggegevens (implementatie/01l §3).
 *
 * Een signaal is een eigen entiteit en geen regel op de gebruiker: een piek
 * over tien onbekende adressen heeft geen gebruiker om aan te hangen, en
 * `audit_logregels.entiteit_id` is niet nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beveiligingssignalen', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->timestamp('tijdstip')->index();

            // De use cases uit §4. Een enum: de lijst is de definitie.
            $tabel->enum('soort', array_keys(Beveiligingssignaal::SOORTEN));

            // Nullable: use case A gaat over de hele installatie.
            $tabel->foreignId('gebruiker_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $tabel->string('ip_adres', 45)->nullable();

            // Onderdrukking (§5): hetzelfde signaal niet twee keer binnen het venster.
            $tabel->string('sleutel', 120);

            // Aantallen, venster, drukste adressen, netwerkprefix. Geen
            // wachtwoorden en geen codes: die staan ook niet in loginpogingen.
            $tabel->json('details');

            // Bij het aanmaken vastgesteld uit de configuratie (§6.1), dus deel
            // van de regel "aangemaakt" in de trail.
            $tabel->enum('kanaal', ['syslog', 'mail', 'geen']);

            // Alleen bij een fout die we kúnnen zien (TCP, SMTP). Bij UDP blijft
            // dit leeg, en dat betekent "verstuurd", niet "aangekomen".
            $tabel->text('afleverfout')->nullable();

            $tabel->timestamps();
            $tabel->index(['sleutel', 'tijdstip']);
        });

        // De queries uit §4 draaien bij elke inlogpoging.
        Schema::table('loginpogingen', function (Blueprint $tabel) {
            $tabel->index(['succesvol', 'tijdstip']);
            $tabel->index(['gebruiker_id', 'succesvol', 'tijdstip']);
        });
    }

    public function down(): void
    {
        Schema::table('loginpogingen', function (Blueprint $tabel) {
            $tabel->dropIndex(['succesvol', 'tijdstip']);
            $tabel->dropIndex(['gebruiker_id', 'succesvol', 'tijdstip']);
        });

        Schema::dropIfExists('beveiligingssignalen');
    }
};
