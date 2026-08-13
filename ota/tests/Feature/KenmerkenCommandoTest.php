<?php

namespace Tests\Feature;

use App\Console\Commands\Kenmerken;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\SoaRegel;
use App\Models\Taak;
use Database\Seeders\BlokSeeder;
use Database\Seeders\MaatregelKenmerkenSeeder;
use Database\Seeders\MaatregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `isms:kenmerken` (implementatie/04f §4.1).
 *
 * De meegeleverde classificatie is een uitgangspunt; `kenmerken_eigen` op de
 * SoA-regel is de vaststelling van de organisatie. Dat veld is alles-of-niets,
 * dus een correctie op het uitgangspunt bereikt juist niet de regels waar iemand
 * naar gekeken heeft. Dit commando signaleert dat.
 *
 * De "gewijzigde uitgangswaarde" wordt hier nagebootst door de databasewaarde te
 * verzetten in plaats van het seedbestand te bewerken: het echte bestand staat
 * in `database/seeders/data/` en daar mag een test niet in schrijven.
 */
class KenmerkenCommandoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['norm.actief' => 'iso27001']);

        $this->seed([
            RolSeeder::class,
            BlokSeeder::class,
            RolPermissieSeeder::class,
            MaatregelSeeder::class,
            MaatregelKenmerkenSeeder::class,
        ]);
    }

    /** Zet de databasewaarde scheef, zodat de eerstvolgende seed hem wijzigt. */
    private function verzetUitgangspunt(string $referentie): Maatregel
    {
        $maatregel = Maatregel::where('annex_a_referentie', $referentie)->sole();
        $kenmerken = $maatregel->kenmerken;
        $kenmerken['type'] = ['Iets anders'];

        $maatregel->update(['kenmerken' => $kenmerken]);

        return $maatregel->fresh();
    }

    private function metEigenClassificatie(Maatregel $maatregel): SoaRegel
    {
        $regel = $maatregel->soaRegel;
        $regel->update(['kenmerken_eigen' => ['type' => ['Preventief']]]);

        return $regel->fresh();
    }

    private function taken(SoaRegel $regel): int
    {
        return Taak::where('gekoppeld_entiteit_type', $regel->getMorphClass())
            ->where('gekoppeld_entiteit_id', $regel->id)
            ->where('soort', Kenmerken::SOORT)
            ->count();
    }

    public function test_een_gewijzigd_uitgangspunt_levert_een_taak_bij_een_eigen_classificatie(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $regel = $this->metEigenClassificatie($this->verzetUitgangspunt('5.1'));

        $this->artisan('isms:kenmerken')
            ->expectsOutputToContain('1 SoA-regels met een eigen classificatie volgen een gewijzigd '
                .'uitgangspunt niet — taken aangemaakt.')
            ->assertSuccessful();

        $this->assertSame(1, $this->taken($regel));

        $taak = Taak::where('soort', Kenmerken::SOORT)->sole();
        $this->assertSame($ciso->id, $taak->eigenaar_id);
        $this->assertStringContainsString('A.5.1', $taak->titel);
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($taak->deadline));
        $this->assertSame('soa', $taak->gekoppeld_blok_naam);
    }

    /**
     * Zonder eigen classificatie volgt de regel het uitgangspunt vanzelf. Een
     * taak zou daar niets toevoegen en zou bij elke uitrol ruis geven.
     */
    public function test_een_regel_zonder_eigen_classificatie_levert_geen_taak(): void
    {
        $this->verzetUitgangspunt('5.1');

        $this->artisan('isms:kenmerken')
            ->expectsOutputToContain('0 SoA-regels met een eigen classificatie volgen een gewijzigd '
                .'uitgangspunt niet.')
            ->assertSuccessful();

        $this->assertSame(0, Taak::where('soort', Kenmerken::SOORT)->count());
    }

    /**
     * Ongewijzigd uitgangspunt, wél een eigen classificatie: dat is een bewuste
     * afwijking en geen achterstand. Zonder deze grens zou elke uitrol dezelfde
     * taken opnieuw opwerpen.
     */
    public function test_een_bewuste_afwijking_zonder_wijziging_levert_geen_taak(): void
    {
        $this->metEigenClassificatie(Maatregel::where('annex_a_referentie', '5.1')->sole());

        $this->artisan('isms:kenmerken')->assertSuccessful();

        $this->assertSame(0, Taak::where('soort', Kenmerken::SOORT)->count());
    }

    /** TaakPlanner is idempotent op (entiteit, soort): verzetten, niet stapelen. */
    public function test_een_tweede_ronde_verzet_de_taak_in_plaats_van_een_tweede_aan_te_maken(): void
    {
        $regel = $this->metEigenClassificatie($this->verzetUitgangspunt('5.1'));

        $this->artisan('isms:kenmerken')->assertSuccessful();
        $eerste = Taak::where('soort', Kenmerken::SOORT)->sole();

        $this->verzetUitgangspunt('5.1');
        $this->travel(5)->days();
        $this->artisan('isms:kenmerken')->assertSuccessful();

        $this->assertSame(1, $this->taken($regel));
        $this->assertSame($eerste->id, Taak::where('soort', Kenmerken::SOORT)->sole()->id);
        $this->assertTrue(Taak::find($eerste->id)->deadline->greaterThan($eerste->deadline));
    }

    /**
     * De verse-installatiestand: `isms:eerste-ciso` draait pas ná het seeden, dus
     * er is dan geen CISO. Het commando hoort daar niet op te struikelen.
     */
    public function test_zonder_ciso_komt_de_taak_er_zonder_eigenaar(): void
    {
        $regel = $this->metEigenClassificatie($this->verzetUitgangspunt('5.1'));

        $this->artisan('isms:kenmerken')
            ->expectsOutputToContain('Geen actieve CISO gevonden')
            ->assertSuccessful();

        $this->assertSame(1, $this->taken($regel));
        $this->assertNull(Taak::where('soort', Kenmerken::SOORT)->sole()->eigenaar_id);
    }

    /** Een gedeactiveerde CISO is geen eigenaar: je wijst geen werk toe aan wie niet kan inloggen. */
    public function test_een_gedeactiveerde_ciso_telt_niet_als_eigenaar(): void
    {
        Gebruiker::factory()->metRol('CISO')->create(['status' => 'gedeactiveerd']);
        $this->metEigenClassificatie($this->verzetUitgangspunt('5.1'));

        $this->artisan('isms:kenmerken')->assertSuccessful();

        $this->assertNull(Taak::where('soort', Kenmerken::SOORT)->sole()->eigenaar_id);
    }

    /** `--controleer` rapporteert en schrijft niets — geen seed, geen taken. */
    public function test_controleer_schrijft_niets(): void
    {
        $maatregel = $this->verzetUitgangspunt('5.1');
        $this->metEigenClassificatie($maatregel);

        $this->artisan('isms:kenmerken', ['--controleer' => true])
            ->expectsOutputToContain('1 SoA-regels met een eigen classificatie zouden die wijziging niet volgen.')
            ->assertSuccessful();

        $this->assertSame(0, Taak::where('soort', Kenmerken::SOORT)->count());
        $this->assertSame(['Iets anders'], $maatregel->fresh()->kenmerken['type']);
    }

    /** Het commando seedt ook echt: na afloop staat het uitgangspunt er weer op. */
    public function test_het_uitgangspunt_wordt_hersteld(): void
    {
        $maatregel = $this->verzetUitgangspunt('5.1');

        $this->artisan('isms:kenmerken')->assertSuccessful();

        $this->assertNotSame(['Iets anders'], $maatregel->fresh()->kenmerken['type']);
    }
}
