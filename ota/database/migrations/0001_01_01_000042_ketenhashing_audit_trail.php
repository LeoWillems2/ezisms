<?php

use App\Support\Audittrailketen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keten-hashing van de audit trail (implementatie/06c).
 *
 * Elke logregel draagt de hash van zijn voorganger. Daarmee wordt het stil
 * verwijderen, wijzigen of tussenvoegen van een regel detecteerbaar — niet
 * onmogelijk; zie 06c §0 voor wat dit wel en niet oplost.
 *
 * Deze migratie leunt op `Audittrailketen`. Dat is bewust: de canonieke vorm
 * hoort op één plek te staan. Verandert die vorm ooit, dan levert een verse
 * `migrate` een andere keten op dan een bestaande installatie heeft — en dat is
 * geen probleem, want een keten hoeft alleen intern consistent te zijn. Voor
 * bestaande installaties is zo'n wijziging wél een verzegelstap, en `KetenhashTest`
 * zorgt dat niemand hem per ongeluk zet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // De uitslagen van de ketencontrole. Bewust een eigen tabel en geen
        // logregel: de trail zelf is de verdachte, dus de uitslag van het
        // onderzoek hoort er niet in. En een auditor vraagt niet of de keten
        // vandaag klopt, maar of hij al twee jaar elke nacht is gecontroleerd —
        // zonder deze geschiedenis is de maatregel er wel en is hij niet
        // aantoonbaar (06c §5).
        Schema::create('audit_ketencontroles', function (Blueprint $table) {
            $table->id();
            $table->timestamp('tijdstip')->index();
            $table->enum('soort', ['controle', 'verzegeld']);
            $table->boolean('intact');
            $table->unsignedInteger('regels');
            // Tot welke logregel de keten is nagelopen, en waar hij brak.
            $table->unsignedBigInteger('tot_id')->nullable();
            $table->unsignedBigInteger('kapotte_id')->nullable();
            $table->char('kophash', 64)->nullable();
            // Alleen bij een verzegeling: waarom de keten opnieuw is aangelegd.
            $table->string('reden')->nullable();
        });

        Schema::table('audit_logregels', function (Blueprint $table) {
            $table->char('hash', 64)->nullable()->after('nieuwe_waarde');
            $table->char('vorige_hash', 64)->nullable()->after('hash');
        });

        // Verzegelen: de keten over de bestaande regels aanleggen. Dit legt de
        // inhoud vast zoals die nú is en zegt niets over wat er daarvóór is
        // gebeurd — vandaar dat het als `verzegeld`-rij wordt vastgelegd, zodat
        // er geen misverstand kan bestaan waar de bewijskracht begint (06c §7).
        Audittrailketen::verzegel('Migratie 000042: keten aangelegd over de bestaande regels.');

        // Pas ná het verzegelen: vóór die tijd staat overal null en zou de
        // unieke index op vorige_hash niets betekenen.
        //
        // Deze index is de goedkope oplossing voor de race uit 06c §4: twee
        // gelijktijdige schrijvers die dezelfde kop lezen, zouden de keten
        // vorken. Een hash mag hoogstens één opvolger hebben, dus dat wordt een
        // duplicate-key-fout bij het schrijven (met retry) in plaats van een
        // raadsel bij het controleren.
        Schema::table('audit_logregels', function (Blueprint $table) {
            $table->unique('hash', 'al_hash_uniek');
            $table->unique('vorige_hash', 'al_vorige_hash_uniek');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logregels', function (Blueprint $table) {
            $table->dropUnique('al_hash_uniek');
            $table->dropUnique('al_vorige_hash_uniek');
            $table->dropColumn(['hash', 'vorige_hash']);
        });

        Schema::dropIfExists('audit_ketencontroles');
    }
};
