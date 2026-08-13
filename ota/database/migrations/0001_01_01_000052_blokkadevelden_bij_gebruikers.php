<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wie heeft dit account geblokkeerd, wanneer en waarom (implementatie/01f §1).
 *
 * De status `geblokkeerd` bestond al, maar kon maar op één manier ontstaan: de
 * teller op mislukte inlogpogingen. Nu de CISO zelf kan blokkeren zijn er twee
 * bronnen, en het verschil doet er op twee plekken toe — de melding op het
 * loginscherm, en de regel in de gebruikerslijst waarop de CISO besluit of hij
 * de blokkade opheft.
 *
 * Waarom kolommen en niet de audit trail: die is de historie, dit is de huidige
 * toestand. Zonder kolommen zou de lijst per rij de trail moeten bevragen, en
 * het loginscherm ook — op het pad waar nog niemand is ingelogd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            $table->timestamp('geblokkeerd_op')->nullable()->after('status');
            // Null = door het systeem. Bestaande geblokkeerde accounts houden
            // null in alle drie de kolommen en lezen dus als een automatische
            // blokkade; dat klopt, want handmatig blokkeren bestond nog niet.
            $table->foreignId('geblokkeerd_door_id')->nullable()->after('geblokkeerd_op')
                ->constrained('gebruikers')->nullOnDelete();
            $table->string('blokkade_reden')->nullable()->after('geblokkeerd_door_id');
        });
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            $table->dropForeign(['geblokkeerd_door_id']);
            $table->dropColumn(['geblokkeerd_op', 'geblokkeerd_door_id', 'blokkade_reden']);
        });
    }
};
