<?php

namespace Tests\Feature;

use App\Livewire\GebruikersOverzicht;
use App\Models\Gebruiker;
use App\Support\Koppelbaar;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersoneelsbeveiligingTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Gap-signalen (A.6) ------------------------------------------------

    public function test_actief_account_zonder_pre_employment_is_een_gap(): void
    {
        $g = Gebruiker::factory()->create(); // actief, geen NDA/screening

        $this->assertTrue($g->preEmploymentGap());
        $this->assertFalse($g->preEmploymentCompleet());
        $this->assertEqualsCanonicalizing(
            ['getekende NDA', 'screening (VOG/referentiecheck)'],
            $g->preEmploymentOntbrekend()
        );

        $g->update(['nda_getekend_op' => now()->subDays(10), 'screening_type' => 'vog', 'screening_op' => now()->subDays(9)]);

        $this->assertFalse($g->fresh()->preEmploymentGap());
        $this->assertTrue($g->fresh()->preEmploymentCompleet());
    }

    public function test_gedeactiveerd_account_zonder_offboarding_is_een_gap(): void
    {
        $g = Gebruiker::factory()->gedeactiveerd()->create();

        $this->assertTrue($g->offboardingGap());

        $g->update(['accounts_ingetrokken_op' => now()]);
        $this->assertFalse($g->fresh()->offboardingGap());
    }

    // --- Dossier vastleggen ------------------------------------------------

    public function test_ciso_legt_dossier_vast_en_het_komt_in_de_audit_trail(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openDossier', $medewerker->id)
            ->set('ndaGetekendOp', now()->subDays(5)->format('Y-m-d'))
            ->set('screeningType', 'referentiecheck')
            ->set('screeningOp', now()->subDays(4)->format('Y-m-d'))
            ->set('accountsIngetrokkenOp', '')
            ->call('slaDossierOp')
            ->assertHasNoErrors();

        $medewerker->refresh();
        $this->assertNotNull($medewerker->nda_getekend_op);
        $this->assertSame('referentiecheck', $medewerker->screening_type);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'gebruiker',
            'entiteit_id' => $medewerker->id,
            'actie' => 'gewijzigd',
        ]);
    }

    public function test_screeningstype_en_datum_horen_samen(): void
    {
        $medewerker = Gebruiker::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openDossier', $medewerker->id)
            ->set('screeningType', 'vog')
            ->set('screeningOp', '') // type zonder datum
            ->call('slaDossierOp')
            ->assertHasErrors(['screeningOp']);
    }

    public function test_geen_datum_in_de_toekomst(): void
    {
        $medewerker = Gebruiker::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('openDossier', $medewerker->id)
            ->set('ndaGetekendOp', now()->addDays(3)->format('Y-m-d'))
            ->call('slaDossierOp')
            ->assertHasErrors(['ndaGetekendOp']);
    }

    // --- Bewijs ------------------------------------------------------------

    public function test_gebruiker_is_koppelbaar_voor_bewijs(): void
    {
        $this->assertArrayHasKey('gebruiker', Koppelbaar::TYPES);

        // Als CISO (muteren op identity-access) staat 'gebruiker' in de keuzelijst.
        $this->actingAs($this->ciso);
        $this->assertArrayHasKey('gebruiker', Koppelbaar::toegestaneTypes());
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_auditor_mag_geen_dossier_muteren(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $medewerker = Gebruiker::factory()->create();

        Livewire::actingAs($auditor)
            ->test(GebruikersOverzicht::class)
            ->call('openDossier', $medewerker->id)
            ->assertForbidden();
    }
}
