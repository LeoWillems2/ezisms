<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 12d fase 2 — implementatie/12d §2: de norm waartegen beoordeeld wordt.
return new class extends Migration
{
    public function up(): void
    {
        // `streefwaarde` en niet `drempelwaarde`: die term is bezet door de
        // risicoacceptatiegrens (Risico::drempelwaarde). Twee verschillende
        // drempels met dezelfde naam in hetzelfde dashboard is een leesfout die
        // niemand opmerkt.
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->decimal('streefwaarde', 6, 1)->nullable()->after('richting');   // vanaf hier: op norm
            $table->decimal('signaalwaarde', 6, 1)->nullable()->after('streefwaarde'); // voorbij deze grens: rood
        });

        // Dezelfde twee waarden op de meetrij, gevuld bij het meten — hetzelfde
        // patroon als `definitie_versie` (12 §2b).
        //
        // Zonder deze kolommen wordt de status van elk historisch meetpunt live
        // berekend tegen de huidige norm. Verlaag je volgend jaar een
        // streefwaarde, dan kleuren twee jaar rode punten met terugwerkende
        // kracht groen — "een cijfer dat meebeweegt als je later kijkt is geen
        // meting" (12 §2c). Dat weegt hier zwaarder dan bij de berekeningswijze,
        // omdat het bijstellen van een norm veel lager drempelt dan het
        // herschrijven van een formule.
        Schema::table('metingen', function (Blueprint $table) {
            $table->decimal('streefwaarde', 6, 1)->nullable()->after('definitie_versie');
            $table->decimal('signaalwaarde', 6, 1)->nullable()->after('streefwaarde');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->dropColumn(['streefwaarde', 'signaalwaarde']);
        });

        Schema::table('metingen', function (Blueprint $table) {
            $table->dropColumn(['streefwaarde', 'signaalwaarde']);
        });
    }
};
