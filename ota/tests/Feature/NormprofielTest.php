<?php

namespace Tests\Feature;

use App\Console\Commands\ExporteerIsms;
use App\Livewire\LeverancierDetail;
use App\Livewire\SoaOverzicht;
use App\Models\Gebruiker;
use App\Models\Leverancier;
use App\Support\Normprofiel;
use Database\Seeders\BlokSeeder;
use Database\Seeders\NormprofielSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * Het normprofiel (implementatie/00h-normprofiel.md).
 *
 * De schakelaar en de labellaag; nog geen inhoud. Wat hier NIET wordt getest,
 * omdat het nog niet bestaat: de controlset van 93 naar 101, de
 * zorgaanvullingen, de kennisbankvarianten en de schaalteksten (stap 4 t/m 6
 * van nen7510-opzet.md §7).
 *
 * Elke test zet het profiel dat hij nodig heeft zélf, ook de ISO-kant. Leunen op
 * de standaard uit `phpunit.xml` maakt de hele suite onbruikbaar als controle:
 * draai je hem één keer met `ISMS_NORM=nen7510` om te zien of de applicatie
 * profielvast is, dan vallen juist deze tests om zonder dat er iets mis is
 * (00k §3).
 */
#[Group('nen7510')]
class NormprofielTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    public function test_het_iso_profiel_levert_de_iso_labels(): void
    {
        config()->set('norm.actief', 'iso27001');

        $this->assertTrue(Normprofiel::is('iso27001'));
        $this->assertSame('ISO/IEC 27001', Normprofiel::label('naam'));
        $this->assertSame('Annex A', Normprofiel::label('bijlage'));
    }

    /**
     * De keuze staat in de database, niet in `.env` (migratie 000048). Deze
     * tests zetten de configcache expliciet op null: `Tests\TestCase` vult hem
     * met iso27001 zodat de rest van de suite geen rij hoeft te seeden.
     */
    public function test_het_actieve_profiel_komt_uit_de_tabel(): void
    {
        config()->set('norm.actief', null);
        DB::table('normprofiel')->insert(['profiel' => 'nen7510', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('nen7510', Normprofiel::actief());
        // En daarna uit de cache: nog één query per verzoek is er één te veel.
        $this->assertSame('nen7510', config('norm.actief'));
    }

    /**
     * Zonder vastgelegd profiel is er geen terugval. Stil op ISO uitkomen zou van
     * een half opgezette zorginstallatie een ISO-installatie maken, en dat merkt
     * niemand tot de eerste audit.
     */
    public function test_zonder_vastgelegd_profiel_gooit_de_applicatie(): void
    {
        config()->set('norm.actief', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('geen normprofiel vastgelegd');

        Normprofiel::actief();
    }

    public function test_de_seeder_legt_vast_wat_in_ismsnorm_staat(): void
    {
        $this->metNormkeuze('nen7510', function () {
            $this->seed(NormprofielSeeder::class);

            $this->assertDatabaseHas('normprofiel', ['profiel' => 'nen7510']);
        });
    }

    /**
     * De regressietest bij het uitrolincident van 06-08-2026: een uitrol met
     * `ISMS_NORM=nen7510` in `.env` leverde een ISO-installatie op. `deploy.sh`
     * draait `config:cache` vóór `db:seed`, en daarna leest Laravel `.env` niet
     * meer — de `env('ISMS_NORM')` in deze seeder gaf toen stilzwijgend de
     * standaard terug. Hier staat de variabele dus nergens in de omgeving en
     * alleen in de configuratie, precies zoals op een uitgerolde host.
     */
    public function test_de_seeder_leest_de_keuze_zonder_leesbare_env(): void
    {
        $this->metNormkeuze('nen7510', function () {
            $this->assertFalse(getenv('ISMS_NORM'), 'Deze test moet het zonder de omgevingsvariabele doen.');

            $this->seed(NormprofielSeeder::class);

            $this->assertDatabaseHas('normprofiel', ['profiel' => 'nen7510']);
            $this->assertDatabaseMissing('normprofiel', ['profiel' => 'iso27001']);
        });
    }

    /** De uitgeleverde standaard is ISO; dat is een keuze van de seeder. */
    public function test_de_seeder_valt_zonder_ismsnorm_terug_op_iso(): void
    {
        $this->metNormkeuze(null, function () {
            $this->seed(NormprofielSeeder::class);

            $this->assertDatabaseHas('normprofiel', ['profiel' => 'iso27001']);
        });
    }

    /**
     * De installatie is het laatste moment waarop een typefout nog te corrigeren
     * is; daarna staat hij in de tabel en volgt de hele SoA hem.
     */
    public function test_de_seeder_weigert_een_onbekend_profiel(): void
    {
        $this->metNormkeuze('nen7501', function () {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("Onbekend normprofiel 'nen7501'");

            $this->seed(NormprofielSeeder::class);
        });
    }

    /**
     * Een deploy die de seeders herhaalt mag de norm niet omzetten omdat er
     * toevallig een andere `ISMS_NORM` in de omgeving staat. Wisselen kan alleen
     * door de database opnieuw op te bouwen.
     */
    public function test_de_seeder_zet_een_vastgelegd_profiel_niet_om(): void
    {
        DB::table('normprofiel')->insert(['profiel' => 'iso27001', 'created_at' => now(), 'updated_at' => now()]);

        $this->metNormkeuze('nen7510', function () {
            $this->seed(NormprofielSeeder::class);

            $this->assertDatabaseHas('normprofiel', ['profiel' => 'iso27001']);
            $this->assertSame(1, DB::table('normprofiel')->count());
        });
    }

    /**
     * Zet de installatiekeuze zoals `config:cache` hem zou vastleggen: de waarde
     * in `config('norm.keuze')`, en `ISMS_NORM` wég uit de omgeving. Dat laatste
     * hoort erbij en is geen bijzaak — het is precies de toestand op een
     * uitgerolde host, en een seeder die stiekem toch `env()` gebruikt valt er
     * hier op om. `null` betekent: geen keuze uitgesproken.
     */
    private function metNormkeuze(?string $waarde, callable $test): void
    {
        $eerder = [getenv('ISMS_NORM'), $_ENV['ISMS_NORM'] ?? null, $_SERVER['ISMS_NORM'] ?? null];

        putenv('ISMS_NORM');
        unset($_ENV['ISMS_NORM'], $_SERVER['ISMS_NORM']);
        config()->set('norm.keuze', $waarde);

        try {
            $test();
        } finally {
            [$envvar, $env, $server] = $eerder;
            $envvar === false ? putenv('ISMS_NORM') : putenv('ISMS_NORM='.$envvar);
            unset($_ENV['ISMS_NORM'], $_SERVER['ISMS_NORM']);
            if ($env !== null) {
                $_ENV['ISMS_NORM'] = $env;
            }
            if ($server !== null) {
                $_SERVER['ISMS_NORM'] = $server;
            }
        }
    }

    /**
     * De belangrijkste test van dit plan. Een typefout in `ISMS_NORM` mag niet
     * stilzwijgend het ISO-profiel opleveren op een zorginstallatie — dan claimt
     * het ISMS een norm die het niet volgt en merkt niemand het.
     */
    public function test_een_onbekend_profiel_gooit_en_valt_niet_terug_op_iso(): void
    {
        config()->set('norm.actief', 'nen7501');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Onbekend normprofiel 'nen7501'");

        Normprofiel::actief();
    }

    /** Een typefout in een blade hoort een fout te zijn, geen gat in de zin. */
    public function test_een_onbekende_labelsleutel_gooit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Onbekende normlabel-sleutel 'bijlaag'");

        Normprofiel::label('bijlaag');
    }

    public function test_capaciteiten_horen_bij_het_profiel_en_niet_bij_de_rechten(): void
    {
        config()->set('norm.actief', 'iso27001');
        $this->assertFalse(Normprofiel::heeft('zorgaanvulling'));

        config()->set('norm.actief', 'nen7510');
        $this->assertTrue(Normprofiel::heeft('zorgaanvulling'));
        $this->assertTrue(Normprofiel::heeft('zorgterminologie'));
        // Vragen naar wat je nodig hebt, niet naar wie je bent — een onbekende
        // capaciteit is gewoon false en geen fout.
        $this->assertFalse(Normprofiel::heeft('verzonnen-capaciteit'));
    }

    /**
     * Bewijst dat de labellaag echt doorwerkt tot in de schermtekst, in beide
     * profielen. Zonder deze test is een vergeten hardgecodeerde "Annex A" niet
     * te zien tot iemand de schakelaar omzet.
     */
    public function test_de_soa_toont_de_actieve_norm(): void
    {
        config()->set('norm.actief', 'iso27001');

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->assertSee('Annex A')
            ->assertSee('ISO/IEC 27001')
            ->assertDontSee('NEN 7510');

        config()->set('norm.actief', 'nen7510');

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->assertSee('Bijlage A')
            ->assertSee('NEN 7510')
            ->assertDontSee('ISO/IEC 27001');
    }

    /**
     * De zijbalk staat op élk scherm, dus dit is de plek waar de norm nooit weg
     * kan vallen. Geen `assertDontSee` op de andere norm hier: de zijbalk toont
     * ook een link naar het kennisbankartikel over NEN 7510.
     */
    public function test_de_zijbalk_toont_de_actieve_norm_boven_het_menu(): void
    {
        config()->set('norm.actief', 'iso27001');

        $this->actingAs($this->ciso)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ISMS ISO 27001');

        config()->set('norm.actief', 'nen7510');

        $this->actingAs($this->ciso)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ISMS NEN 7510');
    }

    /**
     * Wat je van een leverancier vraagt is iets anders dan wat je zelf volgt:
     * ISO 27001 is wat leveranciers internationaal aantoonbaar hebben. Wie dit
     * meevertaalt, laat het ISMS om een certificaat vragen dat de leverancier
     * niet kan leveren.
     */
    public function test_het_leverancierscertificaat_blijft_iso_noemen_in_zorgmodus(): void
    {
        config()->set('norm.actief', 'nen7510');
        $leverancier = Leverancier::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->assertSee('NEN 7510 of ISO 27001');
    }

    /** Een export verlaat het systeem en moet zichzelf kunnen uitleggen. */
    public function test_de_export_noemt_de_actieve_norm(): void
    {
        config()->set('norm.actief', 'nen7510');
        $doel = storage_path('framework/testing/normprofiel-export');
        File::deleteDirectory($doel);

        $this->artisan(ExporteerIsms::class, ['--doel' => $doel])->assertSuccessful();

        $overzicht = File::glob($doel.'/*/00-overzicht.md')[0] ?? null;
        $this->assertNotNull($overzicht, 'Geen overzichtsbestand gegenereerd.');

        $md = File::get($overzicht);
        $this->assertStringContainsString('ingericht op **NEN 7510**', $md);
        $this->assertStringContainsString('Bijlage A', $md);

        File::deleteDirectory($doel);
    }
}
