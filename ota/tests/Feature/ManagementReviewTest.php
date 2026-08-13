<?php

namespace Tests\Feature;

use App\Livewire\ManagementReviewOverzicht;
use App\Livewire\ReviewsessieDetail;
use App\Models\Agendapunt;
use App\Models\Besluit;
use App\Models\Gebruiker;
use App\Models\Reviewsessie;
use App\Models\Taak;
use App\Models\Verbeteractie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementReviewTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    /** De rol die de review afsluit — `goedkeuren`, zie implementatie/01c §4. */
    private Gebruiker $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->directeur = Gebruiker::factory()->metRol('Management')->create();
    }

    /** Een sessie met agendapunten voor alle negen (of de eerste $aantal) categorieën. */
    private function sessieMetCategorieen(int $aantal): Reviewsessie
    {
        $sessie = Reviewsessie::factory()->create(['status' => 'gepland']);

        foreach (array_slice(Reviewsessie::VERPLICHTE_CATEGORIEEN, 0, $aantal) as $categorie) {
            Agendapunt::factory()->create(['reviewsessie_id' => $sessie->id, 'categorie' => $categorie]);
        }

        return $sessie;
    }

    // --- Autorisatie (§8) --------------------------------------------------

    public function test_auditor_leest_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $sessie = Reviewsessie::factory()->create();

        $this->actingAs($auditor)->get('/management-review')->assertOk();

        Livewire::actingAs($auditor)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->set('besluitOmschrijving', 'Poging')
            ->call('voegBesluitToe')
            ->assertForbidden();
    }

    // --- §9.3-volledigheid (§4) --------------------------------------------

    public function test_review_niet_gehouden_zonder_alle_negen_onderwerpen(): void
    {
        $sessie = $this->sessieMetCategorieen(8);

        Livewire::actingAs($this->directeur)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->call('markeerGehouden')
            ->assertHasErrors('status');

        $this->assertSame('gepland', $sessie->fresh()->status);
    }

    public function test_review_gehouden_met_alle_negen_onderwerpen(): void
    {
        $sessie = $this->sessieMetCategorieen(9);

        Livewire::actingAs($this->directeur)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->call('markeerGehouden')
            ->assertHasNoErrors();

        $this->assertSame('gehouden', $sessie->fresh()->status);
    }

    // --- Functiescheiding rond het afsluiten (implementatie/01c §4) --------

    public function test_ciso_vult_de_agenda_maar_sluit_de_review_niet_af(): void
    {
        $sessie = $this->sessieMetCategorieen(9);

        Livewire::actingAs($this->ciso)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->call('markeerGehouden')
            ->assertForbidden();

        $this->assertSame('gepland', $sessie->fresh()->status);
    }

    public function test_management_sluit_af_maar_bewerkt_de_agenda_niet(): void
    {
        $sessie = $this->sessieMetCategorieen(9);

        Livewire::actingAs($this->directeur)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->set('besluitOmschrijving', 'Poging')
            ->call('voegBesluitToe')
            ->assertForbidden();
    }

    // --- Verbeteractie & herinneringstaak (§5) -----------------------------

    public function test_verbeteractie_plant_verzet_en_sluit_de_herinneringstaak(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Medewerker')->create();
        $besluit = Besluit::factory()->create();

        $actie = Verbeteractie::factory()->create([
            'besluit_id' => $besluit->id,
            'eigenaar_id' => $eigenaar->id,
            'deadline' => now()->addDays(10)->toDateString(),
            'status' => 'open',
        ]);

        // Gepland: één herinneringstaak op de deadline, op naam van de eigenaar.
        $this->assertDatabaseHas('taken', [
            'soort' => 'verbeteractie-herinnering',
            'eigenaar_id' => $eigenaar->id,
            'gekoppeld_entiteit_type' => 'verbeteractie',
            'gekoppeld_entiteit_id' => $actie->id,
            'status' => 'open',
        ]);

        // Verzet: dezelfde taak schuift mee, geen tweede erbij.
        $actie->update(['deadline' => now()->addDays(30)->toDateString()]);
        $this->assertSame(1, Taak::where('gekoppeld_entiteit_type', 'verbeteractie')
            ->where('gekoppeld_entiteit_id', $actie->id)->count());

        // Voltooid: de handeling is verricht, dus de taak sluit.
        $actie->update(['status' => 'voltooid', 'voltooid_op' => now()->toDateString()]);
        $this->assertSame('voltooid', Taak::where('gekoppeld_entiteit_type', 'verbeteractie')
            ->where('gekoppeld_entiteit_id', $actie->id)->first()->status);
    }

    public function test_lege_deadline_ruimt_de_herinnering_op(): void
    {
        $actie = Verbeteractie::factory()->create([
            'eigenaar_id' => Gebruiker::factory()->metRol('Medewerker')->create()->id,
            'deadline' => now()->addDays(5)->toDateString(),
            'status' => 'open',
        ]);

        $this->assertSame(1, Taak::where('gekoppeld_entiteit_id', $actie->id)
            ->where('gekoppeld_entiteit_type', 'verbeteractie')->count());

        $actie->update(['deadline' => null]);

        $this->assertSame(0, Taak::where('gekoppeld_entiteit_id', $actie->id)
            ->where('gekoppeld_entiteit_type', 'verbeteractie')
            ->whereIn('status', Taak::OPENSTAAND)->count());
    }

    // --- Terugkoppeling informatief (§6) -----------------------------------

    public function test_een_besluit_muteert_geen_ander_blok(): void
    {
        $sessie = Reviewsessie::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(ReviewsessieDetail::class, ['reviewsessie' => $sessie])
            ->set('besluitOmschrijving', 'Scope bijstellen naar nieuwe vestiging')
            ->call('voegBesluitToe')
            ->assertHasNoErrors();

        // Het besluit is vastgelegd, maar er ontstaat niets automatisch elders:
        // geen scopeversie, geen verbeteractie, geen taak.
        $this->assertDatabaseCount('besluiten', 1);
        $this->assertDatabaseCount('scope_verklaringen', 0);
        $this->assertDatabaseCount('verbeteracties', 0);
        $this->assertDatabaseCount('taken', 0);
    }

    // --- Rapportage (§11) --------------------------------------------------

    public function test_rapportage_signaleert_ontbrekende_review(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(ManagementReviewOverzicht::class)
            ->assertViewHas('reviewAchterstallig', true);

        Reviewsessie::factory()->create(['status' => 'gehouden', 'datum' => now()->toDateString()]);

        Livewire::actingAs($this->ciso)
            ->test(ManagementReviewOverzicht::class)
            ->assertViewHas('reviewAchterstallig', false);
    }

    // --- Audit trail -------------------------------------------------------

    public function test_reviewsessie_en_verbeteractie_komen_in_de_audit_trail(): void
    {
        $this->actingAs($this->ciso);

        $sessie = Reviewsessie::create(['datum' => now()->toDateString()]);
        $besluit = Besluit::factory()->create(['reviewsessie_id' => $sessie->id]);
        $actie = Verbeteractie::factory()->create(['besluit_id' => $besluit->id]);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'reviewsessie',
            'entiteit_id' => $sessie->id,
            'actie' => 'aangemaakt',
        ]);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'verbeteractie',
            'entiteit_id' => $actie->id,
            'actie' => 'aangemaakt',
        ]);
    }
}
