<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollen', function (Blueprint $table) {
            $table->id();
            $table->string('naam')->unique(); // CISO | Medewerker | Auditor
            $table->string('omschrijving')->nullable();
            $table->timestamps();
        });

        // Referentietabel; groeit mee met elk nieuw gebouwd deelproduct.
        Schema::create('blokken', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // slug, conventies §3, bijv. 'identity-access'
            $table->string('naam');
            $table->timestamps();
        });

        Schema::create('rol_toewijzingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gebruiker_id')->constrained('gebruikers')->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('rollen')->cascadeOnDelete();
            $table->foreignId('toegekend_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamp('toegekend_op');
            $table->timestamps();
            $table->unique(['gebruiker_id', 'rol_id']);
        });

        // Kern van het data-driven autorisatiemodel: geen 'geen'-niveau, het
        // ontbreken van een rij betekent al "geen toegang".
        Schema::create('rol_permissies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('rollen')->cascadeOnDelete();
            $table->foreignId('blok_id')->constrained('blokken')->cascadeOnDelete();
            $table->enum('niveau', ['lezen', 'uitvoeren', 'muteren', 'goedkeuren', 'exporteren']);
            $table->timestamps();
            $table->unique(['rol_id', 'blok_id', 'niveau']);
        });

        // gebruiker_id is nullable: ook een poging met een onbekend e-mailadres
        // moet gelogd kunnen worden.
        Schema::create('loginpogingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gebruiker_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->string('email_ingevoerd');
            $table->timestamp('tijdstip');
            $table->boolean('succesvol');
            $table->string('ip_adres', 45)->nullable();
            $table->timestamps();
            $table->index(['email_ingevoerd', 'tijdstip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loginpogingen');
        Schema::dropIfExists('rol_permissies');
        Schema::dropIfExists('rol_toewijzingen');
        Schema::dropIfExists('blokken');
        Schema::dropIfExists('rollen');
    }
};
