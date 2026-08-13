<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stappenreeksen in de taken-engine (implementatie/07b §5).
 *
 * Bewust géén nieuwe tabel: een stap ís een taak, en krijgt er een plaats in de
 * reeks bij. Wat een reeks bij elkaar houdt is de bestaande polymorfe koppeling
 * plus een gevulde `volgorde` — daarom is er ook geen extra koppelkolom.
 *
 * `taken_sjabloon_deadline_unique` staat op (taaksjabloon_id, deadline); stappen
 * hebben taaksjabloon_id NULL en NULL-waarden botsen daar niet, dus twee stappen
 * met dezelfde deadline zijn geen probleem.
 */
return new class extends Migration
{
    private const STATUS_NIEUW = ['wachtend', 'open', 'in_uitvoering', 'voltooid', 'verlopen'];

    private const STATUS_OUD = ['open', 'in_uitvoering', 'voltooid', 'verlopen'];

    public function up(): void
    {
        Schema::table('taken', function (Blueprint $table) {
            $table->unsignedSmallInteger('volgorde')->nullable()->after('soort');
            // Enum en geen vrije tekst: er wordt op gerapporteerd (afkeurpercentage
            // per sjabloon). 'nvt' is voor stappen zonder resultaat, zoals informeren.
            $table->enum('uitkomst', ['goedgekeurd', 'afgekeurd', 'uitgevoerd', 'nvt'])
                ->nullable()->after('volgorde');
            // Afwijking van 07b §5, zie §15 van dat plan: het takenscherm moet een
            // goedkeuringsstap kunnen herkennen zonder blok 15 te kennen.
            $table->boolean('vraagt_uitkomst')->default(false)->after('uitkomst');
        });

        // Apart van de kolomtoevoegingen: een enum-wijziging is op SQLite een
        // tabelherbouw, en die combineert slecht met nieuwe kolommen in dezelfde
        // blueprint. Patroon verder gelijk aan migratie 000018.
        Schema::table('taken', function (Blueprint $table) {
            $table->enum('status', self::STATUS_NIEUW)->default('open')->change();
        });
    }

    public function down(): void
    {
        // Wachtende stappen bestaan straks niet meer als toestand; zonder deze
        // stap zou de enum-verkleining op bestaande rijen stuklopen.
        DB::table('taken')->where('status', 'wachtend')->update(['status' => 'open']);

        Schema::table('taken', function (Blueprint $table) {
            $table->enum('status', self::STATUS_OUD)->default('open')->change();
        });

        Schema::table('taken', function (Blueprint $table) {
            $table->dropColumn(['volgorde', 'uitkomst', 'vraagt_uitkomst']);
        });
    }
};
