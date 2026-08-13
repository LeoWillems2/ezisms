<?php

namespace Tests\Feature;

use App\Console\Commands\GenereerTaken;
use App\Livewire\DoelgroepenOverzicht;
use App\Livewire\MijnTrainingen;
use App\Livewire\ToetsenUitzetten;
use App\Livewire\TrainingenOverzicht;
use App\Models\AuditLogregel;
use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Taak;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Support\ToetsBestanden;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BewustzijnTrainingTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    /**
     * Eigen toetsfixture op de schijf. Toetsen leven bewust als losse
     * HTML-bestanden (implementatie/10 §8), sinds 01e op de `toetsen`-disk in
     * plaats van in public/. Deze tests hangen daarom niet aan een specifiek
     * productiebestand maar zetten hun eigen fixture neer op een nepdisk.
     */
    private const TOETSFIXTURE = 'test-fixture-toets.html';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();

        Storage::fake(ToetsBestanden::DISK);
        Storage::disk(ToetsBestanden::DISK)->put(
            self::TOETSFIXTURE,
            '<!DOCTYPE html><html lang="nl"><head><title>Testtoets (fixture)</title></head><body></body></html>',
        );
    }

    /** Een module met de gegeven gebruiker als enig doelgroeplid. */
    private function moduleMetLid(Gebruiker $lid, array $attributen = []): Trainingsmodule
    {
        $module = Trainingsmodule::factory()->create($attributen);
        $doelgroep = Doelgroep::factory()->create();
        $doelgroep->gebruikers()->attach($lid);
        $module->doelgroepen()->attach($doelgroep);

        return $module;
    }

    // --- Preview van het toetsbestand --------------------------------------

    public function test_preview_knop_verschijnt_als_het_toetsbestand_bestaat(): void
    {
        Trainingsmodule::factory()->create(['toets_bestand' => self::TOETSFIXTURE]);

        Livewire::actingAs($this->ciso)
            ->test(TrainingenOverzicht::class)
            ->assertSee('Preview')
            ->assertSee(route('toetsen.voorbeeld', self::TOETSFIXTURE));
    }

    public function test_geen_preview_als_het_toetsbestand_niet_op_schijf_staat(): void
    {
        // Naam wél bekend in de module, bestand niet aanwezig — dan zou een
        // preview een 404 opleveren, dus geen knop.
        Trainingsmodule::factory()->create(['toets_bestand' => 'verwijderd.html']);

        Livewire::actingAs($this->ciso)
            ->test(TrainingenOverzicht::class)
            ->assertDontSee(route('toetsen.voorbeeld', 'verwijderd.html'));
    }

    public function test_geen_preview_voor_een_zelfregistratiemodule(): void
    {
        Trainingsmodule::factory()->create(['toets_bestand' => null]);

        Livewire::actingAs($this->ciso)
            ->test(TrainingenOverzicht::class)
            ->assertDontSee('Preview');
    }

    // --- Afleiding (§5) ----------------------------------------------------

    public function test_status_wordt_afgeleid_uit_voltooiingen(): void
    {
        $lid = Gebruiker::factory()->create();
        $module = $this->moduleMetLid($lid);

        $this->assertSame('te_doen', $module->statusVoor($lid));

        $module->registreerVoltooiing($lid, 'zelfregistratie');
        $this->assertSame('voltooid', $module->fresh()->statusVoor($lid));

        Trainingsvoltooiing::query()->update([
            'voltooid_op' => Carbon::today()->subMonths(13),
            'verloopt_op' => Carbon::today()->subMonth(),
        ]);
        $this->assertSame('verlopen', $module->fresh()->statusVoor($lid->fresh()));
    }

    public function test_verloopt_op_volgt_de_geldigheidsduur(): void
    {
        $lid = Gebruiker::factory()->create();

        $module = $this->moduleMetLid($lid, ['geldigheidsduur_maanden' => 6]);
        $voltooiing = $module->registreerVoltooiing($lid, 'zelfregistratie');
        $this->assertEquals(Carbon::today()->addMonths(6), $voltooiing->verloopt_op);

        $eenmalig = $this->moduleMetLid($lid, ['geldigheidsduur_maanden' => null]);
        $this->assertNull($eenmalig->registreerVoltooiing($lid, 'zelfregistratie')->verloopt_op);
    }

    public function test_trainingsgraad_rekent_over_de_actieve_doelgroep(): void
    {
        $module = Trainingsmodule::factory()->create();
        $doelgroep = Doelgroep::factory()->create();
        $module->doelgroepen()->attach($doelgroep);

        $leeg = Trainingsmodule::factory()->create();
        $this->assertNull($leeg->trainingsgraad());

        [$a, $b] = Gebruiker::factory()->count(2)->create();
        $doelgroep->gebruikers()->attach([$a->id, $b->id]);
        $this->assertSame(0, $module->trainingsgraad());

        $module->registreerVoltooiing($a, 'zelfregistratie');
        $this->assertSame(50, $module->fresh()->trainingsgraad());
    }

    public function test_gedeactiveerde_gebruiker_valt_uit_de_telling(): void
    {
        $lid = Gebruiker::factory()->create();
        $module = $this->moduleMetLid($lid);

        $this->assertSame(0, $module->trainingsgraad());

        $lid->update(['status' => 'gedeactiveerd']);
        $this->assertNull($module->fresh()->trainingsgraad());
    }

    // --- Zelfregistratie (§6) ----------------------------------------------

    public function test_medewerker_meldt_eigen_module_voltooid(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $module = $this->moduleMetLid($medewerker);

        Livewire::actingAs($medewerker)
            ->test(MijnTrainingen::class)
            ->call('meldVoltooid', $module->id);

        $this->assertDatabaseHas('trainingsvoltooiingen', [
            'trainingsmodule_id' => $module->id,
            'gebruiker_id' => $medewerker->id,
            'bron' => 'zelfregistratie',
        ]);
    }

    public function test_zelfregistratie_op_module_met_toets_wordt_geweigerd(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $module = $this->moduleMetLid($medewerker, ['toets_bestand' => self::TOETSFIXTURE]);

        Livewire::actingAs($medewerker)
            ->test(MijnTrainingen::class)
            ->call('meldVoltooid', $module->id);

        $this->assertDatabaseCount('trainingsvoltooiingen', 0);
    }

    public function test_medewerker_kan_niet_voor_een_ander_of_buiten_doelgroep_melden(): void
    {
        $buitenstaander = Gebruiker::factory()->metRol('Medewerker')->create();
        $ander = Gebruiker::factory()->create();
        $module = $this->moduleMetLid($ander); // buitenstaander zit er niet in

        Livewire::actingAs($buitenstaander)
            ->test(MijnTrainingen::class)
            ->call('meldVoltooid', $module->id)
            ->assertForbidden();

        $this->assertDatabaseCount('trainingsvoltooiingen', 0);
    }

    // --- Autorisatie (§11) -------------------------------------------------

    public function test_bouwhulp_alleen_voor_ciso_met_downloadbare_functie(): void
    {
        // De CISO ziet de bouwhulp-tab en de pagina met de download.
        $this->actingAs($this->ciso)->get('/mijn-trainingen')
            ->assertOk()
            ->assertSee(route('toetsen.bouwhulp'));

        $this->actingAs($this->ciso)->get('/toetsen/bouwhulp')
            ->assertOk()
            ->assertSee('onQuizVoltooid');

        // De download levert de JS-functie als bestand.
        $this->actingAs($this->ciso)->get('/toetsen/bouwhulp/onquizvoltooid.js')
            ->assertOk()
            ->assertDownload('onQuizVoltooid.js');

        // De Medewerker mag er niet bij en ziet de tab niet.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $this->actingAs($medewerker)->get('/mijn-trainingen')
            ->assertOk()
            ->assertDontSee(route('toetsen.bouwhulp'));
        $this->actingAs($medewerker)->get('/toetsen/bouwhulp')->assertForbidden();
        $this->actingAs($medewerker)->get('/toetsen/bouwhulp/onquizvoltooid.js')->assertForbidden();
    }

    public function test_subnav_toont_beheerlinks_alleen_aan_wie_ze_mag(): void
    {
        // De CISO bereikt de beheerschermen via de subnav op elke blok-10-pagina.
        $this->actingAs($this->ciso)->get('/mijn-trainingen')
            ->assertOk()
            ->assertSee('Toetsen uitzetten')
            ->assertSee(route('toetsen.uitzetten'));

        // De Medewerker ziet die beheerlink niet.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $this->actingAs($medewerker)->get('/mijn-trainingen')
            ->assertOk()
            ->assertDontSee(route('toetsen.uitzetten'));
    }

    public function test_auditor_leest_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/trainingen')->assertOk();
        $this->actingAs($auditor)->get('/toetsen/resultaten')->assertOk();

        Livewire::actingAs($auditor)
            ->test(TrainingenOverzicht::class)
            ->set('titel', 'Nieuw')
            ->call('opslaan')
            ->assertForbidden();
    }

    public function test_ciso_beheert_modules_en_doelgroepen(): void
    {
        $doelgroep = Doelgroep::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(TrainingenOverzicht::class)
            ->set('titel', 'Phishing-awareness')
            ->set('geldigheidsduurMaanden', '12')
            ->set('geselecteerdeDoelgroepen', [$doelgroep->id])
            ->call('opslaan');

        $this->assertDatabaseHas('trainingsmodules', ['titel' => 'Phishing-awareness']);
        $this->assertSame(
            '1 gekoppeld: '.$doelgroep->naam,
            $this->laatsteKoppelregel('trainingsmodule', 'doelgroepen'),
        );

        $lid = Gebruiker::factory()->create();
        Livewire::actingAs($this->ciso)
            ->test(DoelgroepenOverzicht::class)
            ->set('naam', 'IT-beheerders')
            ->set('leden', [$lid->id])
            ->call('opslaan');

        $this->assertDatabaseHas('doelgroepen', ['naam' => 'IT-beheerders']);

        // Wie in welke doelgroep zit bepaalt wie welke training moest volgen (06b §1).
        $this->assertSame('1 gekoppeld: '.$lid->naam, $this->laatsteKoppelregel('doelgroep', 'leden'));
    }

    // --- Toetsen uitzetten (§8) --------------------------------------------

    public function test_losse_toets_uitzetten_maakt_taak_en_opdracht_en_slaat_dubbele_over(): void
    {
        $lid = Gebruiker::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(ToetsenUitzetten::class)
            ->set('bron', 'los')
            ->set('losseToets', self::TOETSFIXTURE)
            ->set('geselecteerdeGebruikers', [$lid->id])
            ->set('weken', 2)
            ->call('uitzetten');

        $this->assertDatabaseCount('toetsopdrachten', 1);
        $opdracht = Toetsopdracht::first();
        // De titel is een snapshot van de <title> van het echte bestand.
        $this->assertSame(ToetsBestanden::titelVoor(self::TOETSFIXTURE), $opdracht->toets_titel);
        $this->assertSame(64, strlen($opdracht->token));
        $this->assertNull($opdracht->trainingsmodule_id); // losse toets, geen koppeling
        $this->assertSame($lid->id, $opdracht->taak->eigenaar_id);

        // Tweede keer: al openstaand ⇒ overgeslagen, geen tweede opdracht.
        Livewire::actingAs($this->ciso)
            ->test(ToetsenUitzetten::class)
            ->set('bron', 'los')
            ->set('losseToets', self::TOETSFIXTURE)
            ->set('geselecteerdeGebruikers', [$lid->id])
            ->call('uitzetten');

        $this->assertDatabaseCount('toetsopdrachten', 1);
    }

    /**
     * Een losse toets hangt aan geen enkele module, en de modulelijst op
     * /mijn-trainingen loopt via de doelgroepen. Zonder eigen sectie was hij
     * daar dus onvindbaar en zag de ontvanger hem alleen nog op /taken.
     */
    public function test_losse_toets_staat_op_mijn_trainingen(): void
    {
        $lid = Gebruiker::factory()->metRol('Medewerker')->create();
        $opdracht = $this->opdrachtVoor($lid);

        $this->actingAs($lid)->get('/mijn-trainingen')
            ->assertOk()
            ->assertSee('Losse toetsen')
            ->assertSee($opdracht->toets_titel)
            ->assertSee($opdracht->deelnemerUrl(), false);
    }

    public function test_de_losse_toets_van_een_ander_blijft_onzichtbaar(): void
    {
        $ander = Gebruiker::factory()->metRol('Medewerker')->create();
        $opdracht = $this->opdrachtVoor($ander);
        $lid = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($lid)->get('/mijn-trainingen')
            ->assertOk()
            ->assertDontSee('Losse toetsen')
            ->assertDontSee($opdracht->deelnemerUrl(), false);
    }

    /**
     * Een toets mét module hoort in de modulelijst en niet óók in de losse
     * sectie — anders staat hij er twee keer.
     */
    public function test_een_moduletoets_verschijnt_niet_als_losse_toets(): void
    {
        $lid = Gebruiker::factory()->metRol('Medewerker')->create();
        $module = Trainingsmodule::factory()->create(['toets_bestand' => self::TOETSFIXTURE]);
        $this->opdrachtVoor($lid, $module);

        $this->actingAs($lid)->get('/mijn-trainingen')
            ->assertOk()
            ->assertDontSee('Losse toetsen');
    }

    public function test_module_toets_uitzetten_leidt_bestand_en_koppeling_af(): void
    {
        $lid = Gebruiker::factory()->create();
        $module = Trainingsmodule::factory()->create(['toets_bestand' => self::TOETSFIXTURE]);

        Livewire::actingAs($this->ciso)
            ->test(ToetsenUitzetten::class)
            ->set('bron', 'module')
            ->set('moduleId', (string) $module->id)
            ->set('geselecteerdeGebruikers', [$lid->id])
            ->call('uitzetten');

        $opdracht = Toetsopdracht::first();
        $this->assertNotNull($opdracht);
        // Bestand én koppeling komen uit de module, niet uit een losse keuze.
        $this->assertSame(self::TOETSFIXTURE, $opdracht->toets_bestand);
        $this->assertSame($module->id, $opdracht->trainingsmodule_id);
    }

    // --- Callback (§7) -----------------------------------------------------

    private function opdrachtVoor(Gebruiker $eigenaar, ?Trainingsmodule $module = null): Toetsopdracht
    {
        $taak = Taak::factory()->create(['eigenaar_id' => $eigenaar->id]);

        return Toetsopdracht::factory()->create([
            'taak_id' => $taak->id,
            'trainingsmodule_id' => $module?->id,
        ]);
    }

    public function test_geslaagde_callback_voltooit_taak_en_schrijft_voltooiing(): void
    {
        $lid = Gebruiker::factory()->create();
        $module = Trainingsmodule::factory()->create(['toets_bestand' => self::TOETSFIXTURE, 'geldigheidsduur_maanden' => 12]);
        $opdracht = $this->opdrachtVoor($lid, $module);

        $this->postJson('/toetsen/callback/'.$opdracht->token, [
            'score' => 8, 'total' => 10, 'passed' => true,
        ])->assertOk()->assertJson(['ok' => true]);

        $opdracht->refresh();
        $this->assertSame('geslaagd', $opdracht->status);
        $this->assertSame(1, $opdracht->pogingen);
        $this->assertSame('voltooid', $opdracht->taak->fresh()->status);

        $voltooiing = Trainingsvoltooiing::where('gebruiker_id', $lid->id)->first();
        $this->assertNotNull($voltooiing);
        $this->assertSame('toets', $voltooiing->bron);
        $this->assertEquals(Carbon::today()->addMonths(12), $voltooiing->verloopt_op);
    }

    public function test_gezakte_callback_laat_taak_open(): void
    {
        $lid = Gebruiker::factory()->create();
        $opdracht = $this->opdrachtVoor($lid);

        $this->postJson('/toetsen/callback/'.$opdracht->token, [
            'score' => 3, 'total' => 10, 'passed' => false,
        ])->assertOk();

        $opdracht->refresh();
        $this->assertSame('gezakt', $opdracht->status);
        $this->assertSame(1, $opdracht->pogingen);
        $this->assertNotSame('voltooid', $opdracht->taak->fresh()->status);
        $this->assertDatabaseCount('trainingsvoltooiingen', 0);
    }

    public function test_losse_toets_zonder_module_schrijft_geen_voltooiing(): void
    {
        $lid = Gebruiker::factory()->create();
        $opdracht = $this->opdrachtVoor($lid, null);

        $this->postJson('/toetsen/callback/'.$opdracht->token, [
            'score' => 9, 'total' => 10, 'passed' => true,
        ])->assertOk();

        $this->assertSame('voltooid', $opdracht->taak->fresh()->status);
        $this->assertDatabaseCount('trainingsvoltooiingen', 0);
    }

    public function test_onbekende_token_geeft_404(): void
    {
        $this->postJson('/toetsen/callback/onbekend', [
            'score' => 1, 'total' => 10, 'passed' => false,
        ])->assertNotFound();
    }

    public function test_tweede_callback_op_voltooide_taak_wijzigt_niets(): void
    {
        $lid = Gebruiker::factory()->create();
        $opdracht = $this->opdrachtVoor($lid);

        $this->postJson('/toetsen/callback/'.$opdracht->token, ['score' => 9, 'total' => 10, 'passed' => true]);
        $eersteScore = $opdracht->fresh()->laatste_score;

        $this->postJson('/toetsen/callback/'.$opdracht->token, ['score' => 2, 'total' => 10, 'passed' => false])
            ->assertOk();

        $this->assertSame($eersteScore, $opdracht->fresh()->laatste_score);
        $this->assertSame('geslaagd', $opdracht->fresh()->status);
    }

    public function test_token_staat_niet_in_audit_trail_en_deelnemer_is_actor(): void
    {
        $lid = Gebruiker::factory()->create(['naam' => 'Deelnemer Jansen']);
        $opdracht = $this->opdrachtVoor($lid);

        $this->postJson('/toetsen/callback/'.$opdracht->token, ['score' => 9, 'total' => 10, 'passed' => true]);

        $regels = AuditLogregel::where('entiteit_type', $opdracht->getMorphClass())->get();
        $this->assertNotEmpty($regels);
        foreach ($regels as $regel) {
            $this->assertStringNotContainsString($opdracht->token, json_encode($regel->nieuwe_waarde));
            $this->assertStringNotContainsString($opdracht->token, json_encode($regel->oude_waarde));
        }
        // De laatste wijziging is door de deelnemer gelogd, niet door "Systeem".
        $this->assertSame('Deelnemer Jansen', $regels->last()->gebruiker_naam);
    }

    // --- Herinneringstaak (§9) ---------------------------------------------

    public function test_sweep_plant_en_ruimt_training_herinneringen(): void
    {
        $lid = Gebruiker::factory()->create();
        $module = $this->moduleMetLid($lid, ['geldigheidsduur_maanden' => null]);

        $this->artisan(GenereerTaken::class)->assertSuccessful();

        $taken = Taak::where('soort', 'training-herinnering')->where('eigenaar_id', $lid->id)->get();
        $this->assertCount(1, $taken);

        // Nog een run dupliceert niet.
        $this->artisan(GenereerTaken::class);
        $this->assertSame(1, Taak::where('soort', 'training-herinnering')->count());

        // Voltooide eenmalige module ⇒ herinnering opgeruimd.
        $module->registreerVoltooiing($lid, 'zelfregistratie');
        $this->artisan(GenereerTaken::class);
        $this->assertSame(0, Taak::where('soort', 'training-herinnering')
            ->whereIn('status', Taak::OPENSTAAND)->count());
    }
}
