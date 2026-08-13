<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy bij assets — implementatie/03b-privacy-bij-assets.md §2.
 *
 * Een eigen veld naast de classificatie, geen vierde classificatiedimensie: de
 * vier niveaus van `classificatieschemas` (openbaar..geheim) betekenen hier
 * niets, want "openbare persoonsgegevens" is geen graad van hetzelfde.
 *
 * Norm-onafhankelijk: de AVG geldt voor elke organisatie. In een latere
 * NEN 7510-variant is PGI geen nieuw assettype maar `persoonsgegevens` =
 * 'bijzonder' met een zorglabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // null = nog niet beoordeeld; 'geen' = beoordeeld, er zitten er geen
            // in. Zelfde onderscheid en zelfde reden als
            // soa_regels.van_toepassing: een onbeoordeeld asset mag er niet
            // uitzien als een bewust "nee" — dat is het gap-signaal.
            //
            // Vocabulaire uit de AVG: art. 4 (gewone persoonsgegevens), art. 9
            // (bijzondere categorieën, waaronder gezondheid), art. 10
            // (strafrechtelijke gegevens).
            $table->enum('persoonsgegevens', ['geen', 'gewoon', 'bijzonder', 'strafrechtelijk'])
                ->nullable()
                ->after('beschikbaarheidsniveau');

            // Zonder datum is "beoordeeld: geen" niet te onderscheiden van "ooit
            // ingevuld en sindsdien nooit meer gekeken" — zelfde reden als
            // laatst_geclassificeerd_op ernaast.
            $table->date('privacy_beoordeeld_op')->nullable()->after('persoonsgegevens');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['persoonsgegevens', 'privacy_beoordeeld_op']);
        });
    }
};
