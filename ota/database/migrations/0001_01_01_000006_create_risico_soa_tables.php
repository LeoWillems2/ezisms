<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 4 (Risicomanagement & Statement of Applicability) — implementatie/04-risico-soa.md §2.
return new class extends Migration
{
    public function up(): void
    {
        // Referentiedata: de 93 Annex A-maatregelen, geïmporteerd uit de
        // normtekst (MaatregelSeeder), niet handmatig ingevoerd.
        Schema::create('maatregelen', function (Blueprint $table) {
            $table->id();
            $table->string('annex_a_referentie')->unique(); // bijv. '5.9'
            $table->enum('thema', ['organisatorisch', 'mensgericht', 'fysiek', 'technologisch']);
            $table->string('naam');
            $table->text('omschrijving')->nullable();
            $table->timestamps();
        });

        Schema::create('soa_regels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maatregel_id')->unique()->constrained('maatregelen')->cascadeOnDelete();
            // Bewust GEEN ->default(): de kolom start op null ("onbeslist"), niet
            // op false. Dat onderscheid is het gap-signaal dat de SoA moet tonen —
            // een onbeoordeelde maatregel mag niet lijken op een bewuste
            // "niet van toepassing" (§2).
            $table->boolean('van_toepassing')->nullable();
            $table->text('motivatie')->nullable();
            $table->enum('implementatiestatus', ['nvt', 'niet_gestart', 'in_uitvoering', 'geimplementeerd'])
                ->default('niet_gestart');
            $table->date('laatst_beoordeeld_op')->nullable();
            $table->timestamps();
        });

        Schema::create('risicoacceptatiecriteria', function (Blueprint $table) {
            $table->id();
            $table->string('omschrijving');
            $table->unsignedTinyInteger('drempelwaarde_score'); // 15 bij een 5x5-matrix
            $table->timestamps();
        });

        Schema::create('risicos', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->text('dreiging')->nullable();
            $table->text('kwetsbaarheid')->nullable();
            $table->foreignId('gekoppeld_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            // Vooruitverwijzing naar blok 4.7 (Leveranciers, nog niet gebouwd):
            // bewust geen FK — zelfde patroon als systemen.leverancier_id.
            $table->unsignedBigInteger('gekoppeld_leverancier_id')->nullable();
            $table->unsignedTinyInteger('kans_niveau')->nullable();   // 1-5
            $table->unsignedTinyInteger('impact_niveau')->nullable(); // 1-5
            $table->unsignedSmallInteger('risicoscore')->nullable();  // afgeleid: kans x impact
            $table->enum('status', [
                'geidentificeerd', 'beoordeeld', 'behandelplan_opgesteld',
                'geaccepteerd', 'in_uitvoering', 'gemitigeerd',
            ])->default('geidentificeerd');
            $table->foreignId('risico_eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->date('volgende_beoordeling_gepland')->nullable();
            $table->timestamps();
        });

        Schema::create('risicobehandelingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risico_id')->constrained('risicos')->cascadeOnDelete();
            $table->enum('behandeloptie', ['mitigeren', 'accepteren', 'overdragen', 'vermijden']);
            $table->unsignedSmallInteger('restrisico_score')->nullable();
            $table->string('geaccepteerd_door')->nullable();
            $table->date('geaccepteerd_op')->nullable();
            $table->timestamps();
        });

        Schema::create('risicobehandeling_soa_regel', function (Blueprint $table) {
            $table->id();
            // Expliciete korte FK-/indexnamen: de auto-gegenereerde variant
            // overschrijdt de 64-tekengrens van MySQL (zie ook blok 2).
            $table->unsignedBigInteger('risicobehandeling_id');
            $table->unsignedBigInteger('soa_regel_id');
            $table->foreign('risicobehandeling_id', 'rb_soa_rb_fk')
                ->references('id')->on('risicobehandelingen')->cascadeOnDelete();
            $table->foreign('soa_regel_id', 'rb_soa_sr_fk')
                ->references('id')->on('soa_regels')->cascadeOnDelete();
            $table->unique(['risicobehandeling_id', 'soa_regel_id'], 'rb_soa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risicobehandeling_soa_regel');
        Schema::dropIfExists('risicobehandelingen');
        Schema::dropIfExists('risicos');
        Schema::dropIfExists('risicoacceptatiecriteria');
        Schema::dropIfExists('soa_regels');
        Schema::dropIfExists('maatregelen');
    }
};
