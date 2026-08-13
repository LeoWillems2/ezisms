<?php

namespace Tests\Feature;

use App\Livewire\AssetDetail;
use App\Livewire\AssetsOverzicht;
use App\Livewire\SystemenOverzicht;
use App\Models\Asset;
use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Models\Systeem;
use Database\Seeders\BlokSeeder;
use Database\Seeders\ClassificatieschemaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetClassificatieTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, ClassificatieschemaSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    public function test_classificatieschema_is_geseed_met_twaalf_rijen(): void
    {
        $this->assertDatabaseCount('classificatieschemas', 12);
    }

    public function test_medewerker_mag_de_assetpaginas_lezen(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/assets')->assertOk();
        $this->actingAs($medewerker)->get('/systemen')->assertOk();
    }

    public function test_gebruiker_zonder_rol_wordt_geweerd(): void
    {
        $zonderRol = Gebruiker::factory()->create();

        $this->actingAs($zonderRol)->get('/assets')->assertForbidden();
    }

    public function test_ciso_kan_een_asset_toevoegen_en_wordt_doorgestuurd(): void
    {
        $eenheid = OrganisatieEenheid::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetsOverzicht::class)
            ->set('naam', 'HR-systeem')
            ->set('type', 'systeem_of_dienst')
            ->set('organisatieEenheidId', $eenheid->id)
            ->call('opslaan')
            ->assertHasNoErrors()
            ->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertSame('HR-systeem', $asset->naam);
        $this->assertSame('geregistreerd', $asset->status);
    }

    public function test_volledige_classificatie_zet_de_status_automatisch_op_actief(): void
    {
        $asset = Asset::factory()->create();
        $this->assertSame('geregistreerd', $asset->status);

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('vertrouwelijkheidsniveau', 'vertrouwelijk')
            ->set('integriteitsniveau', 'intern')
            ->set('beschikbaarheidsniveau', 'hoog')
            ->call('opslaanClassificatie')
            ->assertHasNoErrors();

        $asset->refresh();
        $this->assertSame('actief', $asset->status);
        $this->assertNotNull($asset->laatst_geclassificeerd_op);
    }

    public function test_gedeeltelijke_classificatie_laat_de_status_op_geregistreerd(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('vertrouwelijkheidsniveau', 'geheim')
            // '' en niet null: de selects binden aan een string, zodat de lege
            // waarde met een bestaande <option> overeenkomt (zie x-keuzelijst).
            ->set('integriteitsniveau', '')
            ->set('beschikbaarheidsniveau', '')
            ->call('opslaanClassificatie');

        $this->assertSame('geregistreerd', $asset->fresh()->status);
    }

    // ---- Privacy bij assets (implementatie/03b) ----

    /**
     * De regressietest van 03b §7. `persoonsgegevens` mag niet meetellen in
     * `isGeclassificeerd()`: doet het dat wel, dan activeert de observer geen
     * enkel bestaand asset meer.
     */
    public function test_privacyveld_telt_niet_mee_in_de_classificatiestatus(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('vertrouwelijkheidsniveau', 'intern')
            ->set('integriteitsniveau', 'intern')
            ->set('beschikbaarheidsniveau', 'normaal')
            ->set('persoonsgegevens', '')
            ->call('opslaanClassificatie')
            ->assertHasNoErrors();

        $asset->refresh();
        $this->assertSame('actief', $asset->status);
        $this->assertNull($asset->persoonsgegevens);
    }

    public function test_soort_persoonsgegevens_wordt_opgeslagen_met_beoordelingsdatum(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('persoonsgegevens', 'bijzonder')
            ->call('opslaanClassificatie')
            ->assertHasNoErrors();

        $asset->refresh();
        $this->assertSame('bijzonder', $asset->persoonsgegevens);
        $this->assertNotNull($asset->privacy_beoordeeld_op);
        $this->assertTrue($asset->bevatPersoonsgegevens());
    }

    public function test_leegmaken_zet_het_terug_op_onbeoordeeld_inclusief_datum(): void
    {
        $asset = Asset::factory()->create([
            'persoonsgegevens' => 'gewoon',
            'privacy_beoordeeld_op' => now(),
        ]);

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('persoonsgegevens', '')
            ->call('opslaanClassificatie');

        $asset->refresh();
        $this->assertNull($asset->persoonsgegevens);
        $this->assertNull($asset->privacy_beoordeeld_op);
        $this->assertNull($asset->bevatPersoonsgegevens());
    }

    /** 'geen' is een beoordeling, null is de afwezigheid ervan — het gap-signaal. */
    public function test_geen_persoonsgegevens_verschilt_van_onbeoordeeld(): void
    {
        $beoordeeld = Asset::factory()->create(['naam' => 'Beoordeeld', 'persoonsgegevens' => 'geen']);
        $onbeoordeeld = Asset::factory()->create(['naam' => 'Onbeoordeeld']);

        $this->assertFalse($beoordeeld->bevatPersoonsgegevens());
        $this->assertNull($onbeoordeeld->bevatPersoonsgegevens());

        Livewire::actingAs($this->ciso)
            ->test(AssetsOverzicht::class)
            ->set('filterPersoonsgegevens', 'onbeoordeeld')
            ->assertSee('Onbeoordeeld')
            ->assertDontSee('Beoordeeld')
            ->set('filterPersoonsgegevens', 'geen')
            ->assertSee('Beoordeeld')
            ->assertDontSee('Onbeoordeeld');
    }

    public function test_bijzondere_persoonsgegevens_op_intern_waarschuwt_maar_blokkeert_niet(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('vertrouwelijkheidsniveau', 'intern')
            ->set('persoonsgegevens', 'bijzonder')
            ->call('opslaanClassificatie')
            ->assertHasNoErrors()
            ->assertSee('minstens op "vertrouwelijk"');

        // Opgeslagen ondanks de waarschuwing: het ISMS legt vast wat er ís.
        $this->assertSame('bijzonder', $asset->fresh()->persoonsgegevens);
    }

    public function test_geen_waarschuwing_bij_passende_classificatie(): void
    {
        $asset = Asset::factory()->create([
            'vertrouwelijkheidsniveau' => 'vertrouwelijk',
            'persoonsgegevens' => 'bijzonder',
        ]);

        $this->assertNull($asset->privacywaarschuwing());
    }

    public function test_onbekende_soort_persoonsgegevens_wordt_geweigerd(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('persoonsgegevens', 'medisch')
            ->call('opslaanClassificatie')
            ->assertHasErrors('persoonsgegevens');
    }

    public function test_afstoten_geblokkeerd_bij_openstaande_toewijzing(): void
    {
        $asset = Asset::factory()->geclassificeerd()->create();
        $medewerker = Gebruiker::factory()->create();

        $component = Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('toewijzingGebruikerId', $medewerker->id)
            ->call('toewijzen')
            ->assertHasNoErrors();

        $component->call('afstoten');
        $this->assertNotSame('afgestoten', $asset->fresh()->status);

        // Na het registreren van de retour mag afstoten wél.
        $toewijzing = $asset->toewijzingen()->firstOrFail();
        $component->call('retourRegistreren', $toewijzing->id)
            ->call('afstoten')
            ->assertHasNoErrors();

        $this->assertSame('afgestoten', $asset->fresh()->status);
    }

    public function test_asset_aan_systeem_koppelen(): void
    {
        $asset = Asset::factory()->create();
        $systeem = Systeem::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('geselecteerdeSystemen', [$systeem->id])
            ->call('systemenOpslaan')
            ->assertHasNoErrors();

        $this->assertTrue($asset->systemen()->where('systemen.id', $systeem->id)->exists());
        $this->assertSame('1 gekoppeld: '.$systeem->naam, $this->laatsteKoppelregel('asset', 'systemen'));
    }

    public function test_buiten_gebruik_stellen(): void
    {
        $asset = Asset::factory()->geclassificeerd()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->call('buitenGebruikStellen');

        $this->assertSame('buiten_gebruik', $asset->fresh()->status);
    }

    public function test_medewerker_mag_geen_classificatie_muteren(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $asset = Asset::factory()->create();

        Livewire::actingAs($medewerker)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('vertrouwelijkheidsniveau', 'geheim')
            ->call('opslaanClassificatie')
            ->assertForbidden();

        $this->assertNull($asset->fresh()->vertrouwelijkheidsniveau);
    }

    public function test_ciso_kan_een_systeem_toevoegen(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->set('naam', 'Fileserver')
            ->set('hostingtype', 'intern')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('systemen', ['naam' => 'Fileserver', 'hostingtype' => 'intern']);
    }

    public function test_systeem_afvoeren_bewaart_rij_koppeling_en_audit(): void
    {
        $asset = Asset::factory()->create();
        $systeem = Systeem::factory()->create();
        $asset->systemen()->attach($systeem);

        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->call('afvoeren', $systeem->id)
            ->assertHasNoErrors();

        $vers = $systeem->fresh();
        // Rij blijft bestaan (geen delete), met status en datum.
        $this->assertSame('afgevoerd', $vers->status);
        $this->assertNotNull($vers->afgevoerd_op);
        // De assetkoppeling blijft als historie behouden.
        $this->assertTrue($asset->systemen()->where('systemen.id', $systeem->id)->exists());
        // En de gebeurtenis staat in de audit trail.
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'systeem',
            'entiteit_id' => $systeem->id,
            'blok_naam' => 'asset-classificatie',
        ]);
    }

    public function test_afgevoerd_systeem_verdwijnt_uit_lijst_maar_is_terug_te_halen(): void
    {
        $actief = Systeem::factory()->create(['naam' => 'Actief systeem']);
        $afgevoerd = Systeem::factory()->create(['naam' => 'Oud systeem', 'status' => 'afgevoerd']);

        // Standaard verborgen, met de toggle zichtbaar.
        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->assertSee('Actief systeem')
            ->assertDontSee('Oud systeem')
            ->set('toonAfgevoerde', true)
            ->assertSee('Oud systeem')
            ->call('heractiveren', $afgevoerd->id)
            ->assertHasNoErrors();

        $this->assertSame('in_gebruik', $afgevoerd->fresh()->status);
        $this->assertNull($afgevoerd->fresh()->afgevoerd_op);
    }

    public function test_afgevoerd_systeem_is_niet_nieuw_koppelbaar_maar_bestaande_koppeling_blijft(): void
    {
        $asset = Asset::factory()->create();
        $gekoppeld = Systeem::factory()->create(['naam' => 'Blijft gekoppeld', 'status' => 'afgevoerd']);
        $los = Systeem::factory()->create(['naam' => 'Niet meer kiesbaar', 'status' => 'afgevoerd']);
        $asset->systemen()->attach($gekoppeld);

        $component = Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset]);

        // De al gekoppelde (ook al afgevoerd) blijft in de keuzelijst; een andere
        // afgevoerde niet.
        $systeemIds = $component->viewData('systemen')->pluck('id');
        $this->assertTrue($systeemIds->contains($gekoppeld->id));
        $this->assertFalse($systeemIds->contains($los->id));
    }

    // --- Beschikbaarheidseis / A.8.14 --------------------------------------

    public function test_beschikbaarheidseis_en_redundantie_worden_opgeslagen(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->set('naam', 'Kern-DB')
            ->set('hostingtype', 'intern')
            ->set('beschikbaarheidseis', 'bedrijfskritiek')
            ->set('redundant', '1')
            ->set('redundantieToelichting', 'Gespiegeld cluster')
            ->call('opslaan')
            ->assertHasNoErrors();

        $systeem = Systeem::where('naam', 'Kern-DB')->first();
        $this->assertSame('bedrijfskritiek', $systeem->beschikbaarheidseis);
        $this->assertTrue($systeem->redundant);
        $this->assertSame('Gespiegeld cluster', $systeem->redundantie_toelichting);
        $this->assertFalse($systeem->heeftRedundantieGap());
    }

    public function test_onbekende_redundantie_wordt_als_null_opgeslagen(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->set('naam', 'Randsysteem')
            ->set('hostingtype', 'extern')
            ->set('redundant', '')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertNull(Systeem::where('naam', 'Randsysteem')->first()->redundant);
    }

    public function test_beschikbaarheid_gebruikt_de_a814_schaal(): void
    {
        // Het schema heeft beschikbaarheid nu op de systeemschaal, niet meer op
        // de vertrouwelijkheidsschaal.
        $this->assertDatabaseHas('classificatieschemas', ['dimensie' => 'beschikbaarheid', 'niveau' => 'bedrijfskritiek']);
        $this->assertDatabaseMissing('classificatieschemas', ['dimensie' => 'beschikbaarheid', 'niveau' => 'geheim']);

        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('beschikbaarheidsniveau', 'bedrijfskritiek')
            ->call('opslaanClassificatie')
            ->assertHasNoErrors();

        $this->assertSame('bedrijfskritiek', $asset->fresh()->beschikbaarheidsniveau);

        // Een gevoeligheidswaarde wordt voor beschikbaarheid niet meer geaccepteerd.
        Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('beschikbaarheidsniveau', 'geheim')
            ->call('opslaanClassificatie')
            ->assertHasErrors('beschikbaarheidsniveau');
    }

    public function test_kritiek_systeem_zonder_redundantie_is_een_gap(): void
    {
        // Expliciet 'nee' en 'onbekend' tellen allebei als gap; een afgevoerd
        // systeem niet.
        $nee = Systeem::factory()->create(['beschikbaarheidseis' => 'hoog', 'redundant' => false]);
        $onbekend = Systeem::factory()->create(['beschikbaarheidseis' => 'bedrijfskritiek', 'redundant' => null]);
        $redundant = Systeem::factory()->create(['beschikbaarheidseis' => 'bedrijfskritiek', 'redundant' => true]);
        $laag = Systeem::factory()->create(['beschikbaarheidseis' => 'normaal', 'redundant' => false]);
        $afgevoerd = Systeem::factory()->create([
            'beschikbaarheidseis' => 'bedrijfskritiek', 'redundant' => false, 'status' => 'afgevoerd',
        ]);

        $this->assertTrue($nee->heeftRedundantieGap());
        $this->assertTrue($onbekend->heeftRedundantieGap());
        $this->assertFalse($redundant->heeftRedundantieGap());
        $this->assertFalse($laag->heeftRedundantieGap());
        $this->assertFalse($afgevoerd->heeftRedundantieGap());

        Livewire::actingAs($this->ciso)
            ->test(SystemenOverzicht::class)
            ->assertSee('zonder aangetoonde redundantie');
    }
}
