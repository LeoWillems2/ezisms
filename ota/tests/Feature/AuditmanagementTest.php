<?php

namespace Tests\Feature;

use App\Livewire\AuditrondeDetail;
use App\Livewire\AuditsOverzicht;
use App\Livewire\BevindingenOverzicht;
use App\Models\Afwijking;
use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Support\Schermkopie;
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

    /** Een actief auditobject om een bevinding aan te hangen (plan 11d). */
    private function auditobject(string $nummer = '9.2'): Auditobject
    {
        return Auditobject::factory()->create(['clausule_nummer' => $nummer, 'titel' => 'Interne audit']);
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
        $object = $this->auditobject();

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set([
                'bevindingType' => 'non_conformiteit_minor',
                'bevindingOmschrijving' => 'Toegangsrechten niet periodiek herzien.',
                'bevindingAuditobjectId' => (string) $object->id,
                'bevindingBron' => (string) $auditor->id,
            ])
            ->call('slaBevindingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bevindingen', [
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_minor',
            'status' => 'open',
            'auditobject_id' => $object->id,
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

        // Afwijking gesloten: nu wél — met de afhandelingsnotitie erbij.
        $afwijking->update(['status' => 'gesloten', 'gesloten_op' => now()]);
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id)
            ->assertSet('toontSluitFormulier', true)
            ->set('sluitNotitie', 'Corrigerende maatregel afgerond en getoetst.')
            ->call('bevestigSluiten')
            ->assertHasNoErrors();
        $this->assertSame('gesloten', $bevinding->fresh()->status);
    }

    /**
     * Sluiten zonder te zeggen wat er is gebeurd, laat een dossier achter waarin
     * "gesloten door X op Y" de hele verantwoording is (11d §13).
     */
    public function test_sluiten_zonder_afhandelingsnotitie_wordt_geweigerd(): void
    {
        $ronde = $this->interneRonde('afgerond', Gebruiker::factory()->metRol('Auditor')->create());
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'type' => 'observatie',
            'status' => 'open',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('sluitBevinding', $bevinding->id)
            ->call('bevestigSluiten')
            ->assertHasErrors('sluitNotitie');

        $this->assertSame('open', $bevinding->fresh()->status);
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
            ->call('sluitBevinding', $bevinding->id)
            ->set('sluitNotitie', 'Meegenomen bij de herinrichting van het logboek.')
            ->call('bevestigSluiten')
            ->assertHasNoErrors();

        $bevinding->refresh();
        $this->assertSame('gesloten', $bevinding->status);
        $this->assertSame('Meegenomen bij de herinrichting van het logboek.', $bevinding->afhandelingsnotitie);
        $this->assertSame($this->ciso->id, $bevinding->gesloten_door_id);
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
            ->set([
                'externAuditorNaam' => 'Certificerende Instelling BV',
                'bevindingType' => 'non_conformiteit_major',
                'bevindingOmschrijving' => 'Directiebetrokkenheid onvoldoende aangetoond.',
                'bevindingAuditobjectId' => (string) $this->auditobject('5.1')->id,
                'bevindingBron' => (string) $this->ciso->id,
            ])
            ->call('slaBevindingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bevindingen', [
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_major',
        ]);
    }

    // --- Behandeling per auditobject (plan 11d) ----------------------------

    /** Een lopende interne ronde met één object in de normatieve scope. */
    private function rondeMetScope(Gebruiker $auditor, string $status = 'in_uitvoering'): array
    {
        $ronde = $this->interneRonde($status, $auditor);
        $object = $this->auditobject();
        $ronde->auditobjecten()->attach($object->id);

        return [$ronde->load('auditobjecten'), $object];
    }

    public function test_auditor_legt_geen_opmerkingen_vast_met_gesprekspartner(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);
        $gesprokene = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('behandelObject', $object->id)
            ->set([
                'behandelAfhandeling' => 'geen_opmerkingen',
                'behandelBron' => (string) $gesprokene->id,
            ])
            ->call('slaBehandelingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('auditronde_auditobject', [
            'auditronde_id' => $ronde->id,
            'auditobject_id' => $object->id,
            'afhandeling' => 'geen_opmerkingen',
            'gesproken_met_id' => $gesprokene->id,
        ]);
    }

    /**
     * Zonder bron is groen een bewering en geen bewijs — dat is de hele reden dat
     * dit veld bestaat (plan 11d §0).
     */
    public function test_geen_opmerkingen_zonder_gesprekspartner_wordt_geweigerd(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('behandelObject', $object->id)
            ->set('behandelAfhandeling', 'geen_opmerkingen')
            ->call('slaBehandelingOp')
            ->assertHasErrors('behandelBron');
    }

    /**
     * Ook bij "geen opmerkingen" is eigen onderzoek een geldige bron: een
     * nagelezen procedure is bewijs, een verzonnen gesprekspartner niet
     * (11d §12).
     */
    public function test_geen_opmerkingen_mag_op_eigen_waarneming_steunen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('behandelObject', $object->id)
            ->set([
                'behandelAfhandeling' => 'geen_opmerkingen',
                'behandelBron' => AuditrondeDetail::EIGEN_WAARNEMING,
            ])
            ->call('slaBehandelingOp')
            ->assertHasNoErrors()
            ->assertSee('eigen waarneming');

        $this->assertDatabaseHas('auditronde_auditobject', [
            'auditronde_id' => $ronde->id,
            'auditobject_id' => $object->id,
            'afhandeling' => 'geen_opmerkingen',
            'gesproken_met_id' => null,
            'eigen_waarneming' => true,
        ]);
    }

    public function test_niet_toegekomen_zonder_reden_wordt_geweigerd(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('behandelObject', $object->id)
            ->set('behandelAfhandeling', 'niet_toegekomen')
            ->call('slaBehandelingOp')
            ->assertHasErrors('behandelToelichting');
    }

    /**
     * Een object waar een bevinding op zit, is niet handmatig op "geen
     * opmerkingen" te zetten: die status is afgeleid en twee vastleggingen van
     * hetzelfde feit kunnen elkaar tegenspreken.
     */
    public function test_object_met_bevinding_is_niet_handmatig_te_zetten(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);
        Bevinding::factory()->create(['auditronde_id' => $ronde->id, 'auditobject_id' => $object->id]);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde->load('bevindingen')])
            ->call('behandelObject', $object->id)
            ->assertSet('toontBehandelFormulier', false);
    }

    /**
     * Wie tijdens een interview over iets buiten de scope struikelt, moet het
     * kwijt kunnen; het object groeit de scope in en is herkenbaar als bijgroei.
     */
    public function test_bevinding_buiten_de_scope_laat_de_scope_meegroeien(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde] = $this->rondeMetScope($auditor);
        $buiten = $this->auditobject('7.5');

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set([
                'bevindingType' => 'observatie',
                'bevindingOmschrijving' => 'Documentbeheer niet geregeld.',
                'bevindingAuditobjectId' => (string) $buiten->id,
                'bevindingBron' => AuditrondeDetail::EIGEN_WAARNEMING,
            ])
            ->call('slaBevindingOp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('auditronde_auditobject', [
            'auditronde_id' => $ronde->id,
            'auditobject_id' => $buiten->id,
            'buiten_planning' => true,
        ]);
    }

    public function test_ciso_en_afgeronde_ronde_kunnen_de_behandeling_niet_zetten(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        // De record-guard: de CISO mag de bevindingen van een interne ronde niet
        // vastleggen, en dus ook de behandeling niet (implementatie/11 §4a).
        Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('behandelObject', $object->id)
            ->assertForbidden();

        [$afgerond, $objectB] = $this->rondeMetScope($auditor, 'afgerond');

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $afgerond])
            ->call('behandelObject', $objectB->id)
            ->assertForbidden();
    }

    /**
     * Afronden met grijze objecten blokkeert niet, maar dwingt de auditor het uit
     * te spreken: "niet aan toegekomen" is een reëel auditresultaat (11d §0).
     */
    public function test_afronden_met_grijze_objecten_vraagt_eerst_een_reden(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        $component = Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('rondAf')
            ->assertSet('toontAfrondFormulier', true);

        // Nog niets afgerond zolang de reden ontbreekt.
        $this->assertSame('in_uitvoering', $ronde->fresh()->status);

        $component->call('rondAfMetRedenen')->assertHasErrors('afrondRedenen.'.$object->id);

        $component->set('afrondRedenen.'.$object->id, 'Beheerder was afwezig.')
            ->call('rondAfMetRedenen')
            ->assertHasNoErrors();

        $this->assertSame('afgerond', $ronde->fresh()->status);
        $this->assertDatabaseHas('auditronde_auditobject', [
            'auditronde_id' => $ronde->id,
            'auditobject_id' => $object->id,
            'afhandeling' => 'niet_toegekomen',
            'toelichting' => 'Beheerder was afwezig.',
        ]);
    }

    /**
     * De bron van een bevinding is verplicht: een constatering die niemand kan
     * navragen, is niet na te lopen. De uitweg is een expliciete keuze en geen
     * leeg veld — niet elke bevinding komt uit een gesprek (11d §12).
     */
    public function test_bevinding_zonder_bron_wordt_geweigerd(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set([
                'bevindingType' => 'observatie',
                'bevindingOmschrijving' => 'Geen bron opgegeven.',
                'bevindingAuditobjectId' => (string) $object->id,
            ])
            ->call('slaBevindingOp')
            ->assertHasErrors('bevindingBron');
    }

    public function test_eigen_waarneming_legt_de_bevinding_vast_zonder_gebruiker(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set([
                'bevindingType' => 'observatie',
                'bevindingOmschrijving' => 'In de logbestanden gezien.',
                'bevindingAuditobjectId' => (string) $object->id,
                'bevindingBron' => AuditrondeDetail::EIGEN_WAARNEMING,
            ])
            ->call('slaBevindingOp')
            ->assertHasNoErrors()
            ->assertSee('eigen waarneming');

        $this->assertDatabaseHas('bevindingen', [
            'auditobject_id' => $object->id,
            'gesproken_met_id' => null,
            'eigen_waarneming' => true,
        ]);
    }

    /**
     * De erfenis uit 11d §0 werkt bij het invullen en niet bij het lezen: een
     * object dat al een gesprekspartner droeg, stelt die voor.
     */
    public function test_het_onderwerp_stelt_de_gesprekspartner_van_het_object_voor(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde, $object] = $this->rondeMetScope($auditor);
        $gesprokene = Gebruiker::factory()->metRol('Medewerker')->create();
        $ronde->auditobjecten()->updateExistingPivot($object->id, [
            'afhandeling' => 'geen_opmerkingen',
            'gesproken_met_id' => $gesprokene->id,
        ]);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde->load('auditobjecten')])
            ->set('bevindingAuditobjectId', (string) $object->id)
            ->assertSet('bevindingBron', (string) $gesprokene->id);
    }

    // --- Kopie voor de auditor (12h) ---------------------------------------

    /**
     * Het rondedossier als Word-document: de kopgegevens met de status, de
     * normatieve scope met wat er met elk object is gebeurd, de bevindingen
     * eronder, en de titels van het gekoppelde bewijs.
     */
    public function test_de_rondekopie_bevat_de_status_de_scope_en_het_bewijs(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create(['naam' => 'Aurelius Aardappel']);
        [$ronde, $behandeld] = $this->rondeMetScope($auditor);
        $gesprokene = Gebruiker::factory()->metRol('Medewerker')->create(['naam' => 'Barbara Bes']);

        $ronde->auditobjecten()->updateExistingPivot($behandeld->id, [
            'afhandeling' => 'geen_opmerkingen',
            'gesproken_met_id' => $gesprokene->id,
        ]);

        $gat = $this->auditobject('7.2');
        $ronde->auditobjecten()->attach($gat->id, [
            'afhandeling' => 'niet_toegekomen',
            'toelichting' => 'Teamleider was afwezig.',
        ]);

        // Een derde object met een bevinding: die status is afgeleid en zou de
        // 'geen opmerkingen' op hetzelfde object overschrijven.
        $metBevinding = $this->auditobject('9.1');
        $ronde->auditobjecten()->attach($metBevinding->id);

        Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'auditobject_id' => $metBevinding->id,
            'type' => 'observatie',
            'omschrijving' => 'Logboek niet volledig ingevuld.',
            'eigen_waarneming' => true,
        ]);

        Bewijsstuk::factory()->create(['naam' => 'Auditrapport 2029'])->koppelingen()->create([
            'blok_naam' => 'auditmanagement',
            'entiteit_type' => 'auditronde',
            'entiteit_id' => $ronde->id,
        ]);

        $component = Livewire::actingAs($this->ciso)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde->fresh()])
            ->instance();
        $markdown = (fn (): Schermkopie => $this->schermkopie())->call($component)->markdown();

        $this->assertStringContainsString('# Auditronde — Intern, plan', $markdown);
        $this->assertStringContainsString('| Status | In uitvoering |', $markdown);
        $this->assertStringContainsString('| Bewijs | 1 stuk(ken): Auditrapport 2029 |', $markdown);

        // De scope met de afhandeling per object, en de reden bij het gat.
        $this->assertStringContainsString('| Geen opmerkingen |', $markdown);
        $this->assertStringContainsString('Teamleider was afwezig.', $markdown);
        // Een object met een bevinding: die status is afgeleid, niet gezet.
        $this->assertStringContainsString('| Bevinding |', $markdown);

        // De bijlage met de bevindingen.
        $this->assertStringContainsString('## Bevindingen', $markdown);
        $this->assertStringContainsString('1 bevinding, nog open.', $markdown);
        $this->assertStringContainsString('Logboek niet volledig ingevuld.', $markdown);
        $this->assertStringContainsString('eigen waarneming', $markdown);

        // Personen staan als initialen met hun rol, niet met hun volle naam.
        $this->assertStringContainsString('BB (Medewerker)', $markdown);
        $this->assertStringNotContainsString('Barbara Bes', $markdown);
        $this->assertStringNotContainsString('Aurelius Aardappel', $markdown);
    }

    /** Wie het scherm mag lezen, mag een kopie van wat hij ziet (12h). */
    public function test_de_rondekopie_vraagt_leesrecht_op_auditmanagement(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        [$ronde] = $this->rondeMetScope($auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->assertSee('Kopie voor de auditor');

        Livewire::actingAs(Gebruiker::factory()->metRol('Medewerker')->create())
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->call('kopieerVoorAuditor')
            ->assertForbidden();
    }

    // --- Audit trail -------------------------------------------------------

    public function test_bevinding_aanmaken_komt_in_de_audit_trail(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $ronde = $this->interneRonde('in_uitvoering', $auditor);

        Livewire::actingAs($auditor)
            ->test(AuditrondeDetail::class, ['auditronde' => $ronde])
            ->set([
                'bevindingType' => 'observatie',
                'bevindingOmschrijving' => 'Logboek niet volledig ingevuld.',
                'bevindingAuditobjectId' => (string) $this->auditobject()->id,
                'bevindingBron' => AuditrondeDetail::EIGEN_WAARNEMING,
            ])
            ->call('slaBevindingOp');

        $bevinding = Bevinding::where('auditronde_id', $ronde->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'bevinding',
            'entiteit_id' => $bevinding->id,
            'actie' => 'aangemaakt',
        ]);
    }

    // --- Bevindingenregister over de rondes heen ---------------------------

    /**
     * Drie bevindingen in twee rondes, zodat filteren iets te scheiden heeft:
     * een openstaande minor, een gesloten minor en een open observatie.
     *
     * @return array{0: Bevinding, 1: Bevinding, 2: Bevinding}
     */
    private function registerVulling(): array
    {
        $rondeA = $this->interneRonde('afgerond', Gebruiker::factory()->metRol('Auditor')->create());
        $rondeB = Auditronde::factory()->create(['type' => 'extern_surveillance', 'status' => 'afgerond']);

        return [
            Bevinding::factory()->create([
                'auditronde_id' => $rondeA->id,
                'type' => 'non_conformiteit_minor',
                'omschrijving' => 'Continuïteitsplan nooit getest.',
                'status' => 'non_conformiteit_gestart',
            ]),
            Bevinding::factory()->create([
                'auditronde_id' => $rondeA->id,
                'type' => 'non_conformiteit_minor',
                'omschrijving' => 'Doelstellingen niet meetbaar.',
                'status' => 'gesloten',
            ]),
            Bevinding::factory()->create([
                'auditronde_id' => $rondeB->id,
                'type' => 'observatie',
                'omschrijving' => 'Patchronde niet vastgelegd.',
                'status' => 'open',
            ]),
        ];
    }

    public function test_register_toont_standaard_alleen_wat_nog_openstaat(): void
    {
        [$openstaand, $gesloten, $observatie] = $this->registerVulling();

        Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->assertSee($openstaand->omschrijving)
            ->assertSee($observatie->omschrijving)
            // 'non_conformiteit_gestart' telt als openstaand, 'gesloten' niet.
            ->assertDontSee($gesloten->omschrijving);
    }

    public function test_register_filtert_op_type_ronde_en_status(): void
    {
        [$openstaand, $gesloten, $observatie] = $this->registerVulling();

        // Zoals de badge op /audits linkt: type + openstaand.
        Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->set('filterType', 'non_conformiteit_minor')
            ->assertSee($openstaand->omschrijving)
            ->assertDontSee($observatie->omschrijving)
            ->assertDontSee($gesloten->omschrijving);

        // Alle statussen: de gesloten minor hoort er dan wél bij te staan.
        Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->set('filterType', 'non_conformiteit_minor')
            ->set('filterStatus', '')
            ->assertSee($openstaand->omschrijving)
            ->assertSee($gesloten->omschrijving);

        // Eén ronde: de bevinding uit de andere ronde valt weg.
        Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->set('filterRonde', (string) $observatie->auditronde_id)
            ->assertSee($observatie->omschrijving)
            ->assertDontSee($openstaand->omschrijving);
    }

    public function test_register_valt_terug_op_de_standaard_bij_een_onzinnig_urlfilter(): void
    {
        [$openstaand] = $this->registerVulling();

        // Een handmatig getikte URL mag geen lege lijst opleveren die als
        // "niets gevonden" leest.
        Livewire::actingAs($this->ciso)
            ->withQueryParams(['filterType' => 'zwerfvuil', 'filterStatus' => 'zwerfvuil', 'filterRonde' => '9999'])
            ->test(BevindingenOverzicht::class)
            ->assertSet('filterType', '')
            ->assertSet('filterStatus', BevindingenOverzicht::OPENSTAAND)
            ->assertSet('filterRonde', '')
            ->assertSee($openstaand->omschrijving);
    }

    public function test_badge_op_audits_linkt_naar_het_register_met_het_filter_erin(): void
    {
        $this->registerVulling();

        Livewire::actingAs($this->ciso)
            ->test(AuditsOverzicht::class)
            // De telling zelf, en de badge per type: allebei doorklikbaar naar
            // dezelfde selectie als waar het getal op slaat.
            ->assertSee(route('audits.bevindingen', [
                'filterStatus' => BevindingenOverzicht::OPENSTAAND,
            ]))
            ->assertSee(route('audits.bevindingen', [
                'filterType' => 'non_conformiteit_minor',
                'filterStatus' => BevindingenOverzicht::OPENSTAAND,
            ]));
    }

    public function test_auditor_leest_het_register_maar_medewerker_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($auditor)->get('/audits/bevindingen')->assertOk();
        $this->actingAs($medewerker)->get('/audits/bevindingen')->assertForbidden();
    }

    // --- Schermkopie van het register (12h §11 test 7) ---------------------

    /** De kopie is `protected`; het scherm bouwt hem, de trait roept hem aan. */
    private function registerKopie(BevindingenOverzicht $component): Schermkopie
    {
        return (fn (): Schermkopie => $this->schermkopie())->call($component);
    }

    public function test_de_kopie_bevat_de_kolommen_van_het_scherm_plus_de_opvolging(): void
    {
        [$openstaand] = $this->registerVulling();
        $maatregel = Maatregel::factory()->create(['annex_a_referentie' => '5.30']);
        $openstaand->update(['auditobject_id' => Auditobject::factory()->maatregel($maatregel->id)->create()->id]);

        $component = Livewire::actingAs($this->ciso)->test(BevindingenOverzicht::class)->instance();
        $markdown = $this->registerKopie($component)->markdown();

        foreach (['Type', 'Omschrijving', 'Betreft', 'Auditronde', 'Status', 'Opvolging', 'Gesloten op', 'Afhandeling'] as $kolom) {
            $this->assertStringContainsString($kolom, $markdown);
        }

        $this->assertStringContainsString('Non conformiteit minor', $markdown);
        $this->assertStringContainsString('Continuïteitsplan nooit getest.', $markdown);
        $this->assertStringContainsString('A.5.30', $markdown);
        $this->assertStringContainsString('bevindingen', $markdown);
    }

    /**
     * De opvolging is de reden dat dit register bewijs is en geen lijst met
     * constateringen: een non-conformiteit hoort naar een afwijking te leiden.
     */
    public function test_de_kopie_toont_de_gekoppelde_afwijking_en_de_sluitdatum(): void
    {
        $ronde = $this->interneRonde('afgerond', Gebruiker::factory()->metRol('Auditor')->create());
        $bevinding = Bevinding::factory()->create([
            'auditronde_id' => $ronde->id,
            'type' => 'non_conformiteit_major',
            'status' => 'non_conformiteit_gestart',
        ]);

        // Zonder afwijking blijft de kolom leeg — de markdown maakt daar een
        // liggend streepje van.
        $component = Livewire::actingAs($this->ciso)->test(BevindingenOverzicht::class)->instance();
        $markdown = $this->registerKopie($component)->markdown();
        $this->assertStringNotContainsString('Afwijking loopt', $markdown);
        $this->assertStringNotContainsString('Afwijking gesloten', $markdown);

        $afwijking = Afwijking::factory()->create(['bevinding_id' => $bevinding->id, 'gesloten_op' => null]);

        $component = Livewire::actingAs($this->ciso)->test(BevindingenOverzicht::class)->instance();
        $this->assertStringContainsString('Afwijking loopt', $this->registerKopie($component)->markdown());

        $afwijking->update(['gesloten_op' => '2026-03-04']);

        $component = Livewire::actingAs($this->ciso)->test(BevindingenOverzicht::class)->instance();
        $this->assertStringContainsString('Afwijking gesloten op 04-03-2026', $this->registerKopie($component)->markdown());
    }

    /**
     * De scherpste regel uit 12h §4, en hier extra scherp: dit scherm filtert
     * standaard op "openstaand", dus een kopie die zwijgt over dat filter zou
     * elke keer als het volledige register lezen.
     */
    public function test_de_kopie_noemt_het_standaardfilter_altijd(): void
    {
        $this->registerVulling();

        $component = Livewire::actingAs($this->ciso)->test(BevindingenOverzicht::class)->instance();

        $this->assertStringContainsString(
            '| Omvang | 2 van 3 bevindingen — filter: status Openstaand (niet gesloten). |',
            $this->registerKopie($component)->markdown(),
        );

        // Alle statussen: dan pas mag het document zeggen dat het compleet is.
        $volledig = Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->set('filterStatus', '')
            ->instance();

        $this->assertStringContainsString('| Omvang | Alle 3 bevindingen. |', $this->registerKopie($volledig)->markdown());
    }

    /**
     * De vastlegging (§9) moet terug te lezen zijn als "wélk scherm met welke
     * filters is de deur uit gegaan". Hier via `legVast()` en niet via de knop:
     * dan draait de test zonder pandoc, net als in SchermkopieTest.
     */
    public function test_de_kopie_legt_het_actieve_filter_vast(): void
    {
        $this->registerVulling();
        $this->actingAs($this->ciso);

        $component = Livewire::actingAs($this->ciso)
            ->test(BevindingenOverzicht::class)
            ->set('filterType', 'non_conformiteit_minor')
            ->instance();

        $registratie = $this->registerKopie($component)->legVast();

        $this->assertSame('Bevindingenregister', $registratie->scherm);
        $this->assertSame($this->ciso->id, $registratie->gebruiker_id);
        $this->assertSame(1, $registratie->aantal_rijen);
        $this->assertSame(3, $registratie->totaal_rijen);
        $this->assertSame([
            'Type' => 'Non conformiteit minor',
            'Status' => 'Openstaand (niet gesloten)',
        ], $registratie->filters);
    }
}
