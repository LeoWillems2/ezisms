<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De herziening van een beleidsversie zonder nieuwe versie (A.5.1: beoordeeld
 * "met geplande tussenpozen"). Tot hier sloot de taak "Beleid herzien" met één
 * klik, zonder conclusie en zonder dat de termijn opschoof: een gesloten taak
 * naast een document dat over zijn herzieningsdatum stond (Chrometoets
 * 24-09-2026, B6).
 *
 * Een eigen rij en geen velden op de versie: een versie kan jaren actief zijn
 * en elke jaarlijkse beoordeling is apart bewijs. Wie, wanneer, met welke
 * conclusie — dat is wat een auditor bij A.5.1 vraagt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beleidsherzieningen', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('beleidsversie_id')->constrained('beleidsversies')->cascadeOnDelete();
            $tabel->foreignId('beoordeeld_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $tabel->timestamp('beoordeeld_op');

            // De conclusie mét reden. Verplicht in het scherm: "gedaan" is geen
            // beoordeling.
            $tabel->text('toelichting');

            // De termijn die deze beoordeling zet; wordt ook op de versie
            // geschreven, zodat de takenplanning er gewoon op blijft draaien.
            $tabel->date('volgende_herziening_gepland');

            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beleidsherzieningen');
    }
};
