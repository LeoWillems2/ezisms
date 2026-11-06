<?php

namespace Tests\Feature;

use App\Mcp\IsmsServer;
use App\Mcp\Tools\IsmsOverzicht;
use App\Mcp\Tools\Zoek;
use App\Models\Afwijking;
use App\Models\AssistentAanroep;
use App\Models\Bewijsstuk;
use App\Models\Blok;
use App\Models\CorrigerendeMaatregel;
use App\Models\Gebruiker;
use App\Models\Incident;
use App\Models\Rol;
use App\Models\RolPermissie;
use App\Support\Assistent\Configuratie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Assistenttoegang via MCP, fase A (implementatie/14b §12.1, §12.2, §12.4).
 *
 * De routes bestaan alleen als ISMS_ASSISTENT aan staat, en dat wordt gelezen
 * bij het opstarten van de applicatie. Daarom zet `setUp()` de variabele vóór
 * `parent::setUp()`; het geval "uit" staat in {@see AssistenttoegangUitTest}.
 */
class AssistenttoegangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_ENV['ISMS_ASSISTENT'] = $_SERVER['ISMS_ASSISTENT'] = 'true';

        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
        $this->metPassportSleutels();
    }

    protected function tearDown(): void
    {
        unset($_ENV['ISMS_ASSISTENT'], $_SERVER['ISMS_ASSISTENT']);

        parent::tearDown();
    }

    // ── Toegang tot /mcp (§12.1) ────────────────────────────────────────────

    public function test_zonder_token_een_401_die_naar_de_oauth_metadata_wijst(): void
    {
        $antwoord = $this->mcp('tools/list');

        $antwoord->assertStatus(401);
        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"',
            (string) $antwoord->headers->get('WWW-Authenticate'),
        );
    }

    public function test_discovery_noemt_de_routes_van_dit_isms(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_rol_zonder_rij_op_assistenttoegang_krijgt_een_403(): void
    {
        // Na installatie mag niemand koppelen, ook de CISO niet (14b §2.2).
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Passport::actingAs($ciso, [Configuratie::SCOPE], 'api');

        $this->mcp('tools/list')->assertStatus(403);
    }

    public function test_rol_met_rij_ziet_de_tools(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Passport::actingAs($ciso, [Configuratie::SCOPE], 'api');

        $namen = collect($this->mcp('tools/list')->assertOk()->json('result.tools'))->pluck('name');

        $this->assertContains('isms-overzicht', $namen);
        $this->assertContains('zoek', $namen);
        $this->assertContains('kpi-reeks', $namen);
    }

    public function test_tools_list_toont_alleen_tools_waar_de_rol_recht_op_heeft(): void
    {
        // De Medewerker heeft geen leesrecht op Management Review (de KPI's).
        $this->staToe('Medewerker');
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        Passport::actingAs($medewerker, [Configuratie::SCOPE], 'api');

        $namen = collect($this->mcp('tools/list')->assertOk()->json('result.tools'))->pluck('name');

        $this->assertContains('zoek', $namen);
        $this->assertNotContains('kpi-reeks', $namen);
    }

    public function test_token_zonder_de_mcp_scope_krijgt_een_403(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Passport::actingAs($ciso, [], 'api');

        $this->mcp('tools/list')->assertStatus(403);
    }

    public function test_geblokkeerd_account_krijgt_een_401_ook_met_geldig_token(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $ciso->blokkeer(door: null);
        Passport::actingAs($ciso->fresh(), [Configuratie::SCOPE], 'api');

        $this->mcp('tools/list')->assertStatus(401);
    }

    public function test_ingetrokken_rij_geldt_bij_de_volgende_aanroep(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Passport::actingAs($ciso, [Configuratie::SCOPE], 'api');
        $this->mcp('tools/list')->assertOk();

        // Via het model, zoals het beheerscherm het doet: dat wist de
        // onthouden autorisatieantwoorden (RolPermissie::booted).
        RolPermissie::query()
            ->where('blok_id', Blok::where('code', Configuratie::BLOK)->value('id'))
            ->sole()
            ->delete();

        $this->mcp('tools/list')->assertStatus(403);
    }

    // ── Toestemming en registratie (§3) ─────────────────────────────────────

    public function test_toestemming_vraagt_eerst_om_in_te_loggen(): void
    {
        $client = $this->registreer()->json('client_id');

        $this->get($this->autorisatieUrl($client))->assertRedirect(route('login'));
    }

    public function test_rol_zonder_rij_krijgt_geen_toestemmingsscherm(): void
    {
        $client = $this->registreer()->json('client_id');
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)
            ->get($this->autorisatieUrl($client))
            ->assertStatus(403)
            ->assertSee('Geen assistenttoegang');
    }

    public function test_goedkeuren_zonder_rij_wordt_ook_geweigerd(): void
    {
        // Passport slaat het scherm over bij een eerdere toestemming; de check
        // moet dus ook op de goedkeurroute staan, niet alleen in de view.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)
            ->post(route('passport.authorizations.approve'), ['auth_token' => 'x'])
            ->assertStatus(403);
    }

    public function test_registratie_weigert_een_onbekend_redirect_domein(): void
    {
        $this->registreer('https://aanvaller.example/callback')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_registratie_staat_loopback_en_claude_toe(): void
    {
        $this->registreer('http://localhost:33418/callback')->assertCreated();
        $this->registreer('https://claude.ai/api/mcp/auth_callback')->assertCreated();
    }

    public function test_de_volledige_koppeling_met_pkce_levert_een_bruikbaar_token(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        $redirect = 'http://localhost:33418/callback';
        $client = $this->registreer($redirect)->json('client_id');
        $verifier = Str::random(64);

        $this->actingAs($ciso)
            ->get($this->autorisatieUrl($client, $redirect, $verifier))
            ->assertOk()
            ->assertSee('Assistent koppelen');

        $terug = $this->post(route('passport.authorizations.approve'), [
            'auth_token' => session('authToken'),
        ])->assertRedirect();

        parse_str((string) parse_url($terug->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('staat', $query['state']);

        $token = $this->postJson(route('passport.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk()->json('access_token');

        // Uitloggen uit de websessie: /mcp moet het met het token alleen doen.
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $this->mcp('tools/list', [], ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    // ── Tools en de aanroeplog (§12.2, §12.4) ───────────────────────────────

    public function test_een_aanroep_komt_in_de_log(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Passport::actingAs($ciso, [Configuratie::SCOPE], 'api');

        $this->mcp('tools/call', ['name' => 'zoek', 'arguments' => ['zoekterm' => 'phishing']])->assertOk();

        $regel = AssistentAanroep::sole();
        $this->assertSame($ciso->id, $regel->gebruiker_id);
        $this->assertSame('tool', $regel->soort);
        $this->assertSame('zoek', $regel->naam);
        $this->assertSame(['zoekterm' => 'phishing'], $regel->argumenten);
        $this->assertSame('ok', $regel->uitkomst);
        $this->assertSame(0, $regel->aantal_resultaten);
    }

    public function test_een_bestand_ophalen_komt_met_het_bewijsstuk_in_de_log(): void
    {
        Storage::fake(Bewijsstuk::DISK);
        Storage::disk(Bewijsstuk::DISK)->put('test/x.txt', 'inhoud');
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = Bewijsstuk::factory()->create([
            'bestandstype' => 'text/plain', 'bestandsgrootte' => 6,
            'opslaglocatie_referentie' => 'test/x.txt', 'bestandshash' => hash('sha256', 'inhoud'),
        ]);
        Passport::actingAs($ciso, [Configuratie::SCOPE], 'api');

        $this->mcp('resources/read', ['uri' => "isms://bewijs/{$bewijs->id}"])
            ->assertOk()
            ->assertJsonPath('result.contents.0.mimeType', 'text/plain');

        $regel = AssistentAanroep::sole();
        $this->assertSame('resource', $regel->soort);
        $this->assertSame("isms://bewijs/{$bewijs->id}", $regel->naam);
        $this->assertSame($bewijs->id, $regel->bewijsstuk_id);
        $this->assertSame('ok', $regel->uitkomst);
    }

    public function test_een_tool_zonder_recht_staat_als_geweigerd_in_de_log(): void
    {
        $this->staToe('Medewerker');
        Passport::actingAs(Gebruiker::factory()->metRol('Medewerker')->create(), [Configuratie::SCOPE], 'api');

        $this->mcp('tools/call', ['name' => 'kpi-reeks', 'arguments' => []]);
        $this->mcp('tools/call', ['name' => 'bestaat-niet', 'arguments' => []]);

        $this->assertSame(['geweigerd', 'fout'], AssistentAanroep::orderBy('id')->pluck('uitkomst')->all());
    }

    public function test_tools_list_komt_niet_in_de_log(): void
    {
        $this->staToe('CISO');
        Passport::actingAs(Gebruiker::factory()->metRol('CISO')->create(), [Configuratie::SCOPE], 'api');

        $this->mcp('tools/list')->assertOk();

        $this->assertSame(0, AssistentAanroep::count());
    }

    public function test_lange_argumenten_worden_ingekort(): void
    {
        config(['assistent.log_argumenten_bytes' => 20]);
        $this->staToe('CISO');
        Passport::actingAs(Gebruiker::factory()->metRol('CISO')->create(), [Configuratie::SCOPE], 'api');

        $this->mcp('tools/call', ['name' => 'zoek', 'arguments' => ['zoekterm' => str_repeat('a', 100)]]);

        $this->assertArrayHasKey('_ingekort', AssistentAanroep::sole()->argumenten);
    }

    public function test_zoek_toont_een_medewerker_alleen_zijn_eigen_incidenten(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $collega = Gebruiker::factory()->metRol('Medewerker')->create();
        $eigen = Incident::factory()->create(['titel' => 'Phishingmail eigen', 'gemeld_door_id' => $medewerker->id]);
        Incident::factory()->create(['titel' => 'Phishingmail collega', 'gemeld_door_id' => $collega->id]);

        $resultaat = IsmsServer::actingAs($medewerker)
            ->tool(Zoek::class, ['zoekterm' => 'phishing', 'types' => ['incident']]);

        $resultaat->assertOk()->assertStructuredContent(function ($json) use ($eigen) {
            $json->where('per_type.incident.gevonden', 1)
                ->where('resultaten.0.id', $eigen->id)
                ->etc();
        });
    }

    public function test_zoek_laat_types_zonder_leesrecht_weg_en_zegt_dat(): void
    {
        // De Medewerker heeft geen rij op risico-soa.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        IsmsServer::actingAs($medewerker)
            ->tool(Zoek::class, ['zoekterm' => 'lek'])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('weggelaten', fn ($lijst) => collect($lijst)->contains("Risico's (geen leesrecht)"))
                    ->missing('per_type.risico')
                    ->etc();
            });
    }

    public function test_zoek_volgt_bij_afwijkingen_de_regel_van_het_detailscherm(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $viaMaatregel = Afwijking::factory()->create(['omschrijving' => 'Back-up niet getest A']);
        CorrigerendeMaatregel::factory()->create(['afwijking_id' => $viaMaatregel->id, 'eigenaar_id' => $medewerker->id]);
        Afwijking::factory()->create(['omschrijving' => 'Back-up niet getest B']);

        IsmsServer::actingAs($medewerker)
            ->tool(Zoek::class, ['zoekterm' => 'back-up', 'types' => ['afwijking']])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($viaMaatregel) {
                $json->where('per_type.afwijking.gevonden', 1)
                    ->where('resultaten.0.id', $viaMaatregel->id)
                    ->etc();
            });
    }

    public function test_zoek_behandelt_procent_en_underscore_als_gewone_tekens(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        Incident::factory()->create(['titel' => '100% uitval', 'gemeld_door_id' => $ciso->id]);
        Incident::factory()->create(['titel' => '1000 uitval', 'gemeld_door_id' => $ciso->id]);

        IsmsServer::actingAs($ciso)
            ->tool(Zoek::class, ['zoekterm' => '0%', 'types' => ['incident']])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json->where('per_type.incident.gevonden', 1)->etc());
    }

    public function test_overzicht_laat_onderdelen_zonder_recht_weg(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        IsmsServer::actingAs($medewerker)
            ->tool(IsmsOverzicht::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->missing('risicos')
                    ->missing('kpis')
                    ->has('weggelaten')
                    ->etc();
            });
    }

    public function test_overzicht_voor_de_ciso_bevat_risicos_en_maatregelen(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        IsmsServer::actingAs($ciso)
            ->tool(IsmsOverzicht::class)
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json->has('risicos')->has('maatregelen')->has('kpis')->etc());
    }

    // ── Hulpjes ─────────────────────────────────────────────────────────────

    /** Geeft een rol de rij die koppelen toestaat (14b §2.2). */
    private function staToe(string $rolNaam): void
    {
        RolPermissie::create([
            'rol_id' => Rol::where('naam', $rolNaam)->value('id'),
            'blok_id' => Blok::where('code', Configuratie::BLOK)->value('id'),
            'niveau' => 'lezen',
        ]);
    }

    /**
     * Eén JSON-RPC-bericht naar /mcp.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    private function mcp(string $methode, array $params = [], array $headers = []): TestResponse
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $methode,
            'params' => (object) $params,
        ], ['Accept' => 'application/json, text/event-stream', ...$headers]);
    }

    private function registreer(string $redirect = 'http://localhost:33418/callback'): TestResponse
    {
        return $this->postJson('/oauth/register', [
            'client_name' => 'Testassistent',
            'redirect_uris' => [$redirect],
        ]);
    }

    private function autorisatieUrl(string $client, string $redirect = 'http://localhost:33418/callback', ?string $verifier = null): string
    {
        $verifier ??= Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => Configuratie::SCOPE,
            'state' => 'staat',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Passport bouwt zijn autorisatieserver met een sleutelpaar, ook voor de
     * toestemmingsstap. In een tijdelijke map,
     * nooit in storage/ van wie de suite draait.
     */
    private function metPassportSleutels(): void
    {
        $map = sys_get_temp_dir().'/isms-passport-'.getmypid();
        @mkdir($map, 0700, true);

        if (! file_exists($map.'/oauth-private.key')) {
            $sleutel = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($sleutel, $prive);
            file_put_contents($map.'/oauth-private.key', $prive);
            file_put_contents($map.'/oauth-public.key', openssl_pkey_get_details($sleutel)['key']);
            chmod($map.'/oauth-private.key', 0600);
            chmod($map.'/oauth-public.key', 0600);
        }

        Passport::loadKeysFrom($map);
    }
}
