<?php

namespace Tests\Feature;

use App\Livewire\AfwijkingDetail;
use App\Livewire\AfwijkingenOverzicht;
use App\Livewire\IncidentDetail;
use App\Livewire\IncidentenOverzicht;
use App\Mail\IncidentGemeld;
use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\CorrigerendeMaatregel;
use App\Models\Effectiviteitstoets;
use App\Models\Gebruiker;
use App\Models\Grondoorzaak;
use App\Models\Incident;
use App\Models\IncidentMelding;
use App\Models\Taak;
use App\Observers\CorrigerendeMaatregelObserver;
use App\Support\Afwijkingafsluiting;
use App\Support\Meetbronnen;
use App\Support\Meldplicht;
use App\Support\Schermkopie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\NotificatieregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        // NotificatieregelSeeder erbij: sinds blok 14 loopt de incident-mail via
        // de notificatielaag, die de actieve regel 'incident_gemeld' → CISO nodig
        // heeft (in productie geseed via DatabaseSeeder).
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, NotificatieregelSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    // --- Rechten en scoping (§9) -------------------------------------------

    public function test_medewerker_meldt_en_ziet_alleen_eigen_meldingen(): void
    {
        Mail::fake();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $ander = Gebruiker::factory()->metRol('Medewerker')->create();

        Incident::factory()->create(['titel' => 'Melding van een ander', 'gemeld_door_id' => $ander->id]);

        Livewire::actingAs($medewerker)
            ->test(IncidentenOverzicht::class)
            ->call('nieuwIncident')
            ->set('titel', 'Laptop kwijt in de trein')
            ->set('ernst', 'hoog')
            ->call('melden')
            ->assertHasNoErrors()
            ->assertSee('Laptop kwijt in de trein')
            ->assertDontSee('Melding van een ander');

        $this->assertSame($medewerker->id, Incident::where('titel', 'Laptop kwijt in de trein')->sole()->gemeld_door_id);

        // De CISO ziet ze allebei.
        Livewire::actingAs($this->ciso)
            ->test(IncidentenOverzicht::class)
            ->assertSee('Laptop kwijt in de trein')
            ->assertSee('Melding van een ander');
    }

    public function test_medewerker_komt_niet_op_andermans_melding_of_op_afwijkingen(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        // 404 en niet 403: andermans melding bestaat voor hem niet.
        $this->actingAs($medewerker)->get('/incidenten/'.$incident->id)->assertNotFound();
        $this->actingAs($medewerker)->get('/afwijkingen')->assertForbidden();
        $this->actingAs($this->ciso)->get('/afwijkingen')->assertOk();
    }

    // --- Afgeleide status (§5) ---------------------------------------------

    public function test_afwijkingstatus_volgt_grondoorzaak_en_maatregel(): void
    {
        $afwijking = Afwijking::factory()->create();
        $this->assertSame('open', $afwijking->fresh()->status);

        Grondoorzaak::factory()->for($afwijking, 'afwijking')->create();
        $this->assertSame('analyse', $afwijking->fresh()->status);

        $maatregel = CorrigerendeMaatregel::factory()->for($afwijking, 'afwijking')->create();
        $this->assertSame('actie_lopend', $afwijking->fresh()->status);

        // En terug: de maatregel weghalen zet de afwijking op analyse.
        $maatregel->delete();
        $this->assertSame('analyse', $afwijking->fresh()->status);
    }

    // --- Sluiten is een daad, geen gevolg (§5) ------------------------------

    public function test_sluiten_weigert_zonder_maatregel(): void
    {
        $afwijking = Afwijking::factory()->create();

        $this->assertNotNull(Afwijkingafsluiting::belemmering($afwijking));
        $this->expectException(ValidationException::class);
        Afwijkingafsluiting::sluit($afwijking, $this->ciso);
    }

    public function test_sluiten_weigert_met_onvoltooide_of_ongetoetste_maatregel(): void
    {
        $afwijking = Afwijking::factory()->create();
        $maatregel = CorrigerendeMaatregel::factory()->for($afwijking, 'afwijking')->create();

        $this->assertStringContainsString('niet voltooid', Afwijkingafsluiting::belemmering($afwijking));

        $maatregel->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        $this->assertStringContainsString('effectiviteitstoets', Afwijkingafsluiting::belemmering($afwijking->fresh()));

        // Een toets die 'niet effectief' zegt telt niet als afronding.
        Effectiviteitstoets::factory()->nietEffectief()->for($maatregel, 'maatregel')->create();
        $this->assertNotNull(Afwijkingafsluiting::belemmering($afwijking->fresh()));
    }

    public function test_sluiten_lukt_en_legt_de_sluiter_vast(): void
    {
        $afwijking = Afwijking::factory()->create();
        $maatregel = CorrigerendeMaatregel::factory()->voltooid()->for($afwijking, 'afwijking')->create();
        Effectiviteitstoets::factory()->for($maatregel, 'maatregel')->create();

        $this->assertNull(Afwijkingafsluiting::belemmering($afwijking->fresh()));

        Livewire::actingAs($this->ciso)
            ->test(AfwijkingDetail::class, ['afwijking' => $afwijking->fresh()])
            ->call('sluiten')
            ->assertHasNoErrors();

        $gesloten = $afwijking->fresh();
        $this->assertSame('gesloten', $gesloten->status);
        $this->assertSame($this->ciso->id, $gesloten->gesloten_door_id);
        $this->assertNotNull($gesloten->gesloten_op);
    }

    /**
     * De terugweg uit de statemachine. Zonder deze regel is een
     * effectiviteitstoets een afvinkveld.
     */
    public function test_niet_effectieve_toets_heropent_de_gesloten_afwijking(): void
    {
        $afwijking = Afwijking::factory()->create();
        $maatregel = CorrigerendeMaatregel::factory()->voltooid()->for($afwijking, 'afwijking')->create();
        Effectiviteitstoets::factory()->for($maatregel, 'maatregel')->create(['uitgevoerd_op' => now()->subDay()]);

        Afwijkingafsluiting::sluit($afwijking, $this->ciso);
        $this->assertSame('gesloten', $afwijking->fresh()->status);

        Effectiviteitstoets::factory()->nietEffectief()->for($maatregel, 'maatregel')->create(['uitgevoerd_op' => now()]);

        $heropend = $afwijking->fresh();
        $this->assertSame('actie_lopend', $heropend->status);
        $this->assertNull($heropend->gesloten_op);
        $this->assertSame('in_uitvoering', $maatregel->fresh()->status);
    }

    // --- Taken (§8) ---------------------------------------------------------

    public function test_maatregel_levert_taken_op_en_ruimt_ze_weer_op(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Medewerker')->create();
        $afwijking = Afwijking::factory()->create();

        $maatregel = CorrigerendeMaatregel::factory()->for($afwijking, 'afwijking')->create([
            'eigenaar_id' => $eigenaar->id,
            'deadline' => now()->addDays(10),
        ]);

        $this->assertSame(1, Taak::where('soort', 'corrigerende-maatregel')->count());
        $this->assertSame(0, Taak::where('soort', 'effectiviteitstoets')->count());

        // Voltooien ruimt de uitvoeringstaak op en zet de toetstaak neer.
        $maatregel->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertSame(0, Taak::where('soort', 'corrigerende-maatregel')->count());
        $toets = Taak::where('soort', 'effectiviteitstoets')->sole();
        $this->assertSame($eigenaar->id, $toets->eigenaar_id);
        $this->assertTrue(
            $toets->deadline->isSameDay(now()->addDays(CorrigerendeMaatregelObserver::TOETSTERMIJN_DAGEN))
        );

        // En de toets zelf ruimt de taak op.
        Effectiviteitstoets::factory()->for($maatregel, 'maatregel')->create();
        $this->assertSame(0, Taak::where('soort', 'effectiviteitstoets')->count());
    }

    // --- Incidentstatus (§6) ------------------------------------------------

    public function test_incident_sluit_niet_met_een_open_afwijking(): void
    {
        $incident = Incident::factory()->meldplichtBeoordeeld()->create(['gemeld_door_id' => $this->ciso->id]);
        $afwijking = Afwijking::factory()->create(['incident_id' => $incident->id, 'bron' => 'incident']);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->call('opslaan')
            ->assertHasErrors('status');

        $this->assertSame('gemeld', $incident->fresh()->status);

        // Sluit de afwijking en het incident kan wél dicht — via 'opgelost',
        // want sluiten is de afronding van het dossier, niet van het probleem.
        $maatregel = CorrigerendeMaatregel::factory()->voltooid()->for($afwijking, 'afwijking')->create();
        Effectiviteitstoets::factory()->for($maatregel, 'maatregel')->create();
        Afwijkingafsluiting::sluit($afwijking->fresh(), $this->ciso);

        $incident->update(['status' => 'opgelost']);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident->fresh()])
            ->set('status', 'gesloten')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('gesloten', $incident->fresh()->status);
    }

    // --- Opgelost versus gesloten (§6) --------------------------------------

    public function test_gemeld_kan_niet_rechtstreeks_naar_gesloten(): void
    {
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->set('geenAfwijkingReden', 'Eenmalige menselijke fout, geen patroon.')
            ->call('opslaan')
            ->assertHasErrors('status');

        $this->assertSame('gemeld', $incident->fresh()->status);
    }

    /**
     * Het besluit dat §10.1 bedoelt: een incident mag zonder corrigerende
     * maatregel worden afgesloten, maar niet zonder dat iemand die vraag heeft
     * beantwoord.
     */
    public function test_sluiten_zonder_afwijking_vereist_een_vastgelegd_besluit(): void
    {
        $incident = Incident::factory()->meldplichtBeoordeeld()->create([
            'gemeld_door_id' => $this->ciso->id,
            'status' => 'opgelost',
        ]);

        $component = Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->call('opslaan')
            ->assertHasErrors('status');

        $this->assertNull($incident->fresh()->gesloten_op);

        $component->set('geenAfwijkingReden', 'Losse phishingmail, al afgevangen door de filter.')
            ->call('opslaan')
            ->assertHasNoErrors();

        $gesloten = $incident->fresh();
        $this->assertSame('gesloten', $gesloten->status);
        $this->assertSame($this->ciso->id, $gesloten->gesloten_door_id);
        $this->assertNotNull($gesloten->gesloten_op);
        $this->assertStringContainsString('phishingmail', $gesloten->geen_afwijking_reden);
    }

    // ---- Externe meldplicht (implementatie/08b) ----

    public function test_sluiten_weigert_zolang_de_meldplicht_niet_beoordeeld_is(): void
    {
        $incident = Incident::factory()->create([
            'gemeld_door_id' => $this->ciso->id,
            'status' => 'opgelost',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->set('geenAfwijkingReden', 'Losse phishingmail.')
            ->call('opslaan')
            ->assertHasErrors('status');

        $this->assertNull($incident->fresh()->gesloten_op);
    }

    /**
     * Zonder raakvlak is er geen documentatieplicht: AVG art. 33 lid 5 gaat over
     * inbreuken *in verband met persoonsgegevens*, niet over elk
     * beveiligingsincident. Eén "nee" is dan een volledig antwoord.
     */
    public function test_geen_raakvlak_vraagt_geen_motivatie(): void
    {
        $incident = Incident::factory()->create([
            'gemeld_door_id' => $this->ciso->id,
            'status' => 'opgelost',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('raaktPersoonsgegevens', '0')
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors()
            // En het incident is daarmee ook sluitbaar.
            ->set('status', 'gesloten')
            ->set('geenAfwijkingReden', 'Losse storing, geen structurele oorzaak.')
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeeld = $incident->fresh();
        $this->assertFalse($beoordeeld->raakt_persoonsgegevens);
        $this->assertFalse($beoordeeld->heeftDocumentatieplicht());
        $this->assertNull($beoordeeld->meldplicht_motivatie);
        $this->assertSame('gesloten', $beoordeeld->status);
    }

    /**
     * Mét raakvlak bijt de documentatieplicht wél: het oordeel dat een risico
     * onwaarschijnlijk is (AVG art. 33 lid 1) hoort navolgbaar te zijn.
     */
    public function test_raakvlak_zonder_meldplicht_vereist_evengoed_een_motivatie(): void
    {
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        $component = Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('raaktPersoonsgegevens', '1')
            ->set('externMeldingsplichtig', '0')
            ->call('beoordeelMeldplicht')
            ->assertHasErrors('meldplichtMotivatie');

        $this->assertNull($incident->fresh()->extern_meldingsplichtig);

        $component->set('meldplichtMotivatie', 'Eén verkeerd geadresseerde e-mail, direct ingetrokken; risico voor de betrokkene onwaarschijnlijk.')
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors();

        $beoordeeld = $incident->fresh();
        $this->assertTrue($beoordeeld->heeftDocumentatieplicht());
        $this->assertFalse($beoordeeld->extern_meldingsplichtig);
        // Geen verplichtingen bij een "nee" — wel een vastgelegd besluit.
        $this->assertSame(0, $beoordeeld->meldingen()->count());
    }

    /** De Cbw-vraag is een inrichtingsbeslissing, geen vraag per incident. */
    public function test_de_cbw_vraag_verschijnt_alleen_bij_een_cbw_plichtige_organisatie(): void
    {
        $incident = Incident::factory()->create([
            'gemeld_door_id' => $this->ciso->id,
            'status' => 'opgelost',
        ]);

        // Uit: één "nee" volstaat en de kolom blijft null (niet gevraagd ≠ nee).
        config()->set('meldplicht.cbw_plichtig', false);
        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('raaktPersoonsgegevens', '0')
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors();

        $this->assertNull($incident->fresh()->is_netwerk_informatie_incident);
        $this->assertNull($incident->fresh()->belemmeringVoorMeldplicht());

        // Aan: de tweede vraag moet beantwoord zijn voordat het incident sluit.
        config()->set('meldplicht.cbw_plichtig', true);
        $this->assertNotNull($incident->fresh()->belemmeringVoorMeldplicht());

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident->fresh()])
            ->set('raaktPersoonsgegevens', '0')
            ->set('isNetwerkInformatieIncident', '1')
            ->call('beoordeelMeldplicht')
            ->assertHasErrors('meldplichtMotivatie');
    }

    /**
     * Het gekoppelde asset spreekt een "nee" tegen, maar blokkeert niet — of een
     * incident écht persoonsgegevens raakt, hangt af van wat er gebeurd is.
     */
    public function test_het_gekoppelde_asset_spreekt_een_nee_tegen(): void
    {
        $asset = Asset::factory()->create(['naam' => 'Klantenbestand', 'persoonsgegevens' => 'bijzonder']);
        $incident = Incident::factory()->create([
            'gemeld_door_id' => $this->ciso->id,
            'gekoppeld_asset_id' => $asset->id,
        ]);

        // Onbeoordeeld: een hint.
        $this->assertStringContainsString('bevat bijzonder', $incident->meldplichtsignaalUitAsset(null));

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('raaktPersoonsgegevens', '0')
            ->assertSee('Klopt dat?')
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors();

        // Opgeslagen ondanks de tegenspraak.
        $this->assertFalse($incident->fresh()->raakt_persoonsgegevens);

        // En geen signaal wanneer het asset geen persoonsgegevens bevat.
        $asset->update(['persoonsgegevens' => 'geen']);
        $this->assertNull($incident->fresh()->meldplichtsignaalUitAsset(false));
    }

    /**
     * De kerntest van 08b: de termijn loopt vanaf kennisname, niet vanaf de
     * registratie in het ISMS. Rekenen vanaf `gemeld_op` zou hier een deadline
     * in de toekomst opleveren terwijl hij wettelijk al verstreken is.
     */
    public function test_de_termijn_rekent_vanaf_kennisname_en_niet_vanaf_de_registratie(): void
    {
        $kennisname = now()->subDays(3);

        $incident = Incident::factory()->create([
            'gemeld_door_id' => $this->ciso->id,
            'gemeld_op' => now(),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('kennisnameOp', $kennisname->format('Y-m-d\TH:i'))
            ->set('externMeldingsplichtig', '1')
            ->set('meldplichtMotivatie', 'Datalek met klantgegevens.')
            ->set('raaktPersoonsgegevens', '1')
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors();

        $melding = $incident->fresh()->meldingen()->where('fase', 'melding')->firstOrFail();

        $this->assertSame(72, $melding->meldtermijn_uren);
        $this->assertTrue($melding->uiterlijk_op->isBefore(now()), 'Deadline moet al verstreken zijn.');
        $this->assertTrue($melding->isTeLaat());
    }

    public function test_avg_en_cbw_leveren_samen_vijf_verplichtingen_op(): void
    {
        config()->set('meldplicht.cbw_plichtig', true);
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('kennisnameOp', now()->format('Y-m-d\TH:i'))
            ->set('externMeldingsplichtig', '1')
            ->set('meldplichtMotivatie', 'Datalek bij een Cbw-plichtige entiteit.')
            ->set('raaktPersoonsgegevens', '1')
            ->set('isNetwerkInformatieIncident', '1')
            ->set('mededelingBetrokkenen', true)
            ->call('beoordeelMeldplicht')
            ->assertHasNoErrors();

        $meldingen = $incident->fresh()->meldingen;

        // AVG: melding + betrokkenen. Cbw: waarschuwing + melding + eindverslag.
        $this->assertCount(5, $meldingen);
        $this->assertSame(24, $meldingen->firstWhere('fase', 'waarschuwing')->meldtermijn_uren);
        $this->assertCount(2, $meldingen->where('fase', 'melding'), 'AVG en Cbw naast elkaar op dezelfde fase.');
    }

    /** Opnieuw beoordelen mag geen duplicaten opleveren (unique op incident+grondslag+fase). */
    public function test_opnieuw_beoordelen_dupliceert_de_verplichtingen_niet(): void
    {
        config()->set('meldplicht.cbw_plichtig', true);
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        $component = Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('kennisnameOp', now()->format('Y-m-d\TH:i'))
            ->set('externMeldingsplichtig', '1')
            ->set('meldplichtMotivatie', 'Datalek.')
            ->set('raaktPersoonsgegevens', '0')
            ->set('isNetwerkInformatieIncident', '1')
            ->call('beoordeelMeldplicht');

        $component->set('meldplichtMotivatie', 'Datalek, herzien.')->call('beoordeelMeldplicht')->assertHasNoErrors();

        $this->assertSame(3, $incident->fresh()->meldingen()->count());
    }

    /**
     * `uiterlijk_op` is een opgeslagen besluit, geen berekening: een gewijzigde
     * configuratiewaarde mag een reeds vastgestelde deadline niet verschuiven.
     */
    public function test_een_vastgestelde_deadline_beweegt_niet_mee_met_de_configuratie(): void
    {
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('kennisnameOp', now()->format('Y-m-d\TH:i'))
            ->set('externMeldingsplichtig', '1')
            ->set('meldplichtMotivatie', 'Datalek.')
            ->set('raaktPersoonsgegevens', '1')
            ->call('beoordeelMeldplicht');

        $oorspronkelijk = $incident->fresh()->meldingen()->firstOrFail()->uiterlijk_op;

        config()->set('meldplicht.grondslagen.avg.fasen.melding.uren', 1);
        Meldplicht::stelVast($incident->fresh(), ['avg']);

        $this->assertEquals($oorspronkelijk, $incident->fresh()->meldingen()->firstOrFail()->uiterlijk_op);
    }

    /**
     * Een verplichting zonder klok is het gewone geval (AVG art. 34, Cbw art. 29
     * lid 2 bij een lopend incident) — die kan nooit te laat zijn en telt niet
     * mee in de KPI-noemer.
     */
    public function test_verplichting_zonder_termijn_is_nooit_te_laat_en_telt_niet_mee(): void
    {
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);
        $zonder = IncidentMelding::factory()->zonderTermijn()->create(['incident_id' => $incident->id]);
        IncidentMelding::factory()->teLaat()->create(['incident_id' => $incident->id]);

        $this->assertFalse($zonder->isTeLaat());
        $this->assertCount(1, $incident->fresh()->teLateMeldingen());

        [$teller, $noemer] = Meetbronnen::bereken('incident_tijdig_extern_gemeld');
        $this->assertSame(0, $teller);
        $this->assertSame(1, $noemer, 'Alleen de melding mét termijn hoort in de noemer.');
    }

    /** Een gemiste termijn is een feit om vast te leggen, geen reden het dossier open te houden. */
    public function test_een_te_late_melding_blokkeert_het_sluiten_niet(): void
    {
        $incident = Incident::factory()->meldplichtBeoordeeld()->create([
            'gemeld_door_id' => $this->ciso->id,
            'status' => 'opgelost',
        ]);
        IncidentMelding::factory()->teLaat()->create(['incident_id' => $incident->id]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->set('geenAfwijkingReden', 'Bron weggenomen.')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('gesloten', $incident->fresh()->status);
    }

    /** Cbw art. 29 lid 1: het eindverslag rekent vanaf de melding, niet vanaf kennisname. */
    public function test_het_eindverslag_krijgt_pas_een_deadline_zodra_de_melding_gedaan_is(): void
    {
        config()->set('meldplicht.cbw_plichtig', true);
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        $component = Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('kennisnameOp', now()->format('Y-m-d\TH:i'))
            ->set('externMeldingsplichtig', '1')
            ->set('meldplichtMotivatie', 'Significant incident.')
            ->set('raaktPersoonsgegevens', '0')
            ->set('isNetwerkInformatieIncident', '1')
            ->call('beoordeelMeldplicht');

        $eindverslag = $incident->fresh()->meldingen()->where('fase', 'eindverslag')->firstOrFail();
        $this->assertNull($eindverslag->uiterlijk_op, 'Zonder gedane melding is er nog geen ankerpunt.');

        $melding = $incident->fresh()->meldingen()->where('fase', 'melding')->firstOrFail();
        $component->call('meldingGedaan', $melding->id)->assertHasNoErrors();

        $this->assertNotNull($eindverslag->fresh()->uiterlijk_op);
    }

    public function test_afsluiting_levert_een_doorlooptijd_en_verdwijnt_bij_heropenen(): void
    {
        $incident = Incident::factory()->meldplichtBeoordeeld()->create([
            'gemeld_door_id' => $this->ciso->id,
            'gemeld_op' => now()->subDays(5),
            'status' => 'opgelost',
        ]);

        $component = Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->set('status', 'gesloten')
            ->set('geenAfwijkingReden', 'Geen structurele oorzaak.')
            ->call('opslaan')
            ->assertHasNoErrors();

        // De doorlooptijd die deelproducten/08 §6 vraagt, nu betrouwbaar: uit
        // gemeld_op en gesloten_op, niet uit updated_at.
        $this->assertSame(5, $incident->fresh()->doorlooptijdInDagen());

        $component->set('status', 'in_onderzoek')->call('opslaan')->assertHasNoErrors();

        $heropend = $incident->fresh();
        $this->assertNull($heropend->gesloten_op);
        $this->assertNull($heropend->gesloten_door_id);
        $this->assertNull($heropend->doorlooptijdInDagen());
    }

    public function test_toetsen_kan_pas_nadat_de_maatregel_voltooid_is(): void
    {
        $afwijking = Afwijking::factory()->create();
        $maatregel = CorrigerendeMaatregel::factory()->for($afwijking, 'afwijking')->create();

        $component = Livewire::actingAs($this->ciso)
            ->test(AfwijkingDetail::class, ['afwijking' => $afwijking->fresh()])
            // String en geen int: de select/placeholder-afspraak uit de README.
            ->set('toetsMaatregelId', (string) $maatregel->id)
            ->call('legToetsVast')
            ->assertHasErrors('toetsMaatregelId');

        $this->assertSame(0, Effectiviteitstoets::count());

        $maatregel->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $component->call('legToetsVast')->assertHasNoErrors();

        $toets = Effectiviteitstoets::sole();
        $this->assertSame('effectief', $toets->resultaat);
        // Afwijking van het deelproduct (§4a): een oordeel zonder oordelaar is
        // geen bewijs.
        $this->assertSame($this->ciso->id, $toets->uitgevoerd_door_id);
    }

    // --- Notificatie (§7) ---------------------------------------------------

    public function test_de_notificatiemail_rendert(): void
    {
        $incident = Incident::factory()->create(['gemeld_door_id' => $this->ciso->id]);

        // Mail::fake() rendert de template niet; een kapotte Blade zou anders
        // pas in productie opvallen.
        $html = (new IncidentGemeld($incident))->render();

        $this->assertStringContainsString($incident->titel, $html);
        $this->assertStringContainsString(route('incidenten.detail', $incident), $html);
    }

    public function test_nieuwe_melding_gaat_per_mail_naar_de_ciso(): void
    {
        Mail::fake();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(IncidentenOverzicht::class)
            ->call('nieuwIncident')
            ->set('titel', 'Phishingmail ontvangen')
            ->set('ernst', 'midden')
            ->call('melden');

        Mail::assertSent(IncidentGemeld::class, fn ($mail) => $mail->hasTo($this->ciso->email));
        // De melder krijgt geen kopie: dit is een signaal naar wie het oppakt.
        Mail::assertSent(IncidentGemeld::class, 1);
    }

    /**
     * De registratie is de primaire plicht. Een onbereikbare mailserver mag
     * geen melding kosten (§7).
     */
    public function test_mislukte_verzending_blokkeert_de_registratie_niet(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        Livewire::actingAs($medewerker)
            ->test(IncidentenOverzicht::class)
            ->call('nieuwIncident')
            ->set('titel', 'Serverruimte stond open')
            ->set('ernst', 'hoog')
            ->call('melden')
            ->assertHasNoErrors();

        $this->assertSame(1, Incident::where('titel', 'Serverruimte stond open')->count());
    }

    // --- Afwijking uit incident --------------------------------------------

    public function test_afwijking_openen_vanuit_incident_legt_de_herkomst_vast(): void
    {
        $incident = Incident::factory()->create(['titel' => 'Datalek', 'gemeld_door_id' => $this->ciso->id]);

        Livewire::actingAs($this->ciso)
            ->test(IncidentDetail::class, ['incident' => $incident])
            ->call('openAfwijking');

        $afwijking = Afwijking::sole();
        $this->assertSame('incident', $afwijking->bron);
        $this->assertSame($incident->id, $afwijking->incident_id);

        Livewire::actingAs($this->ciso)
            ->test(AfwijkingenOverzicht::class)
            ->assertSee('Datalek');
    }

    // --- Schermkopie: de afwijkingen (12h §11 test 7) ----------------------

    private function afwijkingKopie(AfwijkingenOverzicht $component): Schermkopie
    {
        return (fn (): Schermkopie => $this->schermkopie())->call($component);
    }

    public function test_de_afwijkingkopie_bevat_de_kolommen_van_het_scherm(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Management')->create(['naam' => 'Dana Wolters']);

        $afwijking = Afwijking::create([
            'omschrijving' => 'De herbeoordeling van leveranciers is twee kwartalen blijven liggen.',
            'bron' => 'interne_signalering',
            'status' => 'actie_lopend',
            'eigenaar_id' => $eigenaar->id,
        ]);
        CorrigerendeMaatregel::create([
            'afwijking_id' => $afwijking->id,
            'omschrijving' => 'Herbeoordeling inhalen',
            'eigenaar_id' => $eigenaar->id,
            'deadline' => now()->addMonth(),
            'status' => 'voltooid',
        ]);

        $component = Livewire::actingAs($this->ciso)->test(AfwijkingenOverzicht::class);
        $markdown = $this->afwijkingKopie($component->instance())->markdown();

        $this->assertStringContainsString('# Afwijkingen', $markdown);
        $this->assertStringContainsString(
            '| Omschrijving | Bron | Status | Eigenaar | Maatregelen | Nog te toetsen |',
            $markdown,
        );
        $this->assertStringContainsString('Interne signalering', $markdown);
        $this->assertStringContainsString('Actie lopend', $markdown);

        // Eén maatregel, voltooid maar nog niet effectief bevonden.
        $this->assertStringContainsString('| 1 | 1 |', $markdown);
    }

    /**
     * De omschrijving gaat voluit mee. Het scherm kapt hem op 80 tekens af, maar
     * dat is een kolombreedte en geen inhoudelijke keuze — en juist de
     * omschrijving is waar een §10.2-afwijking om draait.
     */
    public function test_de_kopie_bevat_de_volledige_omschrijving_en_niet_de_afgekapte(): void
    {
        $lang = 'De herbeoordeling van leveranciers is twee kwartalen blijven liggen, '
            .'waardoor de contractuele beveiligingseisen niet zijn getoetst bij drie partijen.';

        Afwijking::create(['omschrijving' => $lang, 'bron' => 'incident', 'status' => 'open']);

        $component = Livewire::actingAs($this->ciso)->test(AfwijkingenOverzicht::class);

        $this->assertStringContainsString($lang, $this->afwijkingKopie($component->instance())->markdown());
        $component->assertSee(Str::limit($lang, 80));
    }

    /** Hetzelfde anonimiseringsschema als het risicoregister: initialen + rol. */
    public function test_de_eigenaar_van_een_afwijking_staat_als_initialen_en_rol(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Management')->create(['naam' => 'Dana Wolters']);

        Afwijking::create([
            'omschrijving' => 'Toegangsrechten niet tijdig ingetrokken.',
            'bron' => 'interne_signalering',
            'status' => 'open',
            'eigenaar_id' => $eigenaar->id,
        ]);

        $component = Livewire::actingAs($this->ciso)->test(AfwijkingenOverzicht::class);
        $markdown = $this->afwijkingKopie($component->instance())->markdown();

        $this->assertStringContainsString('DW (Management)', $markdown);
        $this->assertStringNotContainsString('Dana Wolters', $markdown);

        // Het scherm toont de naam wél; de kopie is de plek waar hij weggaat.
        $component->assertSee('Dana Wolters');
    }

    /** De test bij §4: gefilterd meegeven mag, verzwijgen dát er gefilterd is niet. */
    public function test_de_kop_noemt_het_afwijkingfilter_en_hoeveel_van_hoeveel(): void
    {
        Afwijking::create(['omschrijving' => 'Open punt.', 'bron' => 'incident', 'status' => 'open']);
        Afwijking::create(['omschrijving' => 'Afgerond punt.', 'bron' => 'incident', 'status' => 'gesloten']);
        Afwijking::create(['omschrijving' => 'Nog een open punt.', 'bron' => 'incident', 'status' => 'open']);

        $component = Livewire::actingAs($this->ciso)
            ->test(AfwijkingenOverzicht::class)
            ->set('filterStatus', 'gesloten');

        $this->assertStringContainsString(
            '| Omvang | 1 van 3 regels — filter: status Gesloten. |',
            $this->afwijkingKopie($component->instance())->markdown(),
        );
    }

    /**
     * Drie standen die op het scherm en in het document uit elkaar moeten
     * blijven: nog niets ingericht (n.v.t.), alles getoetst (0) en een gat (n).
     * Zonder dat onderscheid leest een afwijking zónder corrigerende maatregel
     * als een afgeronde behandeling — precies andersom dus.
     */
    public function test_geen_maatregelen_is_nvt_en_niet_nul(): void
    {
        $zonder = Afwijking::create([
            'omschrijving' => 'Nog geen maatregel belegd.',
            'bron' => 'interne_signalering',
            'status' => 'open',
        ]);

        $rond = Afwijking::create([
            'omschrijving' => 'Volledig afgehandeld en getoetst.',
            'bron' => 'interne_signalering',
            'status' => 'gesloten',
        ]);
        $maatregel = CorrigerendeMaatregel::create([
            'afwijking_id' => $rond->id,
            'omschrijving' => 'Herbeoordeling ingehaald',
            'eigenaar_id' => $this->ciso->id,
            'deadline' => now()->subWeek(),
            'status' => 'voltooid',
        ]);
        Effectiviteitstoets::create([
            'corrigerende_maatregel_id' => $maatregel->id,
            'uitgevoerd_op' => now(),
            'resultaat' => 'effectief',
            'uitgevoerd_door_id' => $this->ciso->id,
        ]);

        $this->assertNull($zonder->load('maatregelen.laatsteToets')->nogTeToetsen());
        $this->assertSame('n.v.t.', $zonder->nogTeToetsenLabel());
        $this->assertSame(0, $rond->load('maatregelen.laatsteToets')->nogTeToetsen());
        $this->assertSame('0', $rond->nogTeToetsenLabel());

        $component = Livewire::actingAs($this->ciso)->test(AfwijkingenOverzicht::class);
        $component->assertSee('n.v.t.');

        // In het document staat geen kleur, dus moet het woord er staan.
        $markdown = $this->afwijkingKopie($component->instance())->markdown();
        $this->assertStringContainsString('| 0 | n.v.t. |', $markdown);
        $this->assertStringContainsString('| 1 | 0 |', $markdown);
    }

    /**
     * De knop hangt op `lezen`, maar het scherm eronder eist `muteren`
     * (`routes/web.php`). In de praktijk is de CISO dus de enige die hier een
     * kopie maakt — ook voor de Auditor, die op dit scherm 403 krijgt. Dat past
     * bij het uitgangspunt van 12h: de auditor zit vóór het scherm en vraagt om
     * een kopie, hij haalt hem niet zelf op.
     */
    public function test_de_kopieknop_hangt_op_leesrecht_op_het_blok(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $component = Livewire::actingAs($this->ciso)->test(AfwijkingenOverzicht::class);
        $this->assertTrue($component->instance()->magKopieren());

        $this->actingAs($zonderRol);
        $this->assertFalse($component->instance()->magKopieren());
    }
}
