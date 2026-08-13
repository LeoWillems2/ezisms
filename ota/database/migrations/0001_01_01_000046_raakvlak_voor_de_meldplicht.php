<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raakvlak vóór de meldplichtvraag — correctie op `000045`.
 *
 * Die migratie kende maar twee toestanden: meldingsplichtig of niet. Daardoor
 * viel "dit incident raakt de meldplicht helemaal niet" (een stroomstoring, een
 * mislukte hersteltest) samen met "dit ís een geval waarop de wet ziet, maar het
 * hoeft niet gemeld" — en eiste het scherm bij allebei een motivatie. Dat is de
 * documentatieplicht te ruim toegepast: AVG art. 33 lid 5 gaat over "alle
 * inbreuken **in verband met persoonsgegevens**", niet over elk
 * beveiligingsincident.
 *
 * De twee vragen hieronder volgen de twee wetten en bepalen samen of er een
 * documentatieplicht is. Ze impliceren bovendien de grondslag, zodat de losse
 * keuzelijst met grondslagen kon verdwijnen.
 *
 * Zelfde driedeling als bij `assets.persoonsgegevens` (migratie `000044`):
 * null = niet beoordeeld, false = beoordeeld en geen raakvlak, true = raakvlak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            // AVG. Het gekoppelde asset geeft hier een signaal bij: een incident
            // op een asset met persoonsgegevens dat op "nee" staat, spreekt
            // zichzelf tegen.
            $table->boolean('raakt_persoonsgegevens')->nullable()->after('kennisname_op');

            // Cyberbeveiligingswet. Wordt alleen gevraagd wanneer de organisatie
            // Cbw-plichtig is (`config('meldplicht.cbw_plichtig')`); anders
            // blijft deze kolom null en telt hij niet mee.
            $table->boolean('is_netwerk_informatie_incident')->nullable()->after('raakt_persoonsgegevens');
        });
    }

    public function down(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            $table->dropColumn(['raakt_persoonsgegevens', 'is_netwerk_informatie_incident']);
        });
    }
};
