<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 11c fase 1: de nulmeting als eigen rondetype, en dekking als eigenschap
 * van de ronde in plaats van een gevolgtrekking uit "uitgevoerd".
 *
 * Bij de start van een certificeringstraject vinden meerdere interne audits in
 * korte tijd plaats: eerst een nulmeting tegen de hele norm, daarna een
 * volwaardige interne audit, soms een her-audit. Een nulmeting dekt per
 * definitie álles in één keer; registreer je hem als gewone programma-ronde, dan
 * kleurt de dekkingsmatrix in jaar 1 volledig groen en zien jaar 2 en 3 er
 * overbodig uit — het omgekeerde van wat §9.2.2 wil tonen.
 *
 * Twee kolommen met een strikte taakverdeling:
 *  - `type` is beschrijvend (wát de ronde was) en verandert nooit;
 *  - `telt_mee_voor_dekking` is een planningsbeslissing die de CISO mag omzetten.
 * Een her-audit is daardoor gewoon `intern` met de vlag uit; daar is geen derde
 * type voor nodig.
 *
 * Puur additief: bestaande rondes houden hun type en krijgen de vlag op true,
 * wat exact het huidige gedrag is.
 */
return new class extends Migration
{
    private const NIEUW = ['intern', 'intern_nulmeting', 'extern_certificering', 'extern_surveillance'];

    private const OUD = ['intern', 'extern_certificering', 'extern_surveillance'];

    public function up(): void
    {
        Schema::table('auditrondes', function (Blueprint $table) {
            $table->enum('type', self::NIEUW)->change();
        });

        Schema::table('auditrondes', function (Blueprint $table) {
            // Default true: wat er nu staat telde mee, en dat moet zo blijven.
            $table->boolean('telt_mee_voor_dekking')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        // De enum kan niet terug zolang er nulmetingen bestaan. Die worden gewone
        // interne rondes — dat is inhoudelijk wat ze zijn; alleen het onderscheid
        // gaat verloren. De vlag verdwijnt hier sowieso, dus een teruggedraaide
        // installatie telt ze daarna weer mee voor de dekking.
        DB::table('auditrondes')->where('type', 'intern_nulmeting')->update(['type' => 'intern']);

        Schema::table('auditrondes', function (Blueprint $table) {
            $table->dropColumn('telt_mee_voor_dekking');
        });

        Schema::table('auditrondes', function (Blueprint $table) {
            $table->enum('type', self::OUD)->change();
        });
    }
};
