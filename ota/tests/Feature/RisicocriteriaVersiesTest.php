<?php

namespace Tests\Feature;

use App\Actions\ActiveerRisicocriteria;
use App\Livewire\RisicoCriteria;
use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\Bandverschuiving;
use Database\Seeders\BlokSeeder;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De risicocriteria als vastgesteld kader (implementatie/04g).
 *
 * Twee dingen worden hier bewaakt en ze horen bij elkaar. Ten eerste de
 * functiescheiding: de CISO stelt op, Management stelt vast, en geen van beide
 * kan het werk van de ander doen. Ten tweede wat een vastgestelde wijziging met
 * het bestaande register doet — de stempel op elk risico, de bandverschuiving,
 * de herbeoordelingstaken en de breuk in de meetreeks. Dat laatste is de reden
 * dat de versionering bestaat: zonder die gevolgen is een nieuwe drempel alleen
 * een ander getal.
 */
class RisicocriteriaVersiesTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    private Gebruiker $directie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class,
            RisicocriteriaSeeder::class,
        ]);

        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->directie = Gebruiker::factory()->metRol('Management')->create();
    }

    /**
     * Een conceptversie met de gevraagde drempels, ingediend ter goedkeuring.
     * De weg die de CISO op het scherm ook loopt.
     */
    private function dienIn(int $drempel, int $waarschuwing = 10): RisicocriteriaVersie
    {
        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->call('nieuweConceptversieStarten')
            ->set('drempelwaardeScore', $drempel)
            ->set('waarschuwingsdrempelScore', $waarschuwing)
            ->call('conceptOpslaan')
            ->assertHasNoErrors()
            ->call('indienenTerGoedkeuring');

        return RisicocriteriaVersie::where('status', 'ter_goedkeuring')->firstOrFail();
    }

    // --- Functiescheiding (§2.2) -------------------------------------------

    public function test_ciso_stelt_op_management_stelt_vast_en_de_vorige_wordt_vervangen(): void
    {
        $eerste = RisicocriteriaVersie::actief();
        $tweede = $this->dienIn(12);

        Livewire::actingAs($this->directie)
            ->test(RisicoCriteria::class)
            ->set('goedgekeurdDoor', 'Directie')
            ->call('activeren')
            ->assertHasNoErrors();

        $this->assertSame('vervangen', $eerste->fresh()->status);
        $this->assertSame('actief', $tweede->fresh()->status);
        $this->assertSame(12, Risico::drempelwaarde());
    }

    /**
     * Management heeft op `risico-soa` alleen `goedkeuren`, en dat impliceert
     * sinds 29-07-2026 alleen `lezen`. Vaststellen zonder te kunnen herschrijven
     * is de hele reden dat die rol bestaat.
     */
    public function test_management_stelt_vast_maar_bewerkt_niet(): void
    {
        $this->dienIn(12);

        Livewire::actingAs($this->directie)
            ->test(RisicoCriteria::class)
            ->set('drempelwaardeScore', 3)
            ->call('conceptOpslaan')
            ->assertForbidden();

        Livewire::actingAs($this->directie)
            ->test(RisicoCriteria::class)
            ->call('nieuweConceptversieStarten')
            ->assertForbidden();
    }

    public function test_de_ciso_stelt_zijn_eigen_criteria_niet_vast(): void
    {
        $this->dienIn(12);

        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->set('goedgekeurdDoor', 'Ikzelf')
            ->call('activeren')
            ->assertForbidden();

        // Afwijzen is hier óók een handeling van de goedkeurder; de CISO houdt
        // zijn weg terug via een nieuw concept.
        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->call('terugNaarConcept')
            ->assertForbidden();
    }

    public function test_activatie_legt_de_vaststelling_vast_in_de_audit_trail(): void
    {
        $versie = $this->dienIn(12);

        app(ActiveerRisicocriteria::class)($versie, 'Directie');

        $versie->refresh();
        $this->assertSame('actief', $versie->status);
        $this->assertNotNull($versie->geldig_vanaf);
        $this->assertSame('Directie', $versie->goedgekeurd_door);
        // Default: jaarlijkse herziening, gelijk aan scope en beleid.
        $this->assertTrue($versie->volgende_herziening_gepland->isSameDay(now()->addYear()));

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'risicocriteria_versie',
            'entiteit_id' => $versie->id,
        ]);
    }

    // --- De kopie (§6) -----------------------------------------------------

    public function test_een_nieuwe_conceptversie_neemt_de_tien_niveaus_mee(): void
    {
        RisicocriteriaVersie::actief()->niveaus()
            ->where('as', 'impact')->where('niveau', 4)
            ->update(['kwantitatieve_band' => '1 tot 5% van de jaaromzet']);

        Livewire::actingAs($this->ciso)
            ->test(RisicoCriteria::class)
            ->call('nieuweConceptversieStarten');

        $concept = RisicocriteriaVersie::where('status', 'concept')->firstOrFail();

        $this->assertCount(10, $concept->niveaus);
        $this->assertSame('1 tot 5% van de jaaromzet', $concept->niveausVan('impact')[4]->kwantitatieve_band);
    }

    // --- De stempel op het risico (§2.6a) ----------------------------------

    public function test_een_risico_draagt_de_criteriaversie_waaronder_het_beoordeeld_is(): void
    {
        $eerste = RisicocriteriaVersie::actief();
        $oud = Risico::factory()->beoordeeld(3, 3)->create();

        $this->assertSame($eerste->id, $oud->fresh()->risicocriteria_versie_id);

        $tweede = $this->dienIn(12);
        app(ActiveerRisicocriteria::class)($tweede, 'Directie');

        $nieuw = Risico::factory()->beoordeeld(3, 3)->create();

        $this->assertSame($tweede->id, $nieuw->fresh()->risicocriteria_versie_id);
        // Het oude risico blijft naar het kader wijzen waaronder het beoordeeld
        // is; anders zou de historie een functie van vandaag zijn.
        $this->assertSame($eerste->id, $oud->fresh()->risicocriteria_versie_id);
        $this->assertTrue($oud->fresh()->beoordeeldOnderOudCriterium());
    }

    public function test_een_titelwijziging_verzet_het_beoordelingsmoment_niet(): void
    {
        $eerste = RisicocriteriaVersie::actief();
        $risico = Risico::factory()->beoordeeld(3, 3)->create();

        $tweede = $this->dienIn(12);
        app(ActiveerRisicocriteria::class)($tweede, 'Directie');

        $risico->update(['titel' => 'Andere titel']);

        $this->assertSame($eerste->id, $risico->fresh()->risicocriteria_versie_id);
    }

    // --- De bandverschuiving (§4.4) ----------------------------------------

    public function test_de_verschuiving_telt_de_risicos_die_boven_de_drempel_komen(): void
    {
        Risico::factory()->beoordeeld(3, 4)->create(); // score 12
        Risico::factory()->beoordeeld(4, 4)->create(); // score 16, stond al rood
        Risico::factory()->beoordeeld(1, 2)->create(); // score 2, blijft groen

        // Drempel van 15 naar 10: alleen de 12 schuift naar rood.
        $verschuiving = Bandverschuiving::tussen(15, 10, 10, 6);

        $this->assertSame(1, $verschuiving->aantalNieuwBovenDrempel());
        $this->assertCount(1, $verschuiving->omhoog());
        $this->assertSame(12, $verschuiving->omhoog()->first()->risicoscore);
    }

    // --- De herbeoordelingstaken (§2.6c) -----------------------------------

    public function test_een_risico_dat_naar_rood_schuift_krijgt_een_eigen_herbeoordelingstaak(): void
    {
        $risico = Risico::factory()->beoordeeld(3, 4)->create([
            'risico_eigenaar_id' => $this->ciso->id,
            'volgende_beoordeling_gepland' => now()->addMonths(6),
        ]);

        $versie = $this->dienIn(10, 6);
        app(ActiveerRisicocriteria::class)($versie, 'Directie');

        $this->assertDatabaseHas('taken', [
            'soort' => 'risico-herbeoordeling-criteria',
            'gekoppeld_entiteit_type' => 'risico',
            'gekoppeld_entiteit_id' => $risico->id,
            'eigenaar_id' => $this->ciso->id,
        ]);

        // De veldgestuurde herbeoordeling blijft staan. Dezelfde soort gebruiken
        // zou die taak overschrijven, want de planner is idempotent op
        // (entiteit, soort) — precies de val waar deze test voor bestaat.
        $this->assertDatabaseHas('taken', [
            'soort' => 'risico-herbeoordeling',
            'gekoppeld_entiteit_id' => $risico->id,
        ]);
    }

    public function test_een_verruimde_drempel_plant_geen_taken(): void
    {
        Risico::factory()->beoordeeld(4, 4)->create(); // score 16, nu rood

        $versie = $this->dienIn(20, 10);
        app(ActiveerRisicocriteria::class)($versie, 'Directie');

        // Lichter wegen is een besluit dat hiermee genomen is; een taak zou
        // suggereren dat er nog iets moet gebeuren.
        $this->assertDatabaseMissing('taken', ['soort' => 'risico-herbeoordeling-criteria']);
    }

    // --- De meetreeks breekt (§8.2) ----------------------------------------

    public function test_de_drempelafhankelijke_kpi_krijgt_een_nieuwe_definitieversie(): void
    {
        $this->seed(KpiDefinitieSeeder::class);

        $kpi = KpiDefinitie::where('meetbron', 'risico_boven_drempel_met_plan')->firstOrFail();
        $voor = $kpi->definitie_versie;

        $versie = $this->dienIn(12);
        app(ActiveerRisicocriteria::class)($versie, 'Directie');

        // Zonder deze bump tekent het dashboard een verbetering die alleen een
        // verzette drempel is.
        $this->assertSame($voor + 1, $kpi->fresh()->definitie_versie);

        // Een KPI die niet op de drempel rekent blijft ongemoeid.
        $andere = KpiDefinitie::where('sleutel', 'context_binnen_herzieningstermijn')->first();
        if ($andere !== null) {
            $this->assertSame(1, $andere->fresh()->definitie_versie);
        }
    }

    // --- De herziening (§4.6) ----------------------------------------------

    public function test_de_actieve_versie_krijgt_een_herzieningstaak_en_de_vervangen_versie_niet(): void
    {
        $versie = $this->dienIn(12);
        app(ActiveerRisicocriteria::class)($versie, 'Directie');

        $this->assertDatabaseHas('taken', [
            'soort' => 'risicocriteria-herziening',
            'gekoppeld_entiteit_type' => 'risicocriteria_versie',
            'gekoppeld_entiteit_id' => $versie->id,
        ]);

        $volgende = $this->dienIn(14);
        app(ActiveerRisicocriteria::class)($volgende, 'Directie');

        // De vervangen versie is niet meer te herzien; die taak hoort weg.
        $this->assertDatabaseMissing('taken', [
            'soort' => 'risicocriteria-herziening',
            'gekoppeld_entiteit_id' => $versie->id,
        ]);
    }
}
