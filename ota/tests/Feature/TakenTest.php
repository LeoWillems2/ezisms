<?php

namespace Tests\Feature;

use App\Livewire\TaaksjablonenOverzicht;
use App\Livewire\TakenOverzicht;
use App\Models\Asset;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Models\Taak;
use App\Models\Taaksjabloon;
use App\Models\Toetsopdracht;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TakenTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_auditor_krijgt_exporteren_maar_geen_muteerrecht(): void
    {
        // Het besluit uit implementatie/07 §8: exporteren maakt de record-scope
        // positief te bepalen, zonder de Auditor muteerrechten te geven.
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->assertTrue($auditor->can('heeft-niveau', ['taken-workflow-engine', 'exporteren']));
        $this->assertTrue($auditor->can('heeft-niveau', ['taken-workflow-engine', 'lezen']));
        $this->assertFalse($auditor->can('heeft-niveau', ['taken-workflow-engine', 'muteren']));
        $this->assertFalse($auditor->can('heeft-niveau', ['taken-workflow-engine', 'goedkeuren']));
    }

    public function test_medewerker_ziet_alleen_eigen_taken_auditor_ziet_alles(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        Taak::factory()->create(['titel' => 'Mijn eigen taak', 'eigenaar_id' => $medewerker->id]);
        Taak::factory()->create(['titel' => 'Taak van de CISO', 'eigenaar_id' => $this->ciso->id]);

        Livewire::actingAs($medewerker)
            ->test(TakenOverzicht::class)
            ->assertSee('Mijn eigen taak')
            ->assertDontSee('Taak van de CISO');

        Livewire::actingAs($auditor)
            ->test(TakenOverzicht::class)
            ->assertSee('Mijn eigen taak')
            ->assertSee('Taak van de CISO');
    }

    public function test_medewerker_mag_andermans_taak_niet_voltooien(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $taak = Taak::factory()->create(['eigenaar_id' => $this->ciso->id]);

        try {
            Livewire::actingAs($medewerker)
                ->test(TakenOverzicht::class)
                ->call('voltooien', $taak->id);
            $this->fail('Andermans taak had buiten de scope moeten vallen.');
        } catch (ModelNotFoundException) {
            // Bewust een 404 en geen 403: een medewerker hoort niet te kunnen
            // afleiden dát er een taak met dit id bestaat.
        }

        $this->assertSame('open', $taak->fresh()->status);
    }

    public function test_medewerker_mag_zijn_eigen_taak_wel_voltooien(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $taak = Taak::factory()->create(['eigenaar_id' => $medewerker->id]);

        Livewire::actingAs($medewerker)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $taak->id);

        $this->assertSame('voltooid', $taak->fresh()->status);
    }

    // --- Levenscyclus ------------------------------------------------------

    public function test_losse_taak_aanmaken_en_voltooien(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('nieuweTaak')
            ->set('titel', 'Offerte pentest opvragen')
            ->set('eigenaarId', $this->ciso->id)
            ->set('deadline', now()->addDays(10)->format('Y-m-d'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $taak = Taak::firstOrFail();
        // Losse taak: geen sjabloon, en dat mag naast andere losse taken.
        $this->assertNull($taak->taaksjabloon_id);
        $this->assertSame('open', $taak->status);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('oppakken', $taak->id);
        $this->assertSame('in_uitvoering', $taak->fresh()->status);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $taak->id);

        $taak->refresh();
        $this->assertSame('voltooid', $taak->status);
        $this->assertNotNull($taak->voltooid_op);
    }

    public function test_twee_losse_taken_met_dezelfde_deadline_mogen(): void
    {
        // NULL botst niet in de unique-index; die constraint geldt alleen voor
        // taken uit een sjabloon.
        Taak::factory()->count(2)->create(['deadline' => now()->addWeek()]);

        $this->assertDatabaseCount('taken', 2);
    }

    public function test_verlopen_en_escaleren(): void
    {
        $net = Taak::factory()->verstreken(1)->create();
        $lang = Taak::factory()->verstreken(20)->create();
        $toekomst = Taak::factory()->create();

        $this->artisan('isms:verloop-taken')->assertSuccessful();

        $this->assertSame('verlopen', $net->fresh()->status);
        $this->assertSame(1, $net->fresh()->escalatie_niveau);
        $this->assertSame(2, $lang->fresh()->escalatie_niveau);
        $this->assertSame('open', $toekomst->fresh()->status);
    }

    public function test_verlopen_wordt_gelogd_in_de_audit_trail(): void
    {
        // Regressie op de massa-update-valkuil: updateGeaudit, niet update.
        $taak = Taak::factory()->verstreken()->create();

        $this->artisan('isms:verloop-taken')->assertSuccessful();

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'taak',
            'entiteit_id' => $taak->id,
            'blok_naam' => 'taken-workflow-engine',
        ]);
    }

    public function test_vertraging_wordt_berekend(): void
    {
        $taak = Taak::factory()->verstreken(5)->create(['eigenaar_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $taak->id);

        $this->assertSame(5, $taak->fresh()->vertragingInDagen());
    }

    public function test_ciso_kan_gewone_voltooide_taak_heropenen(): void
    {
        $taak = Taak::factory()->create([
            'eigenaar_id' => $this->ciso->id,
            'status' => 'voltooid',
            'voltooid_op' => now()->subDay(),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('heropenen', $taak->id);

        $taak->refresh();
        $this->assertSame('open', $taak->status);
        $this->assertNull($taak->voltooid_op);

        // De vorige voltooiing blijft herleidbaar in de append-only audit trail.
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'taak',
            'entiteit_id' => $taak->id,
            'blok_naam' => 'taken-workflow-engine',
        ]);
    }

    public function test_medewerker_mag_niet_heropenen(): void
    {
        // Heropenen is een toezichtsactie (muteren): zelfs op zijn eigen taak
        // mag de eigenaar zijn afmelding niet terugdraaien.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $taak = Taak::factory()->create([
            'eigenaar_id' => $medewerker->id,
            'status' => 'voltooid',
            'voltooid_op' => now(),
        ]);

        Livewire::actingAs($medewerker)
            ->test(TakenOverzicht::class)
            ->call('heropenen', $taak->id)
            ->assertForbidden();

        $this->assertSame('voltooid', $taak->fresh()->status);
    }

    public function test_beheerde_taak_is_niet_heropenbaar(): void
    {
        // De voltooiing van een beheerde taak is een afgeleide van het bronblok;
        // heropenen zou de observer toch overrulen. Dus geweigerd (422).
        Risico::factory()->create([
            'volgende_beoordeling_gepland' => now()->addMonths(3),
            'risico_eigenaar_id' => $this->ciso->id,
        ]);
        $taak = Taak::where('soort', 'risico-herbeoordeling')->firstOrFail();
        $taak->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertFalse($taak->isHeropenbaar());

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('heropenen', $taak->id)
            ->assertStatus(422);

        $this->assertSame('voltooid', $taak->fresh()->status);
    }

    public function test_toetstaak_is_niet_heropenbaar(): void
    {
        // Voltooid komt uit de callback bij een geslaagde toets; die uitslag
        // blijft staan, dus de taak terugzetten mag niet.
        $opdracht = Toetsopdracht::factory()->create();
        $taak = $opdracht->taak;
        $taak->update([
            'eigenaar_id' => $this->ciso->id,
            'status' => 'voltooid',
            'voltooid_op' => now(),
        ]);

        $this->assertFalse($taak->fresh()->isHeropenbaar());

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('heropenen', $taak->id)
            ->assertStatus(422);

        $this->assertSame('voltooid', $taak->fresh()->status);
    }

    public function test_feitelijk_verlopen_hangt_niet_van_de_cron_af(): void
    {
        // De scheduler heeft nog niet gedraaid: status is 'open', maar de UI
        // mag dat niet als "op tijd" tonen.
        $taak = Taak::factory()->verstreken()->create();

        $this->assertSame('open', $taak->status);
        $this->assertTrue($taak->isFeitelijkVerlopen());
    }

    // --- Genereren uit sjablonen ------------------------------------------

    public function test_sjabloon_genereert_een_taak_en_is_idempotent(): void
    {
        $sjabloon = Taaksjabloon::factory()->create(['herhaling' => 'jaarlijks', 'aanmaken_dagen_vooraf' => 14]);

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertSame(1, $sjabloon->taken()->count());
    }

    public function test_inactief_sjabloon_genereert_niets(): void
    {
        Taaksjabloon::factory()->create(['actief' => false]);

        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertDatabaseCount('taken', 0);
    }

    // --- Aansluiting van blok 2, 3 en 4 -----------------------------------

    public function test_risico_met_herbeoordelingsdatum_krijgt_een_taak(): void
    {
        $risico = Risico::factory()->create([
            'titel' => 'Uitval fileserver',
            'volgende_beoordeling_gepland' => now()->addMonths(3),
            'risico_eigenaar_id' => $this->ciso->id,
        ]);

        $taak = Taak::where('soort', 'risico-herbeoordeling')->firstOrFail();
        $this->assertSame('Risico herbeoordelen: Uitval fileserver', $taak->titel);
        $this->assertSame($risico->id, $taak->gekoppeld_entiteit_id);
        $this->assertSame('risico', $taak->gekoppeld_entiteit_type);
        $this->assertSame($this->ciso->id, $taak->eigenaar_id);

        // Datum verzetten verplaatst de taak in plaats van er een tweede bij
        // te zetten.
        $risico->update(['volgende_beoordeling_gepland' => now()->addMonths(6)]);
        $this->assertSame(1, Taak::where('soort', 'risico-herbeoordeling')->count());
        $this->assertTrue($taak->fresh()->deadline->isSameDay(now()->addMonths(6)));

        // Datum leegmaken ruimt de taak op.
        $risico->update(['volgende_beoordeling_gepland' => null]);
        $this->assertSame(0, Taak::where('soort', 'risico-herbeoordeling')->count());
    }

    public function test_actieve_scopeversie_krijgt_een_herzieningstaak(): void
    {
        $versie = ScopeVerklaring::factory()->create([
            'status' => 'actief',
            'volgende_herziening_gepland' => now()->addYear(),
        ]);

        $this->assertSame(1, Taak::where('soort', 'scope-herziening')->count());

        // Vervangen versie hoeft geen herinnering meer.
        $versie->update(['status' => 'vervangen']);
        $this->assertSame(0, Taak::where('soort', 'scope-herziening')->count());
    }

    public function test_achterstallige_retour_en_soa_beoordeling_worden_gesignaleerd(): void
    {
        $asset = Asset::factory()->create(['naam' => 'Laptop 42']);
        $asset->toewijzingen()->create([
            'gebruiker_id' => Gebruiker::factory()->create()->id,
            'toegewezen_op' => now()->subMonths(18),
        ]);

        $maatregel = Maatregel::factory()->metSoaRegel()->create();
        $maatregel->soaRegel->update([
            'van_toepassing' => true,
            'motivatie' => 'Nodig.',
            'laatst_beoordeeld_op' => now()->subMonths(18),
        ]);

        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertDatabaseHas('taken', [
            'soort' => 'asset-retourcontrole',
            'gekoppeld_entiteit_type' => 'asset',
            'gekoppeld_entiteit_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('taken', [
            'soort' => 'soa-herbeoordeling',
            'gekoppeld_entiteit_type' => 'soa_regel',
            'gekoppeld_entiteit_id' => $maatregel->soaRegel->id,
        ]);

        // Tweede run levert geen duplicaten op.
        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $this->assertSame(1, Taak::where('soort', 'asset-retourcontrole')->count());
    }

    // --- Sjabloonbeheer ----------------------------------------------------

    public function test_aangepaste_herhaling_vereist_een_interval(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(TaaksjablonenOverzicht::class)
            ->call('nieuwSjabloon')
            ->set('naam', 'Maandelijkse check')
            ->set('herhaling', 'aangepast')
            ->set('intervalDagen', null)
            ->call('opslaan')
            ->assertHasErrors(['intervalDagen' => 'required']);
    }

    // --- Bewerken, eigenaarschap en de koppelingskolom ---------------------

    public function test_losse_taak_zonder_eigenaar_wordt_geweigerd(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('nieuweTaak')
            ->set('titel', 'Iets doen')
            // Lege keuzelijst = '' (getypte string-property), niet null.
            ->set('eigenaarId', '')
            ->call('opslaan')
            ->assertHasErrors(['eigenaarId' => 'required']);

        $this->assertDatabaseCount('taken', 0);
    }

    public function test_losse_taak_bewerken(): void
    {
        $ander = Gebruiker::factory()->create();
        $taak = Taak::factory()->create([
            'titel' => 'Typfaut',
            'eigenaar_id' => $this->ciso->id,
            'deadline' => now()->addDays(5),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            ->set('titel', 'Typefout hersteld')
            ->set('eigenaarId', $ander->id)
            ->set('deadline', now()->addDays(30)->format('Y-m-d'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $taak->refresh();
        $this->assertSame('Typefout hersteld', $taak->titel);
        $this->assertSame($ander->id, $taak->eigenaar_id);
        $this->assertTrue($taak->deadline->isSameDay(now()->addDays(30)));
    }

    public function test_deadline_van_een_beheerde_taak_is_niet_te_wijzigen(): void
    {
        // De deadline komt uit het risico; hem hier verzetten zou de volgende
        // observer-run toch overschrijven, dus het formulier negeert hem.
        $risico = Risico::factory()->create([
            'volgende_beoordeling_gepland' => now()->addMonths(3),
            'risico_eigenaar_id' => $this->ciso->id,
        ]);
        $taak = Taak::where('soort', 'risico-herbeoordeling')->firstOrFail();
        $origineel = $taak->deadline->copy();

        $this->assertTrue($taak->deadlineWordtBeheerd());

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            ->set('titel', 'Eigen titel')
            ->set('deadline', now()->addDays(2)->format('Y-m-d'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $taak->refresh();
        $this->assertSame('Eigen titel', $taak->titel);
        $this->assertTrue($taak->deadline->isSameDay($origineel));
    }

    public function test_handmatig_toegewezen_eigenaar_overleeft_een_herplanning(): void
    {
        $risico = Risico::factory()->create(['volgende_beoordeling_gepland' => now()->addMonths(3)]);
        $taak = Taak::where('soort', 'risico-herbeoordeling')->firstOrFail();
        $this->assertNull($taak->eigenaar_id);

        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            ->set('eigenaarId', $medewerker->id)
            ->call('opslaan')
            ->assertHasNoErrors();

        // Datum verzetten in het bronblok verplaatst de taak, maar mag de
        // handmatig gekozen eigenaar niet wegvagen.
        $risico->update(['volgende_beoordeling_gepland' => now()->addMonths(9)]);

        $taak->refresh();
        $this->assertSame($medewerker->id, $taak->eigenaar_id);
        $this->assertTrue($taak->deadline->isSameDay(now()->addMonths(9)));
    }

    public function test_koppelingskolom_toont_de_entiteit_of_anders_het_blok(): void
    {
        $risico = Risico::factory()->create([
            'titel' => 'Uitval fileserver',
            'volgende_beoordeling_gepland' => now()->addMonth(),
        ]);
        $entiteitTaak = Taak::where('soort', 'risico-herbeoordeling')->firstOrFail();

        // Entiteit: naam, niet alleen het type.
        $this->assertSame('Risico: Uitval fileserver', $entiteitTaak->gekoppeldOmschrijving());

        // Sjabloontaak: geen entiteit, wel een blok — dat hoort niet als
        // streepje te verdwijnen.
        $sjabloonTaak = Taak::factory()->create([
            'gekoppeld_blok_naam' => 'context-scope',
            'eigenaar_id' => $this->ciso->id,
        ]);
        $this->assertSame('context-scope', $sjabloonTaak->gekoppeldOmschrijving());

        // Losse taak zonder beide: dan pas een streepje.
        $los = Taak::factory()->create(['eigenaar_id' => $this->ciso->id]);
        $this->assertNull($los->gekoppeldOmschrijving());
    }

    public function test_lege_eigenaar_correspondeert_met_een_bestaande_optie(): void
    {
        // De kern van de select-bug: bindt het formulier aan null, dan komt de
        // state met geen enkele <option> overeen en toont de browser de eerste
        // echte optie terwijl er niets gekozen is. Een lege string matcht wél
        // de lege optie.
        $taak = Taak::factory()->create(['eigenaar_id' => null]);

        $component = Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            ->assertSet('eigenaarId', '');

        $this->assertIsString($component->get('eigenaarId'));

        // En opslaan zonder keuze faalt zichtbaar in plaats van stil de eerste
        // gebruiker te pakken.
        $component->call('opslaan')->assertHasErrors(['eigenaarId' => 'required']);
        $this->assertNull($taak->fresh()->eigenaar_id);
    }

    public function test_bestaande_eigenaar_wordt_als_string_geladen(): void
    {
        $taak = Taak::factory()->create(['eigenaar_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('bewerk', $taak->id)
            // Als string, want de option-waarden in de HTML zijn ook strings.
            ->assertSet('eigenaarId', (string) $this->ciso->id);
    }
}
