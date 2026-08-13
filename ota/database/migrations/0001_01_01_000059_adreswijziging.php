<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een aangevraagde, nog niet bevestigde adreswijziging (implementatie/01h §1).
 *
 * Twee kolommen en geen aparte tabel: de toestand is één-op-één met het account,
 * hoort bij de levensduur ervan, en er is er hooguit één tegelijk. Dezelfde
 * afweging als bij `Uitnodiging`, dat om die reden geen tokentabel heeft.
 *
 * Bewust **geen** unique-index op `nieuw_email`. Twee lopende aanvragen naar
 * hetzelfde adres is een zeldzaam maar legitiem tussenstadium; de botsing die er
 * echt toe doet wordt op `email` afgevangen bij de bevestiging. Een index hier
 * zou een aanvraag laten falen op iets wat pas bij het effectueren een probleem
 * is.
 *
 * Geen terugvulling: er zijn geen lopende wijzigingen op het moment dat deze
 * kolommen ontstaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->string('nieuw_email')->nullable()->after('uitnodiging_verstuurd_op');
            $tabel->timestamp('nieuw_email_aangevraagd_op')->nullable()->after('nieuw_email');
        });
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->dropColumn(['nieuw_email', 'nieuw_email_aangevraagd_op']);
        });
    }
};
