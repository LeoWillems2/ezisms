<?php

namespace Tests\Feature;

use App\Livewire\AuditProgrammaBeheer;
use App\Livewire\AuditrondeDetail;
use App\Livewire\Dekkingsmatrix;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Auditronde;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use Database\Seeders\AuditobjectClausuleSeeder;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditProgrammaTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Fase 1: audit-universe -------------------------------------------

    public function test_clausule_seeder_zet_de_clausules_met_groepen(): void
    {
        $this->seed(AuditobjectClausuleSeeder::class);

        $this->assertSame(23, Auditobject::where('soort', 'clausule')->count());
        $this->assertEqualsCanonicalizing(
            ['4 Context', '5 Leiderschap', '6 Planning', '7 Ondersteuning', '8 Uitvoering', '9 Evaluatie', '10 Verbetering'],
            Auditobject::where('soort', 'clausule')->distinct()->pluck('groep')->all(),
        );
    }

    public function test_sync_maakt_maatregel_object_zonder_normtekst(): void
    {
        $maatregel = Maatregel::factory()->create(['thema' => 'technologisch', 'naam' => 'Beheer van kwetsbaarheden']);
        $maatregel->soaRegel()->create(['van_toepassing' => true]);

        $this->artisan('isms:sync-auditobjecten')->assertSuccessful();

        $object = Auditobject::where('soort', 'maatregel')->where('maatregel_id', $maatregel->id)->firstOrFail();
        // Geen kopie van de normtekst: de titel blijft leeg, de omschrijving komt
        // uit de relatie.
        $this->assertNull($object->titel);
        $this->assertSame('Beheer van kwetsbaarheden', $object->omschrijving());
        $this->assertTrue($object->actief);
    }

    public function test_sync_deactiveert_controls_die_niet_meer_van_toepassing_zijn(): void
    {
        $maatregel = Maatregel::factory()->create();
        $regel = $maatregel->soaRegel()->create(['van_toepassing' => true]);

        $this->artisan('isms:sync-auditobjecten')->assertSuccessful();
        $this->assertTrue(Auditobject::where('maatregel_id', $maatregel->id)->value('actief'));

        // Niet meer van toepassing → object inactief, niet verwijderd.
        $regel->update(['van_toepassing' => false]);
        $this->artisan('isms:sync-auditobjecten')->assertSuccessful();

        $object = Auditobject::where('maatregel_id', $maatregel->id)->firstOrFail();
        $this->assertFalse($object->actief);
    }

    // --- Fase 2: programma + dekkingsplanning ------------------------------

    public function test_ciso_maakt_een_programma(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('naam', 'Interne auditcyclus 2026–2028')
            ->set('startDatum', '2026-01-01')
            ->set('aantalJaren', '3')
            ->call('slaOp')
            ->assertHasNoErrors();

        // Op het model en niet met assertDatabaseHas: een date-cast schrijft
        // driverafhankelijk '2026-01-01' of '2026-01-01 00:00:00' weg.
        $programma = Auditprogramma::firstOrFail();
        $this->assertSame('Interne auditcyclus 2026–2028', $programma->naam);
        $this->assertSame('2026-01-01', $programma->start_datum->toDateString());
        $this->assertSame(3, $programma->aantal_jaren);
        $this->assertSame('concept', $programma->status);
        $this->assertSame('certificeringscyclus', $programma->aard);
    }

    public function test_auditor_mag_geen_programma_muteren(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        Livewire::actingAs($auditor)
            ->test(AuditProgrammaBeheer::class)
            ->call('nieuw')
            ->assertForbidden();
    }

    /**
     * Sinds plan 11c bepaalt het kalenderjaar van een plan niet meer of het bij
     * de cyclus mag horen — het plan krijgt het eerstvolgende vrije
     * programmajaar. Wat blijft begrenzen is de lengte van de cyclus.
     */
    public function test_jaarplan_koppelen_vult_het_eerstvolgende_programmajaar(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2027-05-14', 'aantal_jaren' => 2]);
        // Een jaartal dat niets met het venster te maken heeft: dat mag nu.
        $eerste = Auditplan::factory()->create(['jaar' => 2019]);
        $tweede = Auditplan::factory()->create(['jaar' => 2030]);
        $derde = Auditplan::factory()->create(['jaar' => 2031]);

        $component = Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('geselecteerdId', $programma->id);

        $component->call('koppelPlan', $eerste->id)->assertHasNoErrors();
        $component->call('koppelPlan', $tweede->id)->assertHasNoErrors();

        $this->assertSame(1, $eerste->fresh()->programmajaar);
        $this->assertSame('2027-05-14', $eerste->fresh()->periode_start->toDateString());
        $this->assertSame('2028-05-13', $eerste->fresh()->periode_eind->toDateString());
        $this->assertSame(2, $tweede->fresh()->programmajaar);

        // De cyclus telt twee programmajaren; een derde plan past niet.
        $component->call('koppelPlan', $derde->id)->assertStatus(422);
        $this->assertNull($derde->fresh()->auditprogramma_id);
    }

    public function test_ontkoppelen_haalt_ook_de_verankering_weg(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();

        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('geselecteerdId', $programma->id)
            ->call('ontkoppelPlan', $plan->id);

        $plan->refresh();
        $this->assertNull($plan->auditprogramma_id);
        $this->assertNull($plan->programmajaar);
        $this->assertNull($plan->periode_start);
    }

    public function test_vul_standaardplanning_plant_alle_actieve_objecten(): void
    {
        $this->seed(AuditobjectClausuleSeeder::class); // 23 actieve clausules
        Auditobject::factory()->inactief()->create(); // telt niet mee
        $programma = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);

        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('geselecteerdId', $programma->id)
            ->call('vulStandaardplanning');

        // Eén dekkingsregel per actief object, default interval = cycluslengte.
        $this->assertSame(23, $programma->dekkingen()->count());
        $this->assertSame(3, $programma->dekkingen()->first()->interval_jaren);
    }

    public function test_stel_interval_slaat_de_frequentie_per_object_op(): void
    {
        $object = Auditobject::factory()->create();
        $programma = Auditprogramma::factory()->create(['start_datum' => '2026-01-01', 'aantal_jaren' => 3]);

        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('geselecteerdId', $programma->id)
            ->call('stelInterval', $object->id, 1);

        $this->assertDatabaseHas('auditprogramma_dekkingen', [
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $object->id,
            'interval_jaren' => 1,
        ]);
    }

    public function test_programma_aanmaken_komt_in_de_audit_trail(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->set('naam', 'Cyclus')
            ->set('startDatum', '2026-01-01')
            ->set('aantalJaren', '3')
            ->call('slaOp');

        $this->assertDatabaseHas('audit_logregels', [
            'blok_naam' => 'auditmanagement',
            'entiteit_type' => 'auditprogramma',
            'actie' => 'aangemaakt',
        ]);
    }

    // --- Fase 3: ronde-koppeling + dekkingsmatrix --------------------------

    public function test_ronde_dekt_geselecteerde_auditobjecten(): void
    {
        $object = Auditobject::factory()->create();
        $ronde = Auditronde::factory()->create(['type' => 'intern', 'status' => 'gepland']);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set('scopeObjecten', [$object->id])
            ->call('slaPlanningOp')
            ->assertHasNoErrors();

        $this->assertTrue($ronde->auditobjecten()->where('auditobjecten.id', $object->id)->exists());
    }

    public function test_ronde_scope_toont_gekozen_bovenaan_en_overige_onder_uitklap(): void
    {
        $gekozen = Auditobject::factory()->create(['clausule_nummer' => '9.2', 'titel' => 'Interne audit']);
        $overig = Auditobject::factory()->create(['clausule_nummer' => '4.1', 'titel' => 'Context bepalen']);
        $ronde = Auditronde::factory()->create(['type' => 'intern', 'status' => 'gepland']);
        $ronde->auditobjecten()->attach($gekozen->id);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            // Beide takken renderen: gekozen bovenaan, overige in de uitklap.
            ->assertSee('9.2 Interne audit')
            ->assertSee('Context bepalen')
            ->assertSee('overige controls');
    }

    public function test_dekkingsmatrix_toont_uitgevoerd_en_gat(): void
    {
        // Cyclus in het verleden zodat een niet-uitgevoerd gepland jaar een 'gat' is.
        $programma = Auditprogramma::factory()->create(['start_datum' => '2024-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();

        $gedekt = Auditobject::factory()->create();
        $gat = Auditobject::factory()->create();

        // 'gat' is gepland (interval 1 vanaf 2024) maar nergens uitgevoerd.
        AuditprogrammaDekking::create([
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $gat->id,
            'interval_jaren' => 1,
            'gepland_start_programmajaar' => 1,
        ]);

        // 'gedekt' door een afgeronde ronde in 2024.
        $ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id, 'type' => 'intern',
            'status' => 'afgerond', 'uitgevoerd_op' => '2024-05-01',
        ]);
        $ronde->auditobjecten()->attach($gedekt->id);

        $component = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id);

        $cellen = $component->viewData('cellen');
        $this->assertSame('uitgevoerd', $cellen[$gedekt->id][1]);
        $this->assertSame('gat', $cellen[$gat->id][1]);

        $kpi = $component->viewData('kpi');
        $this->assertSame(1, $kpi['gedekt']);   // alleen 'gedekt' telt
        $this->assertSame(2, $kpi['totaal']);
    }

    public function test_kpi_telt_alleen_afgeronde_rondes(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2024-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();
        $object = Auditobject::factory()->create();

        // Nog niet afgerond → mag niet als dekking tellen.
        $ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id, 'type' => 'intern',
            'status' => 'in_uitvoering', 'uitgevoerd_op' => null,
        ]);
        $ronde->auditobjecten()->attach($object->id);

        $kpi = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id)
            ->viewData('kpi');

        $this->assertSame(0, $kpi['gedekt']);
    }

    public function test_auditor_leest_de_matrix(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/audits/dekking')->assertOk();
        $this->actingAs($auditor)->get('/audits/programma')->assertOk();
    }

    // --- Plan 11c fase 1: nulmeting en dekkingsvlag ------------------------

    /**
     * De kern van fase 1: uitgevoerd is niet hetzelfde als dekkend. Zonder dit
     * onderscheid kleurt één nulmeting de hele matrix groen in jaar 1 en zien
     * jaar 2 en 3 er overbodig uit — het omgekeerde van wat §9.2.2 wil tonen.
     */
    public function test_niet_dekkende_ronde_kleurt_de_matrix_niet(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2024-01-01', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();
        $object = Auditobject::factory()->create();

        AuditprogrammaDekking::create([
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $object->id,
            'interval_jaren' => 1,
            'gepland_start_programmajaar' => 1,
        ]);

        // Volwaardig uitgevoerd, maar bewust buiten de dekking gehouden.
        $ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id,
            'type' => 'intern_nulmeting',
            'telt_mee_voor_dekking' => false,
            'status' => 'afgerond',
            'uitgevoerd_op' => '2024-05-01',
        ]);
        $ronde->auditobjecten()->attach($object->id);

        $component = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id);

        $this->assertSame('gat', $component->viewData('cellen')[$object->id][1]);
        $this->assertSame(0, $component->viewData('kpi')['gedekt']);
    }

    public function test_nulmeting_krijgt_de_dekkingsvlag_automatisch_uit(): void
    {
        $plan = Auditplan::factory()->create();

        $nulmeting = Auditronde::create(['auditplan_id' => $plan->id, 'type' => 'intern_nulmeting']);
        $gewoon = Auditronde::create(['auditplan_id' => $plan->id, 'type' => 'intern']);

        $this->assertFalse($nulmeting->telt_mee_voor_dekking);
        $this->assertTrue($gewoon->telt_mee_voor_dekking);
    }

    /** De automaat is een default, geen wet: wie de vlag meegeeft, wint. */
    public function test_expliciete_dekkingsvlag_wint_van_de_automaat(): void
    {
        $plan = Auditplan::factory()->create();

        $ronde = Auditronde::create([
            'auditplan_id' => $plan->id,
            'type' => 'intern_nulmeting',
            'telt_mee_voor_dekking' => true,
        ]);

        $this->assertTrue($ronde->fresh()->telt_mee_voor_dekking);
    }

    /**
     * Een nulmeting ís een interne audit: dezelfde auditor-guards uit blok 11 §4.
     * Zonder dit zou de CISO de status van zijn eigen nulmeting kunnen doorzetten.
     */
    public function test_nulmeting_valt_onder_dezelfde_auditor_guards(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $plan = Auditplan::factory()->create();
        $ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id,
            'type' => 'intern_nulmeting',
            'auditor_gebruiker_id' => $auditor->id,
            'status' => 'gepland',
        ]);

        $this->assertTrue($ronde->isIntern());
        $this->assertTrue($ronde->magUitvoerenDoor($auditor));
        $this->assertFalse($ronde->magUitvoerenDoor($this->ciso));

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('startUitvoering');

        $this->assertSame('in_uitvoering', $ronde->fresh()->status);
    }

    /**
     * De vlag mag nooit een verstopmechanisme worden (plan 11c §8): alleen de
     * dekkingsmatrix laat de ronde buiten beschouwing, het dossier niet.
     */
    public function test_niet_dekkende_ronde_blijft_volledig_zichtbaar(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = Auditronde::factory()->create([
            'type' => 'intern_nulmeting',
            'telt_mee_voor_dekking' => false,
            'auditor_gebruiker_id' => $auditor->id,
            'status' => 'in_uitvoering',
        ]);
        $ronde->bevindingen()->create([
            'type' => 'non_conformiteit_minor',
            'omschrijving' => 'Geen bewijs van een uitgevoerde hersteltest',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->assertSee('Geen bewijs van een uitgevoerde hersteltest')
            ->assertSee('Interne nulmeting')
            ->assertSee('Telt niet mee');
    }

    // --- Plan 11c fase 2: programmajaar los van kalenderjaar ---------------

    public function test_programmajaren_volgen_de_startdatum_en_niet_het_kalenderjaar(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2027-05-14', 'aantal_jaren' => 3]);

        $jaren = $programma->programmajaren();

        $this->assertSame('2027-05-14', $jaren[0]['start']->toDateString());
        $this->assertSame('2028-05-13', $jaren[0]['eind']->toDateString());
        $this->assertSame('2028-05-14', $jaren[1]['start']->toDateString());
        $this->assertSame('2030-05-13', $programma->eindDatum()->toDateString());
    }

    /**
     * De kern van fase 2: een ronde van 2 januari hoort bij het programmajaar dat
     * het jaar ervóór begon. Op `uitgevoerd_op->year` zou hij in een kolom vallen
     * die niet bestaat, of in de verkeerde.
     */
    public function test_ronde_valt_in_het_programmajaar_en_niet_in_het_kalenderjaar(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2027-05-14', 'aantal_jaren' => 3]);
        $plan = Auditplan::factory()->voorProgramma($programma)->create();
        $object = Auditobject::factory()->create();

        $ronde = Auditronde::factory()->create([
            'auditplan_id' => $plan->id,
            'type' => 'intern',
            'status' => 'afgerond',
            'uitgevoerd_op' => '2028-01-02',
        ]);
        $ronde->auditobjecten()->attach($object->id);

        $cellen = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id)
            ->viewData('cellen');

        $this->assertSame('uitgevoerd', $cellen[$object->id][1]);
        $this->assertNotSame('uitgevoerd', $cellen[$object->id][2]);
    }

    /**
     * De oude globale unique op `auditplannen.jaar` maakte dit onmogelijk, en
     * juist in de opstartfase liggen er meerdere plannen in hetzelfde jaar.
     */
    public function test_twee_programmas_mogen_hetzelfde_kalenderjaar_gebruiken(): void
    {
        $een = Auditprogramma::factory()->create(['start_datum' => '2027-01-01', 'aantal_jaren' => 1]);
        $twee = Auditprogramma::factory()->create(['start_datum' => '2027-06-01', 'aantal_jaren' => 1]);

        Auditplan::factory()->voorProgramma($een)->create();
        Auditplan::factory()->voorProgramma($twee)->create();

        $this->assertSame(2, Auditplan::where('jaar', 2027)->count());
    }

    public function test_twee_plannen_met_hetzelfde_programmajaar_worden_geweigerd(): void
    {
        $programma = Auditprogramma::factory()->create(['start_datum' => '2027-01-01', 'aantal_jaren' => 3]);
        Auditplan::factory()->voorProgramma($programma, 1)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        Auditplan::factory()->voorProgramma($programma, 1)->create();
    }

    public function test_gat_verschijnt_pas_als_het_programmajaar_voorbij_is(): void
    {
        // Jaar 1 is verstreken, jaar 2 loopt nu: alleen jaar 1 mag een gat tonen.
        $programma = Auditprogramma::factory()->create([
            'start_datum' => now()->subMonths(14)->toDateString(),
            'aantal_jaren' => 3,
        ]);
        $object = Auditobject::factory()->create();

        AuditprogrammaDekking::create([
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $object->id,
            'interval_jaren' => 1,
            'gepland_start_programmajaar' => 1,
        ]);

        $cellen = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id)
            ->viewData('cellen');

        $this->assertSame('gat', $cellen[$object->id][1]);
        $this->assertSame('gepland', $cellen[$object->id][2]);
        $this->assertSame('gepland', $cellen[$object->id][3]);
    }

    // --- Plan 11c fase 3: het voorbereidingsprogramma ----------------------

    public function test_voorbereidingsprogramma_toont_geen_gaten(): void
    {
        $programma = Auditprogramma::factory()->create([
            'start_datum' => '2020-01-01',
            'aantal_jaren' => 1,
            'aard' => 'voorbereiding',
        ]);
        $object = Auditobject::factory()->create();

        AuditprogrammaDekking::create([
            'auditprogramma_id' => $programma->id,
            'auditobject_id' => $object->id,
            'interval_jaren' => 1,
            'gepland_start_programmajaar' => 1,
        ]);

        $cellen = Livewire::actingAs($this->ciso)
            ->test(Dekkingsmatrix::class)
            ->set('programmaId', $programma->id)
            ->viewData('cellen');

        // Ruim in het verleden en niet uitgevoerd — bij een cyclus was dit een
        // gat. De opstartfase hóórt gaten te hebben; dat is de uitkomst van de
        // nulmeting, geen tekortkoming in de planning.
        $this->assertSame('gepland', $cellen[$object->id][1]);
    }

    public function test_voorbereiding_afsluiten_komt_in_de_audit_trail(): void
    {
        $programma = Auditprogramma::factory()->create([
            'start_datum' => '2026-01-01',
            'aard' => 'voorbereiding',
            'status' => 'actief',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AuditProgrammaBeheer::class)
            ->call('sluitAf', $programma->id);

        $this->assertSame('afgesloten', $programma->fresh()->status);
        $this->assertDatabaseHas('audit_logregels', [
            'blok_naam' => 'auditmanagement',
            'entiteit_type' => 'auditprogramma',
            'entiteit_id' => $programma->id,
            'actie' => 'status_gewijzigd',
        ]);
    }

    /**
     * §9: de vlag is een planningsbeslissing van de CISO. Een auditor mag zijn
     * eigen ronde niet uit de dekkingsmatrix schrijven.
     */
    public function test_alleen_muteren_zet_de_dekkingsvlag_om(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = Auditronde::factory()->create([
            'type' => 'intern',
            'auditor_gebruiker_id' => $auditor->id,
            'status' => 'afgerond',
            'uitgevoerd_op' => '2026-05-01',
        ]);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('wisselDekkingsvlag')
            ->assertForbidden();

        $this->assertTrue($ronde->fresh()->telt_mee_voor_dekking);

        // De CISO mag het wél, ook nadat de ronde is afgerond.
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('wisselDekkingsvlag');

        $this->assertFalse($ronde->fresh()->telt_mee_voor_dekking);
    }
}
