<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 3 (Asset & Informatie-classificatie) — implementatie/03-asset-classificatie.md §2.
return new class extends Migration
{
    public function up(): void
    {
        // Referentie-/configuratietabel: 3 dimensies x 4 niveaus. Bevat de
        // omgangsregels per niveau (Annex A 5.10/5.13/5.14).
        Schema::create('classificatieschemas', function (Blueprint $table) {
            $table->id();
            $table->enum('dimensie', ['vertrouwelijkheid', 'integriteit', 'beschikbaarheid']);
            $table->enum('niveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim']);
            $table->text('omschrijving')->nullable();
            $table->text('omgangsregels')->nullable();
            $table->timestamps();
            $table->unique(['dimensie', 'niveau']);
        });

        Schema::create('systemen', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('hostingtype', ['intern', 'extern']);
            // Vooruitverwijzing naar blok 4.7 (Leveranciers, nog niet gebouwd):
            // bewust GEEN foreignId()->constrained() — die tabel bestaat nog niet.
            $table->unsignedBigInteger('leverancier_id')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('type', ['informatie', 'systeem_of_dienst', 'hardware']);
            $table->text('omschrijving')->nullable();
            $table->foreignId('organisatie_eenheid_id')->constrained('organisatie_eenheden');
            // Eigenaarschap is puur informatief (RACI), geen systeemrecht — §4.
            $table->foreignId('accountable_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->enum('status', ['geregistreerd', 'actief', 'buiten_gebruik', 'afgestoten'])
                ->default('geregistreerd');
            // Assets buiten scope worden expliciet gemarkeerd, niet weggelaten (contract met blok 2).
            $table->boolean('binnen_scope')->default(true);
            // Drie losse enum-kolommen i.p.v. FK's naar classificatieschemas: alle
            // dimensies delen dezelfde vier niveaus, een FK per dimensie zou een
            // composite-integriteitscheck vereisen zonder meerwaarde (§2).
            $table->enum('vertrouwelijkheidsniveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim'])->nullable();
            $table->enum('integriteitsniveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim'])->nullable();
            $table->enum('beschikbaarheidsniveau', ['openbaar', 'intern', 'vertrouwelijk', 'geheim'])->nullable();
            $table->date('laatst_geclassificeerd_op')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_systeem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('systeem_id')->constrained('systemen')->cascadeOnDelete();
            $table->unique(['asset_id', 'systeem_id']);
        });

        Schema::create('asset_toewijzingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->constrained('gebruikers');
            $table->date('toegewezen_op');
            $table->date('geretourneerd_op')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_toewijzingen');
        Schema::dropIfExists('asset_systeem');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('systemen');
        Schema::dropIfExists('classificatieschemas');
    }
};
