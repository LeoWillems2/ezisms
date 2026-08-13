<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 2 (Context & Scope) — implementatie/02-context-scope.md §2.
return new class extends Migration
{
    public function up(): void
    {
        // organisatie_eenheden — zelfverwijzend voor hiërarchie (afdeling >
        // locatie > proces). Geen aparte tabellen per niveau.
        Schema::create('organisatie_eenheden', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('type', ['afdeling', 'locatie', 'proces']);
            $table->foreignId('bovenliggende_eenheid_id')->nullable()
                ->constrained('organisatie_eenheden')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->enum('aard', ['intern', 'extern']);
            $table->string('categorie'); // bijv. juridisch, technologisch, markt
            $table->text('omschrijving');
            $table->date('laatst_beoordeeld_op')->nullable();
            $table->timestamps();
        });

        Schema::create('belanghebbenden', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('aard', ['intern', 'extern']);
            $table->text('relevantie_voor_isms')->nullable();
            $table->timestamps();
        });

        Schema::create('eisen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('belanghebbende_id')->constrained('belanghebbenden')->cascadeOnDelete();
            $table->text('omschrijving');
            $table->enum('bron', ['contractueel', 'wettelijk', 'verwachting']);
            $table->timestamps();
        });

        // Expliciet versioneerbaar: nooit muteren, alleen status wijzigen — een
        // auditor moet de scope-ontwikkeling over tijd kunnen zien (deelproduct 2 §2).
        Schema::create('scope_verklaringen', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('versienummer');
            $table->text('scopetekst');
            $table->enum('status', ['concept', 'ter_goedkeuring', 'actief', 'vervangen'])
                ->default('concept');
            $table->date('geldig_vanaf')->nullable();
            $table->string('goedgekeurd_door')->nullable(); // vrij tekstveld (architectuur.md sectie 2)
            $table->date('volgende_herziening_gepland')->nullable();
            $table->timestamps();
        });

        Schema::create('scope_verklaring_organisatie_eenheid', function (Blueprint $table) {
            $table->id();
            // Expliciete, korte FK-namen: de auto-gegenereerde naam
            // (scope_verklaring_organisatie_eenheid_organisatie_eenheid_id_foreign)
            // overschrijdt de 64-tekengrens van MySQL.
            $table->unsignedBigInteger('scope_verklaring_id');
            $table->unsignedBigInteger('organisatie_eenheid_id');
            $table->foreign('scope_verklaring_id', 'sv_oe_sv_fk')
                ->references('id')->on('scope_verklaringen')->cascadeOnDelete();
            $table->foreign('organisatie_eenheid_id', 'sv_oe_oe_fk')
                ->references('id')->on('organisatie_eenheden')->cascadeOnDelete();
            $table->unique(['scope_verklaring_id', 'organisatie_eenheid_id'], 'sv_oe_unique');
        });

        Schema::create('scope_verklaring_issue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_verklaring_id')->constrained('scope_verklaringen')->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->unique(['scope_verklaring_id', 'issue_id'], 'sv_issue_unique');
        });

        Schema::create('scope_verklaring_belanghebbende', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_verklaring_id')->constrained('scope_verklaringen')->cascadeOnDelete();
            $table->foreignId('belanghebbende_id')->constrained('belanghebbenden')->cascadeOnDelete();
            $table->unique(['scope_verklaring_id', 'belanghebbende_id'], 'sv_bh_unique');
        });

        // motivatie is bewust NIET nullable: dwingt §4.3 van de norm af op
        // databaseniveau — geen uitsluiting zonder motivatie.
        Schema::create('uitsluitingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_verklaring_id')->constrained('scope_verklaringen')->cascadeOnDelete();
            $table->text('omschrijving');
            $table->text('motivatie');
            $table->timestamps();
        });

        // 'scope_interfaces', niet 'interfaces': 'interface' is een gereserveerd
        // PHP-woord en kan geen Eloquent-classnaam zijn (implementatie 02 §2).
        Schema::create('scope_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_verklaring_id')->constrained('scope_verklaringen')->cascadeOnDelete();
            $table->text('omschrijving');
            $table->text('risico_implicatie')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_interfaces');
        Schema::dropIfExists('uitsluitingen');
        Schema::dropIfExists('scope_verklaring_belanghebbende');
        Schema::dropIfExists('scope_verklaring_issue');
        Schema::dropIfExists('scope_verklaring_organisatie_eenheid');
        Schema::dropIfExists('scope_verklaringen');
        Schema::dropIfExists('eisen');
        Schema::dropIfExists('belanghebbenden');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('organisatie_eenheden');
    }
};
