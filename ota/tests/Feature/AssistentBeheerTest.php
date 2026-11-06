<?php

namespace Tests\Feature;

use App\Livewire\AssistenttoegangBeheer;
use App\Models\AssistentAanroep;
use App\Models\AuditLogregel;
use App\Models\Blok;
use App\Models\Gebruiker;
use App\Models\IntegratieAdapter;
use App\Models\Rol;
use App\Models\RolPermissie;
use App\Support\Assistent\Configuratie;
use App\Support\Assistent\Koppelingen;
use Database\Seeders\AssistenttoegangSeeder;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Beheer van de assistenttoegang (implementatie/14b §3.5, §7.3, §8).
 *
 * Zet ISMS_ASSISTENT vóór het opstarten, zoals {@see AssistenttoegangTest}: de
 * schermen bestaan alleen als de functie aan staat.
 */
class AssistentBeheerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_ENV['ISMS_ASSISTENT'] = $_SERVER['ISMS_ASSISTENT'] = 'true';

        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['ISMS_ASSISTENT'], $_SERVER['ISMS_ASSISTENT']);

        parent::tearDown();
    }

    // ── Rollen aan- en uitzetten (§8.1) ─────────────────────────────────────

    public function test_de_ciso_zet_een_rol_aan_en_dat_staat_in_de_audit_trail(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $auditor = Rol::where('naam', 'Auditor')->firstOrFail();

        Livewire::actingAs($ciso)->test(AssistenttoegangBeheer::class)->call('zetRol', $auditor->id, true);

        $this->assertTrue(RolPermissie::where('rol_id', $auditor->id)->where('blok_id', $this->blokId())->exists());
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'rol_permissie')->where('actie', 'aangemaakt')->exists());
    }

    public function test_uitzetten_trekt_koppelingen_in_van_wie_geen_recht_meer_heeft(): void
    {
        $this->staToe('Auditor');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $token = $this->token($auditor);

        Livewire::actingAs($ciso)->test(AssistenttoegangBeheer::class)
            ->call('zetRol', Rol::where('naam', 'Auditor')->value('id'), false);

        $this->assertTrue((bool) $token->fresh()->revoked);
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'rol_permissie')->where('actie', 'verwijderd')->exists());
    }

    public function test_de_administrator_staat_niet_in_de_lijst_en_kan_niet_worden_aangezet(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $admin = Rol::where('naam', 'Administrator')->firstOrFail();

        $scherm = Livewire::actingAs($ciso)->test(AssistenttoegangBeheer::class)
            ->assertViewHas('rollen', fn ($rollen) => ! $rollen->contains('id', $admin->id));

        try {
            $scherm->call('zetRol', $admin->id, true);
            $this->fail('De Administrator had niet aangezet mogen worden.');
        } catch (ModelNotFoundException) {
            // In de browser een 404.
        }

        $this->assertFalse(RolPermissie::where('rol_id', $admin->id)->where('blok_id', $this->blokId())->exists());
    }

    public function test_de_auditor_leest_mee_maar_wijzigt_niets(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get(route('assistenttoegang.index'))->assertOk();

        Livewire::actingAs($auditor)->test(AssistenttoegangBeheer::class)
            ->call('zetRol', Rol::where('naam', 'CISO')->value('id'), true)
            ->assertForbidden();
    }

    public function test_een_medewerker_komt_niet_op_het_beheerscherm(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get(route('assistenttoegang.index'))->assertForbidden();
    }

    // ── Intrekken (§3.5) ────────────────────────────────────────────────────

    public function test_de_ciso_trekt_een_koppeling_in(): void
    {
        $this->staToe('Auditor');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $token = $this->token($auditor);
        $verversing = RefreshToken::create(['id' => Str::random(80), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDays(30)]);

        Livewire::actingAs($ciso)->test(AssistenttoegangBeheer::class)
            ->call('trekIn', $auditor->id, $token->client_id);

        $this->assertTrue((bool) $token->fresh()->revoked);
        $this->assertTrue((bool) $verversing->fresh()->revoked);
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'gebruiker')
            ->where('entiteit_id', $auditor->id)
            ->get()
            ->contains(fn ($r) => str_contains((string) ($r->nieuwe_waarde['assistentkoppeling'] ?? ''), 'ingetrokken')));
    }

    public function test_blokkeren_trekt_de_koppelingen_in(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $token = $this->token($ciso);

        $ciso->blokkeer(door: null);

        $this->assertTrue((bool) $token->fresh()->revoked);
    }

    public function test_een_gebruiker_trekt_zijn_eigen_koppeling_in(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $token = $this->token($ciso, 'Claude Code');

        Volt::actingAs($ciso)->test('settings.assistenten')
            ->assertSee('Claude Code')
            ->call('trekIn', $token->client_id)
            ->assertSee('U hebt geen assistent gekoppeld.');

        $this->assertTrue((bool) $token->fresh()->revoked);
    }

    public function test_een_gebruiker_kan_andermans_koppeling_niet_intrekken(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $ander = Gebruiker::factory()->metRol('CISO')->create();
        $token = $this->token($ander);

        Volt::actingAs($ciso)->test('settings.assistenten')->call('trekIn', $token->client_id);

        $this->assertFalse((bool) $token->fresh()->revoked);
    }

    public function test_het_instellingenscherm_zegt_het_als_de_rol_niet_mag_koppelen(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($medewerker)->get(route('settings.assistenten'))
            ->assertOk()
            ->assertSee('Uw rol mag geen assistent koppelen');
    }

    // ── Opruimen (§7.3) ─────────────────────────────────────────────────────

    public function test_opruimen_verwijdert_oude_aanroepen_en_verlopen_tokens(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        AssistentAanroep::create($this->aanroep($ciso, now()->subDays(400)));
        AssistentAanroep::create($this->aanroep($ciso, now()->subDay()));
        $oud = $this->token($ciso);
        $oud->forceFill(['expires_at' => now()->subDays(10)])->save();
        $geldig = $this->token($ciso);

        $this->artisan('isms:assistent-opruimen')->assertSuccessful();

        $this->assertSame(1, AssistentAanroep::count());
        $this->assertNull(Token::find($oud->id));
        $this->assertNotNull(Token::find($geldig->id));
        $this->assertTrue(AuditLogregel::where('entiteit_type', 'assistent_aanroep')->exists());
    }

    public function test_opruimen_verwijdert_clients_zonder_geldig_token_na_dertig_dagen(): void
    {
        $vergeten = $this->client('Nooit afgemaakt');
        $vergeten->forceFill(['created_at' => now()->subDays(31)])->save();
        $nieuw = $this->client('Net geregistreerd');

        $this->artisan('isms:assistent-opruimen')->assertSuccessful();

        $this->assertNull(Client::find($vergeten->id));
        $this->assertNotNull(Client::find($nieuw->id));
    }

    public function test_alles_trekt_alles_in_en_verwijdert_de_clients(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->token($ciso);

        $this->artisan('isms:assistent-opruimen', ['--alles' => true])->assertSuccessful();

        $this->assertSame(0, Client::count());
        $this->assertSame(0, Token::count());
    }

    // ── Integratieregister (§8.3) ───────────────────────────────────────────

    public function test_de_seeder_zet_de_koppeling_in_het_integratieregister(): void
    {
        $this->seed(AssistenttoegangSeeder::class);
        $this->seed(AssistenttoegangSeeder::class);

        $adapter = IntegratieAdapter::where('naam', AssistenttoegangSeeder::NAAM)->sole();
        $this->assertSame('actief', $adapter->status);

        config(['assistent.aan' => false]);
        $this->seed(AssistenttoegangSeeder::class);

        $this->assertSame('inactief', $adapter->fresh()->status);
    }

    public function test_koppelingen_van_een_gebruiker_noemen_naam_host_en_laatste_gebruik(): void
    {
        $this->staToe('CISO');
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $token = $this->token($ciso, 'Claude Code');
        AssistentAanroep::create([...$this->aanroep($ciso, now()->subHour()), 'client_id' => $token->client_id]);

        $koppeling = Koppelingen::van($ciso)->sole();

        $this->assertSame('Claude Code', $koppeling['naam']);
        $this->assertSame(['localhost'], $koppeling['hosts']);
        $this->assertNotNull($koppeling['laatst_gebruikt']);
    }

    // ── Hulpjes ─────────────────────────────────────────────────────────────

    private function blokId(): int
    {
        return (int) Blok::where('code', Configuratie::BLOK)->value('id');
    }

    private function staToe(string $rolNaam): void
    {
        RolPermissie::create([
            'rol_id' => Rol::where('naam', $rolNaam)->value('id'),
            'blok_id' => $this->blokId(),
            'niveau' => 'lezen',
        ]);
    }

    private function client(string $naam = 'Testassistent'): Client
    {
        return Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $naam,
            'redirect_uris' => ['http://localhost:33418/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    private function token(Gebruiker $gebruiker, string $clientNaam = 'Testassistent'): Token
    {
        return Token::forceCreate([
            'id' => Str::random(80),
            'user_id' => $gebruiker->id,
            'client_id' => $this->client($clientNaam)->id,
            'scopes' => [Configuratie::SCOPE],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
    }

    /** @return array<string, mixed> */
    private function aanroep(Gebruiker $gebruiker, $tijdstip): array
    {
        return [
            'gebruiker_id' => $gebruiker->id,
            'soort' => 'tool',
            'naam' => 'zoek',
            'uitkomst' => 'ok',
            'duur_ms' => 10,
            'aangeroepen_op' => $tijdstip,
        ];
    }
}
