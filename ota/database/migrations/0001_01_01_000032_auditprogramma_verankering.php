<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 11c fase 2 en 3: het programmajaar loskoppelen van het kalenderjaar, en
 * de opstartfase als eigen soort programma.
 *
 * De auditcyclus hoort te synchroniseren met de certificeringscyclus, en die
 * begint op de certificaatdatum — niet op 1 januari. In het oude model kon dat
 * niet: `auditprogrammas.start_jaar` is een jaartal, `auditplannen.jaar` was
 * globaal `unique()` (dus één plan per kalenderjaar in de hele installatie), en
 * de dekkingsmatrix bucket op `uitgevoerd_op->year`. Een cyclus die in mei
 * begint, laat daardoor elke ronde in de verkeerde kolom vallen.
 *
 * Daarom:
 *  - `auditprogrammas.start_jaar` → `start_datum` (date), plus `aard` om een
 *    voorbereidingsprogramma van een certificeringscyclus te onderscheiden;
 *  - `auditplannen` krijgt `programmajaar` (1..N) en het feitelijke venster
 *    `periode_start`/`periode_eind`. `jaar` blijft als menselijk label, maar
 *    verliest zijn unique — in de opstartfase liggen er juist meerdere plannen
 *    in hetzelfde kalenderjaar;
 *  - `auditprogramma_dekkingen.gepland_start_peiljaar` → `..._programmajaar`:
 *    een dekkingsregel plant in cyclustermen, niet in kalendertermen.
 *
 * `periode_start`/`periode_eind` zijn gedenormaliseerd uit het programma. Dat is
 * een bewuste keuze (plan 11c §8): de matrix moet in één query kunnen bucketen.
 * Verschuift de startdatum van een programma, dan moeten de periodes van zijn
 * jaarplannen mee — dat gebeurt op één plek, in AuditProgrammaBeheer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditprogrammas', function (Blueprint $table) {
            $table->date('start_datum')->nullable()->after('naam');
            $table->enum('aard', ['voorbereiding', 'certificeringscyclus'])
                ->default('certificeringscyclus')->after('aantal_jaren');
        });

        Schema::table('auditplannen', function (Blueprint $table) {
            $table->unsignedTinyInteger('programmajaar')->nullable()->after('auditprogramma_id');
            $table->date('periode_start')->nullable()->after('jaar');
            $table->date('periode_eind')->nullable()->after('periode_start');
        });

        Schema::table('auditprogramma_dekkingen', function (Blueprint $table) {
            $table->unsignedTinyInteger('gepland_start_programmajaar')->nullable()->after('interval_jaren');
        });

        $this->vulNieuweKolommen();

        // Pas ná het vullen: de oude kolommen zijn de bron van de nieuwe.
        Schema::table('auditplannen', function (Blueprint $table) {
            $table->dropUnique('auditplannen_jaar_unique');
            // Binnen één cyclus bestaat elk programmajaar precies één keer. Losse
            // plannen (auditprogramma_id null) vallen hier buiten — die zijn er in
            // de opstartfase juist meerdere.
            $table->unique(['auditprogramma_id', 'programmajaar'], 'auditplan_programmajaar_uniek');
        });

        Schema::table('auditprogrammas', function (Blueprint $table) {
            $table->dropColumn('start_jaar');
        });

        Schema::table('auditprogramma_dekkingen', function (Blueprint $table) {
            $table->dropColumn('gepland_start_peiljaar');
        });
    }

    /**
     * Bestaande cycli waren kalendergebonden, dus deze omzetting is verliesloos:
     * 1 januari van het startjaar, programmajaren die op 1 januari beginnen.
     */
    private function vulNieuweKolommen(): void
    {
        $programmas = DB::table('auditprogrammas')->get()->keyBy('id');

        foreach ($programmas as $programma) {
            DB::table('auditprogrammas')->where('id', $programma->id)->update([
                'start_datum' => Carbon::create($programma->start_jaar, 1, 1)->toDateString(),
            ]);
        }

        foreach (DB::table('auditplannen')->whereNotNull('auditprogramma_id')->get() as $plan) {
            $programma = $programmas->get($plan->auditprogramma_id);

            if ($programma === null) {
                continue;
            }

            DB::table('auditplannen')->where('id', $plan->id)->update([
                'programmajaar' => max(1, $plan->jaar - $programma->start_jaar + 1),
                'periode_start' => Carbon::create($plan->jaar, 1, 1)->toDateString(),
                'periode_eind' => Carbon::create($plan->jaar, 12, 31)->toDateString(),
            ]);
        }

        foreach (DB::table('auditprogramma_dekkingen')->get() as $dekking) {
            $programma = $programmas->get($dekking->auditprogramma_id);

            if ($programma === null || $dekking->gepland_start_peiljaar === null) {
                continue;
            }

            DB::table('auditprogramma_dekkingen')->where('id', $dekking->id)->update([
                'gepland_start_programmajaar' => max(1, $dekking->gepland_start_peiljaar - $programma->start_jaar + 1),
            ]);
        }
    }

    /**
     * Terugdraaien is een noodrem, geen routine, en het is lossy: bij een
     * programma dat niet op 1 januari start, valt `start_jaar` terug op het jaar
     * van `start_datum` en verschuift de cyclus naar het kalenderjaar. Precies de
     * fout die deze migratie repareert komt daarmee terug.
     */
    public function down(): void
    {
        Schema::table('auditprogrammas', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_jaar')->default(2000)->after('naam');
        });

        Schema::table('auditprogramma_dekkingen', function (Blueprint $table) {
            $table->unsignedSmallInteger('gepland_start_peiljaar')->nullable()->after('interval_jaren');
        });

        $programmas = DB::table('auditprogrammas')->get()->keyBy('id');

        foreach ($programmas as $programma) {
            $startJaar = (int) Carbon::parse($programma->start_datum)->year;
            DB::table('auditprogrammas')->where('id', $programma->id)->update(['start_jaar' => $startJaar]);

            DB::table('auditprogramma_dekkingen')
                ->where('auditprogramma_id', $programma->id)
                ->whereNotNull('gepland_start_programmajaar')
                ->get()
                ->each(fn ($dekking) => DB::table('auditprogramma_dekkingen')
                    ->where('id', $dekking->id)
                    ->update(['gepland_start_peiljaar' => $startJaar + $dekking->gepland_start_programmajaar - 1]));
        }

        Schema::table('auditplannen', function (Blueprint $table) {
            $table->dropUnique('auditplan_programmajaar_uniek');
            $table->dropColumn(['programmajaar', 'periode_start', 'periode_eind']);
        });

        // De oude unique kan alleen terug als er geen dubbele jaren staan; die
        // ontstaan juist in de opstartfase. Bewust geen poging tot opruimen.
        Schema::table('auditplannen', function (Blueprint $table) {
            $table->unique('jaar', 'auditplannen_jaar_unique');
        });

        Schema::table('auditprogrammas', function (Blueprint $table) {
            $table->dropColumn(['start_datum', 'aard']);
        });

        Schema::table('auditprogramma_dekkingen', function (Blueprint $table) {
            $table->dropColumn('gepland_start_programmajaar');
        });
    }
};
