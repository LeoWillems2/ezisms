<?php

namespace Tests\Feature;

use App\Livewire\BeleidsdocumentenOverzicht;
use App\Livewire\BewijsstukkenOverzicht;
use App\Mcp\IsmsServer;
use App\Mcp\Tools\Beleid;
use App\Mcp\Tools\Bewijs;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\Maatregel;
use App\Models\OrganisatieEenheid;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De tools `bewijs` en `beleid` (implementatie/14b §5, §12.2): dezelfde
 * `zichtbaar()`-scope als /bewijsstukken en /beleid, achter dezelfde
 * autorisatiecheck.
 */
class AssistentBewijsBeleidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    // ── bewijs ──────────────────────────────────────────────────────────────

    public function test_medewerker_krijgt_alleen_het_eigen_bewijs_zoals_het_scherm(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        Bewijsstuk::factory()->count(2)->create(['geupload_door' => $medewerker->id]);
        Bewijsstuk::factory()->gearchiveerd()->create(['geupload_door' => $medewerker->id]);
        Bewijsstuk::factory()->count(2)->create();

        $scherm = Livewire::actingAs($medewerker)
            ->withQueryParams(['filterStatus' => ''])
            ->test(BewijsstukkenOverzicht::class)
            ->viewData('bewijsstukken')
            ->pluck('id')->sort()->values()->all();

        $this->assertCount(3, $scherm);

        IsmsServer::actingAs($medewerker)
            ->tool(Bewijs::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($scherm) {
                $json->where('totaal', 3)
                    ->where('bewijsstukken', fn ($rijen) => collect($rijen)->pluck('id')->sort()->values()->all() === $scherm)
                    ->etc();
            });
    }

    public function test_management_ziet_al_het_bewijs_zoals_het_scherm(): void
    {
        $management = Gebruiker::factory()->metRol('Management')->create();
        Bewijsstuk::factory()->count(3)->create();

        $scherm = Livewire::actingAs($management)
            ->test(BewijsstukkenOverzicht::class)
            ->viewData('bewijsstukken')
            ->pluck('id')->sort()->values()->all();

        IsmsServer::actingAs($management)
            ->tool(Bewijs::class, ['status' => 'actief'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('bewijsstukken', fn ($rijen) => collect($rijen)->pluck('id')->sort()->values()->all() === $scherm)
                ->etc());
    }

    public function test_rollen_zonder_uitvoeren_op_bewijs_worden_geweigerd(): void
    {
        Bewijsstuk::factory()->create();

        // De Auditor heeft op blok 6 `lezen` en `exporteren`, geen `uitvoeren`,
        // en komt daarmee ook niet op /bewijsstukken.
        foreach (['Auditor', 'Administrator'] as $rol) {
            IsmsServer::actingAs(Gebruiker::factory()->metRol($rol)->create())
                ->tool(Bewijs::class)
                ->assertHasErrors();
        }
    }

    public function test_bewijs_filtert_op_maatregel_en_ouderdom(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create(['naam' => 'Carla Ciso']);
        $regel = Maatregel::factory()->create(['annex_a_referentie' => '5.15'])->soaRegel()->create();
        $andereRegel = Maatregel::factory()->create(['annex_a_referentie' => '5.16'])->soaRegel()->create();

        $oud = Bewijsstuk::factory()->create([
            'naam' => 'Toegangsreview 2025', 'geupload_door' => $ciso->id, 'geupload_op' => now()->subDays(400),
        ]);
        $nieuw = Bewijsstuk::factory()->create(['naam' => 'Toegangsreview 2026', 'geupload_op' => now()->subDays(5)]);
        $elders = Bewijsstuk::factory()->create(['geupload_op' => now()->subDays(500)]);
        Bewijsstuk::factory()->create(['geupload_op' => now()->subDays(600)]); // ongekoppeld

        foreach ([$oud, $nieuw] as $stuk) {
            $stuk->koppelingen()->create(['blok_naam' => 'risico-soa', 'entiteit_type' => 'soa_regel', 'entiteit_id' => $regel->id]);
        }
        $elders->koppelingen()->create(['blok_naam' => 'risico-soa', 'entiteit_type' => 'soa_regel', 'entiteit_id' => $andereRegel->id]);

        IsmsServer::actingAs($ciso)
            ->tool(Bewijs::class, ['maatregel' => '5.15', 'ouder_dan_dagen' => 365])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($oud) {
                $json->where('totaal', 1)
                    ->where('bewijsstukken.0.id', $oud->id)
                    ->where('bewijsstukken.0.naam', 'Toegangsreview 2025')
                    ->where('bewijsstukken.0.leeftijd_dagen', 400)
                    ->where('bewijsstukken.0.geupload_door', 'Carla Ciso')
                    ->where('bewijsstukken.0.status.code', 'actief')
                    ->where('bewijsstukken.0.koppelingen.0.type.code', 'soa_regel')
                    ->where('bewijsstukken.0.resource_uri', 'isms://bewijs/'.$oud->id)
                    ->where('bewijsstukken.0.url', route('bewijsstukken.download', $oud))
                    ->etc();
            });

        IsmsServer::actingAs($ciso)
            ->tool(Bewijs::class, ['ongekoppeld' => true])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json->where('totaal', 1)->etc());
    }

    // ── beleid ──────────────────────────────────────────────────────────────

    public function test_medewerker_krijgt_dezelfde_documenten_als_het_scherm_zonder_concepten(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $actief = $this->actiefDocument('Wachtwoordbeleid');
        // Een nieuwe versie die op goedkeuring wacht: niet voor de Medewerker.
        Beleidsversie::factory()->terGoedkeuring()->create([
            'beleidsdocument_id' => $actief->id, 'versienummer' => 2, 'aangeboden_op' => now(),
        ]);
        Beleidsdocument::factory()->create(['status' => 'concept']);
        Beleidsdocument::factory()->create(['status' => 'ingetrokken']);

        foreach (['', 'actief', 'concept', 'ter_goedkeuring'] as $status) {
            $scherm = Livewire::actingAs($medewerker)
                ->withQueryParams(['filterStatus' => $status])
                ->test(BeleidsdocumentenOverzicht::class)
                ->viewData('documenten')
                ->pluck('id')->sort()->values()->all();

            IsmsServer::actingAs($medewerker)
                ->tool(Beleid::class, $status === '' ? [] : ['status' => $status])
                ->assertOk()
                ->assertStructuredContent(function ($json) use ($scherm) {
                    $json->where('totaal', count($scherm))
                        ->where('documenten', fn ($rijen) => collect($rijen)->pluck('id')->sort()->values()->all() === $scherm)
                        ->etc();
                });
        }

        IsmsServer::actingAs($medewerker)
            ->tool(Beleid::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('totaal', 1)
                    ->where('documenten.0.actieve_versie.versienummer', 1)
                    ->missing('documenten.0.versie_ter_goedkeuring')
                    ->missing('documenten.0.leesbevestiging.bevestigingsgraad')
                    ->where('documenten.0.leesbevestiging.zelf_bevestigd', false)
                    ->etc();
            });
    }

    public function test_ciso_ziet_concepten_en_wachtende_versies_zoals_het_scherm(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $actief = $this->actiefDocument('Wachtwoordbeleid');
        Beleidsversie::factory()->terGoedkeuring()->create([
            'beleidsdocument_id' => $actief->id, 'versienummer' => 2, 'aangeboden_op' => now(),
        ]);
        Beleidsdocument::factory()->create(['status' => 'concept']);

        foreach (['', 'ter_goedkeuring'] as $status) {
            $scherm = Livewire::actingAs($ciso)
                ->withQueryParams(['filterStatus' => $status])
                ->test(BeleidsdocumentenOverzicht::class)
                ->viewData('documenten')
                ->pluck('id')->sort()->values()->all();

            IsmsServer::actingAs($ciso)
                ->tool(Beleid::class, $status === '' ? [] : ['status' => $status])
                ->assertOk()
                ->assertStructuredContent(fn ($json) => $json
                    ->where('documenten', fn ($rijen) => collect($rijen)->pluck('id')->sort()->values()->all() === $scherm)
                    ->etc());
        }

        IsmsServer::actingAs($ciso)
            ->tool(Beleid::class, ['id' => $actief->id])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('documenten.0.versie_ter_goedkeuring.versienummer', 2)
                ->has('documenten.0.leesbevestiging.bevestigingsgraad')
                ->etc());
    }

    public function test_administrator_krijgt_geen_beleid(): void
    {
        $this->actiefDocument('Wachtwoordbeleid');

        IsmsServer::actingAs(Gebruiker::factory()->metRol('Administrator')->create())
            ->tool(Beleid::class)
            ->assertHasErrors();
    }

    public function test_actieve_versie_met_goedkeurder_herziening_en_resource(): void
    {
        $afdeling = OrganisatieEenheid::factory()->afdeling()->create();
        $auditor = Gebruiker::factory()->metRol('Auditor')->opAfdeling($afdeling)->create();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->opAfdeling($afdeling)->create();
        $directeur = Gebruiker::factory()->metRol('Management')->create(['naam' => 'Dirk Directeur']);

        $document = $this->actiefDocument('Back-upbeleid', [
            'goedgekeurd_door_id' => $directeur->id,
            'goedgekeurd_op' => now()->subYear(),
            'volgende_herziening_gepland' => now()->subDay(),
        ]);
        $document->afdelingen()->attach($afdeling->id);
        Leesbevestiging::create([
            'beleidsversie_id' => $document->actieveVersie->id,
            'gebruiker_id' => $medewerker->id,
            'bevestigd_op' => now(),
        ]);
        $this->actiefDocument('Procedure zonder herziening');

        // De Auditor heeft `exporteren` op blok 5 en ziet dus alles, inclusief de graad.
        IsmsServer::actingAs($auditor)
            ->tool(Beleid::class, ['herziening_verstreken' => true])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($document) {
                $json->where('totaal', 1)
                    ->where('documenten.0.id', $document->id)
                    ->where('documenten.0.status.code', 'actief')
                    ->where('documenten.0.actieve_versie.versienummer', 1)
                    ->where('documenten.0.actieve_versie.goedgekeurd_door', 'Dirk Directeur')
                    ->where('documenten.0.actieve_versie.herziening_verstreken', true)
                    ->where('documenten.0.actieve_versie.volgende_herziening_gepland', now()->subDay()->toDateString())
                    ->where('documenten.0.actieve_versie.resource_uri', 'isms://beleid/'.$document->id.'/versie/1')
                    ->where('documenten.0.leesbevestiging.bevestigingsgraad', 50)
                    ->where('documenten.0.url', route('beleid.detail', $document))
                    ->etc();
            });

        IsmsServer::actingAs($medewerker)
            ->tool(Beleid::class, ['id' => $document->id])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('documenten.0.leesbevestiging.in_doelgroep', true)
                ->where('documenten.0.leesbevestiging.zelf_bevestigd', true)
                ->missing('documenten.0.leesbevestiging.bevestigingsgraad')
                ->etc());
    }

    /** @param  array<string, mixed>  $versie */
    private function actiefDocument(string $titel, array $versie = []): Beleidsdocument
    {
        $document = Beleidsdocument::factory()->create(['titel' => $titel, 'status' => 'actief']);
        Beleidsversie::factory()->actief()->create(['beleidsdocument_id' => $document->id, 'versienummer' => 1, ...$versie]);

        return $document->fresh();
    }
}
