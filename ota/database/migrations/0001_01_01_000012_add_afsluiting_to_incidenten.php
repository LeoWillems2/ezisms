<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maakt het verschil tussen `opgelost` en `gesloten` afdwingbaar
 * (implementatie/08 §6).
 *
 * Zonder deze velden was `opgelost` een label zonder gevolg en kon een incident
 * van `gemeld` rechtstreeks naar `gesloten` — precies de route waarlangs de
 * vraag "vergt dit een corrigerende maatregel?" nooit gesteld wordt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            // Zelfde paar als bij afwijkingen: een afsluiting zonder naam en
            // tijdstip is geen afsluiting. Levert bovendien de doorlooptijd
            // (gemeld_op → gesloten_op) die deelproducten/08 §6 vraagt; die was
            // alleen uit updated_at af te leiden, en dat veld verschuift bij
            // elke willekeurige wijziging.
            $table->timestamp('gesloten_op')->nullable()->after('status');
            $table->foreignId('gesloten_door_id')->nullable()->after('gesloten_op')
                ->constrained('gebruikers')->nullOnDelete();
            // Het besluit dat §10.1 bedoelt, expliciet gemaakt. Gevuld wanneer
            // er bij het sluiten géén afwijking is geopend; is er wél een
            // afwijking, dan is het besluit die afwijking zelf.
            $table->text('geen_afwijking_reden')->nullable()->after('gesloten_door_id');
        });
    }

    public function down(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gesloten_door_id');
            $table->dropColumn(['gesloten_op', 'geen_afwijking_reden']);
        });
    }
};
