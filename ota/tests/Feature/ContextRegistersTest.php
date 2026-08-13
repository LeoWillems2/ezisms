<?php

namespace Tests\Feature;

use App\Livewire\BelanghebbendenOverzicht;
use App\Livewire\IssuesOverzicht;
use App\Livewire\OrganisatieEenhedenOverzicht;
use App\Livewire\RisicoDetail;
use App\Livewire\RisicosOverzicht;
use App\Models\Belanghebbende;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\OrganisatieEenheid;
use App\Models\Risico;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContextRegistersTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    public function test_medewerker_mag_de_contextpaginas_lezen(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/scope')->assertOk();
        $this->actingAs($medewerker)->get('/organisatie-eenheden')->assertOk();
        $this->actingAs($medewerker)->get('/issues')->assertOk();
        $this->actingAs($medewerker)->get('/belanghebbenden')->assertOk();
    }

    public function test_gebruiker_zonder_rol_wordt_geweerd(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $this->actingAs($zonderRol)->get('/scope')->assertForbidden();
    }

    public function test_ciso_kan_een_hierarchische_organisatie_eenheid_aanmaken(): void
    {
        $afdeling = OrganisatieEenheid::factory()->create(['naam' => 'ICT', 'type' => 'afdeling']);

        Livewire::actingAs($this->ciso)
            ->test(OrganisatieEenhedenOverzicht::class)
            ->call('nieuweEenheid', $afdeling->id)
            ->set('naam', 'Salarisadministratie')
            ->set('type', 'proces')
            ->call('opslaan')
            ->assertHasNoErrors();

        $sub = OrganisatieEenheid::where('naam', 'Salarisadministratie')->first();

        $this->assertNotNull($sub);
        $this->assertSame($afdeling->id, $sub->bovenliggende_eenheid_id);
    }

    public function test_medewerker_mag_geen_organisatie_eenheid_aanmaken(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(OrganisatieEenhedenOverzicht::class)
            ->set('naam', 'Stiekem')
            ->call('opslaan')
            ->assertForbidden();

        $this->assertDatabaseMissing('organisatie_eenheden', ['naam' => 'Stiekem']);
    }

    public function test_ciso_kan_een_issue_toevoegen_en_bewerken(): void
    {
        $component = Livewire::actingAs($this->ciso)
            ->test(IssuesOverzicht::class)
            ->set('aard', 'extern')
            ->set('categorie', 'juridisch')
            ->set('omschrijving', 'Nieuwe privacywetgeving')
            ->call('opslaan')
            ->assertHasNoErrors();

        $issue = Issue::first();
        $this->assertSame('juridisch', $issue->categorie);

        $component->call('bewerk', $issue)
            ->set('categorie', 'wettelijk-technisch')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('wettelijk-technisch', $issue->fresh()->categorie);
    }

    /*
     |--------------------------------------------------------------------------
     | Plan 02b: issue → risico
     |--------------------------------------------------------------------------
     | De doorvertaling van §4.1 naar §6.1. Beheerd vanaf het risico (dáár hoort
     | de identificatie), getoond op het issue-register.
     */

    public function test_een_risico_kan_aan_issues_gekoppeld_worden(): void
    {
        $risico = Risico::factory()->create();
        $issues = Issue::factory()->count(2)->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('geselecteerdeAanleidingen', $issues->pluck('id')->all())
            ->call('opslaanBasis')
            ->assertHasNoErrors();

        $this->assertCount(2, $risico->fresh()->aanleidingen);
        $this->assertTrue($issues->first()->risicos->contains($risico));
    }

    public function test_dezelfde_koppeling_levert_geen_dubbele_rij(): void
    {
        $risico = Risico::factory()->create();
        $issue = Issue::factory()->create();

        $risico->aanleidingen()->sync([$issue->id, $issue->id]);
        $risico->aanleidingen()->syncWithoutDetaching([$issue->id]);

        $this->assertSame(1, $risico->aanleidingen()->count());
    }

    public function test_een_verwijderd_issue_neemt_zijn_koppelingen_mee(): void
    {
        $risico = Risico::factory()->create();
        $issue = Issue::factory()->create();
        $risico->aanleidingen()->attach($issue);

        $issue->delete();

        // Het risico zelf blijft bestaan: het verliest alleen zijn aanleiding.
        $this->assertNotNull($risico->fresh());
        $this->assertCount(0, $risico->fresh()->aanleidingen);
        $this->assertDatabaseCount('issue_risico', 0);
    }

    /**
     * De rechtenval uit plan 02b §5: de Medewerker mag issues lezen maar staat
     * niet in de rij voor risico-soa. De doorvertaling mag dus niet lekken.
     */
    public function test_de_medewerker_ziet_de_doorvertaling_niet(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $risico = Risico::factory()->create(['titel' => 'Ransomware legt de productie plat']);
        Issue::factory()->create(['omschrijving' => 'Alles loopt via één platform'])
            ->risicos()->attach($risico);
        Issue::factory()->create(['omschrijving' => 'Kwestie zonder risico']);

        $this->actingAs($medewerker)->get('/issues')
            ->assertOk()
            ->assertSee('Alles loopt via één platform')
            ->assertDontSee('Ransomware legt de productie plat')
            ->assertDontSee('niet doorvertaald naar een risico');

        // En de plek waar de link naartoe zou wijzen blijft dicht.
        $this->actingAs($medewerker)->get('/risicos')->assertForbidden();
    }

    public function test_de_ciso_ziet_hoeveel_issues_niet_doorvertaald_zijn(): void
    {
        $risico = Risico::factory()->create();
        Issue::factory()->create()->risicos()->attach($risico);
        Issue::factory()->count(3)->create();

        Livewire::actingAs($this->ciso)
            ->test(IssuesOverzicht::class)
            ->assertViewHas('zonderRisico', 3)
            ->assertSee('3 van de 4 issues');
    }

    /**
     * Borgt de asymmetrie uit plan 02b §6: het signaal loopt één kant op.
     * Risico's komen legitiem uit assets, incidenten en leveranciers, dus een
     * risico zonder aanleiding is geen tekortkoming en mag niet gaan zeuren.
     */
    public function test_een_risico_zonder_aanleiding_levert_geen_signaal(): void
    {
        Risico::factory()->count(3)->create();

        Livewire::actingAs($this->ciso)
            ->test(IssuesOverzicht::class)
            ->assertViewHas('zonderRisico', 0)
            ->assertDontSee('niet doorvertaald naar een risico');
    }

    public function test_het_risico_overzicht_filtert_op_aanleiding(): void
    {
        $metAanleiding = Risico::factory()->create(['titel' => 'Uitval van het platform']);
        Risico::factory()->create(['titel' => 'Onbevoegde toegang tot het archief']);
        $issue = Issue::factory()->create();
        $issue->risicos()->attach($metAanleiding);

        Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class, ['filterIssue' => (string) $issue->id])
            ->assertSee('Uitval van het platform')
            ->assertDontSee('Onbevoegde toegang tot het archief');
    }

    public function test_ciso_kan_een_belanghebbende_met_eis_vastleggen(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BelanghebbendenOverzicht::class)
            ->set('naam', 'Toezichthouder')
            ->set('aard', 'extern')
            ->call('opslaan')
            ->assertHasNoErrors();

        $belanghebbende = Belanghebbende::where('naam', 'Toezichthouder')->firstOrFail();

        Livewire::actingAs($this->ciso)
            ->test(BelanghebbendenOverzicht::class)
            ->call('eisToevoegenAan', $belanghebbende->id)
            ->set('eisOmschrijving', 'Jaarlijkse rapportage aanleveren')
            ->set('eisBron', 'wettelijk')
            ->call('eisOpslaan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('eisen', [
            'belanghebbende_id' => $belanghebbende->id,
            'bron' => 'wettelijk',
        ]);
    }
}
