<?php

namespace Tests\Feature;

use App\Models\Maatregel;
use App\Models\Risico;
use App\Models\SoaRegel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestrisicoSnapshotTest extends TestCase
{
    use RefreshDatabase;

    /** Koppelt een behandeling met restrisico aan een control en geeft de regel terug. */
    private function controlMetRisico(?int $restrisico): SoaRegel
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;
        $risico = Risico::factory()->create();
        $behandeling = $risico->behandelingen()->create([
            'behandeloptie' => 'mitigeren',
            'restrisico_score' => $restrisico,
        ]);
        $regel->risicobehandelingen()->attach($behandeling->id);

        return $regel;
    }

    public function test_vastleggen_schrijft_een_snapshot_per_control_met_risico(): void
    {
        $metRisico = $this->controlMetRisico(12);
        $onbepaald = $this->controlMetRisico(null);          // gekoppeld, geen restrisico
        $zonderRisico = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        $this->artisan('isms:leg-restrisico-vast', ['--jaar' => 2026])
            ->assertExitCode(0);

        // Alleen controls met een gekoppeld risico krijgen een rij.
        $this->assertDatabaseCount('restrisico_snapshots', 2);

        $this->assertDatabaseHas('restrisico_snapshots', [
            'soa_regel_id' => $metRisico->id,
            'peiljaar' => 2026,
            'max_restrisico' => 12,
            'aantal_risicos' => 1,
        ]);

        // Onbepaald: gekoppeld maar restrisico leeg -> null, niet 0.
        $this->assertDatabaseHas('restrisico_snapshots', [
            'soa_regel_id' => $onbepaald->id,
            'peiljaar' => 2026,
            'max_restrisico' => null,
            'aantal_risicos' => 1,
        ]);

        $this->assertDatabaseMissing('restrisico_snapshots', [
            'soa_regel_id' => $zonderRisico->id,
        ]);
    }

    public function test_tweede_vastlegging_in_hetzelfde_jaar_wordt_geweigerd(): void
    {
        $this->controlMetRisico(8);

        $this->artisan('isms:leg-restrisico-vast', ['--jaar' => 2026])->assertExitCode(0);
        $this->assertDatabaseCount('restrisico_snapshots', 1);

        // Tweede keer: geweigerd, geen extra rij.
        $this->artisan('isms:leg-restrisico-vast', ['--jaar' => 2026])->assertExitCode(1);
        $this->assertDatabaseCount('restrisico_snapshots', 1);
    }

    public function test_snapshot_blijft_onveranderd_als_het_restrisico_daarna_verandert(): void
    {
        $regel = $this->controlMetRisico(12);

        $this->artisan('isms:leg-restrisico-vast', ['--jaar' => 2026])->assertExitCode(0);

        // Het restrisico wordt later verlaagd (mitigatie in 2027).
        $regel->risicobehandelingen()->first()->update(['restrisico_score' => 4]);

        // De 2026-snapshot is bevroren: nog steeds 12, niet herrekend.
        $this->assertDatabaseHas('restrisico_snapshots', [
            'soa_regel_id' => $regel->id,
            'peiljaar' => 2026,
            'max_restrisico' => 12,
        ]);

        // Een nieuw peiljaar legt de nieuwe stand vast -> zichtbare trend.
        $this->artisan('isms:leg-restrisico-vast', ['--jaar' => 2027])->assertExitCode(0);
        $this->assertDatabaseHas('restrisico_snapshots', [
            'soa_regel_id' => $regel->id,
            'peiljaar' => 2027,
            'max_restrisico' => 4,
        ]);
    }
}
