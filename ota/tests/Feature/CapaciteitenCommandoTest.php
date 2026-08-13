<?php

namespace Tests\Feature;

use App\Models\Maatregel;
use App\Support\Maatregelkenmerken;
use Database\Seeders\MaatregelKenmerkenSeeder;
use Database\Seeders\MaatregelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `isms:capaciteiten` is de ondersteunde weg om de vijfde attribuutdimensie aan
 * te zetten in een installatie die ISO 27002 bezit. Het repo levert de data niet
 * mee; het commando leest een lokaal, gitignored bronbestand.
 *
 * De schakelaar staat in `.env`. Deze test wijst de omgevingsmap naar een
 * tijdelijke map, zodat de echte `.env` van de ontwikkelaar nooit geraakt wordt.
 */
class CapaciteitenCommandoTest extends TestCase
{
    use RefreshDatabase;

    private string $tijdelijkeMap;

    private string $bronpad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tijdelijkeMap = sys_get_temp_dir().'/capaciteiten-'.uniqid();
        mkdir($this->tijdelijkeMap);
        file_put_contents($this->tijdelijkeMap.'/.env', "APP_ENV=testing\n");

        $this->app->useEnvironmentPath($this->tijdelijkeMap);

        // Het bronbestand OOK in de tijdelijke map: het echte pad ligt in
        // database/seeders/data/ en daar staat het bestand van de installatie
        // zelf. Een test die dat overschrijft en daarna opruimt, gooit werk van
        // de gebruiker weg — dat is één keer gebeurd en mag niet nog eens.
        $this->bronpad = $this->tijdelijkeMap.'/maatregel-capaciteiten.json';
        config(['maatregelkenmerken.capaciteiten.bron' => $this->bronpad]);

        Maatregelkenmerken::vergeetBron();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tijdelijkeMap.'/*') ?: []);
        @rmdir($this->tijdelijkeMap);

        Maatregelkenmerken::vergeetBron();

        parent::tearDown();
    }

    private function schrijfBron(array $data): void
    {
        file_put_contents($this->bronpad, json_encode($data));
        Maatregelkenmerken::vergeetBron();
    }

    private function geldigeBron(): array
    {
        return [
            'vocabulaire' => ['Governance', 'Veilige configuratie'],
            'regels' => [
                ['annex_a_referentie' => '5.1', 'capaciteiten' => ['Governance']],
                ['annex_a_referentie' => '8.9', 'capaciteiten' => ['Veilige configuratie']],
            ],
        ];
    }

    private function envInhoud(): string
    {
        return file_get_contents($this->tijdelijkeMap.'/.env');
    }

    public function test_standaard_staat_de_dimensie_uit(): void
    {
        $this->assertFalse(Maatregelkenmerken::isActief('capaciteiten'));
        $this->assertArrayNotHasKey('capaciteiten', Maatregelkenmerken::dimensies());
    }

    public function test_aanzetten_zonder_bronbestand_weigert(): void
    {
        $this->artisan('isms:capaciteiten aan')
            ->expectsOutputToContain('Geen bronbestand')
            ->assertFailed();

        $this->assertStringNotContainsString('ISMS_CAPACITEITEN', $this->envInhoud());
    }

    public function test_aanzetten_schrijft_de_schakelaar_en_de_waarden(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);
        $this->schrijfBron($this->geldigeBron());

        $this->artisan('isms:capaciteiten aan')->assertSuccessful();

        $this->assertStringContainsString('ISMS_CAPACITEITEN=true', $this->envInhoud());

        $vijfEen = Maatregel::where('annex_a_referentie', '5.1')->firstOrFail();
        $this->assertSame(['Governance'], $vijfEen->kenmerken['capaciteiten']);

        // De vier meegeleverde dimensies blijven staan naast de vijfde.
        $this->assertNotEmpty($vijfEen->kenmerken['type']);
        $this->assertNotEmpty($vijfEen->kenmerken['domeinen']);
    }

    public function test_bron_met_waarden_buiten_het_vocabulaire_wordt_geweigerd(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);
        $this->schrijfBron([
            'vocabulaire' => ['Governance'],
            'regels' => [
                ['annex_a_referentie' => '5.1', 'capaciteiten' => ['Verzonnen capaciteit']],
            ],
        ]);

        $this->artisan('isms:capaciteiten aan')
            ->expectsOutputToContain('buiten het vocabulaire')
            ->assertFailed();

        // Niets half toegepast: geen schakelaar en geen data.
        $this->assertStringNotContainsString('ISMS_CAPACITEITEN', $this->envInhoud());
        $this->assertArrayNotHasKey(
            'capaciteiten',
            Maatregel::where('annex_a_referentie', '5.1')->firstOrFail()->kenmerken
        );
    }

    public function test_uitzetten_ruimt_de_waarden_op_en_laat_de_rest_staan(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);
        $this->schrijfBron($this->geldigeBron());
        $this->artisan('isms:capaciteiten aan')->assertSuccessful();

        $this->artisan('isms:capaciteiten uit')->assertSuccessful();

        $this->assertStringContainsString('ISMS_CAPACITEITEN=false', $this->envInhoud());
        $this->assertStringNotContainsString('ISMS_CAPACITEITEN=true', $this->envInhoud());

        $vijfEen = Maatregel::where('annex_a_referentie', '5.1')->firstOrFail();
        $this->assertArrayNotHasKey('capaciteiten', $vijfEen->kenmerken);
        $this->assertNotEmpty($vijfEen->kenmerken['type']);
    }

    /**
     * De regressietest die het hele ontwerp draagt: MaatregelKenmerkenSeeder
     * draait bij elke deploy. Overschreef die de kolom plat, dan zou een
     * installatie haar capaciteiten stilzwijgend kwijtraken zodra er uitgerold
     * wordt.
     */
    public function test_deploy_seeder_laat_de_capaciteiten_staan(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);
        $this->schrijfBron($this->geldigeBron());
        $this->artisan('isms:capaciteiten aan')->assertSuccessful();

        $this->seed(MaatregelKenmerkenSeeder::class);

        $vijfEen = Maatregel::where('annex_a_referentie', '5.1')->firstOrFail();
        $this->assertSame(['Governance'], $vijfEen->kenmerken['capaciteiten']);
        $this->assertNotEmpty($vijfEen->kenmerken['type']);
    }

    public function test_vocabulaire_komt_uit_de_bron_maar_alleen_als_de_dimensie_aanstaat(): void
    {
        $this->schrijfBron($this->geldigeBron());

        // Bronbestand aanwezig, schakelaar uit: nog steeds geen waarden.
        $this->assertSame([], Maatregelkenmerken::waarden('capaciteiten'));

        config(['maatregelkenmerken.capaciteiten.actief' => true]);

        $this->assertSame(['Governance', 'Veilige configuratie'], Maatregelkenmerken::waarden('capaciteiten'));
    }

    public function test_status_meldt_data_die_niemand_ziet(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);
        $this->schrijfBron($this->geldigeBron());
        $this->artisan('isms:capaciteiten aan')->assertSuccessful();

        // Schakelaar terug naar uit zonder de data op te ruimen — precies de
        // situatie die iemand met de hand kan veroorzaken.
        config(['maatregelkenmerken.capaciteiten.actief' => false]);

        $this->artisan('isms:capaciteiten status')
            ->expectsOutputToContain('data in de database die niemand ziet')
            ->assertSuccessful();
    }
}
