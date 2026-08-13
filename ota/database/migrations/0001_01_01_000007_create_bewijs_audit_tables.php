<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 6 (Bewijsrepository & Audit Trail) — implementatie/06-bewijsrepository-audit-trail.md §2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bewijsstukken', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->text('omschrijving')->nullable();
            $table->string('bestandsnaam');       // originele naam, voor de download
            $table->string('bestandstype', 100);  // MIME-type
            $table->unsignedBigInteger('bestandsgrootte');
            // Pad binnen de disk, niet absoluut: een latere overstap naar een
            // andere disk blijft daarmee een configuratiewijziging.
            $table->string('opslaglocatie_referentie');
            // Maakt "onveranderlijke opslag" verifieerbaar in plaats van een
            // belofte: wijkt het bestand op schijf af van deze hash, dan is het
            // bewijsstuk niet langer betrouwbaar (§3a).
            $table->string('bestandshash', 64);
            $table->foreignId('geupload_door')->constrained('gebruikers');
            $table->timestamp('geupload_op');
            $table->enum('status', ['actief', 'gearchiveerd'])->default('actief');
            $table->date('bewaren_tot'); // kolom, geen berekening — zie §3d
            $table->timestamps();
        });

        Schema::create('bewijs_koppelingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bewijsstuk_id')->constrained('bewijsstukken')->cascadeOnDelete();
            $table->string('blok_naam', 50);      // blok-code, bijv. 'risico-soa'
            $table->string('entiteit_type', 50);  // morph-alias, bijv. 'soa_regel'
            $table->unsignedBigInteger('entiteit_id');
            $table->timestamps();

            // Expliciete korte namen: de auto-gegenereerde unique-naam wordt 65
            // tekens en overschrijdt de 64-tekengrens van MySQL (zelfde valkuil
            // als in blok 2 en 4).
            $table->unique(['bewijsstuk_id', 'entiteit_type', 'entiteit_id'], 'bk_stuk_entiteit_unique');
            $table->index(['entiteit_type', 'entiteit_id'], 'bk_entiteit_index');
        });

        Schema::create('audit_logregels', function (Blueprint $table) {
            $table->id();
            $table->timestamp('tijdstip')->index();
            // nullOnDelete, niet cascade: een logregel moet blijven bestaan als
            // de gebruiker later wordt verwijderd — anders verdwijnt juist het
            // bewijs van wat die persoon deed.
            $table->foreignId('gebruiker_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Momentopnamen (§3b): een audit trail moet leesbaar blijven als de
            // gebruiker weg is of de entiteit hernoemd.
            $table->string('gebruiker_naam');
            $table->string('blok_naam', 50);
            $table->string('entiteit_type', 50);
            $table->unsignedBigInteger('entiteit_id');
            $table->string('entiteit_omschrijving');
            $table->enum('actie', ['aangemaakt', 'gewijzigd', 'status_gewijzigd', 'verwijderd']);
            // JSON, niet string: één wijziging raakt vaak meerdere velden (§3c).
            $table->json('oude_waarde')->nullable();
            $table->json('nieuwe_waarde')->nullable();
            // Bewust GEEN timestamps(): een append-only regel wordt nooit
            // bijgewerkt, dus updated_at zou alleen verwarren.

            $table->index(['entiteit_type', 'entiteit_id'], 'al_entiteit_index');
            $table->index(['blok_naam', 'tijdstip'], 'al_blok_tijdstip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logregels');
        Schema::dropIfExists('bewijs_koppelingen');
        Schema::dropIfExists('bewijsstukken');
    }
};
