<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 9 (Leveranciers- & Derdenrisico) — implementatie/09-leveranciers-derdenrisico.md §3.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leveranciers', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('status', ['kandidaat', 'actief', 'beeindigd'])->default('kandidaat');
            // Grof registerlabel om op te filteren/rapporteren — géén tweede
            // risicomatrix; een echte beoordeling wordt een Risico (blok 4, §4).
            $table->enum('risiconiveau', ['laag', 'midden', 'hoog'])->nullable();
            // Een geldig eigen ISO 27001-certificaat is een tweede manier om
            // "recht op audit" aan te tonen (§3, deelproducten/09 §7).
            $table->date('eigen_certificering_geldig_tot')->nullable();
            // De beëindigingscontrole (§5): beeindigd mag alleen met bevestigde
            // teruggave; deze drie worden bij die overgang gezet en niet meer
            // overschreven.
            $table->date('beeindigd_op')->nullable();
            $table->timestamp('data_teruggave_bevestigd_op')->nullable();
            $table->foreignId('data_teruggave_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('diensten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leverancier_id')->constrained('leveranciers')->cascadeOnDelete();
            $table->string('omschrijving');
            $table->timestamps();
        });

        Schema::create('dienst_systeem', function (Blueprint $table) {
            $table->foreignId('dienst_id')->constrained('diensten')->cascadeOnDelete();
            $table->foreignId('systeem_id')->constrained('systemen')->cascadeOnDelete();
            $table->primary(['dienst_id', 'systeem_id']);
        });

        Schema::create('leveranciersbeoordelingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leverancier_id')->constrained('leveranciers')->cascadeOnDelete();
            $table->date('uitgevoerd_op');
            $table->text('bevindingen')->nullable();
            $table->date('volgende_beoordeling_gepland')->nullable();
            $table->foreignId('uitgevoerd_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contractclausules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leverancier_id')->constrained('leveranciers')->cascadeOnDelete();
            $table->enum('type', ['vertrouwelijkheid', 'recht_op_audit', 'sla', 'incidentmeldplicht']);
            $table->boolean('aanwezig')->default(false);
            $table->timestamps();
            $table->unique(['leverancier_id', 'type']);
        });

        // De twee vooruitverwijzingen die sinds blok 3 en 4 openstonden krijgen
        // nu hun constraint. nullOnDelete en niet cascade: een leverancier
        // verwijderen mag nooit een systeem of risico wissen — die raken alleen
        // hun leverancierskoppeling kwijt (§3).
        Schema::table('systemen', function (Blueprint $table) {
            $table->foreign('leverancier_id')->references('id')->on('leveranciers')->nullOnDelete();
        });

        Schema::table('risicos', function (Blueprint $table) {
            $table->foreign('gekoppeld_leverancier_id')->references('id')->on('leveranciers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('risicos', function (Blueprint $table) {
            $table->dropForeign(['gekoppeld_leverancier_id']);
        });

        Schema::table('systemen', function (Blueprint $table) {
            $table->dropForeign(['leverancier_id']);
        });

        Schema::dropIfExists('contractclausules');
        Schema::dropIfExists('leveranciersbeoordelingen');
        Schema::dropIfExists('dienst_systeem');
        Schema::dropIfExists('diensten');
        Schema::dropIfExists('leveranciers');
    }
};
