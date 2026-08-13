<?php

namespace Tests\Feature;

use App\Livewire\LeverancierDetail;
use App\Livewire\LeveranciersOverzicht;
use App\Models\Contractclausule;
use App\Models\Gebruiker;
use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use App\Models\Risico;
use App\Models\Systeem;
use App\Models\Taak;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LeverancierTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Autorisatie (§8) --------------------------------------------------

    public function test_auditor_leest_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $leverancier = Leverancier::factory()->create();

        $this->actingAs($auditor)->get('/leveranciers')->assertOk();

        Livewire::actingAs($auditor)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->set('naam', 'Aangepast')
            ->call('slaBasisgegevensOp')
            ->assertForbidden();
    }

    // --- Registreren en levenscyclus (§5) ----------------------------------

    public function test_registreren_start_als_kandidaat_en_stuurt_door(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(LeveranciersOverzicht::class)
            ->set('naam', 'Hostingpartij BV')
            ->set('risiconiveau', 'midden')
            ->call('opslaan')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('leveranciers', [
            'naam' => 'Hostingpartij BV',
            'status' => 'kandidaat',
            'risiconiveau' => 'midden',
        ]);
    }

    public function test_eerste_beoordeling_maakt_leverancier_actief(): void
    {
        $leverancier = Leverancier::factory()->create(); // kandidaat

        Leveranciersbeoordeling::factory()->create(['leverancier_id' => $leverancier->id]);

        $this->assertSame('actief', $leverancier->fresh()->status);
    }

    public function test_beeindigen_zonder_teruggave_wordt_geweigerd(): void
    {
        $leverancier = Leverancier::factory()->actief()->create();

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->set('dataTeruggaveBevestigd', false)
            ->call('beeindig')
            ->assertHasErrors('dataTeruggaveBevestigd');

        $this->assertSame('actief', $leverancier->fresh()->status);
    }

    public function test_beeindigen_slaagt_met_bevestigde_teruggave(): void
    {
        $leverancier = Leverancier::factory()->actief()->create();

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->set('dataTeruggaveBevestigd', true)
            ->call('beeindig')
            ->assertHasNoErrors();

        $vers = $leverancier->fresh();
        $this->assertSame('beeindigd', $vers->status);
        $this->assertNotNull($vers->beeindigd_op);
        $this->assertNotNull($vers->data_teruggave_bevestigd_op);
        $this->assertSame($this->ciso->id, $vers->data_teruggave_door_id);
    }

    public function test_beeindigde_leverancier_is_read_only(): void
    {
        $leverancier = Leverancier::factory()->create(['status' => 'beeindigd', 'beeindigd_op' => now()]);

        $component = Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier]);

        $this->assertFalse($component->instance()->magBewerken());

        // Elke muteer-actie wordt geweigerd zolang hij beëindigd is.
        $component->set('naam', 'Poging tot wijzigen')->call('slaBasisgegevensOp')->assertForbidden();
        $this->assertSame($leverancier->naam, $leverancier->fresh()->naam);

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->call('wisselClausule', 'sla')
            ->assertForbidden();

        $this->assertDatabaseCount('contractclausules', 0);
    }

    public function test_beeindigde_leverancier_kan_worden_geheractiveerd(): void
    {
        $leverancier = Leverancier::factory()->create([
            'status' => 'beeindigd',
            'beeindigd_op' => now(),
            'data_teruggave_bevestigd_op' => now(),
            'data_teruggave_door_id' => $this->ciso->id,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->call('heractiveren')
            ->assertHasNoErrors();

        $vers = $leverancier->fresh();
        $this->assertSame('actief', $vers->status);
        $this->assertNull($vers->beeindigd_op);
        $this->assertNull($vers->data_teruggave_bevestigd_op);
    }

    // --- Herbeoordelingstaak (§6) ------------------------------------------

    public function test_herbeoordelingstaak_wordt_gepland_en_verzet(): void
    {
        $leverancier = Leverancier::factory()->create();

        Leveranciersbeoordeling::factory()->create([
            'leverancier_id' => $leverancier->id,
            'uitgevoerd_op' => now()->subDays(10),
            'volgende_beoordeling_gepland' => now()->addYear(),
        ]);

        $this->assertDatabaseCount('taken', 1);
        $taak = Taak::first();
        $this->assertSame('leverancier-herbeoordeling', $taak->soort);
        $this->assertSame($leverancier->getMorphClass(), $taak->gekoppeld_entiteit_type);
        $this->assertSame($leverancier->id, $taak->gekoppeld_entiteit_id);

        // Een tweede beoordeling verzet dezelfde taak, maakt er geen tweede bij.
        Leveranciersbeoordeling::factory()->create([
            'leverancier_id' => $leverancier->id,
            'uitgevoerd_op' => now(),
            'volgende_beoordeling_gepland' => now()->addYears(2),
        ]);

        $this->assertDatabaseCount('taken', 1);
        $this->assertEquals(
            now()->addYears(2)->toDateString(),
            Taak::first()->deadline->toDateString()
        );
    }

    public function test_verstreken_beoordeling_wordt_gesignaleerd(): void
    {
        $leverancier = Leverancier::factory()->create();
        Leveranciersbeoordeling::factory()->create([
            'leverancier_id' => $leverancier->id,
            'volgende_beoordeling_gepland' => now()->subMonth(),
        ]);

        $this->assertTrue($leverancier->fresh()->herbeoordelingVerstreken());

        Livewire::actingAs($this->ciso)
            ->test(LeveranciersOverzicht::class)
            ->assertSee('verstreken herbeoordeling');
    }

    // --- Vooruitverwezen FK's (§3, §7) -------------------------------------

    public function test_vooruitverwezen_fks_laten_zich_leggen_en_null_bij_verwijderen(): void
    {
        $leverancier = Leverancier::factory()->create();
        $systeem = Systeem::factory()->create(['leverancier_id' => $leverancier->id]);
        $risico = Risico::factory()->create(['gekoppeld_leverancier_id' => $leverancier->id]);

        $leverancier->delete();

        $this->assertDatabaseHas('systemen', ['id' => $systeem->id, 'leverancier_id' => null]);
        $this->assertDatabaseHas('risicos', ['id' => $risico->id, 'gekoppeld_leverancier_id' => null]);
    }

    // --- Rapportagesignaal hoog risico (§11) -------------------------------

    public function test_hoog_risico_zonder_auditrecht_is_signaal_tenzij_clausule_of_certificaat(): void
    {
        $leverancier = Leverancier::factory()->hoogRisico()->create();

        $this->assertTrue($leverancier->fresh()->isHoogRisicoZonderAuditrecht());

        // Een aanwezige recht-op-audit-clausule haalt hem uit het signaal.
        Contractclausule::factory()->create([
            'leverancier_id' => $leverancier->id,
            'type' => 'recht_op_audit',
            'aanwezig' => true,
        ]);
        $this->assertFalse($leverancier->fresh()->isHoogRisicoZonderAuditrecht());

        // Zonder clausule maar met een geldig certificaat: ook uit het signaal.
        $ander = Leverancier::factory()->hoogRisico()->create([
            'eigen_certificering_geldig_tot' => now()->addMonths(3),
        ]);
        $this->assertFalse($ander->fresh()->isHoogRisicoZonderAuditrecht());
    }

    /**
     * De dienst draagt de Auditeerbaar-trait niet; de handeling vindt plaats op
     * het scherm van de leverancier, dus daar hangt de regel aan (06b §4).
     */
    public function test_systeem_koppelen_en_ontkoppelen_komt_op_de_leverancier_in_de_trail(): void
    {
        $leverancier = Leverancier::factory()->create();
        $systeem = Systeem::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->set('dienstOmschrijving', 'Hosting van de webomgeving')
            ->set('dienstSystemen', [$systeem->id])
            ->call('voegDienstToe')
            ->assertHasNoErrors();

        $dienst = $leverancier->diensten()->firstOrFail();
        $veld = 'systemen bij '.$dienst->omschrijving;

        $this->assertSame('1 gekoppeld: '.$systeem->naam, $this->laatsteKoppelregel('leverancier', $veld));

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->call('ontkoppelSysteem', $dienst, $systeem->id);

        $this->assertSame(
            '1 ontkoppeld: '.$systeem->naam,
            $this->laatsteKoppelregel('leverancier', $veld, 'oude_waarde'),
        );
    }

    public function test_verwerkersovereenkomst_is_een_beschikbare_clausule(): void
    {
        $this->assertArrayHasKey('verwerkersovereenkomst', Contractclausule::TYPES);

        $leverancier = Leverancier::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(LeverancierDetail::class, ['leverancier' => $leverancier])
            ->call('wisselClausule', 'verwerkersovereenkomst')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contractclausules', [
            'leverancier_id' => $leverancier->id,
            'type' => 'verwerkersovereenkomst',
            'aanwezig' => true,
        ]);
    }

    public function test_contractclausule_is_uniek_per_type(): void
    {
        $leverancier = Leverancier::factory()->create();
        Contractclausule::factory()->create(['leverancier_id' => $leverancier->id, 'type' => 'sla']);

        $this->expectException(QueryException::class);
        DB::table('contractclausules')->insert([
            'leverancier_id' => $leverancier->id,
            'type' => 'sla',
            'aanwezig' => true,
        ]);
    }
}
