<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De reeks volledig bevriezen (implementatie/15 §17).
 *
 * `titel`, `deadline`, `eigenaar_id` en `vraagt_uitkomst` werden al bij het
 * starten gekopieerd; `staptype`, `bewijs_verplicht` en
 * `bij_afkeuren_terug_naar` werden live uit `sjabloonstappen` gelezen. Daardoor
 * kon een aanpassing aan een sjabloon een lopend dossier van gedrag laten
 * veranderen — en in het ergste geval een controle uitzetten die al gold.
 *
 * Deze kolommen horen bij blok 15 en staan daarom in een migratie van dat blok,
 * net als `sjabloonstap_id`. De taken-engine kent ze niet: blok 15 geeft ze mee
 * via de `extra`-doorgave van `Stappenreeks::start()`.
 *
 * `sjabloonstap_id` blijft bestaan als herkomstverwijzing, maar er hangt geen
 * gedrag meer aan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taken', function (Blueprint $table) {
            $table->enum('staptype', ['analyse', 'goedkeuring', 'informeren', 'uitvoeren', 'evaluatie'])
                ->nullable()->after('vraagt_uitkomst');
            $table->boolean('bewijs_verplicht')->default(false)->after('staptype');
            $table->unsignedSmallInteger('bij_afkeuren_terug_naar')->nullable()->after('bewijs_verplicht');
        });

        // Bestaande reeksen krijgen de waarden zoals ze nú in het sjabloon
        // staan. Beter kan niet — wat er gold op het moment van starten is niet
        // meer te achterhalen — en het is nauwkeuriger dan ze leeg laten: leeg
        // betekent "geen uitvoerstap", en dan zou de terugvalplancontrole op een
        // lopend dossier alsnog vervallen.
        DB::table('taken')
            ->whereNotNull('sjabloonstap_id')
            ->orderBy('id')
            ->each(function (object $taak) {
                $stap = DB::table('sjabloonstappen')->find($taak->sjabloonstap_id);

                if ($stap === null) {
                    return;
                }

                DB::table('taken')->where('id', $taak->id)->update([
                    'staptype' => $stap->staptype,
                    'bewijs_verplicht' => $stap->bewijs_verplicht,
                    'bij_afkeuren_terug_naar' => $stap->bij_afkeuren_terug_naar,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('taken', function (Blueprint $table) {
            $table->dropColumn(['staptype', 'bewijs_verplicht', 'bij_afkeuren_terug_naar']);
        });
    }
};
