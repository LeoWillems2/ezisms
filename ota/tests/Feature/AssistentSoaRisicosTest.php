<?php

namespace Tests\Feature;

use App\Livewire\RisicosOverzicht;
use App\Livewire\SoaOverzicht;
use App\Mcp\IsmsServer;
use App\Mcp\Tools\Maatregelstatus;
use App\Mcp\Tools\Risicos;
use App\Models\Asset;
use App\Models\Beleidsdocument;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\Overheidsmaatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\SoaRegel;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * De tools `maatregelstatus` en `risicos` (implementatie/14b §5, §12.2):
 * dezelfde rijen als /soa en /risicos, achter dezelfde autorisatiecheck.
 */
class AssistentSoaRisicosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    // ── maatregelstatus ─────────────────────────────────────────────────────

    public function test_auditor_krijgt_dezelfde_maatregelen_als_de_soa(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $this->maatregel('5.1', 'organisatorisch', ['van_toepassing' => true, 'implementatiestatus' => 'geimplementeerd']);
        $this->maatregel('5.10', 'organisatorisch', ['van_toepassing' => null]);
        $this->maatregel('5.9', 'organisatorisch', ['van_toepassing' => true, 'implementatiestatus' => 'in_uitvoering']);
        $this->maatregel('8.13', 'technologisch', ['van_toepassing' => true, 'implementatiestatus' => 'in_uitvoering']);

        foreach ([
            [],
            ['filterThema' => 'organisatorisch'],
            ['filterStatus' => 'in_uitvoering'],
            ['alleenOnbeslist' => true],
        ] as $filters) {
            $scherm = Livewire::actingAs($auditor)
                ->withQueryParams($filters)
                ->test(SoaOverzicht::class)
                ->viewData('perThema')
                ->flatten()
                ->pluck('annex_a_referentie')
                ->sort()->values()->all();

            $argumenten = array_filter([
                'thema' => $filters['filterThema'] ?? null,
                'implementatiestatus' => $filters['filterStatus'] ?? null,
                'alleen_onbeslist' => $filters['alleenOnbeslist'] ?? null,
            ], fn ($w) => $w !== null);

            IsmsServer::actingAs($auditor)
                ->tool(Maatregelstatus::class, $argumenten)
                ->assertOk()
                ->assertStructuredContent(function ($json) use ($scherm) {
                    $json->where('totaal', count($scherm))
                        ->where('maatregelen', fn ($rijen) => collect($rijen)->pluck('nummer')->sort()->values()->all() === $scherm)
                        ->etc();
                });
        }
    }

    public function test_maatregelstatus_volgt_de_numerieke_volgorde_van_de_soa(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->maatregel('5.10', 'organisatorisch', []);
        $this->maatregel('5.9', 'organisatorisch', []);
        $this->maatregel('5.1', 'organisatorisch', []);

        IsmsServer::actingAs($ciso)
            ->tool(Maatregelstatus::class)
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('maatregelen', fn ($rijen) => collect($rijen)->pluck('nummer')->all() === ['5.1', '5.9', '5.10'])
                ->etc());
    }

    public function test_medewerker_en_administrator_krijgen_geen_maatregelstatus(): void
    {
        $this->maatregel('8.13', 'technologisch', []);

        foreach (['Medewerker', 'Administrator'] as $rol) {
            IsmsServer::actingAs(Gebruiker::factory()->metRol($rol)->create())
                ->tool(Maatregelstatus::class, ['nummer' => '8.13'])
                ->assertHasErrors();
        }
    }

    public function test_een_maatregel_met_motivatie_actief_beleid_bewijs_en_restrisico(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $regel = $this->maatregel('8.13', 'technologisch', [
            'van_toepassing' => true,
            'motivatie' => 'Back-ups beschermen tegen ransomware.',
            'implementatiestatus' => 'in_uitvoering',
            'beleidreferentie' => 'Back-upbeleid §2',
        ]);

        $actief = Beleidsdocument::factory()->create(['titel' => 'Back-upbeleid', 'status' => 'actief']);
        $concept = Beleidsdocument::factory()->create(['titel' => 'Concept back-up', 'status' => 'concept']);
        $regel->beleidsdocumenten()->attach([$actief->id, $concept->id]);

        $oud = Bewijsstuk::factory()->create(['naam' => 'Hersteltest 2025', 'geupload_op' => now()->subDays(400)]);
        $nieuw = Bewijsstuk::factory()->create(['naam' => 'Hersteltest 2026', 'geupload_op' => now()->subDays(10)]);
        foreach ([$oud, $nieuw] as $stuk) {
            $stuk->koppelingen()->create(['blok_naam' => 'risico-soa', 'entiteit_type' => 'soa_regel', 'entiteit_id' => $regel->id]);
        }

        $risico = Risico::factory()->beoordeeld(4, 4)->create(['titel' => 'Ransomware']);
        $behandeling = Risicobehandeling::create(['risico_id' => $risico->id, 'behandeloptie' => 'mitigeren', 'restrisico_score' => 6]);
        $behandeling->soaRegels()->attach($regel->id);

        IsmsServer::actingAs($ciso)
            ->tool(Maatregelstatus::class, ['nummer' => 'A.8.13'])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($actief, $nieuw, $risico) {
                $json->where('totaal', 1)
                    ->where('maatregelen.0.nummer', '8.13')
                    ->where('maatregelen.0.van_toepassing', true)
                    ->where('maatregelen.0.motivatie', 'Back-ups beschermen tegen ransomware.')
                    ->where('maatregelen.0.implementatiestatus', ['code' => 'in_uitvoering', 'label' => 'In uitvoering'])
                    ->where('maatregelen.0.beleidreferentie', 'Back-upbeleid §2')
                    // Alleen het actieve document, zoals de SoA.
                    ->where('maatregelen.0.beleid', fn ($b) => collect($b)->pluck('id')->all() === [$actief->id])
                    ->where('maatregelen.0.restrisico', ['piek' => 6, 'aantal_risicos' => 1])
                    ->where('maatregelen.0.risicos.0.id', $risico->id)
                    ->where('maatregelen.0.risicos.0.url', route('risicos.detail', $risico))
                    ->where('maatregelen.0.bewijs.aantal', 2)
                    ->where('maatregelen.0.bewijs.leeftijd_nieuwste_dagen', 10)
                    ->where('maatregelen.0.bewijs.stukken.0.id', $nieuw->id)
                    ->where('maatregelen.0.bewijs.stukken.0.resource_uri', 'isms://bewijs/'.$nieuw->id)
                    ->etc();
            });
    }

    #[Group('bio2')]
    public function test_in_een_bio_installatie_telt_bewijs_bij_de_verplichting_mee_zonder_normtekst(): void
    {
        config()->set('norm.actief', 'bio2');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $regel = $this->maatregel('5.24', 'organisatorisch', ['van_toepassing' => true]);
        $om = Overheidsmaatregel::create([
            'nummer' => '5.24.03', 'maatregel_id' => $regel->maatregel_id, 'volgnummer' => 3,
            'tekst' => 'GEHEIME NORMTEKST', 'status' => 'geldend',
        ]);
        $beoordeling = OverheidsmaatregelBeoordeling::create([
            'soa_regel_id' => $regel->id, 'overheidsmaatregel_id' => $om->id, 'status' => 'belegd',
        ]);
        $stuk = Bewijsstuk::factory()->create(['geupload_op' => now()->subDays(3)]);
        $stuk->koppelingen()->create([
            'blok_naam' => 'risico-soa', 'entiteit_type' => 'overheidsmaatregel_beoordeling', 'entiteit_id' => $beoordeling->id,
        ]);

        $antwoord = IsmsServer::actingAs($ciso)
            ->tool(Maatregelstatus::class, ['nummer' => '5.24'])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($stuk) {
                $json->where('maatregelen.0.overheidsmaatregelen.0.nummer', '5.24.03')
                    ->where('maatregelen.0.overheidsmaatregelen.0.status.code', 'belegd')
                    ->where('maatregelen.0.overheidsmaatregelen.0.aantal_bewijsstukken', 1)
                    ->where('maatregelen.0.bewijs.aantal', 1)
                    ->where('maatregelen.0.bewijs.stukken.0.id', $stuk->id)
                    ->where('maatregelen.0.bewijs.stukken.0.gekoppeld_aan', 'overheidsmaatregel')
                    ->etc();
            });

        $antwoord->assertDontSee('GEHEIME NORMTEKST');
    }

    public function test_onbekend_maatregelnummer_geeft_een_foutmelding(): void
    {
        IsmsServer::actingAs(Gebruiker::factory()->metRol('CISO')->create())
            ->tool(Maatregelstatus::class, ['nummer' => '9.99'])
            ->assertHasErrors();
    }

    // ── risicos ─────────────────────────────────────────────────────────────

    public function test_management_krijgt_dezelfde_risicos_als_het_register(): void
    {
        $management = Gebruiker::factory()->metRol('Management')->create();
        Risico::factory()->beoordeeld(5, 5)->create(['status' => 'beoordeeld']);
        Risico::factory()->beoordeeld(2, 2)->create();
        Risico::factory()->create(['status' => 'geidentificeerd']);
        Risico::factory()->beoordeeld(4, 5)->create(['status' => 'gemitigeerd']);

        foreach ([
            [[], []],
            [['alleenBovenDrempel' => true], ['boven_drempel' => true]],
            [['filterStatus' => 'beoordeeld'], ['status' => 'beoordeeld']],
        ] as [$schermfilters, $argumenten]) {
            $scherm = Livewire::actingAs($management)
                ->withQueryParams($schermfilters)
                ->test(RisicosOverzicht::class)
                ->viewData('risicos')
                ->pluck('id')->all();

            IsmsServer::actingAs($management)
                ->tool(Risicos::class, $argumenten)
                ->assertOk()
                ->assertStructuredContent(function ($json) use ($scherm) {
                    // Ook de volgorde: hoogste score eerst, zoals het register.
                    $json->where('totaal', count($scherm))
                        ->where('risicos', fn ($rijen) => collect($rijen)->pluck('id')->all() === $scherm)
                        ->etc();
                });
        }
    }

    public function test_medewerker_en_administrator_krijgen_geen_risicos(): void
    {
        Risico::factory()->create();

        foreach (['Medewerker', 'Administrator'] as $rol) {
            IsmsServer::actingAs(Gebruiker::factory()->metRol($rol)->create())
                ->tool(Risicos::class)
                ->assertHasErrors();
        }
    }

    public function test_risico_met_kans_impact_eigenaar_asset_en_restrisico(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $eigenaar = Gebruiker::factory()->metRol('CISO')->create(['naam' => 'Carla Ciso']);
        $asset = Asset::factory()->create(['naam' => 'Fileserver']);
        $risico = Risico::factory()->beoordeeld(4, 5)->create([
            'titel' => 'Datalek fileserver',
            'risico_eigenaar_id' => $eigenaar->id,
            'gekoppeld_asset_id' => $asset->id,
        ]);
        Risico::factory()->beoordeeld(4, 5)->create(['titel' => 'Ander asset']);
        Risicobehandeling::create(['risico_id' => $risico->id, 'behandeloptie' => 'mitigeren', 'restrisico_score' => 8]);

        IsmsServer::actingAs($auditor)
            ->tool(Risicos::class, ['asset_id' => $asset->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($risico, $asset) {
                $json->where('totaal', 1)
                    ->where('risicos.0.id', $risico->id)
                    ->where('risicos.0.referentie', 'R-'.$risico->id)
                    ->where('risicos.0.kans.niveau', 4)
                    ->where('risicos.0.impact.niveau', 5)
                    ->where('risicos.0.risicoscore', 20)
                    ->where('risicos.0.boven_drempel', true)
                    ->where('risicos.0.status.code', 'beoordeeld')
                    ->where('risicos.0.eigenaar.naam', 'Carla Ciso')
                    ->where('risicos.0.asset.id', $asset->id)
                    ->where('risicos.0.restrisico', 8)
                    ->where('risicos.0.behandelingen.0.behandeloptie.code', 'mitigeren')
                    ->where('risicos.0.url', route('risicos.detail', $risico))
                    ->etc();
            });
    }

    /** @param  array<string, mixed>  $regel */
    private function maatregel(string $nummer, string $thema, array $regel): SoaRegel
    {
        $maatregel = Maatregel::factory()->create(['annex_a_referentie' => $nummer, 'thema' => $thema]);

        return $maatregel->soaRegel()->create($regel);
    }
}
