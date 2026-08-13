<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De schedulerhartslag (implementatie/00m §1).
 *
 * Acht geplande commando's maal 365 dagen is ongeveer 3000 rijen per jaar.
 * Verwaarloosbaar, en het levert een geschiedenis **per commando** op in plaats
 * van één globale "laatst gezien" — zonder dat kun je niet zeggen wélke bewaking
 * eruit lag.
 *
 * Machinale log, dus géén audit trail: de bewijsketen loopt via de taak die uit
 * een gat volgt, en die is wél auditeerbaar (00m §0.3).
 *
 * Nummerwijziging: het plan noemt `000050`, maar dat nummer is inmiddels
 * vergeven. Het volgende vrije nummer geldt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('systeemhartslag', function (Blueprint $tabel) {
            $tabel->id();

            // Het genormaliseerde artisan-commando, bv. 'isms:meet-kpis'
            // (00m §0.5). Niet de mutexnaam — die is stabiel maar een hash — en
            // niet de weergavenaam, die verandert zodra iemand een
            // ->description() toevoegt en dan de historie zou afsnijden.
            $tabel->string('taak_sleutel', 120);

            // Leesbaar, mag veranderen; nooit een sleutel.
            $tabel->string('weergavenaam')->nullable();

            $tabel->dateTime('gedraaid_op');

            // nulpunt = geen run, maar de startlijn van deze installatie
            // (00m §3). Zonder die rij leest een verse installatie als één
            // groot gat sinds 1970.
            $tabel->enum('resultaat', ['gelukt', 'fout', 'overgeslagen', 'nulpunt']);

            $tabel->unsignedInteger('duur_ms')->nullable();

            // Alleen bij 'fout' en 'overgeslagen': waaróm.
            $tabel->text('melding')->nullable();

            // De query die er telkens op draait: "laatste run per commando".
            $tabel->index(['taak_sleutel', 'gedraaid_op']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('systeemhartslag');
    }
};
