<?php

namespace Tests\Feature;

use App\Actions\ActiveerScopeVerklaring;
use App\Livewire\ScopeBeheer;
use App\Models\Belanghebbende;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\OrganisatieEenheid;
use App\Models\ScopeVerklaring;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScopeBeheerTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    /** Activeren vraagt `goedkeuren` — zie implementatie/01c §4. */
    private Gebruiker $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->directeur = Gebruiker::factory()->metRol('Management')->create();
    }

    public function test_eerste_scope_versie_start_als_concept(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->call('eersteVersieOpstellen');

        $versie = ScopeVerklaring::firstOrFail();
        $this->assertSame(1, $versie->versienummer);
        $this->assertSame('concept', $versie->status);
    }

    public function test_uitsluiting_zonder_motivatie_wordt_geweigerd(): void
    {
        $concept = ScopeVerklaring::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->set('nieuweUitsluitingOmschrijving', 'Datacenter in Frankrijk')
            ->set('nieuweUitsluitingMotivatie', '')
            ->call('uitsluitingToevoegen')
            ->assertHasErrors(['nieuweUitsluitingMotivatie' => 'required']);

        $this->assertDatabaseCount('uitsluitingen', 0);
    }

    public function test_ciso_kan_concept_opslaan_met_koppelingen(): void
    {
        ScopeVerklaring::factory()->create();
        $eenheid = OrganisatieEenheid::factory()->create();
        $issue = Issue::factory()->create();
        $belanghebbende = Belanghebbende::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->set('scopetekst', 'Het hoofdkantoor en alle primaire processen.')
            ->set('geselecteerdeEenheden', [$eenheid->id])
            ->set('geselecteerdeIssues', [$issue->id])
            ->set('geselecteerdeBelanghebbenden', [$belanghebbende->id])
            ->call('conceptOpslaan')
            ->assertHasNoErrors();

        $versie = ScopeVerklaring::firstOrFail();
        $this->assertSame('Het hoofdkantoor en alle primaire processen.', $versie->scopetekst);
        $this->assertTrue($versie->organisatieEenheden->contains($eenheid));
        $this->assertTrue($versie->issues->contains($issue));
        $this->assertTrue($versie->belanghebbenden->contains($belanghebbende));

        // De koppelingen zelf horen in de audit trail (06b).
        $this->assertSame(
            '1 gekoppeld: '.$eenheid->naam,
            $this->laatsteKoppelregel('scope_verklaring', 'organisatie-eenheden'),
        );
        $this->assertSame('1 gekoppeld: '.$issue->omschrijving, $this->laatsteKoppelregel('scope_verklaring', 'issues'));
        $this->assertSame(
            '1 gekoppeld: '.$belanghebbende->naam,
            $this->laatsteKoppelregel('scope_verklaring', 'belanghebbenden'),
        );
    }

    public function test_indienen_en_activeren_maakt_de_versie_actief(): void
    {
        ScopeVerklaring::factory()->create(['scopetekst' => 'Volledige organisatie.']);

        // Twee handen: de CISO dient in, de directie activeert (01c §4).
        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->call('indienenTerGoedkeuring')
            ->assertHasNoErrors();

        Livewire::actingAs($this->directeur)
            ->test(ScopeBeheer::class)
            ->set('goedgekeurdDoor', 'Directie')
            ->call('activeren')
            ->assertHasNoErrors();

        $versie = ScopeVerklaring::firstOrFail();
        $this->assertSame('actief', $versie->status);
        $this->assertSame('Directie', $versie->goedgekeurd_door);
        $this->assertNotNull($versie->geldig_vanaf);
    }

    public function test_ciso_dient_in_maar_activeert_niet(): void
    {
        $versie = ScopeVerklaring::factory()->terGoedkeuring()->create();

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->set('goedgekeurdDoor', 'Directie')
            ->call('activeren')
            ->assertForbidden();

        $this->assertSame('ter_goedkeuring', $versie->fresh()->status);
    }

    public function test_management_activeert_maar_bewerkt_de_scope_niet(): void
    {
        ScopeVerklaring::factory()->create();

        Livewire::actingAs($this->directeur)
            ->test(ScopeBeheer::class)
            ->set('scopetekst', 'Poging tot herschrijven.')
            ->call('conceptOpslaan')
            ->assertForbidden();
    }

    public function test_activeren_zonder_goedkeurder_wordt_geweigerd(): void
    {
        ScopeVerklaring::factory()->terGoedkeuring()->create();

        Livewire::actingAs($this->directeur)
            ->test(ScopeBeheer::class)
            ->set('goedgekeurdDoor', '')
            ->call('activeren')
            ->assertHasErrors(['goedgekeurdDoor' => 'required']);

        $this->assertSame('ter_goedkeuring', ScopeVerklaring::first()->status);
    }

    public function test_nieuwe_conceptversie_kopieert_alles_en_verhoogt_versienummer(): void
    {
        $eenheid = OrganisatieEenheid::factory()->create();
        $issue = Issue::factory()->create();
        $belanghebbende = Belanghebbende::factory()->create();
        $actief = ScopeVerklaring::factory()->actief()->create(['versienummer' => 3, 'scopetekst' => 'Bestaande scope.']);
        $actief->organisatieEenheden()->attach($eenheid);
        $actief->issues()->attach($issue);
        $actief->belanghebbenden()->attach($belanghebbende);
        $actief->uitsluitingen()->create(['omschrijving' => 'Datacenter Frankrijk', 'motivatie' => 'Eigen ISMS.']);
        $actief->interfaces()->create(['omschrijving' => 'IT-beheer extern', 'risico_implicatie' => 'Afhankelijkheid leverancier.']);

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->call('nieuweConceptversieStarten')
            ->assertHasNoErrors();

        $concept = ScopeVerklaring::where('status', 'concept')->firstOrFail();
        $this->assertSame(4, $concept->versienummer);
        $this->assertSame('Bestaande scope.', $concept->scopetekst);
        $this->assertTrue($concept->organisatieEenheden->contains($eenheid));
        $this->assertTrue($concept->issues->contains($issue));
        $this->assertTrue($concept->belanghebbenden->contains($belanghebbende));
        // De volledige kopie neemt ook uitsluitingen en interfaces mee.
        $this->assertSame('Datacenter Frankrijk', $concept->uitsluitingen->first()?->omschrijving);
        $this->assertSame('IT-beheer extern', $concept->interfaces->first()?->omschrijving);
    }

    public function test_een_versie_read_only_inzien(): void
    {
        $actief = ScopeVerklaring::factory()->actief()->create(['versienummer' => 2]);
        $actief->uitsluitingen()->create(['omschrijving' => 'Gastennetwerk', 'motivatie' => 'Fysiek gescheiden.']);

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->call('bekijkVersie', $actief->id)
            ->assertSet('toontDetail', true)
            ->assertSet('bekekenVersieId', $actief->id)
            ->assertSee('Gastennetwerk')
            ->call('sluitBekekenVersie')
            ->assertSet('toontDetail', false);
    }

    public function test_activeren_vervangt_de_vorige_actieve_versie(): void
    {
        $oud = ScopeVerklaring::factory()->actief()->create(['versienummer' => 1]);
        $nieuw = ScopeVerklaring::factory()->terGoedkeuring()->create(['versienummer' => 2]);

        app(ActiveerScopeVerklaring::class)($nieuw, 'Directie');

        $this->assertSame('vervangen', $oud->fresh()->status);
        $this->assertSame('actief', $nieuw->fresh()->status);
        // Default: jaarlijkse herziening.
        $this->assertNotNull($nieuw->fresh()->volgende_herziening_gepland);
        $this->assertTrue($nieuw->fresh()->volgende_herziening_gepland->isFuture());
    }

    public function test_concept_van_actieve_versie_is_niet_bewerkbaar(): void
    {
        $actief = ScopeVerklaring::factory()->actief()->create();

        $this->assertFalse($actief->isBewerkbaar());
    }

    public function test_herziening_verstreken_signaleert_een_verlopen_datum(): void
    {
        $actief = ScopeVerklaring::factory()->actief()->create([
            'volgende_herziening_gepland' => now()->subDay(),
        ]);

        $this->assertTrue($actief->herzieningVerstreken());
    }

    public function test_auditor_mag_scope_niet_activeren(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        ScopeVerklaring::factory()->terGoedkeuring()->create();

        Livewire::actingAs($auditor)
            ->test(ScopeBeheer::class)
            ->set('goedgekeurdDoor', 'Iemand')
            ->call('activeren')
            ->assertForbidden();

        $this->assertSame('ter_goedkeuring', ScopeVerklaring::first()->status);
    }
}
