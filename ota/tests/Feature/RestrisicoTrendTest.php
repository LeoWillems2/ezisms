<?php

namespace Tests\Feature;

use App\Livewire\RestrisicoTrend;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\RestrisicoSnapshot;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RestrisicoTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class,
            RisicocriteriaSeeder::class,
        ]);
    }

    public function test_auditor_ziet_de_jaartrend_per_control(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        RestrisicoSnapshot::create([
            'soa_regel_id' => $regel->id, 'peiljaar' => 2026,
            'max_restrisico' => 12, 'aantal_risicos' => 2, 'definitie_versie' => 1,
        ]);
        RestrisicoSnapshot::create([
            'soa_regel_id' => $regel->id, 'peiljaar' => 2027,
            'max_restrisico' => 4, 'aantal_risicos' => 2, 'definitie_versie' => 1,
            'toelichting' => 'R-7 gemitigeerd',
        ]);

        $this->actingAs($auditor)->get('/soa/restrisico-trend')
            ->assertOk()
            ->assertSee('A.'.$regel->maatregel->annex_a_referentie)
            ->assertSee('2026')
            ->assertSee('2027')
            ->assertSee('R-7 gemitigeerd');
    }

    public function test_onbepaald_restrisico_wordt_als_zodanig_getoond(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        RestrisicoSnapshot::create([
            'soa_regel_id' => $regel->id, 'peiljaar' => 2026,
            'max_restrisico' => null, 'aantal_risicos' => 1, 'definitie_versie' => 1,
        ]);

        $this->actingAs($auditor)->get('/soa/restrisico-trend')
            ->assertOk()
            ->assertSee('onbepaald');
    }

    public function test_lege_staat_zonder_snapshots(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/soa/restrisico-trend')
            ->assertOk()
            ->assertSee('Nog geen snapshots');
    }

    public function test_ciso_bewerkt_alleen_de_toelichting_cijfers_blijven_bevroren(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;
        $snapshot = RestrisicoSnapshot::create([
            'soa_regel_id' => $regel->id, 'peiljaar' => 2026,
            'max_restrisico' => 12, 'aantal_risicos' => 2, 'definitie_versie' => 1,
        ]);

        Livewire::actingAs($ciso)
            ->test(RestrisicoTrend::class)
            ->call('bewerk', $snapshot->id)
            ->set('toelichting', 'R-7 gemitigeerd na invoering MFA.')
            ->call('opslaan')
            ->assertHasNoErrors();

        $snapshot->refresh();
        $this->assertSame('R-7 gemitigeerd na invoering MFA.', $snapshot->toelichting);
        // De cijfers zijn niet aangeraakt.
        $this->assertSame(12, $snapshot->max_restrisico);
        $this->assertSame(2, $snapshot->aantal_risicos);

        // En de wijziging staat in de audit trail.
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'restrisico_snapshot',
            'entiteit_id' => $snapshot->id,
            'blok_naam' => 'risico-soa',
            'actie' => 'gewijzigd',
        ]);
    }

    public function test_auditor_mag_de_toelichting_niet_bewerken(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;
        $snapshot = RestrisicoSnapshot::create([
            'soa_regel_id' => $regel->id, 'peiljaar' => 2026,
            'max_restrisico' => 12, 'aantal_risicos' => 2, 'definitie_versie' => 1,
        ]);

        Livewire::actingAs($auditor)
            ->test(RestrisicoTrend::class)
            ->call('bewerk', $snapshot->id)
            ->assertForbidden();
    }
}
