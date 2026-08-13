<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registratie van het ophalen van een bewijsstuk — bewust een eigen tabel en
 * niet `audit_logregels`.
 *
 * De audit trail bevat wijzigingen: een oude en een nieuwe waarde, en een
 * mutatie per veld. Een raadpleging is een ander soort feit, met een ander
 * volume (lezen gebeurt vaker dan muteren) en een andere bewaartermijn. Ze door
 * elkaar zetten maakt /audit-log onleesbaar en verdrinkt de mutaties in de
 * export voor de auditor.
 *
 * Doel is beperkt en expliciet: onderbouwen of een leesbevestiging is afgegeven
 * door iemand die het document ook daadwerkelijk heeft opgehaald. Dit is
 * registratie van gedrag van werknemers — zie de aantekening over bewaartermijn
 * en OR in implementatie/05 §14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raadplegingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bewijsstuk_id')->constrained('bewijsstukken')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->constrained('gebruikers');
            $table->timestamp('geraadpleegd_op');
            // Geen unique: elke download is een eigen feit. Wie een document
            // drie keer ophaalt, heeft dat drie keer gedaan — samenvoegen zou
            // informatie weggooien die juist iets zegt.
            $table->index(['bewijsstuk_id', 'gebruiker_id'], 'rp_bewijsstuk_gebruiker_index');

            // Geen created_at/updated_at: `geraadpleegd_op` ís het tijdstip, en
            // een updated_at suggereert dat een raadpleging te wijzigen valt.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raadplegingen');
    }
};
