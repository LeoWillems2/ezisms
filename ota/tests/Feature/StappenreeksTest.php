<?php

namespace Tests\Feature;

use App\Livewire\TakenOverzicht;
use App\Mail\StapActueel;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Sjabloonstap;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use App\Support\StapGeblokkeerd;
use App\Support\Stappenreeks;
use App\Support\TaakPlanner;
use Database\Seeders\BlokSeeder;
use Database\Seeders\NotificatieregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * De reekslaag op de taken-engine (implementatie/07b).
 *
 * Het dossier is hier een `Systeem`: de engine kent geen dossiersoorten, dus
 * elke gemapte entiteit voldoet, en een systeem is wat blok 15 straks als
 * object van een wijziging gebruikt.
 */
class StappenreeksTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    private Systeem $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->dossier = Systeem::factory()->create(['naam' => 'HR-SaaS']);
    }

    /** @param  list<array<string, mixed>>  $extra */
    private function driestapsReeks(array $extra = []): void
    {
        Stappenreeks::start($this->dossier, 'taken-workflow-engine', $extra !== [] ? $extra : [
            ['titel' => 'Release notes beoordelen', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Impactanalyse', 'volgorde' => 2, 'deadline' => now()->addDays(7)],
            ['titel' => 'Evaluatie', 'volgorde' => 3, 'deadline' => now()->addDays(20)],
        ]);
    }

    // --- Opbouw en activering ----------------------------------------------

    public function test_start_maakt_alle_stappen_en_activeert_alleen_de_eerste_groep(): void
    {
        $this->driestapsReeks();

        $reeks = Stappenreeks::voorEntiteit($this->dossier);

        $this->assertCount(3, $reeks);
        $this->assertSame('open', $reeks[0]->status);
        $this->assertSame('wachtend', $reeks[1]->status);
        $this->assertSame('wachtend', $reeks[2]->status);
    }

    public function test_stappen_met_dezelfde_volgorde_worden_samen_actueel(): void
    {
        $this->driestapsReeks([
            ['titel' => 'Impactanalyse', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Stakeholders informeren', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Uitvoeren', 'volgorde' => 2, 'deadline' => now()->addDays(9)],
        ]);

        $actueel = Stappenreeks::actueleStappen($this->dossier);

        $this->assertCount(2, $actueel);
        $this->assertEqualsCanonicalizing(
            ['Impactanalyse', 'Stakeholders informeren'],
            $actueel->pluck('titel')->all(),
        );
    }

    public function test_een_van_twee_parallelle_stappen_schuift_de_reeks_niet_door(): void
    {
        $this->driestapsReeks([
            ['titel' => 'Impactanalyse', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Stakeholders informeren', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Uitvoeren', 'volgorde' => 2, 'deadline' => now()->addDays(9)],
        ]);

        $eerste = Stappenreeks::voorEntiteit($this->dossier)->firstWhere('titel', 'Impactanalyse');
        $eerste->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame('wachtend', $this->stap('Uitvoeren')->status);

        $tweede = Stappenreeks::voorEntiteit($this->dossier)->firstWhere('titel', 'Stakeholders informeren');
        $tweede->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame('open', $this->stap('Uitvoeren')->status);
    }

    public function test_een_gat_in_de_nummering_wordt_overgeslagen(): void
    {
        $this->driestapsReeks([
            ['titel' => 'Eerste', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
            ['titel' => 'Derde', 'volgorde' => 3, 'deadline' => now()->addDays(9)],
        ]);

        $this->stap('Eerste')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame('open', $this->stap('Derde')->status);
    }

    public function test_tweede_reeks_op_dezelfde_entiteit_wordt_geweigerd(): void
    {
        $this->driestapsReeks();

        $this->expectException(RuntimeException::class);

        Stappenreeks::start($this->dossier, 'taken-workflow-engine', [
            ['titel' => 'Nog een reeks', 'volgorde' => 1, 'deadline' => now()->addDays(3)],
        ]);
    }

    public function test_reeks_is_pas_afgerond_als_alle_stappen_voltooid_zijn(): void
    {
        $this->driestapsReeks();

        $this->assertFalse(Stappenreeks::isAfgerond($this->dossier));

        foreach (['Release notes beoordelen', 'Impactanalyse', 'Evaluatie'] as $titel) {
            $this->stap($titel)->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        }

        $this->assertTrue(Stappenreeks::isAfgerond($this->dossier));
    }

    // --- Uitkomst, afkeuren en heropenen -----------------------------------

    public function test_afgekeurde_stap_schuift_de_reeks_niet_door(): void
    {
        $this->driestapsReeks();

        Stappenreeks::legUitkomstVast($this->stap('Release notes beoordelen'), 'afgekeurd');

        $this->assertSame('voltooid', $this->stap('Release notes beoordelen')->status);
        $this->assertSame('afgekeurd', $this->stap('Release notes beoordelen')->uitkomst);
        $this->assertSame('wachtend', $this->stap('Impactanalyse')->status);
    }

    public function test_goedgekeurde_stap_schuift_wel_door(): void
    {
        $this->driestapsReeks();

        Stappenreeks::legUitkomstVast($this->stap('Release notes beoordelen'), 'goedgekeurd');

        $this->assertSame('open', $this->stap('Impactanalyse')->status);
    }

    public function test_heropenen_zet_stappen_terug_en_activeert_opnieuw(): void
    {
        $this->driestapsReeks();

        Stappenreeks::legUitkomstVast($this->stap('Release notes beoordelen'), 'goedgekeurd');
        $this->stap('Impactanalyse')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Stappenreeks::heropenVanaf($this->dossier, 1);

        $eerste = $this->stap('Release notes beoordelen');
        $this->assertSame('open', $eerste->status);
        $this->assertNull($eerste->uitkomst);
        $this->assertNull($eerste->voltooid_op);
        $this->assertSame('wachtend', $this->stap('Impactanalyse')->status);
    }

    public function test_uitkomst_op_een_losse_taak_wordt_geweigerd(): void
    {
        $los = Taak::factory()->create(['eigenaar_id' => $this->ciso->id]);

        $this->expectException(RuntimeException::class);

        Stappenreeks::legUitkomstVast($los, 'goedgekeurd');
    }

    // --- Samenleven met de bestaande engine --------------------------------

    public function test_wachtende_stap_telt_niet_als_openstaand_en_hindert_de_planner_niet(): void
    {
        $this->driestapsReeks();

        $this->assertSame(1, Taak::whereIn('status', Taak::OPENSTAAND)->count());

        // De planner plant ongestoord een losse taak op dezelfde entiteit: die
        // heeft een gevulde `soort` en matcht dus nooit op een stap (07b §4).
        $los = TaakPlanner::planVoorEntiteit(
            $this->dossier,
            'systeem-review',
            'Systeem herbeoordelen',
            now()->addDays(30),
            'asset-classificatie',
            $this->ciso->id,
        );

        $this->assertNotNull($los);
        $this->assertNull($los->volgorde);
        $this->assertCount(3, Stappenreeks::voorEntiteit($this->dossier));
    }

    public function test_verloop_taken_laat_een_wachtende_stap_met_verstreken_deadline_met_rust(): void
    {
        $this->driestapsReeks([
            ['titel' => 'Eerste', 'volgorde' => 1, 'deadline' => now()->addDays(5)],
            ['titel' => 'Tweede', 'volgorde' => 2, 'deadline' => now()->subDays(3)],
        ]);

        $this->artisan('isms:verloop-taken')->assertExitCode(0);

        $tweede = $this->stap('Tweede');
        $this->assertSame('wachtend', $tweede->status);
        $this->assertSame(0, $tweede->escalatie_niveau);
        // Ook de weergave mag hem niet als te laat tonen zolang hij wacht.
        $this->assertFalse($tweede->isFeitelijkVerlopen());
    }

    public function test_stap_deadline_is_beheerd_en_de_stap_is_niet_heropenbaar(): void
    {
        $this->driestapsReeks();

        $stap = $this->stap('Release notes beoordelen');

        $this->assertTrue($stap->deadlineWordtBeheerd());
        $this->assertFalse($stap->isHeropenbaar());
    }

    // --- Scherm en bericht -------------------------------------------------

    public function test_takenscherm_verbergt_wachtende_stappen_en_weigert_ze_te_voltooien(): void
    {
        $this->driestapsReeks();

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->assertSee('Release notes beoordelen')
            ->assertDontSee('Impactanalyse')
            ->set('filterStatus', 'wachtend')
            ->assertSee('Impactanalyse')
            ->assertDontSee('Release notes beoordelen');

        $wachtend = $this->stap('Impactanalyse');

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $wachtend->id)
            ->assertStatus(422);

        $this->assertSame('wachtend', $this->stap('Impactanalyse')->status);
    }

    public function test_goedkeuringsstap_wordt_op_het_scherm_met_uitkomst_afgerond(): void
    {
        $this->driestapsReeks([
            ['titel' => 'Release notes goedkeuren', 'volgorde' => 1, 'deadline' => now()->addDays(3),
                'eigenaar_id' => $this->ciso->id, 'vraagt_uitkomst' => true],
            ['titel' => 'Uitvoeren', 'volgorde' => 2, 'deadline' => now()->addDays(9)],
        ]);

        $stap = $this->stap('Release notes goedkeuren');

        // Voltooien zonder uitkomst is geen geldige afronding van deze stap.
        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $stap->id)
            ->assertStatus(422);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('legUitkomstVast', $stap->id, 'afgekeurd');

        $this->assertSame('afgekeurd', $this->stap('Release notes goedkeuren')->uitkomst);
        $this->assertSame('wachtend', $this->stap('Uitvoeren')->status);
    }

    public function test_eigenaar_krijgt_bericht_zodra_zijn_stap_aan_de_beurt_is(): void
    {
        $this->seed(NotificatieregelSeeder::class);
        Mail::fake();

        $uitvoerder = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->driestapsReeks([
            ['titel' => 'Eerste', 'volgorde' => 1, 'deadline' => now()->addDays(3),
                'eigenaar_id' => $this->ciso->id],
            ['titel' => 'Tweede', 'volgorde' => 2, 'deadline' => now()->addDays(9),
                'eigenaar_id' => $uitvoerder->id],
        ]);

        // Alleen de eerste groep is actueel, dus alleen die eigenaar hoort iets.
        Mail::assertSent(StapActueel::class, 1);

        $this->stap('Eerste')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Mail::assertSent(StapActueel::class, 2);
        Mail::assertSent(StapActueel::class, fn (StapActueel $mail) => $mail->hasTo($uitvoerder->email));
    }

    public function test_uitkomst_verschijnt_in_de_audit_trail(): void
    {
        $this->driestapsReeks();

        Stappenreeks::legUitkomstVast($this->stap('Release notes beoordelen'), 'goedgekeurd');

        $regels = AuditLogregel::query()
            ->where('entiteit_type', 'taak')
            ->where('entiteit_id', $this->stap('Release notes beoordelen')->id)
            ->get();

        // Geen eigen actie voor de uitkomst: het is een veldwijziging op de taak.
        // Een statusovergang zoek je op de sleutel `status`, de uitkomst op
        // `uitkomst` — en omdat hier meer dan alleen de status wijzigt, is de
        // actie `gewijzigd` en niet `status_gewijzigd`.
        $this->assertTrue(
            $regels->contains(fn (AuditLogregel $regel) => ($regel->nieuwe_waarde['uitkomst'] ?? null) === 'goedgekeurd'),
            'De uitkomst hoort als veldwijziging in de audit trail te staan.',
        );
    }

    // --- Belemmering vanuit het dossier (implementatie/15 §6) --------------

    public function test_dossier_kan_een_stap_tegenhouden(): void
    {
        $wijziging = Wijziging::factory()->create();
        $sjabloon = Wijzigingssjabloon::factory()->create();
        $sjabloonstap = Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('uitvoeren', 1)->create();

        // Zonder terugvalplan mag een uitvoerstap niet worden afgerond.
        Stappenreeks::start($wijziging, 'wijzigingsbeheer', [[
            'titel' => 'Uitvoeren', 'volgorde' => 1, 'deadline' => now()->addDays(3),
            // Zoals blok 15 het doet: het staptype gaat bevroren mee, want de
            // belemmering wordt van de taak gelezen en niet van de sjabloonstap
            // (implementatie/15 §17).
            'extra' => [
                'sjabloonstap_id' => $sjabloonstap->id,
                'staptype' => $sjabloonstap->staptype,
                'bewijs_verplicht' => $sjabloonstap->bewijs_verplicht,
            ],
        ]]);

        $stap = Stappenreeks::voorEntiteit($wijziging)->first();

        try {
            $stap->update(['status' => 'voltooid', 'voltooid_op' => now()]);
            $this->fail('De stap had geblokkeerd moeten worden.');
        } catch (StapGeblokkeerd $e) {
            $this->assertStringContainsString('terugvalplan', $e->getMessage());
        }

        $this->assertSame('open', $stap->fresh()->status);
    }

    public function test_zonder_belemmering_gaat_de_stap_gewoon_door(): void
    {
        $wijziging = Wijziging::factory()->metTerugvalplan()->create();
        $sjabloon = Wijzigingssjabloon::factory()->create();
        $sjabloonstap = Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('uitvoeren', 1)->create();

        Stappenreeks::start($wijziging, 'wijzigingsbeheer', [[
            'titel' => 'Uitvoeren', 'volgorde' => 1, 'deadline' => now()->addDays(3),
            // Zoals blok 15 het doet: het staptype gaat bevroren mee, want de
            // belemmering wordt van de taak gelezen en niet van de sjabloonstap
            // (implementatie/15 §17).
            'extra' => [
                'sjabloonstap_id' => $sjabloonstap->id,
                'staptype' => $sjabloonstap->staptype,
                'bewijs_verplicht' => $sjabloonstap->bewijs_verplicht,
            ],
        ]]);

        $stap = Stappenreeks::voorEntiteit($wijziging)->first();
        $stap->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame('voltooid', $stap->fresh()->status);
    }

    public function test_een_dossier_zonder_de_interface_blokkeert_niets(): void
    {
        // Het `Systeem` uit de andere tests implementeert `Stapbelemmering` niet;
        // de engine hoort dat gewoon te negeren.
        $this->driestapsReeks();

        $this->stap('Release notes beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame('voltooid', $this->stap('Release notes beoordelen')->status);
    }

    /** De verse stand van één stap, op titel. */
    private function stap(string $titel): Taak
    {
        return Stappenreeks::voorEntiteit($this->dossier)->firstWhere('titel', $titel);
    }
}
