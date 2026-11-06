<?php

namespace Tests\Feature;

use App\Livewire\LeveranciersOverzicht;
use App\Mcp\IsmsServer;
use App\Mcp\Tools\Leveranciers;
use App\Models\Contractclausule;
use App\Models\Gebruiker;
use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De tool `leveranciers` (implementatie/14b §5, §12.2): hetzelfde register als
 * /leveranciers, achter dezelfde autorisatiecheck.
 */
class AssistentLeveranciersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    public function test_management_krijgt_dezelfde_leveranciers_als_het_overzichtsscherm(): void
    {
        $management = Gebruiker::factory()->metRol('Management')->create();
        Leverancier::factory()->count(3)->create();
        Leverancier::factory()->create(['status' => 'beeindigd']);

        $scherm = Livewire::actingAs($management)
            ->test(LeveranciersOverzicht::class)
            ->viewData('leveranciers')
            ->pluck('id')
            ->sort()->values()->all();

        IsmsServer::actingAs($management)
            ->tool(Leveranciers::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($scherm) {
                $json->where('totaal', 4)
                    ->where('leveranciers', fn ($rijen) => collect($rijen)->pluck('id')->sort()->values()->all() === $scherm)
                    ->etc();
            });
    }

    public function test_medewerker_zonder_leesrecht_wordt_geweigerd(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        Leverancier::factory()->create();

        // Zonder recht staat de tool niet in tools/list (shouldRegister), dus
        // een aanroep is een fout; met recht werkt dezelfde aanroep wel.
        IsmsServer::actingAs($medewerker)
            ->tool(Leveranciers::class)
            ->assertHasErrors();

        IsmsServer::actingAs(Gebruiker::factory()->metRol('Auditor')->create())
            ->tool(Leveranciers::class)
            ->assertOk();
    }

    public function test_toont_clausules_beoordeling_en_open_punten(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create(['naam' => 'Carla Ciso']);
        $leverancier = Leverancier::factory()->create([
            'naam' => 'Wolkendienst BV',
            'risiconiveau' => 'hoog',
            'eigen_certificering_geldig_tot' => now()->addDays(20)->toDateString(),
        ]);
        Contractclausule::factory()->for($leverancier)->create(['type' => 'vertrouwelijkheid', 'aanwezig' => true]);
        Leveranciersbeoordeling::factory()->for($leverancier)->create([
            'uitgevoerd_op' => now()->subYears(2)->toDateString(),
            'volgende_beoordeling_gepland' => now()->subMonth()->toDateString(),
            'uitgevoerd_door_id' => $ciso->id,
            'bevindingen' => 'Back-ups niet getest.',
        ]);
        // Valt buiten het certificaatfilter.
        Leverancier::factory()->create(['eigen_certificering_geldig_tot' => now()->addYear()->toDateString()]);

        IsmsServer::actingAs($ciso)
            ->tool(Leveranciers::class, ['certificaat_verloopt_binnen_dagen' => 30])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($leverancier) {
                $json->where('totaal', 1)
                    ->where('signalen.herbeoordeling_verstreken', 1)
                    ->where('leveranciers.0.id', $leverancier->id)
                    ->where('leveranciers.0.risiconiveau.code', 'hoog')
                    ->where('leveranciers.0.eigen_certificering_geldig_tot', now()->addDays(20)->toDateString())
                    ->where('leveranciers.0.nieuwste_beoordeling.uitgevoerd_door', 'Carla Ciso')
                    ->where('leveranciers.0.contractclausules', fn ($c) => collect($c)->firstWhere('code', 'vertrouwelijkheid')['aanwezig'] === true
                        && collect($c)->firstWhere('code', 'recht_op_audit')['aanwezig'] === false)
                    ->where('leveranciers.0.open_punten', fn ($p) => collect($p)->contains(fn ($regel) => str_starts_with($regel, 'Herbeoordeling verstreken'))
                        && collect($p)->contains(fn ($regel) => str_starts_with($regel, 'Ontbrekende contractclausules')))
                    ->where('leveranciers.0.url', route('leveranciers.detail', $leverancier))
                    ->etc();
            });
    }
}
