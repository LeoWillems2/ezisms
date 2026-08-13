<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 8 (Incident- & Afwijkingenbeheer) — implementatie/08 §3.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidenten', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->text('omschrijving')->nullable();
            $table->foreignId('gemeld_door_id')->constrained('gebruikers');
            $table->timestamp('gemeld_op');
            $table->enum('ernst', ['laag', 'midden', 'hoog', 'kritiek']);
            // Bewust GEEN afgeleid veld, anders dan bij de afwijking (§6): een
            // melding kan opgelost zijn zonder dat er ooit een afwijking uit
            // voortkwam.
            $table->enum('status', ['gemeld', 'in_onderzoek', 'opgelost', 'gesloten'])
                ->default('gemeld');
            // Nullable FK's mét constraint — anders dan systemen.leverancier_id,
            // want deze blokken bestaan al.
            $table->foreignId('gekoppeld_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('gekoppeld_risico_id')->nullable()->constrained('risicos')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'ernst'], 'inc_status_ernst_index');
        });

        Schema::create('afwijkingen', function (Blueprint $table) {
            $table->id();
            $table->enum('bron', ['audit_bevinding', 'incident', 'interne_signalering']);
            $table->text('omschrijving');
            // Afgeleid uit grondoorzaken en maatregelen, behalve 'gesloten' (§5).
            $table->enum('status', ['open', 'analyse', 'actie_lopend', 'gesloten'])->default('open');
            // Niet elk incident wordt een afwijking, dus nullable.
            $table->foreignId('incident_id')->nullable()->constrained('incidenten')->nullOnDelete();
            $table->foreignId('eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Sluiten is een expliciete daad, geen bijproduct van een formulier.
            $table->foreignId('gesloten_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamp('gesloten_op')->nullable();
            $table->timestamps();
        });

        Schema::create('grondoorzaken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afwijking_id')->constrained('afwijkingen')->cascadeOnDelete();
            $table->text('omschrijving');
            // Vrij tekstveld: deelproducten/08 §7 stelt bewust geen methode
            // verplicht (5x-waarom, Ishikawa).
            $table->string('methodiek')->nullable();
            $table->timestamps();
        });

        Schema::create('corrigerende_maatregelen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afwijking_id')->constrained('afwijkingen')->cascadeOnDelete();
            $table->text('omschrijving');
            $table->foreignId('eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->enum('status', ['open', 'in_uitvoering', 'voltooid'])->default('open');
            $table->date('voltooid_op')->nullable();
            $table->timestamps();
        });

        Schema::create('effectiviteitstoetsen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrigerende_maatregel_id')
                ->constrained('corrigerende_maatregelen')->cascadeOnDelete();
            $table->date('uitgevoerd_op');
            $table->enum('resultaat', ['effectief', 'niet_effectief']);
            $table->text('toelichting')->nullable();
            // Afwijking van het deelproduct (§4a): een oordeel zonder oordelaar
            // is geen bewijs.
            $table->foreignId('uitgevoerd_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('effectiviteitstoetsen');
        Schema::dropIfExists('corrigerende_maatregelen');
        Schema::dropIfExists('grondoorzaken');
        Schema::dropIfExists('afwijkingen');
        Schema::dropIfExists('incidenten');
    }
};
