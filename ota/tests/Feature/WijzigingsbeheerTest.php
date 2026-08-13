<?php

namespace Tests\Feature;

use App\Livewire\TakenOverzicht;
use App\Livewire\WijzigingDetail;
use App\Livewire\WijzigingenOverzicht;
use App\Livewire\WijzigingssjablonenBeheer;
use App\Mail\StapActueel;
use App\Models\AuditLogregel;
use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Sjabloonstap;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use App\Support\StapGeblokkeerd;
use App\Support\Stappenreeks;
use App\Support\Wijzigingsdossier;
use App\Support\Wijzigingsroutes;
use Database\Seeders\BlokSeeder;
use Database\Seeders\NotificatieregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Database\Seeders\WijzigingssjabloonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Blok 15 (Wijzigingsbeheer), implementatie/15 §12.
 */
class WijzigingsbeheerTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    /** Een sjabloon met analyse (1), goedkeuring (2), uitvoeren (3), evaluatie (4). */
    private function sjabloon(?int $terugNaar = null): Wijzigingssjabloon
    {
        $sjabloon = Wijzigingssjabloon::factory()->create(['naam' => 'Testroute']);

        Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('analyse', 1, -10)->create(['titel' => 'Beoordelen']);
        Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('goedkeuring', 2, -3)->create(['titel' => 'Autoriseren', 'bij_afkeuren_terug_naar' => $terugNaar]);
        Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('uitvoeren', 3, 0)->create(['titel' => 'Uitvoeren']);
        Sjabloonstap::factory()->for($sjabloon, 'sjabloon')
            ->type('evaluatie', 4, 7)->create(['titel' => 'Evalueren']);

        return $sjabloon->fresh('stappen');
    }

    private function dossier(bool $metTerugvalplan = true): Wijziging
    {
        $factory = Wijziging::factory();

        return ($metTerugvalplan ? $factory->metTerugvalplan() : $factory)
            ->create(['titel' => 'Upgrade HR-SaaS', 'aangemeld_door_id' => $this->ciso->id]);
    }

    private function stap(Wijziging $wijziging, string $titel): Taak
    {
        return Stappenreeks::voorEntiteit($wijziging)->firstWhere('titel', $titel);
    }

    // --- Reeks opbouwen ----------------------------------------------------

    public function test_in_behandeling_nemen_bouwt_de_reeks_met_deadlines_uit_de_offsets(): void
    {
        $wijziging = $this->dossier();
        $sjabloon = $this->sjabloon();
        $gepland = now()->addDays(20)->startOfDay();

        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon, $gepland);

        $wijziging->refresh();
        $this->assertSame('in_behandeling', $wijziging->status);
        $this->assertSame('standaard', $wijziging->zwaarte);
        $this->assertTrue($gepland->isSameDay($wijziging->gepland_op));

        $reeks = Stappenreeks::voorEntiteit($wijziging);
        $this->assertCount(4, $reeks);
        $this->assertTrue($gepland->copy()->subDays(10)->isSameDay($reeks[0]->deadline));
        $this->assertTrue($gepland->copy()->addDays(7)->isSameDay($reeks[3]->deadline));

        // Alleen de eerste stap is actueel; de goedkeuringsstap vraagt om een uitkomst.
        $this->assertSame('open', $reeks[0]->status);
        $this->assertSame('wachtend', $reeks[1]->status);
        $this->assertTrue($reeks[1]->vraagt_uitkomst);
        $this->assertFalse($reeks[2]->vraagt_uitkomst);
    }

    public function test_een_gewijzigd_sjabloon_raakt_een_lopend_dossier_niet(): void
    {
        $wijziging = $this->dossier();
        $sjabloon = $this->sjabloon();

        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon, now()->addDays(20));

        $sjabloon->stappen->first()->update(['titel' => 'Heel anders']);
        $sjabloon->update(['zwaarte' => 'ingrijpend']);

        $this->assertSame('Beoordelen', Stappenreeks::voorEntiteit($wijziging)->first()->titel);
        $this->assertSame('standaard', $wijziging->fresh()->zwaarte);
    }

    public function test_planning_verzetten_verschuift_alleen_de_onafgeronde_stappen(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        $eerste = $this->stap($wijziging, 'Beoordelen');
        $eersteDeadline = $eerste->deadline->copy();
        $eerste->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Wijzigingsdossier::verzetPlanning($wijziging, now()->addDays(30));

        $this->assertTrue($eersteDeadline->isSameDay($this->stap($wijziging, 'Beoordelen')->deadline));
        $this->assertTrue(now()->addDays(30)->isSameDay($this->stap($wijziging, 'Uitvoeren')->deadline));
    }

    public function test_dossier_dat_al_in_behandeling_is_wordt_niet_opnieuw_gestart(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        $this->expectException(RuntimeException::class);

        Wijzigingsdossier::neemInBehandeling($wijziging->fresh(), $this->sjabloon(), now()->addDays(20));
    }

    // --- Het terugvalplan is echt afdwingbaar (§6) -------------------------

    public function test_uitvoerstap_zonder_terugvalplan_wordt_geweigerd_ook_vanaf_het_takenscherm(): void
    {
        $wijziging = $this->dossier(metTerugvalplan: false);
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        // Reeks doorlopen tot de uitvoerstap actueel is.
        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        Stappenreeks::legUitkomstVast($this->stap($wijziging, 'Autoriseren'), 'goedgekeurd');

        $uitvoeren = $this->stap($wijziging, 'Uitvoeren');
        $this->assertSame('open', $uitvoeren->status);

        // Dit is het punt van §6: dezelfde stap is ook op /taken af te vinken,
        // en de controle moet ook daar gelden.
        $uitvoeren->eigenaar_id = $this->ciso->id;
        $uitvoeren->saveQuietly();

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $uitvoeren->id);

        $this->assertSame('open', $this->stap($wijziging, 'Uitvoeren')->status);

        // En met terugvalplan lukt het wel.
        $wijziging->update(['terugvalplan' => 'Snapshot terugzetten.']);

        Livewire::actingAs($this->ciso)
            ->test(TakenOverzicht::class)
            ->call('voltooien', $uitvoeren->id);

        $this->assertSame('voltooid', $this->stap($wijziging, 'Uitvoeren')->status);
    }

    public function test_stap_met_verplicht_bewijs_wordt_zonder_bewijsstuk_geweigerd(): void
    {
        $wijziging = $this->dossier();
        $sjabloon = $this->sjabloon();
        $sjabloon->stappen->first()->update(['bewijs_verplicht' => true]);

        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon->fresh('stappen'), now()->addDays(20));

        $this->expectException(StapGeblokkeerd::class);

        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);
    }

    // --- Afkeuren ----------------------------------------------------------

    public function test_afkeuren_zonder_terugsprong_wijst_de_wijziging_af(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));
        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Wijzigingsdossier::legUitkomstVast($wijziging, $this->stap($wijziging, 'Autoriseren'), 'afgekeurd');

        $this->assertSame('afgewezen', $wijziging->fresh()->status);
        $this->assertSame('wachtend', $this->stap($wijziging, 'Uitvoeren')->status);
    }

    public function test_afkeuren_met_terugsprong_zet_de_reeks_terug(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(terugNaar: 1), now()->addDays(20));
        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Wijzigingsdossier::legUitkomstVast($wijziging, $this->stap($wijziging, 'Autoriseren'), 'afgekeurd');

        $this->assertSame('in_behandeling', $wijziging->fresh()->status);
        $this->assertSame('open', $this->stap($wijziging, 'Beoordelen')->status);
        $this->assertNull($this->stap($wijziging, 'Autoriseren')->uitkomst);
    }

    // --- Spoed -------------------------------------------------------------

    public function test_de_spoedroute_zet_uitvoeren_voor_goedkeuring_zonder_uitzondering_in_de_engine(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $wijziging = $this->dossier();
        $spoed = Wijzigingssjabloon::where('zwaarte', 'spoed')->with('stappen')->firstOrFail();

        Wijzigingsdossier::neemInBehandeling($wijziging, $spoed, now());

        $reeks = Stappenreeks::voorEntiteit($wijziging);
        $this->assertSame('Uitvoeren', $reeks[0]->titel);
        $this->assertSame('open', $reeks[0]->status);
        $this->assertSame('Goedkeuring achteraf', $reeks[1]->titel);
        $this->assertSame('wachtend', $reeks[1]->status);

        // De goedkeuring staat ná de uitvoering en telt daarom mee in het
        // gap-signaal zolang zij openstaat.
        $this->assertTrue($reeks[1]->vraagt_uitkomst);
        Livewire::actingAs($this->ciso)
            ->test(WijzigingenOverzicht::class)
            ->assertViewHas('spoedZonderGoedkeuring', 1);
    }

    // --- Afsluiten ---------------------------------------------------------

    public function test_sluiten_kan_pas_als_alle_stappen_klaar_zijn(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        $this->assertNotNull(Wijzigingsdossier::belemmeringVoorSluiten($wijziging));

        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        Stappenreeks::legUitkomstVast($this->stap($wijziging, 'Autoriseren'), 'goedgekeurd');
        $this->stap($wijziging, 'Uitvoeren')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        // De uitvoerstap zet het dossier op 'uitgevoerd', met de feitelijke datum.
        Wijzigingsdossier::werkStatusBij($wijziging);
        $this->assertSame('uitgevoerd', $wijziging->fresh()->status);
        $this->assertNotNull($wijziging->fresh()->uitgevoerd_op);

        $this->stap($wijziging, 'Evalueren')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        $this->assertNull(Wijzigingsdossier::belemmeringVoorSluiten($wijziging->fresh()));

        Wijzigingsdossier::sluit($wijziging, geslaagd: true, teruggedraaid: false, evaluatie: 'Ging goed.');

        $wijziging->refresh();
        $this->assertSame('gesloten', $wijziging->status);
        $this->assertTrue($wijziging->geslaagd);
        $this->assertTrue($wijziging->isAfgerond());
    }

    public function test_een_dossier_zonder_stappen_kan_niet_gesloten_worden(): void
    {
        // Gevonden op 12-08-2026 in de ontwikkelomgeving: een dossier dat nooit
        // in behandeling was genomen liet zich sluiten, omdat een lege reeks als
        // "alles klaar" werd gelezen. Daarmee werd ook de terugvalplancontrole
        // overgeslagen — die hangt aan de uitvoerstap die er nooit was.
        $wijziging = $this->dossier(metTerugvalplan: false);

        $this->assertNotNull(Wijzigingsdossier::belemmeringVoorSluiten($wijziging));

        try {
            Wijzigingsdossier::sluit($wijziging, geslaagd: true, teruggedraaid: false, evaluatie: 'Ging goed.');
            $this->fail('Een dossier zonder stappen hoort niet gesloten te kunnen worden.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nog geen stappen', $e->getMessage());
        }

        $this->assertSame('aangemeld', $wijziging->fresh()->status);
        $this->assertSame(0, Wijziging::query()->uitgevoerdZonderTerugvalplan()->count());
    }

    public function test_een_afgerond_dossier_is_te_heropenen(): void
    {
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        $this->stap($wijziging, 'Beoordelen')->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        Stappenreeks::legUitkomstVast($this->stap($wijziging, 'Autoriseren'), 'goedgekeurd');
        $this->stap($wijziging, 'Uitvoeren')->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        $this->stap($wijziging, 'Evalueren')->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        Wijzigingsdossier::sluit($wijziging, geslaagd: true, teruggedraaid: false, evaluatie: 'Ging goed.');
        $this->assertTrue($wijziging->fresh()->isAfgerond());

        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging->fresh()])
            ->call('heropenen');

        $wijziging->refresh();
        $this->assertFalse($wijziging->isAfgerond());
        // De reeks was al doorlopen, dus het dossier hoort terug op 'uitgevoerd'.
        $this->assertSame('uitgevoerd', $wijziging->status);
        $this->assertNull($wijziging->geslaagd);
        $this->assertNull($wijziging->evaluatie);
        // De uitvoerdatum is een feit en blijft staan; het oordeel verviel.
        $this->assertNotNull($wijziging->uitgevoerd_op);
    }

    public function test_heropenen_van_een_dossier_zonder_reeks_zet_het_terug_op_aangemeld(): void
    {
        // De situatie uit de ontwikkelomgeving: ten onrechte gesloten zonder ooit
        // stappen te hebben gehad. Heropenen hoort hem terug te zetten naar het
        // punt waar de overgeslagen stap alsnog gezet kan worden.
        $wijziging = $this->dossier(metTerugvalplan: false);
        $wijziging->update(['status' => 'gesloten', 'geslaagd' => true, 'evaluatie' => 'Te vroeg gesloten.']);

        Wijzigingsdossier::heropen($wijziging);

        $this->assertSame('aangemeld', $wijziging->fresh()->status);
        $this->assertSame(0, Wijziging::query()->uitgevoerdZonderTerugvalplan()->count());
    }

    public function test_heropenen_kan_alleen_door_de_ciso_en_alleen_vanuit_een_eindstand(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $wijziging = $this->dossier();
        $wijziging->update(['status' => 'gesloten']);

        Livewire::actingAs($medewerker)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('heropenen')
            ->assertStatus(403);

        $lopend = $this->dossier();

        $this->expectException(RuntimeException::class);
        Wijzigingsdossier::heropen($lopend);
    }

    /**
     * Het scherm hoort ná een actie de verse stand te tonen, niet die van het
     * begin van het request.
     *
     * Gemeld op 12-08-2026: de reeks zat in een computed property en werd dus
     * één keer per request opgehaald. Gevolg 1 — een geblokkeerde stap kwam tóch
     * op "voltooid" te staan, want `update()` zet de attributen vóórdat de
     * observer gooit. Gevolg 2 — na een geslaagde stap bleef de volgende op
     * "wachtend" staan tot de gebruiker de pagina herlaadde.
     */
    public function test_het_scherm_toont_na_elke_stapactie_de_verse_reeks(): void
    {
        $wijziging = $this->dossier(metTerugvalplan: false);
        $sjabloon = $this->sjabloon();
        $sjabloon->stappen->first()->update(['bewijs_verplicht' => true]);

        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon->fresh('stappen'), now()->addDays(20));

        $eerste = $this->stap($wijziging, 'Beoordelen');

        // Geblokkeerd: de stap hoort open te blijven staan, ook op het scherm.
        $component = Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('stapVoltooien', $eerste->id);

        $component->assertViewHas('stappen', fn ($stappen) => $stappen->firstWhere('id', $eerste->id)->status === 'open');
        $this->assertSame('open', $this->stap($wijziging, 'Beoordelen')->status);

        // En na een geslaagde afronding staat de vólgende stap meteen open,
        // zonder dat de pagina herladen hoeft te worden. De stap vroeg om
        // onderbouwing, dus die komt er eerst.
        BewijsKoppeling::create([
            'bewijsstuk_id' => Bewijsstuk::factory()->create()->id,
            'blok_naam' => 'wijzigingsbeheer',
            'entiteit_type' => 'wijziging',
            'entiteit_id' => $wijziging->id,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging->fresh()])
            ->call('stapVoltooien', $eerste->id)
            ->assertViewHas('stappen', function ($stappen) use ($eerste) {
                return $stappen->firstWhere('id', $eerste->id)->status === 'voltooid'
                    && $stappen->firstWhere('titel', 'Autoriseren')->status === 'open';
            });
    }

    // --- Toewijzen ---------------------------------------------------------

    /**
     * Wie een stap doet, blijkt pas als de wijziging er is — niet bij het
     * inrichten van het sjabloon. Zonder eigenaar staat een stap bij niemand op
     * het bord en gaat er geen bericht uit.
     */
    public function test_ciso_wijst_stappen_toe_nadat_het_dossier_er_is(): void
    {
        $this->seed(NotificatieregelSeeder::class);
        Mail::fake();

        $beheerder = Gebruiker::factory()->metRol('Medewerker')->create(['naam' => 'Aafke Beheerder']);
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        // Het sjabloon zet geen eigenaren, dus de reeks begint leeg.
        $component = Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging]);

        $component->assertViewHas('zonderEigenaar', 4);

        $eerste = $this->stap($wijziging, 'Beoordelen');
        $component->call('wijsToe', $eerste->id, (string) $beheerder->id);

        $this->assertSame($beheerder->id, $this->stap($wijziging, 'Beoordelen')->eigenaar_id);

        // De stap stond al open, dus de nieuwe eigenaar hoort het meteen te weten.
        Mail::assertSent(StapActueel::class, fn (StapActueel $mail) => $mail->hasTo($beheerder->email));

        // En hij verschijnt bij hem in de takenlijst.
        Livewire::actingAs($beheerder)
            ->test(TakenOverzicht::class)
            ->assertSee('Beoordelen');
    }

    public function test_toewijzen_aan_een_wachtende_stap_stuurt_nog_geen_bericht(): void
    {
        $this->seed(NotificatieregelSeeder::class);
        Mail::fake();

        $beheerder = Gebruiker::factory()->metRol('Medewerker')->create();
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        // 'Uitvoeren' is nog niet aan de beurt; het bericht komt pas als de reeks
        // daar is (07b §9), anders krijgt iemand twee keer hetzelfde signaal.
        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('wijsToe', $this->stap($wijziging, 'Uitvoeren')->id, (string) $beheerder->id);

        Mail::assertNothingSent();
        $this->assertSame($beheerder->id, $this->stap($wijziging, 'Uitvoeren')->eigenaar_id);
    }

    public function test_een_voltooide_stap_wordt_niet_opnieuw_toegewezen(): void
    {
        $beheerder = Gebruiker::factory()->metRol('Medewerker')->create();
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        $eerste = $this->stap($wijziging, 'Beoordelen');
        $eerste->update(['status' => 'voltooid', 'voltooid_op' => now()]);

        // Anders zegt de trail dat iemand anders hem heeft afgerond.
        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('wijsToe', $eerste->id, (string) $beheerder->id)
            ->assertStatus(422);
    }

    public function test_medewerker_wijst_geen_stappen_toe(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        Livewire::actingAs($medewerker)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('wijsToe', $this->stap($wijziging, 'Beoordelen')->id, (string) $medewerker->id)
            ->assertStatus(403);
    }

    // --- Autorisatie en schermen -------------------------------------------

    public function test_medewerker_ziet_het_register_en_meldt_maar_muteert_niet(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $wijziging = $this->dossier();

        // Geen record-scoping (§5): een Medewerker ziet ook dossiers van anderen.
        Livewire::actingAs($medewerker)
            ->test(WijzigingenOverzicht::class)
            ->assertSee('Upgrade HR-SaaS');

        $this->assertTrue($medewerker->can('heeft-niveau', ['wijzigingsbeheer', 'uitvoeren']));
        $this->assertFalse($medewerker->can('heeft-niveau', ['wijzigingsbeheer', 'muteren']));

        Livewire::actingAs($medewerker)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('neemInBehandeling')
            ->assertStatus(403);
    }

    public function test_auditor_leest_en_exporteert_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->assertTrue($auditor->can('heeft-niveau', ['wijzigingsbeheer', 'lezen']));
        $this->assertTrue($auditor->can('heeft-niveau', ['wijzigingsbeheer', 'exporteren']));
        $this->assertFalse($auditor->can('heeft-niveau', ['wijzigingsbeheer', 'muteren']));
    }

    public function test_een_gesloten_dossier_is_read_only(): void
    {
        $wijziging = $this->dossier();
        $wijziging->update(['status' => 'gesloten']);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->call('dossierOpslaan')
            ->assertStatus(422);
    }

    public function test_het_detailscherm_toont_de_hele_reeks_en_koppelt_systemen(): void
    {
        $wijziging = $this->dossier();
        $systeem = Systeem::factory()->create(['naam' => 'Personeelssysteem']);
        Wijzigingsdossier::neemInBehandeling($wijziging, $this->sjabloon(), now()->addDays(20));

        Livewire::actingAs($this->ciso)
            ->test(WijzigingDetail::class, ['wijziging' => $wijziging])
            ->assertSee('Beoordelen')
            ->assertSee('Autoriseren')
            ->assertSee('Uitvoeren')
            ->set('systeemIds', [$systeem->id])
            ->call('dossierOpslaan');

        $this->assertTrue($wijziging->fresh()->systemen->contains($systeem));

        // Via Koppeling::sync, dus de koppeling staat in de trail (06b).
        $this->assertTrue(
            AuditLogregel::where('entiteit_type', 'wijziging')
                ->where('entiteit_id', $wijziging->id)
                ->get()
                ->contains(fn (AuditLogregel $r) => array_key_exists('geraakte systemen', $r->nieuwe_waarde ?? [])),
        );
    }

    /**
     * Blok 3 kent blok 15 niet: een systeem afvoeren maakt geen dossier aan. Dit
     * signaal maakt dat gat zichtbaar in plaats van het te laten bestaan
     * (implementatie/15 §16).
     */
    public function test_afgevoerd_systeem_zonder_afvoerdossier_wordt_gesignaleerd(): void
    {
        $zonder = Systeem::factory()->create([
            'naam' => 'Oude urenregistratie',
            'status' => 'afgevoerd',
            'afgevoerd_op' => now()->subMonth(),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingenOverzicht::class)
            ->assertViewHas('afgevoerdZonderDossier', fn ($s) => $s->contains('id', $zonder->id))
            ->assertSee('Oude urenregistratie');

        // Mét een afgerond afvoerdossier verdwijnt het signaal.
        $dossier = Wijziging::factory()->create(['soort' => 'afvoer', 'status' => 'gesloten']);
        $dossier->systemen()->attach($zonder);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingenOverzicht::class)
            ->assertViewHas('afgevoerdZonderDossier', fn ($s) => $s->isEmpty());
    }

    public function test_het_afvoersignaal_kijkt_niet_verder_terug_dan_de_auditperiode(): void
    {
        // Anders kan het bij een bestaande installatie nooit op nul komen, en dan
        // is het geen signaal meer maar een vaste rode balk.
        Systeem::factory()->create([
            'status' => 'afgevoerd',
            'afgevoerd_op' => now()->subMonths(Systeem::SIGNAALPERIODE_MAANDEN + 1),
        ]);

        // Ingelezen historie zonder datum telt evenmin mee.
        Systeem::factory()->create(['status' => 'afgevoerd', 'afgevoerd_op' => null]);

        $this->assertSame(0, Systeem::query()->afgevoerdZonderDossier()->count());
    }

    public function test_een_lopend_afvoerdossier_dempt_het_signaal_nog_niet(): void
    {
        // Pas een afgerond dossier toont dat toegang, gegevens en contract zijn
        // afgehandeld; een dossier dat nog loopt zegt daar niets over.
        $systeem = Systeem::factory()->create(['status' => 'afgevoerd', 'afgevoerd_op' => now()->subWeek()]);
        $dossier = Wijziging::factory()->create(['soort' => 'afvoer', 'status' => 'in_behandeling']);
        $dossier->systemen()->attach($systeem);

        $this->assertSame(1, Systeem::query()->afgevoerdZonderDossier()->count());
    }

    public function test_gap_signalen_tellen_uitvoering_zonder_terugvalplan(): void
    {
        Wijziging::factory()->create(['status' => 'uitgevoerd', 'terugvalplan' => null]);
        Wijziging::factory()->metTerugvalplan()->create(['status' => 'uitgevoerd']);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingenOverzicht::class)
            ->assertViewHas('zonderTerugvalplan', 1);
    }

    // --- Sjabloonbeheer ----------------------------------------------------

    public function test_ciso_beheert_sjabloonstappen_en_dat_komt_in_de_audit_trail(): void
    {
        $sjabloon = $this->sjabloon();

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('nieuweStap', $sjabloon->id)
            ->set('titel', 'Documentatie bijwerken')
            ->set('staptype', 'analyse')
            ->set('volgorde', 5)
            ->set('deadlineOffsetDagen', 3)
            ->call('stapOpslaan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sjabloonstappen', ['titel' => 'Documentatie bijwerken']);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'sjabloonstap',
            'blok_naam' => 'wijzigingsbeheer',
            'actie' => 'aangemaakt',
        ]);
    }

    /**
     * De belofte uit deelproduct 15 §2: de stappenreeks is data, dus een
     * organisatie richt na uitrol zelf een vijfde route in. Zonder dit scherm
     * moet daar alsnog een ontwikkelaar aan te pas komen.
     */
    public function test_ciso_maakt_een_eigen_sjabloon_aan_en_bewerkt_het(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('nieuwSjabloon')
            ->set('sjabloonNaam', 'Configuratiewijziging — licht')
            ->set('sjabloonSoort', 'configuratie')
            ->set('sjabloonZwaarte', 'standaard')
            ->call('sjabloonOpslaan')
            ->assertHasNoErrors();

        $sjabloon = Wijzigingssjabloon::where('naam', 'Configuratiewijziging — licht')->firstOrFail();
        $this->assertSame('configuratie', $sjabloon->soort);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('bewerkSjabloon', $sjabloon->id)
            ->set('sjabloonZwaarte', 'ingrijpend')
            ->call('sjabloonOpslaan')
            ->assertHasNoErrors();

        $this->assertSame('ingrijpend', $sjabloon->fresh()->zwaarte);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'wijzigingssjabloon',
            'blok_naam' => 'wijzigingsbeheer',
            'actie' => 'aangemaakt',
        ]);
    }

    public function test_een_dubbele_sjabloonnaam_wordt_geweigerd(): void
    {
        $this->sjabloon();

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('nieuwSjabloon')
            ->set('sjabloonNaam', 'Testroute')
            ->call('sjabloonOpslaan')
            ->assertHasErrors('sjabloonNaam');
    }

    public function test_een_gebruikt_sjabloon_wordt_niet_verwijderd(): void
    {
        $wijziging = $this->dossier();
        $sjabloon = $this->sjabloon();
        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon, now()->addDays(20));

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderSjabloon', $sjabloon->id);

        $this->assertDatabaseHas('wijzigingssjablonen', ['id' => $sjabloon->id]);

        // Ongebruikt mag wel weg.
        $ongebruikt = Wijzigingssjabloon::factory()->create(['naam' => 'Nooit gebruikt']);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderSjabloon', $ongebruikt->id);

        $this->assertDatabaseMissing('wijzigingssjablonen', ['id' => $ongebruikt->id]);
    }

    /**
     * De kern van §17: een lopend dossier draait op bevroren waarden. Een stap
     * uit het sjabloon verwijderen mag daarom, en de terugvalplancontrole van §6
     * blijft daarna gewoon staan.
     */
    public function test_een_verwijderde_sjabloonstap_raakt_een_lopend_dossier_niet(): void
    {
        $wijziging = $this->dossier(metTerugvalplan: false);
        $sjabloon = $this->sjabloon();
        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon, now()->addDays(20));

        $uitvoerstap = $sjabloon->stappen->firstWhere('staptype', 'uitvoeren');

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderStap', $uitvoerstap->id);

        $this->assertDatabaseMissing('sjabloonstappen', ['id' => $uitvoerstap->id]);

        $lopendeStap = $this->stap($wijziging, 'Uitvoeren');
        $this->assertNull($lopendeStap->sjabloonstap, 'De herkomstverwijzing vervalt.');
        $this->assertSame('uitvoeren', $lopendeStap->staptype, 'Het staptype staat bevroren op de taak.');

        // En de controle die eraan hangt werkt nog steeds.
        $this->assertNotNull($wijziging->belemmeringVoorStap($lopendeStap));
    }

    /**
     * Het sluitstuk van §17: het sjabloon aanpassen verandert niets aan een
     * dossier dat al loopt — ook niet aan de eisen die aan een stap hangen.
     */
    public function test_een_versoepeld_sjabloon_zet_een_lopende_controle_niet_uit(): void
    {
        $wijziging = $this->dossier(metTerugvalplan: false);
        $sjabloon = $this->sjabloon();
        $sjabloon->stappen->first()->update(['bewijs_verplicht' => true]);

        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon->fresh('stappen'), now()->addDays(20));

        // Het sjabloon wordt daarna volledig versoepeld.
        $sjabloon->stappen->first()->update(['bewijs_verplicht' => false]);
        $sjabloon->stappen->firstWhere('staptype', 'uitvoeren')->update(['staptype' => 'analyse']);
        $sjabloon->stappen->firstWhere('staptype', 'goedkeuring')?->update(['bij_afkeuren_terug_naar' => null]);

        $eerste = $this->stap($wijziging, 'Beoordelen');
        $this->assertTrue($eerste->bewijs_verplicht);
        $this->assertNotNull($wijziging->belemmeringVoorStap($eerste), 'De bewijsplicht blijft gelden.');

        $this->assertSame('uitvoeren', $this->stap($wijziging, 'Uitvoeren')->staptype);
    }

    public function test_planning_verzetten_werkt_zonder_het_sjabloon(): void
    {
        // De deadlines schuiven met het verschil mee en worden niet opnieuw uit
        // het sjabloon berekend (§17).
        $wijziging = $this->dossier();
        $sjabloon = $this->sjabloon();
        Wijzigingsdossier::neemInBehandeling($wijziging, $sjabloon, now()->addDays(20));

        $voor = $this->stap($wijziging, 'Uitvoeren')->deadline->copy();

        // Het sjabloon verandert zijn offsets én verdwijnt daarna helemaal.
        $sjabloon->stappen->each(fn ($s) => $s->update(['deadline_offset_dagen' => 999]));
        $sjabloon->stappen->each->delete();

        Wijzigingsdossier::verzetPlanning($wijziging->fresh(), now()->addDays(27));

        $this->assertTrue(
            $voor->addDays(7)->isSameDay($this->stap($wijziging, 'Uitvoeren')->deadline),
            'De deadline hoort zeven dagen mee te schuiven, niet uit het sjabloon te komen.',
        );
    }

    // --- Geleverde routes (§19) --------------------------------------------

    /**
     * `db:seed --force` draait bij elke containerstart mee (`deploy-docker.sh`).
     * Met `updateOrCreate` draaide dat elke aanpassing van de organisatie
     * stilzwijgend terug — en een hernoemde stap kwam er dubbel bij te staan.
     */
    public function test_de_seeder_overschrijft_geen_aanpassingen_van_de_organisatie(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $route = Wijzigingssjabloon::where('naam', 'Configuratiewijziging')->with('stappen')->firstOrFail();
        $aantalVoor = $route->stappen->count();

        $route->stappen->first()->update(['titel' => 'Eigen eerste stap', 'deadline_offset_dagen' => -3]);
        $route->stappen->last()->delete();
        $route->update(['zwaarte' => 'ingrijpend']);

        $this->seed(WijzigingssjabloonSeeder::class);

        $route->refresh()->load('stappen');
        $this->assertSame($aantalVoor - 1, $route->stappen->count(), 'De verwijderde stap hoort weg te blijven.');
        $this->assertSame('Eigen eerste stap', $route->stappen->first()->titel);
        $this->assertSame('ingrijpend', $route->zwaarte);
    }

    public function test_een_aangepaste_geleverde_route_is_terug_te_zetten(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $route = Wijzigingssjabloon::where('naam', 'Configuratiewijziging')->with('stappen')->firstOrFail();
        $geleverdAantal = $route->stappen->count();

        $this->assertTrue($route->geleverd);
        $this->assertFalse($route->isAangepast());

        $route->stappen->firstWhere('staptype', 'goedkeuring')->delete();
        $route->refresh()->load('stappen');

        $this->assertTrue($route->isAangepast());
        $this->assertArrayHasKey('goedkeuring', $route->ontbrekendeStaptypen());

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('zetTerug', $route->id);

        $route->refresh()->load('stappen');
        $this->assertSame($geleverdAantal, $route->stappen->count());
        $this->assertFalse($route->isAangepast());
        $this->assertSame([], $route->ontbrekendeStaptypen());
    }

    public function test_terugzetten_raakt_een_lopend_dossier_niet(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $route = Wijzigingssjabloon::where('naam', 'Configuratiewijziging')->with('stappen')->firstOrFail();
        $wijziging = $this->dossier();
        Wijzigingsdossier::neemInBehandeling($wijziging, $route, now()->addDays(20));

        $route->stappen->first()->delete();
        Wijzigingsroutes::zetTerug($route->refresh()->load('stappen'));

        // De reeks draait op bevroren waarden (§17); alleen de herkomst vervalt.
        $stap = Stappenreeks::voorEntiteit($wijziging)->firstWhere('staptype', 'uitvoeren');
        $this->assertNotNull($stap);
        $this->assertNull($stap->sjabloonstap, 'De herkomstverwijzing vervalt.');

        // Zonder terugvalplan blijft de bevroren eis gelden, ook al is de
        // sjabloonstap eronder vervangen.
        $wijziging->update(['terugvalplan' => null]);
        $this->assertNotNull($wijziging->belemmeringVoorStap($stap), 'De bevroren eisen blijven gelden.');
    }

    /**
     * `deleteGeaudit()` is een query-builder-macro. Op een model valt hij via
     * `__call` door naar een verse query zónder sleutel, en dan verdwijnt de hele
     * tabel. Dat gebeurde tot 12-08-2026 bij het verwijderen van één stap.
     */
    public function test_een_stap_verwijderen_laat_de_andere_stappen_staan(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $route = Wijzigingssjabloon::where('naam', 'Configuratiewijziging')->with('stappen')->firstOrFail();
        $aantalVoor = $route->stappen->count();
        $totaalVoor = Sjabloonstap::count();
        $teVerwijderen = $route->stappen->firstWhere('staptype', 'informeren');

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderStap', $teVerwijderen->id);

        $this->assertSame($aantalVoor - 1, $route->refresh()->load('stappen')->stappen->count());
        $this->assertSame($totaalVoor - 1, Sjabloonstap::count(), 'Alleen déze stap hoort weg te zijn.');
    }

    public function test_een_eigen_sjabloon_verwijderen_laat_de_rest_staan(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $aantalVoor = Wijzigingssjabloon::count();
        $eigen = Wijzigingssjabloon::factory()->create(['naam' => 'Eigen route', 'geleverd' => false]);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderSjabloon', $eigen->id);

        $this->assertSame($aantalVoor, Wijzigingssjabloon::count(), 'Alleen de eigen route hoort weg te zijn.');
    }

    public function test_een_geleverde_route_wordt_niet_verwijderd(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        $route = Wijzigingssjabloon::where('geleverd', true)->firstOrFail();

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderSjabloon', $route->id);

        $this->assertDatabaseHas('wijzigingssjablonen', ['id' => $route->id]);

        // Een eigen route mag wel weg.
        $eigen = Wijzigingssjabloon::factory()->create(['naam' => 'Eigen route', 'geleverd' => false]);

        Livewire::actingAs($this->ciso)
            ->test(WijzigingssjablonenBeheer::class)
            ->call('verwijderSjabloon', $eigen->id);

        $this->assertDatabaseMissing('wijzigingssjablonen', ['id' => $eigen->id]);
    }

    public function test_geen_enkele_geleverde_route_klaagt_uit_zichzelf(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        foreach (Wijzigingssjabloon::with('stappen')->get() as $route) {
            $this->assertSame(
                [],
                $route->ontbrekendeStaptypen(),
                "De geleverde route '{$route->naam}' mist zelf een vereist staptype.",
            );
        }
    }

    public function test_elke_soort_wijziging_heeft_een_meegeleverde_route(): void
    {
        $this->seed(WijzigingssjabloonSeeder::class);

        // Dit is de assertie die het oorspronkelijke gat had gevangen: een soort
        // zonder sjabloon is een enumwaarde die de gebruiker niet kan kiezen.
        foreach (WijzigingenOverzicht::SOORTEN as $soort) {
            $this->assertTrue(
                Wijzigingssjabloon::where('soort', $soort)->exists(),
                "Soort '{$soort}' heeft geen meegeleverd sjabloon en is dus niet te kiezen.",
            );
        }

        $this->assertSame(7, Wijzigingssjabloon::count());
        $this->assertSame(1, Wijzigingssjabloon::where('zwaarte', 'spoed')->count());

        // Idempotent: referentiedata draait ook in productie.
        $this->seed(WijzigingssjabloonSeeder::class);
        $this->assertSame(7, Wijzigingssjabloon::count());

        // Elke route eindigt op een evaluatie (A.8.32 g).
        foreach (Wijzigingssjabloon::with('stappen')->get() as $sjabloon) {
            $this->assertSame('evaluatie', $sjabloon->stappen->last()->staptype, $sjabloon->naam);
        }
    }
}
