<?php

namespace Tests\Feature;

use App\Mcp\IsmsServer;
use App\Mcp\Tools\KpiReeks;
use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De tool `kpi-reeks` (implementatie/14b §5, §12.2): dezelfde catalogus als
 * de meetaanpak, dezelfde afleiding als Kpitrend.
 */
class AssistentKpiReeksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class, KpiDefinitieSeeder::class]);
    }

    public function test_auditor_ziet_dezelfde_catalogus_als_de_meetaanpak(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        KpiDefinitie::query()->orderBy('id')->first()->update(['actief' => false]);

        // De query van MeetaanpakOverzicht::render(): alles, ook inactief, op naam.
        $verwacht = KpiDefinitie::query()->orderBy('naam')->pluck('sleutel')->all();

        IsmsServer::actingAs($auditor)
            ->tool(KpiReeks::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($verwacht) {
                $json->where('totaal', count($verwacht))
                    ->where('kpis', fn ($kpis) => collect($kpis)->pluck('sleutel')->all() === $verwacht)
                    ->etc();
            });
    }

    public function test_medewerker_zonder_leesrecht_wordt_geweigerd(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        IsmsServer::actingAs($medewerker)
            ->tool(KpiReeks::class)
            ->assertHasErrors();
    }

    public function test_reeks_met_periode_trend_en_versiebreuk(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $kpi = KpiDefinitie::create([
            'sleutel' => 'phishing_klikratio',
            'meetbron' => null,
            'naam' => 'Klikratio phishingsimulatie',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omlaag',
            'berekeningswijze' => 'Klikkers gedeeld door verzonden simulatiemails.',
            'streefwaarde' => 10,
            'signaalwaarde' => 20,
            'streefwaarde_vastgesteld_op' => now()->subYears(2),
            'definitie_versie' => 2,
            'actief' => true,
        ]);
        $kpi->metingen()->create([
            'gemeten_op' => now()->subMonthsNoOverflow(14), 'teller' => 30, 'noemer' => 100,
            'definitie_versie' => 1, 'streefwaarde' => 10, 'signaalwaarde' => 20,
        ]);
        $kpi->metingen()->create([
            'gemeten_op' => now()->subMonthsNoOverflow(1), 'teller' => 5, 'noemer' => 100,
            'definitie_versie' => 2, 'streefwaarde' => 10, 'signaalwaarde' => 20,
            'toelichting' => 'Nieuwe simulatiemethode.',
        ]);

        IsmsServer::actingAs($ciso)
            ->tool(KpiReeks::class, ['sleutels' => ['phishing_klikratio'], 'van' => now()->subMonthsNoOverflow(6)->toDateString()])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('totaal', 1)
                    ->where('kpis.0.richting.code', 'omlaag')
                    ->where('kpis.0.definitie_versie', 2)
                    ->where('kpis.0.metingen_totaal', 2)
                    ->where('kpis.0.metingen_in_periode', 1)
                    ->where('kpis.0.metingen.0.teller', 5)
                    ->where('kpis.0.metingen.0.uitkomst', 5)
                    ->where('kpis.0.metingen.0.status.code', 'goed')
                    ->where('kpis.0.metingen.0.toelichting', 'Nieuwe simulatiemethode.')
                    // De trend loopt over de hele reeks, niet alleen de periode.
                    ->where('kpis.0.trend.delta', -25)
                    ->where('kpis.0.trend.beweging.code', 'op')
                    ->where('kpis.0.trend.versiebreuk_in_delta', true)
                    ->where('kpis.0.url', route('meetaanpak.index'))
                    ->etc();
            });
    }
}
