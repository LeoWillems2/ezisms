<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externe meldplicht bij incidenten — implementatie/08b-externe-meldplicht.md §3.
 *
 * Norm-onafhankelijk: de AVG geldt voor iedereen en de Cyberbeveiligingswet
 * hangt aan sector en omvang, niet aan de gekozen norm. In een latere
 * NEN 7510-variant is dit de landing van A.5.43 en komt er niets bij.
 *
 * Wat hier NIET in zit: bij welke instantie gemeld wordt, contactgegevens, een
 * adressenregister. Dat staat in de procedure of werkwijze. Het ISMS houdt het
 * besluit, het feit en de termijn vast — meer heeft een auditor niet nodig en
 * meer wordt niet onderhouden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            // Het wettelijke ankerpunt, en bewust NIET `gemeld_op`. Beide wetten
            // rekenen vanaf kennisname (Cbw art. 26/27 "nadat zij kennis heeft
            // gekregen"; AVG art. 33 lid 1 "nadat hij er kennis van heeft
            // genomen"), terwijl `gemeld_op` het moment van invoeren in het ISMS
            // is — dat kan dagen later zijn. Rekenen vanaf `gemeld_op` geeft een
            // te ruime deadline en een structureel te gunstige KPI.
            $table->timestamp('kennisname_op')->nullable()->after('gemeld_op');

            // null = nog niet beoordeeld. Zelfde onderscheid en zelfde reden als
            // soa_regels.van_toepassing: onbeoordeeld mag er niet uitzien als
            // een bewust "nee".
            $table->boolean('extern_meldingsplichtig')->nullable()->after('kennisname_op');
            $table->timestamp('meldplicht_beoordeeld_op')->nullable()->after('extern_meldingsplichtig');

            // Verplicht bij zowel ja als nee, maar alléén wanneer het incident
            // raakvlak heeft met een van beide wetten — zie migratie `000046`,
            // die dat raakvlak als aparte vraag toevoegt. AVG art. 33 lid 5 gaat
            // over "alle inbreuken in verband met persoonsgegevens", niet over
            // elk beveiligingsincident; deze migratie paste dat aanvankelijk te
            // ruim toe.
            $table->text('meldplicht_motivatie')->nullable()->after('meldplicht_beoordeeld_op');
        });

        Schema::create('incident_meldingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidenten')->cascadeOnDelete();
            $table->enum('grondslag', ['avg', 'cbw']);
            // De Cbw is een gefaseerde meldplicht (art. 26/27/29), geen enkele
            // termijn: één Cbw-plichtig incident levert drie rijen op.
            $table->enum('fase', ['waarschuwing', 'melding', 'betrokkenen', 'eindverslag']);

            // Nullable, want een verplichting zonder klok is het gewone geval:
            // AVG art. 34 (mededeling aan de betrokkene) heeft er nooit een, en
            // het Cbw-eindverslag bij een voortdurend incident krijgt er pas een
            // bij afhandeling (art. 29 lid 2).
            $table->unsignedSmallInteger('meldtermijn_uren')->nullable();

            // Opgeslagen, niet berekend — zelfde keuze en zelfde reden als
            // bewijsstukken.bewaren_tot. Het ankerpunt verschilt per fase
            // (kennisname / de melding / de afhandeling), termijnen veranderen
            // bij wet, en een reeds beoordeeld incident moet de deadline houden
            // die toen gold.
            $table->timestamp('uiterlijk_op')->nullable();

            $table->timestamp('gemeld_op')->nullable(); // null = nog te doen
            // O.a. de motivering voor de vertraging die AVG art. 33 lid 1
            // verlangt bij een melding later dan 72 uur.
            $table->text('toelichting')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'grondslag', 'fase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_meldingen');

        Schema::table('incidenten', function (Blueprint $table) {
            $table->dropColumn([
                'kennisname_op', 'extern_meldingsplichtig',
                'meldplicht_beoordeeld_op', 'meldplicht_motivatie',
            ]);
        });
    }
};
