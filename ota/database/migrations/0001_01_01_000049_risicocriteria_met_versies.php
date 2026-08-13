<?php

use App\Support\Normprofiel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De risicocriteria worden een vastgesteld kader met eigen versies
 * (implementatie/04g §3).
 *
 * `risicoacceptatiecriteria` ging uit van één rij die de CISO op elk moment kon
 * verzetten. Daarmee is de acceptatiedrempel een instelling en geen bestuurlijk
 * kader: geen moment van vaststellen, geen goedkeurder, geen geldigheidsdatum,
 * en — het ergste — geen manier om achteraf te zien tegen welk criterium een
 * risico destijds beoordeeld is. Die tabel wordt daarom niet uitgebreid maar
 * vervangen door een versiereeks, met dezelfde statusgang als
 * `scope_verklaringen`.
 *
 * De betekenis van de niveaus 1 t/m 5 gaat mee de versie in. Tot nu toe stond
 * die alleen in `config/beoordelingsschaal.php` en was ze dus alleen met een
 * deploy te wijzigen; vanaf hier is dat bestand de seedbron en de tabel de
 * bron-tijdens-het-draaien (00j §0 schreef precies deze route voor).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eén versie draagt het hele kader: de risk-appetite-verklaring, beide
        // drempels, de leidraad per as en (via `beoordelingsniveaus`) de tien
        // niveaudefinities. Een acceptatiedrempel van 12 zegt niets zonder de
        // schaal eronder, dus ze worden in één handeling vastgesteld (04g §2.1).
        Schema::create('risicocriteria_versies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('versienummer');
            $table->enum('status', ['concept', 'ter_goedkeuring', 'actief', 'vervangen'])
                ->default('concept');
            $table->text('omschrijving'); // de risk-appetite-verklaring
            $table->unsignedTinyInteger('drempelwaarde_score');       // rood: score > deze waarde
            $table->unsignedTinyInteger('waarschuwingsdrempel_score'); // amber: score >= deze waarde
            // De leidraden als kolommen en niet als derde tabel: het zijn twee
            // vaste assen, geen groeiende verzameling.
            $table->text('leidraad_kans');
            $table->text('leidraad_impact');
            $table->date('geldig_vanaf')->nullable();
            $table->string('goedgekeurd_door')->nullable(); // vrij tekstveld, net als bij scope
            // Waar dit kader is vastgelegd. Een verwijzing en geen import: het
            // beleidsstuk is een PDF, en het systeem kan niet zien dat daar 12
            // in staat waar de database 15 zegt (04g §2.4). Nullable, want een
            // verse installatie heeft nog geen beleid en geen reviewsessie; het
            // scherm meldt het ontbreken in amber in plaats van te blokkeren.
            $table->foreignId('beleidsdocument_id')->nullable()
                ->constrained('beleidsdocumenten')->nullOnDelete();
            $table->foreignId('besluit_id')->nullable()
                ->constrained('besluiten')->nullOnDelete();
            $table->date('volgende_herziening_gepland')->nullable();
            $table->text('wijzigingsreden')->nullable();
            $table->timestamps();
        });

        // Wat een 4 betekent. `kwantitatieve_band` is het veld waar de
        // organisatie het bedrag of percentage kwijt kan dat een op cijfers
        // sturende auditor zoekt ("impact 4 = 1 tot 5% van de jaaromzet"). Leeg
        // uitgeleverd: het ISMS gaat niet zeggen wat 2% van úw omzet is.
        //
        // Let op waar die eis op slaat: aan de *score* hangt geen bedrag, want
        // 10 is zowel 2x5 als 5x2 en die twee kosten niet hetzelfde.
        // Kwantificeren kan alleen per niveau (04g §1.2b).
        Schema::create('beoordelingsniveaus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risicocriteria_versie_id')
                ->constrained('risicocriteria_versies')->cascadeOnDelete();
            $table->enum('as', ['kans', 'impact']);
            $table->unsignedTinyInteger('niveau'); // 1 t/m Risicoverdeling::SCHAAL
            $table->string('naam');
            $table->text('omschrijving');
            $table->string('kwantitatieve_band')->nullable();
            $table->timestamps();

            $table->unique(['risicocriteria_versie_id', 'as', 'niveau'], 'beoordelingsniveau_unique');
        });

        // Het meetpunt draagt de definitie waaronder het tot stand kwam —
        // hetzelfde patroon als `metingen.definitie_versie`. Zonder dit is van
        // een risico dat vorig jaar als aanvaardbaar is beoordeeld achteraf niet
        // vast te stellen tegen welke drempel dat gebeurde.
        Schema::table('risicos', function (Blueprint $table) {
            $table->foreignId('risicocriteria_versie_id')->nullable()->after('risicoscore')
                ->constrained('risicocriteria_versies')->nullOnDelete();
        });

        // Zelfde argument, één niveau hoger: `max_restrisico` is een getal op de
        // 1-25-schaal, en verandert de betekenis van impact 4, dan zijn twee
        // peiljaren niet meer vergelijkbaar terwijl de trendgrafiek er vrolijk
        // doorheen tekent. `definitie_versie` blijft waarvoor het bedoeld is —
        // de versie van de rollup-definitie — en krijgt er niet stilletjes een
        // tweede betekenis bij (04g §8.3).
        Schema::table('restrisico_snapshots', function (Blueprint $table) {
            $table->foreignId('risicocriteria_versie_id')->nullable()->after('definitie_versie')
                ->constrained('risicocriteria_versies')->nullOnDelete();
        });

        $this->neemBestaandeCriteriaOver();

        Schema::dropIfExists('risicoacceptatiecriteria');
    }

    /**
     * De bestaande rij wordt versie 1 met status `actief`, zodat er na de
     * migratie niets te zien is dat de dag ervoor anders was.
     *
     * Alleen als die rij er is. Op een verse database (en dus ook in de
     * testsuite, waar `RefreshDatabase` elke klasse opnieuw migreert) staat er
     * niets om over te nemen; daar legt `RisicocriteriaSeeder` versie 1 aan. Dat
     * is niet alleen zuiniger maar noodzakelijk: het normprofiel hieronder is
     * bij een kale migratie nog niet vastgelegd en `Normprofiel::actief()` gooit
     * dan — terecht, maar niet op dit moment.
     */
    private function neemBestaandeCriteriaOver(): void
    {
        $bestaand = DB::table('risicoacceptatiecriteria')->orderBy('id')->first();

        if ($bestaand === null) {
            return;
        }

        $schaal = config('beoordelingsschaal');
        $profiel = Normprofiel::actief();

        $versieId = DB::table('risicocriteria_versies')->insertGetId([
            'versienummer' => 1,
            'status' => 'actief',
            'omschrijving' => $bestaand->omschrijving,
            'drempelwaarde_score' => $bestaand->drempelwaarde_score,
            'waarschuwingsdrempel_score' => $bestaand->waarschuwingsdrempel_score,
            'leidraad_kans' => $schaal['kans']['leidraad'],
            'leidraad_impact' => $schaal['impact']['profielen'][$profiel]['leidraad'],
            // Expliciet naar een datum: `created_at` is een tijdstip en een
            // DATE-kolom zou dat op MySQL stilzwijgend afkappen.
            'geldig_vanaf' => Carbon::parse($bestaand->created_at)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rijen = [];

        foreach (['kans' => $schaal['kans'], 'impact' => $schaal['impact']['profielen'][$profiel]] as $as => $definitie) {
            foreach ($definitie['niveaus'] as $niveau => $inhoud) {
                $rijen[] = [
                    'risicocriteria_versie_id' => $versieId,
                    'as' => $as,
                    'niveau' => $niveau,
                    'naam' => $inhoud['naam'],
                    'omschrijving' => $inhoud['omschrijving'],
                    'kwantitatieve_band' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('beoordelingsniveaus')->insert($rijen);

        // Elk beoordeeld risico is onder deze criteria beoordeeld; dat is de
        // enige uitspraak die met terugwerkende kracht klopt.
        DB::table('risicos')->whereNotNull('risicoscore')
            ->update(['risicocriteria_versie_id' => $versieId]);

        DB::table('restrisico_snapshots')->update(['risicocriteria_versie_id' => $versieId]);
    }

    /**
     * De omgekeerde weg, met verlies van de versiehistorie: de oude tabel kan
     * maar één kader dragen. Zelfde afweging als bij de andere migraties die
     * een enkele rij door een reeks vervangen.
     */
    public function down(): void
    {
        Schema::create('risicoacceptatiecriteria', function (Blueprint $table) {
            $table->id();
            $table->string('omschrijving');
            $table->unsignedTinyInteger('drempelwaarde_score');
            $table->unsignedTinyInteger('waarschuwingsdrempel_score')->default(10);
            $table->timestamps();
        });

        $actief = DB::table('risicocriteria_versies')->where('status', 'actief')->first();

        if ($actief !== null) {
            DB::table('risicoacceptatiecriteria')->insert([
                'omschrijving' => $actief->omschrijving,
                'drempelwaarde_score' => $actief->drempelwaarde_score,
                'waarschuwingsdrempel_score' => $actief->waarschuwingsdrempel_score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('risicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risicocriteria_versie_id');
        });

        Schema::table('restrisico_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risicocriteria_versie_id');
        });

        Schema::dropIfExists('beoordelingsniveaus');
        Schema::dropIfExists('risicocriteria_versies');
    }
};
