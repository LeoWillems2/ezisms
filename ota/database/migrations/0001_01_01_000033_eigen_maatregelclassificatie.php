<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 04d fase 2: de classificatie per maatregel wordt klantdata.
 *
 * Het ISMS levert een uitgangsclassificatie mee op `maatregelen.kenmerken`; wat
 * de organisatie zélf vaststelt komt hier te staan. Twee redenen dat dit op
 * `soa_regels` hoort en niet op `maatregelen`:
 *
 *  - `maatregelen` is referentiedata die bij elke deploy overschreven wordt
 *    (`deploy.sh` draait `db:seed --force`). Klantwijzigingen zouden daar
 *    geruisloos verdwijnen. MaatregelKenmerkenSeeder voegt sinds 29-07-2026
 *    samen in plaats van te overschrijven, maar de scheiding blijft de echte
 *    borging — zet er nooit klantdata neer.
 *  - `SoaRegel` is auditeerbaar; een vaststelling van de organisatie hoort in de
 *    audit trail. `Maatregel` heeft niet eens een morph-alias.
 *
 * `null` betekent "volg het uitgangspunt". Bestaande installaties gedragen zich
 * na deze migratie dus exact zoals ervoor: geen backfill, niets dat stuk kan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soa_regels', function (Blueprint $table) {
            $table->json('kenmerken_eigen')->nullable()->after('procesreferentie');
        });
    }

    public function down(): void
    {
        Schema::table('soa_regels', function (Blueprint $table) {
            $table->dropColumn('kenmerken_eigen');
        });
    }
};
