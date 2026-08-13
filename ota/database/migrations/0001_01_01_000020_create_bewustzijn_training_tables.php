<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 10 (Bewustzijn, Training & Toetsen) — implementatie/10-bewustzijn-training.md §4.
// Eén migratie bouwt alle blok-10-tabellen, inclusief het toets-mechanisme dat
// het voormalige deelproduct 15 leverde.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainingsmodules', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            // Bestandsnaam in public/toetsen/. Gevuld = voltooiing loopt via de
            // toets, niet via zelfregistratie (§6).
            $table->string('toets_bestand')->nullable();
            // null = eenmalig, geen verloop; anders verloopt_op = voltooid_op + N.
            $table->unsignedInteger('geldigheidsduur_maanden')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('doelgroepen', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('omschrijving')->nullable();
            $table->timestamps();
        });

        Schema::create('doelgroep_gebruiker', function (Blueprint $table) {
            $table->foreignId('doelgroep_id')->constrained('doelgroepen')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->constrained('gebruikers')->cascadeOnDelete();
            $table->primary(['doelgroep_id', 'gebruiker_id']);
        });

        Schema::create('doelgroep_trainingsmodule', function (Blueprint $table) {
            $table->foreignId('doelgroep_id')->constrained('doelgroepen')->cascadeOnDelete();
            $table->foreignId('trainingsmodule_id')->constrained('trainingsmodules')->cascadeOnDelete();
            $table->primary(['doelgroep_id', 'trainingsmodule_id']);
        });

        Schema::create('beleidsdocument_trainingsmodule', function (Blueprint $table) {
            $table->foreignId('beleidsdocument_id')->constrained('beleidsdocumenten')->cascadeOnDelete();
            $table->foreignId('trainingsmodule_id')->constrained('trainingsmodules')->cascadeOnDelete();
            $table->primary(['beleidsdocument_id', 'trainingsmodule_id']);
        });

        Schema::create('trainingsvoltooiingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainingsmodule_id')->constrained('trainingsmodules')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->constrained('gebruikers')->cascadeOnDelete();
            $table->date('voltooid_op');
            // Bij aanmaak berekend uit de geldigheidsduur van de module en daarna
            // vast — de auditwaarheid verandert niet als de module later een
            // andere geldigheidsduur krijgt (§4).
            $table->date('verloopt_op')->nullable();
            $table->enum('bron', ['zelfregistratie', 'toets'])->default('zelfregistratie');
            $table->timestamps();
            // Bewust géén unique op (module, gebruiker): elke cyclus is een nieuwe
            // rij, zodat "getraind in 2025 én 2026" aantoonbaar blijft.
        });

        Schema::create('toetsopdrachten', function (Blueprint $table) {
            $table->id();
            // 1-op-1 met de taak (blok 7): de taak is de werklijst, dit de
            // registratie.
            $table->foreignId('taak_id')->unique()->constrained('taken')->cascadeOnDelete();
            // Gekoppeld: slagen schrijft een Trainingsvoltooiing. Leeg: een losse
            // (ad-hoc) toets zonder module-registratie (§6).
            $table->foreignId('trainingsmodule_id')->nullable()->constrained('trainingsmodules')->nullOnDelete();
            $table->string('toets_bestand');
            // Snapshot van de <title> bij uitzetten: leesbaar ook als het bestand
            // later verdwijnt.
            $table->string('toets_titel');
            // 32 bytes willekeurig; auditUitgesloten op het model zodat het geheim
            // niet in de audit trail belandt (§4).
            $table->string('token', 64)->unique();
            $table->enum('status', ['uitgezet', 'gezakt', 'geslaagd'])->default('uitgezet');
            $table->unsignedInteger('pogingen')->default(0);
            $table->unsignedInteger('laatste_score')->nullable();
            $table->unsignedInteger('laatste_totaal')->nullable();
            $table->timestamp('laatste_poging_op')->nullable();
            $table->timestamp('geslaagd_op')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toetsopdrachten');
        Schema::dropIfExists('trainingsvoltooiingen');
        Schema::dropIfExists('beleidsdocument_trainingsmodule');
        Schema::dropIfExists('doelgroep_trainingsmodule');
        Schema::dropIfExists('doelgroep_gebruiker');
        Schema::dropIfExists('doelgroepen');
        Schema::dropIfExists('trainingsmodules');
    }
};
