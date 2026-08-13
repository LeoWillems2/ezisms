<?php

namespace Tests\Feature;

use App\Livewire\AuditrondeDetail;
use App\Livewire\AuditsOverzicht;
use App\Models\Afwijking;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Gebruiker;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditmanagementTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    /** Een interne ronde met een toegewezen Auditor-account. */
    private function interneRonde(string $status, ?Gebruiker $auditor): Auditronde
    {
        return Auditronde::factory()->create([
            'type' => 'intern',
            'status' => $status,
            'auditor_gebruiker_id' => $auditor?->id,
        ]);
    }

    // --- Autorisatie (§8) --------------------------------------------------

    public function test_auditor_leest_de_audits(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/audits')->assertOk();
    }

    // --- Planning ----------------------------------------------------------

    public function test_ciso_plant_plan_ronde_en_wijst_auditor_toe(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(AuditsOverzicht::class)
            ->set('jaar', '2027')
            ->call('slaPlanOp')
            ->assertHasNoErrors();

        $plan = Auditplan::where('jaar', 2027)->firstOrFail();

        Livewire::actingAs($this->ciso)
            ->test(AuditsOverzicht::class)
            ->set('rondePlanId', $plan->id)
            ->set('rondeType', 'intern')
            ->call('slaRondeOp')
            ->assertRedirect();

        $ronde = Auditronde::where('auditplan_id', $plan->id)->firstOrFail();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $auditobject = Auditobject::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set('auditorGebruikerId', $auditor->id)
            ->set('scopeObjecten', [$auditobject->id])
            ->call('slaPlanningOp')
            ->assertHasNoErrors();

        $this->assertSame($auditor->id, $ronde->fresh()->auditor_gebruiker_id);

        // De normatieve scope van de ronde is een auditorvraag op zich (06b §1).
        $this->assertSame(
            '1 gekoppeld: '.$auditobject->auditOmschrijving(),
            $this->laatsteKoppelregel('auditronde', 'auditobjecten'),
        );
    }

    // --- Record-guard op de bevinding (§4a) --------------------------------

    public function test_ciso_mag_geen_bevinding_op_interne_ronde_vastleggen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = $this->interneRonde('in_uitvoering', $auditor);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('nieuweBevinding')
            ->assertForbidden();
    }

    public function test_toegewezen_auditor_legt_bevinding_vast_tijdens_uitvoering(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = $this->interneRonde('in_uitvoering', $auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set('bevindingType', 'non_conformiteit_minor')
            ->set('bevindingOmschrijving', 'Toegangsrechten niet periodiek herzien.')
            ->call('slaBevindingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bevindingen', [
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_minor',
            'status' => 'open',
        ]);
    }

    public function test_auditor_mag_niet_voor_uitvoering_of_op_andermans_ronde(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        // Nog niet in uitvoering: ook de toegewezen auditor mag niet.
        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $this->interneRonde('gepland', $auditor)])
            ->call('nieuweBevinding')
            ->assertForbidden();

        // In uitvoering, maar een ándere auditor.
        $ander = Gebruiker::factory()->metRol('Auditor')->create();
        Livewire::actingAs($ander)
            ->test(AuditrondeDetail::class, ['auditronde' => $this->interneRonde('in_uitvoering', $auditor)])
            ->call('nieuweBevinding')
            ->assertForbidden();
    }

    // --- Onveranderlijkheid na afronden (§4a/§5) ---------------------------

    public function test_na_afgerond_niemand_wijzigt_maar_ciso_volgt_op(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = $this->interneRonde('afgerond', $auditor);
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_major',
            'status' => 'open',
        ]);

        // Inhoud bevroren — voor de auditor én de CISO.
        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('bewerkBevinding', $bevinding->id)
            ->assertForbidden();

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('bewerkBevinding', $bevinding->id)
            ->assertForbidden();

        // Opvolging mag wél: de CISO start een non-conformiteit.
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('opvolgenAlsNonConformiteit', $bevinding->id)
            ->assertRedirect();

        $this->assertDatabaseHas('afwijkingen', [
            'bron' => 'audit_bevinding',
            'bevinding_id' => $bevinding->id,
        ]);
        $this->assertSame('non_conformiteit_gestart', $bevinding->fresh()->status);
    }

    // --- Sluiten (§6) ------------------------------------------------------

    public function test_non_conformiteit_sluit_pas_als_afwijking_gesloten(): void
    {
        $ronde = $this->interneRonde('afgerond', Gebruiker::factory()->metRol('Auditor')->create());
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_minor',
            'status' => 'open',
        ]);

        // Zonder afwijking: niet te sluiten.
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id);
        $this->assertSame('open', $bevinding->fresh()->status);

        // Non-conformiteit gestart, maar afwijking nog open: nog steeds niet.
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('opvolgenAlsNonConformiteit', $bevinding->id);
        $afwijking = Afwijking::where('bevinding_id', $bevinding->id)->firstOrFail();

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id);
        $this->assertSame('non_conformiteit_gestart', $bevinding->fresh()->status);

        // Afwijking gesloten: nu wél.
        $afwijking->update(['status' => 'gesloten', 'gesloten_op' => now()]);
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id);
        $this->assertSame('gesloten', $bevinding->fresh()->status);
    }

    public function test_observatie_sluit_direct(): void
    {
        $ronde = $this->interneRonde('afgerond', Gebruiker::factory()->metRol('Auditor')->create());
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'type' => 'observatie',
            'status' => 'open',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id);

        $this->assertSame('gesloten', $bevinding->fresh()->status);
        $this->assertSame($this->ciso->id, $bevinding->fresh()->gesloten_door_id);
    }

    // --- Externe ronde (§4a) -----------------------------------------------

    public function test_externe_ronde_beheert_de_ciso(): void
    {
        $ronde = Auditronde::factory()->create([
            'type' => 'extern_certificering',
            'status' => 'gepland',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set('externAuditorNaam', 'Certificerende Instelling BV')
            ->set('bevindingType', 'non_conformiteit_major')
            ->set('bevindingOmschrijving', 'Directiebetrokkenheid onvoldoende aangetoond.')
            ->call('slaBevindingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bevindingen', [
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_major',
        ]);
    }

    // --- Audit trail -------------------------------------------------------

    public function test_bevinding_aanmaken_komt_in_de_audit_trail(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = $this->interneRonde('in_uitvoering', $auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set('bevindingType', 'observatie')
            ->set('bevindingOmschrijving', 'Logboek niet volledig ingevuld.')
            ->call('slaBevindingOp');

        $bevinding = Bevinding::where('auditronde_id', $ronde->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'bevinding',
            'entiteit_id' => $bevinding->id,
            'actie' => 'aangemaakt',
        ]);
    }
}
