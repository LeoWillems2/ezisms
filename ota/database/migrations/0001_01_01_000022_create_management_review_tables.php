<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 13 (Management Review & Verbetercyclus) — implementatie/13 §3.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewsessies', function (Blueprint $table) {
            $table->id();
            $table->date('datum');
            // Vrije tekst, geen managementrol-koppeling — zelfde oplossing als blok 2/11.
            $table->text('deelnemers')->nullable();
            $table->enum('status', ['gepland', 'gehouden'])->default('gepland');
            $table->timestamps();
        });

        Schema::create('agendapunten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewsessie_id')->constrained('reviewsessies')->cascadeOnDelete();
            // De negen verplichte §9.3-inputs.
            $table->enum('categorie', [
                'status_vorige_acties', 'context_wijzigingen', 'belanghebbende_feedback',
                'kpi_resultaten', 'auditresultaten', 'non_conformiteiten',
                'monitoring_resultaten', 'verbeterkansen', 'risico_resultaten',
            ]);
            // Handmatig ingevuld (geen auto-aggregatie, §1).
            $table->text('samenvatting');
            // Optionele verwijzing naar het bronblok (blokken.code), puur als context.
            $table->string('gekoppeld_blok_naam')->nullable();
            $table->timestamps();

            // Eén samenvatting per verplichte input — basis voor de volledigheidscheck (§4).
            $table->unique(['reviewsessie_id', 'categorie'], 'agendapunt_sessie_categorie_unique');
        });

        Schema::create('besluiten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewsessie_id')->constrained('reviewsessies')->cascadeOnDelete();
            $table->text('omschrijving');
            $table->timestamps();
        });

        Schema::create('verbeteracties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('besluit_id')->constrained('besluiten')->cascadeOnDelete();
            $table->text('omschrijving');
            // Informatief (wie is verantwoordelijk); geen systeemrecht (§8).
            $table->foreignId('eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->enum('status', ['open', 'voltooid'])->default('open');
            $table->date('voltooid_op')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbeteracties');
        Schema::dropIfExists('besluiten');
        Schema::dropIfExists('agendapunten');
        Schema::dropIfExists('reviewsessies');
    }
};
