<?php

namespace Tests\Feature;

use App\Livewire\IntegratieRegister;
use App\Livewire\NotificatieBeheer;
use App\Mail\IncidentGemeld;
use App\Mail\TaakGeescaleerd;
use App\Mail\TrainingVerloopt;
use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Incident;
use App\Models\IntegratieAdapter;
use App\Models\Notificatie;
use App\Models\Notificatieregel;
use App\Models\Taak;
use App\Models\Trainingsmodule;
use App\Support\Incidentmelding;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NotificatieIntegratieTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        // Bewust zonder NotificatieregelSeeder: elke test bepaalt zelf welke
        // regels actief zijn.
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    private function regel(string $type, ?string $rol = 'CISO', bool $actief = true): Notificatieregel
    {
        return Notificatieregel::create([
            'gebeurtenis_type' => $type,
            'ontvanger_rol' => $rol,
            'actief' => $actief,
        ]);
    }

    // --- Dispatch (§4/§12) -------------------------------------------------

    public function test_actieve_regel_mailt_elke_ciso_en_logt_succes(): void
    {
        Mail::fake();
        $tweede = Gebruiker::factory()->metRol('CISO')->create();
        $this->regel('incident_gemeld');

        $incident = Incident::factory()->create();
        Incidentmelding::meldAanCiso($incident);

        Mail::assertSent(IncidentGemeld::class, 2);
        $this->assertSame(2, Notificatie::where('resultaat', 'succes')->count());
        $this->assertDatabaseHas('notificaties', [
            'gebeurtenis_type' => 'incident_gemeld',
            'gebruiker_id' => $this->ciso->id,
            'resultaat' => 'succes',
        ]);
        $this->assertDatabaseHas('notificaties', ['gebruiker_id' => $tweede->id]);
    }

    public function test_zonder_actieve_regel_gebeurt_niets(): void
    {
        Mail::fake();
        $this->regel('incident_gemeld', actief: false);

        Incidentmelding::meldAanCiso(Incident::factory()->create());

        Mail::assertNothingSent();
        $this->assertSame(0, Notificatie::count());
    }

    public function test_mailfout_wordt_gelogd_en_niet_doorgegooid(): void
    {
        Log::spy();
        $this->regel('incident_gemeld');

        // Een onbereikbare mailserver: de send() gooit, maar de dispatcher vangt.
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Mailserver onbereikbaar'));

        // Geen exception naar buiten: de primaire handeling voltooit.
        Incidentmelding::meldAanCiso(Incident::factory()->create());

        $this->assertDatabaseHas('notificaties', [
            'gebeurtenis_type' => 'incident_gemeld',
            'resultaat' => 'fout',
            'fout' => 'Mailserver onbereikbaar',
        ]);
        Log::shouldHaveReceived('error')->once();
    }

    // --- Escalatie (§5) ----------------------------------------------------

    public function test_escalatie_naar_niveau_2_mailt_eenmalig(): void
    {
        Mail::fake();
        $this->regel('taak_geescaleerd');

        Taak::factory()->create([
            'status' => 'verlopen',
            'escalatie_niveau' => 1,
            'deadline' => now()->subDays(20),
        ]);

        $this->artisan('isms:verloop-taken')->assertSuccessful();
        Mail::assertSent(TaakGeescaleerd::class, 1);

        // Een volgende sweep mailt niet opnieuw: de taak staat al op niveau 2.
        $this->artisan('isms:verloop-taken')->assertSuccessful();
        Mail::assertSent(TaakGeescaleerd::class, 1);
    }

    // --- Training (§5) -----------------------------------------------------

    public function test_nieuwe_trainingsherinnering_mailt_de_betrokkene_eenmalig(): void
    {
        Mail::fake();
        // ontvanger_rol leeg: de betrokkene uit de gebeurtenis.
        $this->regel('training_verloopt', rol: null);

        $lid = Gebruiker::factory()->metRol('Medewerker')->create();
        $module = Trainingsmodule::factory()->create();
        $doelgroep = Doelgroep::factory()->create();
        $doelgroep->gebruikers()->attach($lid);
        $module->doelgroepen()->attach($doelgroep);

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        Mail::assertSent(TrainingVerloopt::class, 1);
        $this->assertDatabaseHas('notificaties', [
            'gebeurtenis_type' => 'training_verloopt',
            'gebruiker_id' => $lid->id,
            'resultaat' => 'succes',
        ]);

        // Dezelfde cyclus verzet alleen de bestaande taak: geen tweede mail.
        $this->artisan('isms:genereer-taken')->assertSuccessful();
        Mail::assertSent(TrainingVerloopt::class, 1);
    }

    // --- Integratieregister (§6) -------------------------------------------

    public function test_adapter_aanmaken_komt_in_de_audit_trail(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(IntegratieRegister::class)
            ->set('naam', 'Azure AD')
            ->set('type', 'identiteit')
            ->set('status', 'actief')
            ->call('slaOp')
            ->assertHasNoErrors();

        $adapter = IntegratieAdapter::where('naam', 'Azure AD')->firstOrFail();
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'integratie_adapter',
            'entiteit_id' => $adapter->id,
            'actie' => 'aangemaakt',
        ]);
    }

    public function test_sync_vastleggen_werkt_de_adapter_bij(): void
    {
        $adapter = IntegratieAdapter::factory()->create(['status' => 'niet_geconfigureerd']);

        Livewire::actingAs($this->ciso)
            ->test(IntegratieRegister::class)
            ->call('nieuweSync', $adapter->id)
            ->set('syncResultaat', 'succes')
            ->set('syncAantal', '42')
            ->call('legSyncVast')
            ->assertHasNoErrors();

        $adapter->refresh();
        $this->assertSame('actief', $adapter->status);
        $this->assertNotNull($adapter->laatste_synchronisatie_op);
        $this->assertDatabaseHas('synchronisatie_logs', [
            'integratie_adapter_id' => $adapter->id,
            'aantal_verwerkte_records' => 42,
        ]);
    }

    /**
     * De dispatcher verstuurt synchroon en vangt fouten af, zodat een
     * onbereikbare mailserver de primaire handeling niet blokkeert. Zonder
     * timeout klopt dat niet: een trage server geeft geen fout maar blijft
     * hangen. Laravels eigen `config/mail.php` zet `timeout` op null — deze test
     * merkt het als dat bestand bij een upgrade terugkomt zoals het was.
     */
    public function test_de_smtp_transport_heeft_een_timeout(): void
    {
        $timeout = config('mail.mailers.smtp.timeout');

        $this->assertIsInt($timeout);
        $this->assertGreaterThan(0, $timeout);
        $this->assertLessThanOrEqual(30, $timeout, 'Een lange timeout houdt de handelende gebruiker vast.');
    }

    // --- Autorisatie (§8) --------------------------------------------------

    public function test_auditor_leest_maar_muteert_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/notificaties')->assertOk();

        Livewire::actingAs($auditor)
            ->test(NotificatieBeheer::class)
            ->call('nieuw')
            ->assertForbidden();
    }
}
