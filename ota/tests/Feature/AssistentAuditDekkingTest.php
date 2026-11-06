<?php

namespace Tests\Feature;

use App\Livewire\BevindingenOverzicht;
use App\Livewire\Dekkingsmatrix;
use App\Mcp\IsmsServer;
use App\Mcp\Tools\AuditDekking;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Gebruiker;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De tool `audit-dekking` (implementatie/14b §5, §12.2): dezelfde matrix,
 * rondes en bevindingen als de schermen onder /audits.
 */
class AssistentAuditDekkingTest extends TestCase
{
    use RefreshDatabase;

    private Auditprogramma $programma;

    private Auditobject $gedekt;

    private Auditobject $gat;

    private Auditronde $ronde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);

        // Cyclus in het verleden, zodat een gepland en niet-uitgevoerd jaar een gat is.
        $this->programma = Auditprogramma::factory()->create(['start_datum' => '2024-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($this->programma)->create();

        $this->gedekt = Auditobject::factory()->create(['clausule_nummer' => '9.2', 'titel' => 'Interne audit', 'volgorde' => 1]);
        $this->gat = Auditobject::factory()->create(['clausule_nummer' => '9.3', 'titel' => 'Directiebeoordeling', 'volgorde' => 2]);

        AuditprogrammaDekking::create([
            'auditprogramma_id' => $this->programma->id,
            'auditobject_id' => $this->gat->id,
            'interval_jaren' => 1,
            'gepland_start_programmajaar' => 1,
        ]);

        $this->ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id, 'type' => 'intern',
            'status' => 'afgerond', 'uitgevoerd_op' => '2024-05-01',
        ]);
        $this->ronde->auditobjecten()->attach($this->gedekt->id, ['afhandeling' => 'geen_opmerkingen']);
    }

    public function test_auditor_krijgt_dezelfde_matrix_en_bevindingen_als_de_schermen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        Bevinding::factory()->create(['auditronde_id' => $this->ronde->id, 'type' => 'non_conformiteit_minor']);
        Bevinding::factory()->create(['auditronde_id' => $this->ronde->id, 'status' => 'gesloten']);

        $matrix = Livewire::actingAs($auditor)->test(Dekkingsmatrix::class);
        $cellen = $matrix->viewData('cellen');
        $kpi = $matrix->viewData('kpi');

        $register = Livewire::actingAs($auditor)
            ->test(BevindingenOverzicht::class)
            ->viewData('bevindingen')
            ->pluck('id')->all();

        IsmsServer::actingAs($auditor)
            ->tool(AuditDekking::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($cellen, $kpi, $register) {
                $json->where('programma.id', $this->programma->id)
                    ->where('dekking.kpi', $kpi)
                    ->where('dekking.objecten', function ($objecten) use ($cellen) {
                        $perObject = collect($objecten)->mapWithKeys(fn ($o) => [
                            $o['ref'] => collect($o['per_programmajaar'])->pluck('code', 'programmajaar')->all(),
                        ]);

                        return $perObject['9.2'] === $cellen[$this->gedekt->id]
                            && $perObject['9.3'] === $cellen[$this->gat->id];
                    })
                    // Standaard "openstaand", zoals het register.
                    ->where('bevindingen.lijst', fn ($lijst) => collect($lijst)->pluck('id')->all() === $register)
                    ->etc();
            });
    }

    public function test_alleen_gaten_laat_gedekte_objecten_weg(): void
    {
        IsmsServer::actingAs(Gebruiker::factory()->metRol('Management')->create())
            ->tool(AuditDekking::class, ['alleen_gaten' => true])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('dekking.totaal', 1)
                    ->where('dekking.objecten.0.ref', '9.3')
                    // Programmajaar 1 (2024) is in elk geval voorbij.
                    ->where('dekking.objecten.0.gat_in_programmajaren', fn ($jaren) => collect($jaren)->contains(1))
                    ->etc();
            });
    }

    public function test_medewerker_zonder_leesrecht_wordt_geweigerd(): void
    {
        IsmsServer::actingAs(Gebruiker::factory()->metRol('Medewerker')->create())
            ->tool(AuditDekking::class)
            ->assertHasErrors();

        IsmsServer::actingAs(Gebruiker::factory()->metRol('Management')->create())
            ->tool(AuditDekking::class)
            ->assertOk();
    }

    public function test_rondedossier_toont_afhandeling_bron_en_opvolging(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $partner = Gebruiker::factory()->create(['naam' => 'Piet Proceseigenaar']);
        $this->ronde->auditobjecten()->updateExistingPivot($this->gedekt->id, ['gesproken_met_id' => $partner->id]);
        $this->ronde->auditobjecten()->attach($this->gat->id, ['afhandeling' => 'niet_toegekomen', 'toelichting' => 'Geen tijd meer']);
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $this->ronde->id,
            'type' => 'non_conformiteit_major',
            'omschrijving' => 'Geen auditprogramma vastgesteld.',
            'eigen_waarneming' => true,
        ]);

        IsmsServer::actingAs($ciso)
            ->tool(AuditDekking::class, ['auditronde_id' => $this->ronde->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($bevinding) {
                $json->where('ronde.id', $this->ronde->id)
                    ->where('ronde.status.code', 'afgerond')
                    ->where('normatieve_scope.0.ref', '9.2')
                    ->where('normatieve_scope.0.afhandeling.code', 'geen_opmerkingen')
                    ->where('normatieve_scope.0.bron', 'Piet Proceseigenaar')
                    ->where('normatieve_scope.1.afhandeling.code', 'niet_toegekomen')
                    ->where('normatieve_scope.1.toelichting', 'Geen tijd meer')
                    ->where('bevindingen.0.id', $bevinding->id)
                    ->where('bevindingen.0.type.code', 'non_conformiteit_major')
                    ->where('bevindingen.0.bron', 'eigen waarneming')
                    ->where('bevindingen.0.url', route('audits.ronde', $this->ronde))
                    ->etc();
            });
    }
}
