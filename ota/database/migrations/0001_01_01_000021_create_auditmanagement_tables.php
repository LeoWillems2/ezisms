<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 11 (Interne & Externe Auditmanagement) — implementatie/11-auditmanagement.md §3.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditplannen', function (Blueprint $table) {
            $table->id();
            // Eén plan per jaar: unique dwingt dat af.
            $table->unsignedSmallInteger('jaar')->unique();
            $table->enum('status', ['concept', 'vastgesteld'])->default('concept');
            $table->timestamps();
        });

        Schema::create('auditrondes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditplan_id')->constrained('auditplannen')->cascadeOnDelete();
            $table->enum('type', ['intern', 'extern_certificering', 'extern_surveillance']);
            $table->date('gepland_op')->nullable();
            $table->date('uitgevoerd_op')->nullable();
            // Alleen bij 'intern': het (vaak tijdelijke) Auditor-account dat de
            // bevindingen mag vastleggen (§4). nullOnDelete: een vervallen
            // auditor-account mag de ronde niet meeslepen.
            $table->foreignId('auditor_gebruiker_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Alleen bij 'extern_*': de certificerende instelling heeft geen
            // platformaccount, dus vrije tekst; het rapport komt als bewijs.
            $table->string('extern_auditor_naam')->nullable();
            $table->enum('status', ['gepland', 'in_uitvoering', 'afgerond'])->default('gepland');
            $table->timestamps();
        });

        // Auditscope: welke organisatie-eenheden vallen onder de ronde.
        Schema::create('auditronde_organisatie_eenheid', function (Blueprint $table) {
            $table->foreignId('auditronde_id')->constrained('auditrondes')->cascadeOnDelete();
            $table->foreignId('organisatie_eenheid_id')->constrained('organisatie_eenheden')->cascadeOnDelete();
            $table->primary(['auditronde_id', 'organisatie_eenheid_id'], 'auditronde_eenheid_primary');
        });

        Schema::create('bevindingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditronde_id')->constrained('auditrondes')->cascadeOnDelete();
            $table->enum('type', [
                'non_conformiteit_major', 'non_conformiteit_minor', 'observatie', 'verbeterkans',
            ]);
            $table->text('omschrijving');
            // Optionele verwijzing naar de Annex A-maatregel die de bevinding
            // betreft (blok 4).
            $table->foreignId('maatregel_id')->nullable()->constrained('maatregelen')->nullOnDelete();
            $table->enum('status', ['open', 'non_conformiteit_gestart', 'gesloten'])->default('open');
            $table->date('gesloten_op')->nullable();
            $table->foreignId('gesloten_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamps();
        });

        // Terugkoppeling naar blok 8: een bevinding kan als non-conformiteit
        // (Afwijking) worden opgepakt. `afwijkingen.bron` kent 'audit_bevinding'
        // al (blok 8 anticipeerde hierop); deze kolom legt de concrete bron vast.
        Schema::table('afwijkingen', function (Blueprint $table) {
            $table->foreignId('bevinding_id')->nullable()->after('incident_id')
                ->constrained('bevindingen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('afwijkingen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bevinding_id');
        });

        Schema::dropIfExists('bevindingen');
        Schema::dropIfExists('auditronde_organisatie_eenheid');
        Schema::dropIfExists('auditrondes');
        Schema::dropIfExists('auditplannen');
    }
};
