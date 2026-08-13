<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Blok 12g — implementatie/12g §3 en §5: gebeurtenismetingen over een periode.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metingen', function (Blueprint $table) {
            // Leeg = een toestandsmeting op `gemeten_op` (alle bestaande rijen).
            // Gevuld = een gebeurtenismeting over het interval (periode_van,
            // periode_tot]: ondergrens exclusief, bovengrens inclusief. Zo
            // sluiten opeenvolgende vensters exact op elkaar aan — de bovengrens
            // van het vorige is de ondergrens van het volgende, en een
            // gebeurtenis op precies dat moment wordt één keer geteld.
            //
            // Tijdstippen en geen datums: het commando draait om 03:00, en met
            // dagprecisie zou een gebeurtenis van 10:00 op de meetdag in geen
            // enkel venster vallen.
            //
            // Zonder deze kolommen weet een lezer bij "14 nieuwe risico's" niet
            // of dat over een maand of over een kwartaal gaat — en dat kan
            // verschillen, omdat het venster zichzelf herstelt na een gemiste
            // run (12g §3).
            $table->dateTime('periode_van')->nullable()->after('gemeten_op');
            $table->dateTime('periode_tot')->nullable()->after('periode_van');
        });

        // Een telling is geen ratio en geen gemiddelde in dagen. Zonder deze
        // derde waarde zou "14 nieuwe risico's" op het dashboard verschijnen als
        // "14,0 dagen (gem.)" — de noemer-1-truc is eerlijk bij
        // `dagen_sinds_interne_audit`, want dát zijn echt dagen, maar hier niet.
        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->enum('eenheid', ['ratio', 'dagen', 'aantal'])->default('ratio')->change();
        });
    }

    public function down(): void
    {
        Schema::table('metingen', function (Blueprint $table) {
            $table->dropColumn(['periode_van', 'periode_tot']);
        });

        // Eerst controleren, dan pas versmallen. Zonder deze check faalt de
        // enum-wijziging met een kale SQL-fout zodra er nog één KPI op `aantal`
        // staat — en dan is de tabel al half teruggedraaid.
        $inGebruik = DB::table('kpi_definities')->where('eenheid', 'aantal')->pluck('sleutel');

        if ($inGebruik->isNotEmpty()) {
            throw new RuntimeException(
                'Terugdraaien kan niet: deze KPI\'s staan op eenheid `aantal` — '
                .$inGebruik->implode(', ').'. Zet ze eerst om of verwijder ze.'
            );
        }

        Schema::table('kpi_definities', function (Blueprint $table) {
            $table->enum('eenheid', ['ratio', 'dagen'])->default('ratio')->change();
        });
    }
};
