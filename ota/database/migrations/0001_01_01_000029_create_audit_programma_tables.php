<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne-audit-programma: de normatieve dekkings-as bovenop blok 11 (plan
 * 11b, ISO 27001 §9.2.2). Voegt een audit-universe (clausules + verwijzing naar
 * maatregelen), een meerjarige cyclus als eigen entiteit, de dekkingsplanning
 * per object en de feitelijke dekking per ronde toe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // De audit-universe: wát er geaudit kan worden. Referentiedata — de
        // clausules worden geseed, de maatregel-objecten gesynct uit de SoA.
        Schema::create('auditobjecten', function (Blueprint $table) {
            $table->id();
            $table->enum('soort', ['clausule', 'maatregel']);
            // Alleen bij 'clausule': nummer + eigen korte titel (nooit ISO-tekst).
            $table->string('clausule_nummer')->nullable();
            $table->string('titel')->nullable();
            // Alleen bij 'maatregel': verwijzing, geen kopie van de normtekst.
            $table->foreignId('maatregel_id')->nullable()->constrained('maatregelen')->nullOnDelete();
            // Groep voor sortering/weergave: hoofdstuk (H4-H10) of Bijlage-A-thema.
            $table->string('groep');
            $table->unsignedInteger('volgorde')->default(0);
            // Niet meer van toepassing → inactief i.p.v. verwijderen; historie blijft.
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        // De meerjarige cyclus als EIGEN entiteit, bóven het jaarlijkse Auditplan.
        Schema::create('auditprogrammas', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->unsignedSmallInteger('start_jaar');
            $table->unsignedTinyInteger('aantal_jaren')->default(3);
            $table->enum('status', ['concept', 'actief', 'afgesloten'])->default('concept');
            $table->timestamps();
        });

        // Een jaarplan hoort bij ten hoogste één cyclus. nullOnDelete: een
        // verwijderd programma mag de uitvoeringshistorie nooit meeslepen.
        Schema::table('auditplannen', function (Blueprint $table) {
            $table->foreignId('auditprogramma_id')->nullable()->after('id')
                ->constrained('auditprogrammas')->nullOnDelete();
        });

        // De dekkings*planning*: de intentie per object binnen een programma.
        Schema::create('auditprogramma_dekkingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditprogramma_id')->constrained('auditprogrammas')->cascadeOnDelete();
            $table->foreignId('auditobject_id')->constrained('auditobjecten')->cascadeOnDelete();
            // Risicogebaseerde frequentie: 1 = jaarlijks, 3 = eenmaal per 3-jaars cyclus.
            $table->unsignedTinyInteger('interval_jaren')->default(1);
            $table->unsignedSmallInteger('gepland_start_peiljaar')->nullable();
            $table->string('toelichting')->nullable();
            $table->timestamps();

            // Eén dekkingsregel per object per programma.
            $table->unique(['auditprogramma_id', 'auditobject_id'], 'programma_object_uniek');
        });

        // De feitelijke dekking: welke objecten dekt een ronde. Parallel aan
        // auditronde_organisatie_eenheid (de organisatorische scope-as).
        Schema::create('auditronde_auditobject', function (Blueprint $table) {
            $table->foreignId('auditronde_id')->constrained('auditrondes')->cascadeOnDelete();
            $table->foreignId('auditobject_id')->constrained('auditobjecten')->cascadeOnDelete();
            $table->primary(['auditronde_id', 'auditobject_id'], 'auditronde_object_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditronde_auditobject');
        Schema::dropIfExists('auditprogramma_dekkingen');

        Schema::table('auditplannen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auditprogramma_id');
        });

        Schema::dropIfExists('auditprogrammas');
        Schema::dropIfExists('auditobjecten');
    }
};
