<?php

namespace Tests\Feature;

use App\Actions\ActiveerScopeVerklaring;
use App\Livewire\AssetDetail;
use App\Livewire\BewijsPaneel;
use App\Livewire\BewijsstukkenOverzicht;
use App\Livewire\ScopeBeheer;
use App\Models\Asset;
use App\Models\AuditLogregel;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Support\Koppelbaar;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class BewijsAuditTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        Storage::fake(Bewijsstuk::DISK);
    }

    // --- Audit trail -------------------------------------------------------

    public function test_aanmaken_en_wijzigen_worden_gelogd(): void
    {
        $this->actingAs($this->ciso);

        $risico = Risico::create(['titel' => 'Uitval fileserver']);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'risico',
            'entiteit_id' => $risico->id,
            'actie' => 'aangemaakt',
            'blok_naam' => 'risico-soa',
            'gebruiker_id' => $this->ciso->id,
            'gebruiker_naam' => $this->ciso->naam,
        ]);

        $risico->update(['titel' => 'Langdurige uitval fileserver']);

        $regel = AuditLogregel::where('actie', 'gewijzigd')->firstOrFail();
        $this->assertSame(['titel'], $regel->gewijzigdeVelden());
        $this->assertSame('Uitval fileserver', $regel->oude_waarde['titel']);
        $this->assertSame('Langdurige uitval fileserver', $regel->nieuwe_waarde['titel']);
    }

    public function test_alleen_een_statuswijziging_krijgt_een_eigen_actie(): void
    {
        $this->actingAs($this->ciso);

        $risico = Risico::factory()->beoordeeld()->create();
        $risico->update(['status' => 'gemitigeerd']);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_id' => $risico->id,
            'actie' => 'status_gewijzigd',
        ]);
    }

    public function test_wachtwoordhash_komt_niet_in_de_audit_trail(): void
    {
        $this->actingAs($this->ciso);

        $gebruiker = Gebruiker::factory()->create();
        $gebruiker->update(['wachtwoord' => 'een-nieuw-wachtwoord']);

        $regels = AuditLogregel::where('entiteit_type', 'gebruiker')->get();
        $this->assertNotEmpty($regels);

        foreach ($regels as $regel) {
            $velden = array_merge(
                array_keys($regel->oude_waarde ?? []),
                array_keys($regel->nieuwe_waarde ?? []),
            );
            $this->assertNotContains('wachtwoord', $velden);
            $this->assertNotContains('remember_token', $velden);
        }
    }

    public function test_de_omschrijving_is_een_momentopname(): void
    {
        $this->actingAs($this->ciso);

        $risico = Risico::create(['titel' => 'Oorspronkelijke titel']);
        $risico->update(['titel' => 'Hernoemd']);

        // De regel van het aanmaken houdt de titel van toen — anders is de
        // logregel als bewijs waardeloos.
        $eerste = AuditLogregel::where('entiteit_type', 'risico')->where('actie', 'aangemaakt')->firstOrFail();
        $this->assertSame('Oorspronkelijke titel', $eerste->entiteit_omschrijving);
    }

    public function test_console_context_logt_zonder_gebruiker(): void
    {
        // Geen actingAs: dit is de situatie van een geplande taak.
        $asset = Asset::factory()->create();

        $regel = AuditLogregel::where('entiteit_type', 'asset')->firstOrFail();
        $this->assertNull($regel->gebruiker_id);
        $this->assertSame('Systeem (geplande taak)', $regel->gebruiker_naam);
        $this->assertSame($asset->id, $regel->entiteit_id);
    }

    public function test_logregels_zijn_append_only(): void
    {
        $this->actingAs($this->ciso);
        Risico::create(['titel' => 'Iets']);

        $regel = AuditLogregel::firstOrFail();

        $this->expectException(RuntimeException::class);
        $regel->update(['actie' => 'verwijderd']);
    }

    public function test_logregel_verwijderen_wordt_geweigerd(): void
    {
        $this->actingAs($this->ciso);
        Risico::create(['titel' => 'Iets']);

        $this->expectException(RuntimeException::class);
        AuditLogregel::firstOrFail()->delete();
    }

    public function test_inlogpogingen_worden_niet_dubbel_gelogd(): void
    {
        // Loginpoging is zelf al een logboek; de trait erop zetten zou elke
        // poging verdubbelen en de inhoudelijke wijzigingen laten verdrinken.
        // Rechtstreeks via de factory, zodat de test niet afhangt van de
        // veldnamen van het loginformulier.
        Loginpoging::factory()->create();

        $this->assertDatabaseCount('loginpogingen', 1);
        $this->assertDatabaseMissing('audit_logregels', ['entiteit_type' => 'loginpoging']);
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_exporteren_geeft_geen_muteerrecht(): void
    {
        // De kern van de ladder-wijziging: de Auditor heeft `exporteren` op
        // blok 6, maar dat mag geen muteer- of goedkeurrecht opleveren.
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $this->actingAs($auditor);

        $this->assertTrue($auditor->can('heeft-niveau', ['bewijsrepository-audit-trail', 'exporteren']));
        $this->assertTrue($auditor->can('heeft-niveau', ['bewijsrepository-audit-trail', 'lezen']));
        $this->assertFalse($auditor->can('heeft-niveau', ['bewijsrepository-audit-trail', 'muteren']));
        $this->assertFalse($auditor->can('heeft-niveau', ['bewijsrepository-audit-trail', 'goedkeuren']));
    }

    public function test_muteren_impliceert_nog_steeds_de_lagere_niveaus(): void
    {
        $this->assertTrue($this->ciso->can('heeft-niveau', ['bewijsrepository-audit-trail', 'lezen']));
        $this->assertTrue($this->ciso->can('heeft-niveau', ['bewijsrepository-audit-trail', 'uitvoeren']));
        // ...maar exporteren staat buiten de ladder en wordt dus niet geërfd.
        $this->assertFalse($this->ciso->can('heeft-niveau', ['bewijsrepository-audit-trail', 'exporteren']));
    }

    public function test_medewerker_mag_de_audit_trail_niet_inzien(): void
    {
        // Medewerker heeft `uitvoeren`, wat `lezen` impliceert — zonder de
        // extra record-scope zou hij ieders handelen kunnen doorlezen.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get('/audit-log')->assertForbidden();
        $this->actingAs($this->ciso)->get('/audit-log')->assertOk();
        $this->actingAs(Gebruiker::factory()->metRol('Auditor')->create())->get('/audit-log')->assertOk();
    }

    public function test_medewerker_ziet_alleen_eigen_bewijsstukken(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $eigen = Bewijsstuk::factory()->create(['geupload_door' => $medewerker->id, 'naam' => 'Eigen certificaat']);
        $andermans = Bewijsstuk::factory()->create(['naam' => 'Incidentrapport directie']);

        Livewire::actingAs($medewerker)
            ->test(BewijsstukkenOverzicht::class)
            ->assertSee('Eigen certificaat')
            ->assertDontSee('Incidentrapport directie');

        $this->actingAs($medewerker)->get(route('bewijsstukken.download', $andermans))->assertForbidden();

        // De factory schrijft bewust geen bestand; voor de download moet er een staan.
        Storage::disk(Bewijsstuk::DISK)->put($eigen->opslaglocatie_referentie, 'inhoud');
        $this->actingAs($medewerker)->get(route('bewijsstukken.download', $eigen))->assertOk();
    }

    // --- Bewijsstukken -----------------------------------------------------

    public function test_upload_legt_hash_en_bewaartermijn_vast(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->set('naam', 'Directiebesluit scope')
            ->set('bestand', UploadedFile::fake()->create('besluit.pdf', 12, 'application/pdf'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $bewijsstuk = Bewijsstuk::firstOrFail();
        $this->assertSame('besluit.pdf', $bewijsstuk->bestandsnaam);
        $this->assertSame(64, strlen($bewijsstuk->bestandshash));
        $this->assertSame(now()->addYears(3)->toDateString(), $bewijsstuk->bewaren_tot->toDateString());
        Storage::disk(Bewijsstuk::DISK)->assertExists($bewijsstuk->opslaglocatie_referentie);
        $this->assertTrue($bewijsstuk->integriteitIsIntact());
    }

    public function test_gewijzigd_bestand_faalt_de_integriteitscheck(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->set('naam', 'Rapport')
            ->set('bestand', UploadedFile::fake()->create('rapport.pdf', 5, 'application/pdf'))
            ->call('opslaan');

        $bewijsstuk = Bewijsstuk::firstOrFail();
        Storage::disk(Bewijsstuk::DISK)->put($bewijsstuk->opslaglocatie_referentie, 'gemanipuleerd');

        $this->assertFalse($bewijsstuk->integriteitIsIntact());
    }

    public function test_uitvoerbaar_bestandstype_wordt_geweigerd(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->set('naam', 'Script')
            ->set('bestand', UploadedFile::fake()->create('script.zip', 5, 'application/zip'))
            ->call('opslaan')
            ->assertHasErrors('bestand');

        $this->assertDatabaseCount('bewijsstukken', 0);
    }

    public function test_bewijspaneel_koppelt_aan_een_entiteit(): void
    {
        $risico = Risico::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(BewijsPaneel::class, [
                'blokNaam' => 'risico-soa',
                'entiteitType' => 'risico',
                'entiteitId' => $risico->id,
            ])
            ->set('naam', 'Onderbouwing behandelplan')
            ->set('bestand', UploadedFile::fake()->create('plan.pdf', 8, 'application/pdf'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bewijs_koppelingen', [
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => $risico->id,
        ]);
    }

    public function test_koppelen_vereist_muteerrecht_op_het_blok_van_de_entiteit(): void
    {
        // Medewerker mag bewijs uploaden (blok 6) maar heeft geen enkel recht
        // op blok 4 — hij mag dus geen bewijs aan een risico hangen.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $risico = Risico::factory()->create();

        Livewire::actingAs($medewerker)
            ->test(BewijsPaneel::class, [
                'blokNaam' => 'risico-soa',
                'entiteitType' => 'risico',
                'entiteitId' => $risico->id,
            ])
            ->set('naam', 'Poging')
            ->set('bestand', UploadedFile::fake()->create('x.pdf', 2, 'application/pdf'))
            ->call('opslaan')
            ->assertForbidden();

        $this->assertDatabaseCount('bewijs_koppelingen', 0);
    }

    public function test_archiveertaak_zet_verstreken_bewaartermijn_om(): void
    {
        $verstreken = Bewijsstuk::factory()->create(['bewaren_tot' => now()->subDay()]);
        $lopend = Bewijsstuk::factory()->create(['bewaren_tot' => now()->addYear()]);

        $this->artisan('isms:archiveer-bewijsstukken')->assertSuccessful();

        $this->assertSame('gearchiveerd', $verstreken->fresh()->status);
        $this->assertSame('actief', $lopend->fresh()->status);
        // Archiveren verwijdert nooit — de AVG-afweging staat nog open.
        $this->assertDatabaseCount('bewijsstukken', 2);
    }

    // --- Koppelen van bestaande bewijsstukken ------------------------------

    public function test_bestaand_bewijsstuk_koppelen_vanuit_het_paneel(): void
    {
        // Precies het scenario dat eerst doodliep: een stuk dat via
        // /bewijsstukken is opgevoerd en dus ongekoppeld in de lijst stond.
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(BewijsPaneel::class, [
                'blokNaam' => 'asset-classificatie',
                'entiteitType' => 'asset',
                'entiteitId' => $asset->id,
            ])
            ->call('koppelBestaand')
            ->assertSee($bewijsstuk->naam)
            ->call('koppelBestaandBewijsstuk', $bewijsstuk->id);

        $this->assertDatabaseHas('bewijs_koppelingen', [
            'bewijsstuk_id' => $bewijsstuk->id,
            'entiteit_type' => 'asset',
            'entiteit_id' => $asset->id,
        ]);
    }

    public function test_een_bewijsstuk_kan_aan_meerdere_assets_hangen(): void
    {
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $eerste = Asset::factory()->create();
        $tweede = Asset::factory()->create();

        foreach ([$eerste, $tweede] as $asset) {
            Livewire::actingAs($this->ciso)
                ->test(BewijsPaneel::class, [
                    'blokNaam' => 'asset-classificatie',
                    'entiteitType' => 'asset',
                    'entiteitId' => $asset->id,
                ])
                ->call('koppelBestaandBewijsstuk', $bewijsstuk->id);
        }

        $this->assertSame(2, $bewijsstuk->koppelingen()->count());
        // De tweede koppeling vervangt de eerste niet.
        $this->assertSame(
            [$eerste->id, $tweede->id],
            $bewijsstuk->koppelingen()->orderBy('entiteit_id')->pluck('entiteit_id')->all()
        );
    }

    public function test_dubbel_koppelen_levert_geen_tweede_rij_op(): void
    {
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $asset = Asset::factory()->create();

        $paneel = Livewire::actingAs($this->ciso)->test(BewijsPaneel::class, [
            'blokNaam' => 'asset-classificatie',
            'entiteitType' => 'asset',
            'entiteitId' => $asset->id,
        ]);

        // De unique-constraint zou hier een 500 geven zonder firstOrCreate.
        $paneel->call('koppelBestaandBewijsstuk', $bewijsstuk->id)
            ->call('koppelBestaandBewijsstuk', $bewijsstuk->id);

        $this->assertSame(1, $bewijsstuk->koppelingen()->count());
    }

    public function test_al_gekoppelde_stukken_staan_niet_in_de_kandidatenlijst(): void
    {
        $gekoppeld = Bewijsstuk::factory()->create(['naam' => 'Al gekoppeld', 'geupload_door' => $this->ciso->id]);
        $vrij = Bewijsstuk::factory()->create(['naam' => 'Nog vrij', 'geupload_door' => $this->ciso->id]);
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->ciso)
            ->test(BewijsPaneel::class, [
                'blokNaam' => 'asset-classificatie',
                'entiteitType' => 'asset',
                'entiteitId' => $asset->id,
            ])
            ->call('koppelBestaandBewijsstuk', $gekoppeld->id)
            ->call('koppelBestaand')
            // Op de HTML asserten kan hier niet: het al gekoppelde stuk staat
            // bovenaan in de lijst met koppelingen, en dat is juist correct.
            ->assertViewHas('kandidaten', fn ($kandidaten) => $kandidaten
                ->pluck('naam')->all() === ['Nog vrij']);
    }

    public function test_koppelen_vanuit_het_overzicht(): void
    {
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $risico = Risico::factory()->create(['titel' => 'Uitval fileserver']);

        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->call('koppel', $bewijsstuk->id)
            ->set('koppelType', 'risico')
            ->set('koppelEntiteitId', $risico->id)
            ->call('koppelOpslaan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bewijs_koppelingen', [
            'bewijsstuk_id' => $bewijsstuk->id,
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => $risico->id,
        ]);
    }

    public function test_de_keuzelijst_lekt_geen_entiteiten_zonder_muteerrecht(): void
    {
        // Medewerker mag bewijs uploaden maar heeft geen muteerrecht op enig
        // bronblok; de titels van risico's mogen dus niet in de lijst staan.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $medewerker->id]);
        $risico = Risico::factory()->create(['titel' => 'Geheime dreiging']);

        $this->actingAs($medewerker);
        $this->assertSame([], Koppelbaar::toegestaneTypes());
        $this->assertSame([], Koppelbaar::opties('risico'));

        Livewire::actingAs($medewerker)
            ->test(BewijsstukkenOverzicht::class)
            ->assertDontSee('Geheime dreiging')
            ->call('koppel', $bewijsstuk->id)
            ->set('koppelType', 'risico')
            ->set('koppelEntiteitId', $risico->id)
            ->call('koppelOpslaan')
            ->assertHasErrors('koppelType');

        $this->assertDatabaseCount('bewijs_koppelingen', 0);
    }

    public function test_ontkoppelen_vanuit_het_overzicht(): void
    {
        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $asset = Asset::factory()->create();
        $koppeling = $bewijsstuk->koppelingen()->create([
            'blok_naam' => 'asset-classificatie',
            'entiteit_type' => 'asset',
            'entiteit_id' => $asset->id,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            // De kolom toont een naam, niet "asset #3".
            ->assertSee('Asset: '.$asset->naam)
            ->call('ontkoppel', $koppeling->id);

        $this->assertDatabaseCount('bewijs_koppelingen', 0);
        // Het bewijsstuk zelf blijft bestaan.
        $this->assertDatabaseCount('bewijsstukken', 1);
    }
    // --- Massa-updates omzeilen de audit trail niet ------------------------

    public function test_vervaltaak_logt_de_deactivering(): void
    {
        // Regressie: Model::where(...)->update() vuurt geen Eloquent-events en
        // schreef dus niets weg, terwijl Annex A 5.16 juist hierom vraagt.
        $gebruiker = Gebruiker::factory()->create([
            'status' => 'actief',
            'vervalt_op' => now()->subDay(),
        ]);

        $this->artisan('isms:verval-gebruikersaccounts')->assertSuccessful();

        $this->assertSame('gedeactiveerd', $gebruiker->fresh()->status);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'gebruiker',
            'entiteit_id' => $gebruiker->id,
            'actie' => 'status_gewijzigd',
            'gebruiker_naam' => 'Systeem (geplande taak)',
        ]);
    }

    public function test_vervangen_van_de_actieve_scopeversie_wordt_gelogd(): void
    {
        $this->actingAs($this->ciso);

        $oud = ScopeVerklaring::factory()->create(['status' => 'actief']);
        $nieuw = ScopeVerklaring::factory()->create(['status' => 'ter_goedkeuring']);

        app(ActiveerScopeVerklaring::class)($nieuw, 'Directie');

        $this->assertSame('vervangen', $oud->fresh()->status);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'scope_verklaring',
            'entiteit_id' => $oud->id,
            'actie' => 'status_gewijzigd',
        ]);
    }

    public function test_retour_registreren_wordt_gelogd(): void
    {
        $asset = Asset::factory()->geclassificeerd()->create();
        $medewerker = Gebruiker::factory()->create();

        $component = Livewire::actingAs($this->ciso)
            ->test(AssetDetail::class, ['asset' => $asset])
            ->set('toewijzingGebruikerId', $medewerker->id)
            ->call('toewijzen');

        $toewijzing = $asset->toewijzingen()->firstOrFail();
        $component->call('retourRegistreren', $toewijzing->id);

        $this->assertNotNull($toewijzing->fresh()->geretourneerd_op);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'asset_toewijzing',
            'entiteit_id' => $toewijzing->id,
            'actie' => 'gewijzigd',
        ]);
    }

    public function test_updategeaudit_slaat_geen_rijen_over(): void
    {
        // ->each()/chunk() pagineert tijdens het muteren en zou juist bij een
        // filter op de gewijzigde kolom rijen overslaan.
        Gebruiker::factory()->count(5)->create([
            'status' => 'actief',
            'vervalt_op' => now()->subDay(),
        ]);

        $this->artisan('isms:verval-gebruikersaccounts')->assertSuccessful();

        $this->assertSame(0, Gebruiker::where('status', 'actief')->whereNotNull('vervalt_op')->count());
    }

    public function test_upload_en_archivering_van_een_bewijsstuk_worden_gelogd(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->set('naam', 'Pentestrapport 2026')
            ->set('bestand', UploadedFile::fake()->create('pentest.pdf', 9, 'application/pdf'))
            ->call('opslaan')
            ->assertHasNoErrors();

        $bewijsstuk = Bewijsstuk::firstOrFail();

        $aangemaakt = AuditLogregel::where('entiteit_type', 'bewijsstuk')
            ->where('actie', 'aangemaakt')
            ->firstOrFail();
        $this->assertSame('Pentestrapport 2026', $aangemaakt->entiteit_omschrijving);
        // De hash hoort er juist wél in: daarmee is achteraf aantoonbaar welk
        // bestand op dat moment is geupload.
        $this->assertSame($bewijsstuk->bestandshash, $aangemaakt->nieuwe_waarde['bestandshash']);

        // Archiveren loopt via updateGeaudit en komt dus ook in het logboek.
        $bewijsstuk->update(['bewaren_tot' => now()->subDay()]);

        // Uitloggen vóór het commando: de scheduler draait zonder sessie, en
        // zonder dit staat de ingelogde CISO als veroorzaker in het logboek.
        auth()->logout();
        $this->artisan('isms:archiveer-bewijsstukken')->assertSuccessful();

        $this->assertSame('gearchiveerd', $bewijsstuk->fresh()->status);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'bewijsstuk',
            'entiteit_id' => $bewijsstuk->id,
            'actie' => 'status_gewijzigd',
            'gebruiker_naam' => 'Systeem (geplande taak)',
        ]);
    }

    public function test_koppelen_en_ontkoppelen_worden_gelogd_onder_het_blok_van_de_entiteit(): void
    {
        $bewijsstuk = Bewijsstuk::factory()->create([
            'naam' => 'Pentestrapport',
            'geupload_door' => $this->ciso->id,
        ]);
        $asset = Asset::factory()->create(['naam' => 'Fileserver']);

        $paneel = Livewire::actingAs($this->ciso)->test(BewijsPaneel::class, [
            'blokNaam' => 'asset-classificatie',
            'entiteitType' => 'asset',
            'entiteitId' => $asset->id,
        ]);

        $paneel->call('koppelBestaandBewijsstuk', $bewijsstuk->id);

        $aangemaakt = AuditLogregel::where('entiteit_type', 'bewijs_koppeling')
            ->where('actie', 'aangemaakt')
            ->firstOrFail();

        // Onder het blok van de entiteit, niet onder blok 6: een auditor die op
        // asset-classificatie filtert hoort dit te zien.
        $this->assertSame('asset-classificatie', $aangemaakt->blok_naam);
        $this->assertSame('Pentestrapport aan Asset: Fileserver', $aangemaakt->entiteit_omschrijving);

        $koppeling = $bewijsstuk->koppelingen()->firstOrFail();
        $paneel->call('ontkoppel', $koppeling->id);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'bewijs_koppeling',
            'entiteit_id' => $koppeling->id,
            'actie' => 'verwijderd',
        ]);
    }

    public function test_cascade_bij_verwijderen_van_een_bewijsstuk_logt_de_koppeling_niet(): void
    {
        // Bewust vastgelegd in plaats van gerepareerd: de FK cascade ruimt de
        // koppelingen in de database op en vuurt geen Eloquent-events. Het
        // verwijderen van het bewijsstuk zelf wordt wél gelogd, en dat is de
        // gebeurtenis waar het om draait.
        $this->actingAs($this->ciso);

        $bewijsstuk = Bewijsstuk::factory()->create(['geupload_door' => $this->ciso->id]);
        $asset = Asset::factory()->create();
        $bewijsstuk->koppelingen()->create([
            'blok_naam' => 'asset-classificatie',
            'entiteit_type' => 'asset',
            'entiteit_id' => $asset->id,
        ]);

        $bewijsstuk->delete();

        $this->assertDatabaseCount('bewijs_koppelingen', 0);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'bewijsstuk',
            'entiteit_id' => $bewijsstuk->id,
            'actie' => 'verwijderd',
        ]);
        $this->assertDatabaseMissing('audit_logregels', [
            'entiteit_type' => 'bewijs_koppeling',
            'actie' => 'verwijderd',
        ]);
    }

    public function test_uitsluiting_verwijderen_wordt_gelogd(): void
    {
        // Zelfde massa-delete-valkuil als bij de koppeling: een uitsluiting
        // schrappen wijzigt de scope, en §4.3 vraagt daar rekenschap over.
        $this->actingAs($this->ciso);

        $versie = ScopeVerklaring::factory()->create(['status' => 'concept']);
        $uitsluiting = $versie->uitsluitingen()->create([
            'omschrijving' => 'Datacenter Frankfurt',
            'motivatie' => 'Valt onder de certificering van de leverancier.',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(ScopeBeheer::class)
            ->call('uitsluitingVerwijderen', $uitsluiting->id);

        $this->assertDatabaseCount('uitsluitingen', 0);
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'uitsluiting',
            'entiteit_id' => $uitsluiting->id,
            'actie' => 'verwijderd',
        ]);
    }

    public function test_een_onbekende_actie_wordt_geweigerd(): void
    {
        // De suite draait op sqlite en daar dwingt een enum niets af. Zonder
        // deze controle komt een nieuwe of verkeerd gespelde actie er in de
        // tests doorheen en valt hij pas op MySQL om, met een QueryException op
        // het scherm van de gebruiker (11-08-2026: `geexporteerd`).
        $this->expectException(RuntimeException::class);

        AuditLogregel::legVerzamelingVast(
            blokNaam: 'installatiebeheer',
            entiteitType: 'iets',
            actie: 'verzonnen',
            omschrijving: 'Bestaat niet',
        );
    }

    public function test_de_toegestane_acties_staan_gelijk_aan_het_schema(): void
    {
        // De enum in de migratie en de lijst in het model zijn twee plekken die
        // uit elkaar kunnen lopen; dan is de bewaking uit de vorige test een
        // valse zekerheid.
        $migratie = file_get_contents(
            database_path('migrations/0001_01_01_000051_export_als_actie_in_de_audit_trail.php')
        );

        foreach (AuditLogregel::ACTIES as $actie) {
            $this->assertStringContainsString("'{$actie}'", $migratie);
        }
    }
}
