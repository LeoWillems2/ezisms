<?php

namespace Tests\Feature;

use App\Mail\Beveiligingssignaal as SignaalMail;
use App\Models\AuditLogregel;
use App\Models\Beveiligingssignaal;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use App\Support\Beveiligingsbewaking;
use App\Support\Syslogzender;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Bewaking van misbruik van inloggegevens (implementatie/01l §12).
 *
 * De pogingen worden direct als `Loginpoging` aangemaakt: de hook op dat model
 * ís de ingang (§1, beslissing 1), en zo toetst elke test precies één use case
 * zonder de omweg via het loginscherm. De twee tests die wél via het scherm
 * gaan, toetsen de ketting eromheen: de blokkade en "de bewaking breekt het
 * inloggen nooit".
 */
class BeveiligingsbewakingTest extends TestCase
{
    use RefreshDatabase;

    private const WACHTWOORD = 'geheim-wachtwoord';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        Mail::fake();
        config(['beveiligingsbewaking.syslog.host' => null]);
    }

    private function ciso(array $velden = []): Gebruiker
    {
        return Gebruiker::factory()->metRol('CISO')->create($velden);
    }

    /** @param  array<string, mixed>  $velden */
    private function poging(array $velden): Loginpoging
    {
        return Loginpoging::create([
            'email_ingevoerd' => 'iemand@voorbeeld.nl',
            'tijdstip' => now(),
            'succesvol' => false,
            'reden' => 'wachtwoord',
            'ip_adres' => '198.51.100.20',
            ...$velden,
        ]);
    }

    private function mislukt(int $aantal, array $velden = []): void
    {
        for ($i = 0; $i < $aantal; $i++) {
            $this->poging(['email_ingevoerd' => "doel{$i}@voorbeeld.nl", ...$velden]);
        }
    }

    private function geslaagd(Gebruiker $gebruiker, string $ip, array $velden = []): Loginpoging
    {
        return $this->poging([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'succesvol' => true,
            'reden' => null,
            'ip_adres' => $ip,
            ...$velden,
        ]);
    }

    /** @return list<string> */
    private function soorten(): array
    {
        return Beveiligingssignaal::orderBy('id')->pluck('soort')->all();
    }

    // ── A: piek ──────────────────────────────────────────────────────────

    public function test_a_de_twintigste_mislukte_poging_geeft_een_signaal_en_de_volgende_niet(): void
    {
        $this->mislukt(19);
        $this->assertSame([], $this->soorten());

        $this->mislukt(1);
        $this->assertSame(['piek_mislukt'], $this->soorten());

        $this->mislukt(5);
        $this->assertSame(['piek_mislukt'], $this->soorten(), 'Binnen het uur hoort de piek onderdrukt te worden.');

        $details = Beveiligingssignaal::sole()->details;
        $this->assertSame(20, $details['aantal']);
        $this->assertSame(['198.51.100.20' => 20], $details['drukste_ip']);
    }

    public function test_a_pogingen_op_een_niet_actief_account_tellen_niet_mee(): void
    {
        $this->mislukt(25, ['reden' => 'status']);

        $this->assertSame([], $this->soorten());
    }

    public function test_a_na_het_onderdrukkingsvenster_komt_er_een_nieuw_signaal(): void
    {
        $this->mislukt(20);
        $this->travel(61)->minutes();
        $this->mislukt(20);

        $this->assertSame(['piek_mislukt', 'piek_mislukt'], $this->soorten());
    }

    // ── B: blokkade ──────────────────────────────────────────────────────

    public function test_b_de_blokkade_door_het_systeem_geeft_een_signaal(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => self::WACHTWOORD]);

        for ($i = 0; $i < 5; $i++) {
            Volt::test('auth.login')
                ->set(['email' => $gebruiker->email, 'password' => 'fout-wachtwoord'])
                ->call('login');
        }

        $this->assertSame('geblokkeerd', $gebruiker->fresh()->status);
        $this->assertSame(['account_geblokkeerd'], $this->soorten());
        $this->assertSame($gebruiker->id, Beveiligingssignaal::sole()->gebruiker_id);
    }

    public function test_b_een_handmatige_blokkade_is_geen_signaal(): void
    {
        $ciso = $this->ciso();
        Gebruiker::factory()->create()->blokkeer($ciso, 'Vermoeden van misbruik');

        $this->assertSame([], $this->soorten());
    }

    // ── C: tweede factor ─────────────────────────────────────────────────

    public function test_c_drie_foute_tweede_factoren_geven_een_alert(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $fout = fn (string $reden) => $this->poging(['gebruiker_id' => $gebruiker->id, 'email_ingevoerd' => $gebruiker->email, 'reden' => $reden]);

        $fout('totp');
        $fout('herstelcode');
        $this->assertSame([], $this->soorten());

        $fout('totp');
        $this->assertSame(['tweede_factor_mislukt'], $this->soorten());
        $this->assertSame('alert', Beveiligingssignaal::sole()->ernst());
    }

    public function test_c_foute_wachtwoorden_tellen_niet_als_tweede_factor(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $this->mislukt(3, ['gebruiker_id' => $gebruiker->id, 'reden' => 'wachtwoord']);

        $this->assertSame([], $this->soorten());
    }

    // ── D: nieuw netwerk ─────────────────────────────────────────────────

    public function test_d_een_nieuw_netwerk_na_een_basislijn_geeft_een_signaal_eenmaal_per_dag(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->geslaagd($gebruiker, '203.0.113.7');
        $this->assertSame([], $this->soorten(), 'De eerste login ooit heeft geen basislijn.');

        $this->geslaagd($gebruiker, '203.0.113.99');
        $this->assertSame([], $this->soorten(), 'Zelfde /24 is hetzelfde netwerk.');

        $this->geslaagd($gebruiker, '192.0.2.44');
        $this->assertSame(['nieuw_netwerk'], $this->soorten());
        $this->assertSame('192.0.2.0/24', Beveiligingssignaal::sole()->details['netwerk']);
        $this->assertSame('notice', Beveiligingssignaal::sole()->ernst());

        $this->geslaagd($gebruiker, '192.0.2.45');
        $this->assertSame(['nieuw_netwerk'], $this->soorten(), 'Hetzelfde nieuwe netwerk op dezelfde dag: onderdrukt.');
    }

    /**
     * Na de uitrol van §2 bestaat de hele historie van iedereen uit het adres
     * van de proxy. Zonder deze regel krijgt elke gebruiker bij zijn eerste
     * login een signaal (§2.3).
     */
    public function test_d_historie_van_alleen_de_proxy_is_geen_basislijn(): void
    {
        config(['trustedproxy.proxies' => ['192.168.100.220']]);
        $gebruiker = Gebruiker::factory()->create();

        $this->geslaagd($gebruiker, '192.168.100.220');
        $this->geslaagd($gebruiker, '127.0.0.1');
        $this->geslaagd($gebruiker, '203.0.113.7');

        $this->assertSame([], $this->soorten());
    }

    public function test_d_ipv6_telt_per_48(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->geslaagd($gebruiker, '2001:db8:aaaa:1::1');
        $this->geslaagd($gebruiker, '2001:db8:aaaa:ffff::2');
        $this->assertSame([], $this->soorten());

        $this->geslaagd($gebruiker, '2001:db8:bbbb::1');
        $this->assertSame('2001:db8:bbbb::/48', Beveiligingssignaal::sole()->details['netwerk']);
    }

    public function test_d_oude_historie_telt_niet_als_bekend(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $this->geslaagd($gebruiker, '192.0.2.1', ['tijdstip' => now()->subDays(120)]);
        $this->geslaagd($gebruiker, '203.0.113.1', ['tijdstip' => now()->subDays(10)]);

        $this->geslaagd($gebruiker, '192.0.2.2');

        $this->assertSame(['nieuw_netwerk'], $this->soorten());
    }

    // ── E: geslaagd na mislukkingen ──────────────────────────────────────

    public function test_e_geslaagd_na_drie_mislukkingen(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $this->mislukt(3, ['gebruiker_id' => $gebruiker->id]);

        $this->geslaagd($gebruiker, '198.51.100.20');

        $this->assertSame(['succes_na_mislukkingen'], $this->soorten());
        $this->assertSame(3, Beveiligingssignaal::sole()->details['mislukt_ervoor']);
    }

    public function test_d_en_e_op_dezelfde_poging_zijn_twee_signalen(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $this->geslaagd($gebruiker, '203.0.113.7', ['tijdstip' => now()->subDays(3)]);
        $this->mislukt(3, ['gebruiker_id' => $gebruiker->id, 'ip_adres' => '192.0.2.9']);

        $this->geslaagd($gebruiker, '192.0.2.9');

        $this->assertSame(['nieuw_netwerk', 'succes_na_mislukkingen'], $this->soorten());
    }

    public function test_uitgeschakeld_geeft_geen_enkel_signaal(): void
    {
        config(['beveiligingsbewaking.aan' => false]);

        $this->mislukt(25);

        $this->assertSame([], $this->soorten());
    }

    // ── Aflevering ───────────────────────────────────────────────────────

    public function test_zonder_syslog_gaat_de_mail_naar_alle_actieve_cisos_en_verder_niemand(): void
    {
        $actief = $this->ciso();
        $tweede = $this->ciso();
        $gedeactiveerd = Gebruiker::factory()->gedeactiveerd()->metRol('CISO')->create();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->mislukt(20);

        $this->assertSame('mail', Beveiligingssignaal::sole()->kanaal);
        Mail::assertSent(SignaalMail::class, 2);
        Mail::assertSent(SignaalMail::class, fn ($m) => $m->hasTo($actief->email));
        Mail::assertSent(SignaalMail::class, fn ($m) => $m->hasTo($tweede->email));
        Mail::assertNotSent(SignaalMail::class, fn ($m) => $m->hasTo($gedeactiveerd->email) || $m->hasTo($medewerker->email));
    }

    public function test_met_syslog_gaat_er_geen_mail(): void
    {
        $this->ciso();
        [$server, $poort] = $this->udpServer();
        config(['beveiligingsbewaking.syslog' => [...config('beveiligingsbewaking.syslog'), 'host' => '127.0.0.1', 'poort' => $poort]]);

        $this->mislukt(20);

        $this->assertSame('syslog', Beveiligingssignaal::sole()->kanaal);
        Mail::assertNothingSent();

        $ontvangen = stream_socket_recvfrom($server, 4096);
        // authpriv (10) × 8 + warning (4) = 84.
        $this->assertStringStartsWith('<84>1 ', $ontvangen);
        $this->assertStringContainsString(' piek_mislukt - ezisms signaal=piek_mislukt ', $ontvangen);
        $this->assertStringContainsString('aantal=20', $ontvangen);
        $this->assertStringContainsString('id='.Beveiligingssignaal::sole()->id, $ontvangen);
        fclose($server);
    }

    public function test_syslog_over_tcp_sluit_af_met_een_regeleinde(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $code, $tekst);
        $poort = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        (new Syslogzender('127.0.0.1', $poort, 'tcp', 2, 'isms.voorbeeld.nl'))->verstuur('alert', 'ezisms signaal=proef', 'proef');

        $verbinding = stream_socket_accept($server, 2);
        $ontvangen = fread($verbinding, 4096);

        // authpriv (10) × 8 + alert (1) = 81.
        $this->assertMatchesRegularExpression('/^<81>1 \S+ isms\.voorbeeld\.nl ezisms \d+ proef - ezisms signaal=proef\n$/', $ontvangen);
        fclose($verbinding);
        fclose($server);
    }

    public function test_een_geweigerde_tcp_verbinding_valt_terug_op_mail(): void
    {
        $ciso = $this->ciso();
        config(['beveiligingsbewaking.syslog' => [...config('beveiligingsbewaking.syslog'), 'host' => '127.0.0.1', 'poort' => $this->vrijePoort(), 'protocol' => 'tcp']]);

        $this->mislukt(20);

        $signaal = Beveiligingssignaal::sole();
        $this->assertSame('syslog', $signaal->kanaal);
        $this->assertStringContainsString('per mail verstuurd', $signaal->afleverfout);
        Mail::assertSent(SignaalMail::class, fn ($m) => $m->hasTo($ciso->email));
    }

    public function test_zonder_syslog_en_zonder_mailkanaal_is_het_kanaal_geen(): void
    {
        $this->ciso();
        config(['mail.default' => 'log']);

        $this->mislukt(20);

        $this->assertSame('geen', Beveiligingssignaal::sole()->kanaal);
        Mail::assertNothingSent();
    }

    public function test_zonder_actieve_ciso_is_het_kanaal_geen(): void
    {
        $this->mislukt(20);

        $this->assertSame('geen', Beveiligingssignaal::sole()->kanaal);
    }

    /**
     * De bewaking mag niemand buiten sluiten (§6.6). Grof getoetst, net als in
     * 00m: de tabel is weg, en wat er dan ook misgaat, het inloggen slaagt.
     */
    public function test_een_kapotte_bewaking_laat_het_inloggen_slagen(): void
    {
        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => self::WACHTWOORD]);
        $this->geslaagd($gebruiker, '203.0.113.7', ['tijdstip' => now()->subDay()]);
        $this->mislukt(3, ['gebruiker_id' => $gebruiker->id]);
        Schema::drop('beveiligingssignalen');

        Volt::test('auth.login')
            ->set(['email' => $gebruiker->email, 'password' => self::WACHTWOORD])
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($gebruiker);
    }

    // ── Audit trail ──────────────────────────────────────────────────────

    /**
     * D en E vuren in het verzoek waarin iemand net is ingelogd. Zonder
     * `auditSysteemactor()` staat het signaal in de trail op naam van die
     * persoon — bij een overgenomen account de aanvaller (§7).
     */
    public function test_het_signaal_staat_in_de_trail_op_naam_van_het_systeem(): void
    {
        $gebruiker = Gebruiker::factory()->create();
        $this->actingAs($gebruiker);
        $this->mislukt(3, ['gebruiker_id' => $gebruiker->id]);

        $this->geslaagd($gebruiker, '198.51.100.20');

        $regel = AuditLogregel::where('entiteit_type', 'beveiligingssignaal')->sole();
        $this->assertSame('aangemaakt', $regel->actie);
        $this->assertNull($regel->gebruiker_id);
        $this->assertSame('Systeem (beveiligingsbewaking)', $regel->gebruiker_naam);
        $this->assertSame('identity-access', $regel->blok_naam);
        $this->assertStringContainsString($gebruiker->email, $regel->entiteit_omschrijving);
    }

    public function test_een_afleverfout_schrijft_geen_tweede_trailregel(): void
    {
        $this->mislukt(20);
        Beveiligingssignaal::sole()->forceFill(['afleverfout' => 'smtp weigerde'])->save();

        $this->assertSame(1, AuditLogregel::where('entiteit_type', 'beveiligingssignaal')->count());
    }

    public function test_andere_modellen_schrijven_nog_op_naam_van_de_ingelogde_gebruiker(): void
    {
        $ciso = $this->ciso();
        $this->actingAs($ciso);

        $ander = Gebruiker::factory()->create();

        $regel = AuditLogregel::where('entiteit_type', 'gebruiker')->where('entiteit_id', $ander->id)->where('actie', 'aangemaakt')->sole();
        $this->assertSame($ciso->id, $regel->gebruiker_id);
    }

    public function test_het_netwerk_van_een_adres(): void
    {
        $this->assertSame('203.0.113.0/24', Beveiligingsbewaking::netwerk('203.0.113.200'));
        $this->assertSame('2001:db8:1::/48', Beveiligingsbewaking::netwerk('2001:db8:1:2:3::4'));
        $this->assertNull(Beveiligingsbewaking::netwerk('127.0.0.1'));
        $this->assertNull(Beveiligingsbewaking::netwerk('::1'));
        $this->assertNull(Beveiligingsbewaking::netwerk('geen-adres'));
    }

    // ── Proefcommando ────────────────────────────────────────────────────

    public function test_het_proefcommando_mailt_zonder_syslog_en_maakt_geen_rij(): void
    {
        $ciso = $this->ciso();

        $this->artisan('isms:beveiligingssignaal-proef')
            ->expectsOutputToContain('proefmail verstuurd aan 1 CISO')
            ->assertSuccessful();

        Mail::assertSent(SignaalMail::class, fn ($m) => $m->proef && $m->hasTo($ciso->email));
        $this->assertSame(0, Beveiligingssignaal::count());
        $this->assertSame(0, AuditLogregel::where('entiteit_type', 'beveiligingssignaal')->count());
    }

    public function test_het_proefcommando_via_syslog(): void
    {
        [$server, $poort] = $this->udpServer();
        config(['beveiligingsbewaking.syslog' => [...config('beveiligingsbewaking.syslog'), 'host' => '127.0.0.1', 'poort' => $poort]]);

        $this->artisan('isms:beveiligingssignaal-proef')
            ->expectsOutputToContain('UDP geeft geen bevestiging')
            ->assertSuccessful();

        $this->assertStringContainsString('signaal=proef', stream_socket_recvfrom($server, 4096));
        Mail::assertNothingSent();
        fclose($server);
    }

    public function test_het_proefcommando_faalt_als_er_geen_kanaal_is(): void
    {
        $this->artisan('isms:beveiligingssignaal-proef')
            ->expectsOutputToContain('geen actieve gebruiker met de rol CISO')
            ->assertFailed();
    }

    // ── Hulp ─────────────────────────────────────────────────────────────

    /** @return array{0: resource, 1: int} */
    private function udpServer(): array
    {
        $server = stream_socket_server('udp://127.0.0.1:0', $code, $tekst, STREAM_SERVER_BIND);
        stream_set_timeout($server, 2);
        $poort = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        return [$server, $poort];
    }

    /** Een poort waar zeker niemand luistert: net vrijgegeven. */
    private function vrijePoort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $poort = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        return $poort;
    }
}
