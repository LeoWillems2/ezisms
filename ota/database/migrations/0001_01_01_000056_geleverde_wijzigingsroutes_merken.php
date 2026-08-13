<?php

use App\Support\Wijzigingsroutes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onderscheid tussen meegeleverde en eigen routes (implementatie/15 §19).
 *
 * Zonder deze vlag is niet te zien of een route van ons komt of van de
 * organisatie, en dus ook niet of hij is aangepast en waar hij naar terug zou
 * moeten kunnen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wijzigingssjablonen', function (Blueprint $table) {
            $table->boolean('geleverd')->default(false)->after('actief');
        });

        // Bestaande installaties: de routes die op naam overeenkomen met wat wij
        // leveren, krijgen de vlag alsnog. Op naam en niet op inhoud — een route
        // die de organisatie inmiddels heeft aangepast is nog steeds een
        // geleverde route, juist dán is "terugzetten" nuttig.
        DB::table('wijzigingssjablonen')
            ->whereIn('naam', Wijzigingsroutes::namen())
            ->update(['geleverd' => true]);
    }

    public function down(): void
    {
        Schema::table('wijzigingssjablonen', function (Blueprint $table) {
            $table->dropColumn('geleverd');
        });
    }
};
