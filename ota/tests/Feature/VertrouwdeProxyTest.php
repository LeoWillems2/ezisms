<?php

namespace Tests\Feature;

use App\Models\Loginpoging;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Het adres van de bezoeker achter een TLS-terminator (implementatie/01l §2).
 *
 * Via een testroute en niet via `Volt::test('auth.login')`: een Volt-test gaat
 * niet door de globale middleware, en `TrustProxies` ís globale middleware. De
 * route vuurt dezelfde gebeurtenis als een mislukte login, zodat de keten tot
 * en met de rij in `loginpogingen` getoetst wordt.
 */
class VertrouwdeProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '192.168.100.220';

    private const BEZOEKER = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/ip', fn () => request()->ip());

        Route::get('/_test/mislukte-login', function () {
            event(new Failed('web', null, ['email' => 'onbekend@voorbeeld.nl']));

            return 'ok';
        });
    }

    private function vanaf(string $afzender, ?string $doorgestuurd = null): static
    {
        $verzoek = $this->withServerVariables(['REMOTE_ADDR' => $afzender]);

        return $doorgestuurd === null
            ? $verzoek
            : $verzoek->withHeader('X-Forwarded-For', $doorgestuurd);
    }

    public function test_via_een_vertrouwde_proxy_komt_het_adres_van_de_bezoeker_in_de_loginpoging(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        $this->vanaf(self::PROXY, self::BEZOEKER)->get('/_test/mislukte-login')->assertOk();

        $this->assertSame(self::BEZOEKER, Loginpoging::sole()->ip_adres);
    }

    public function test_een_niet_vertrouwde_afzender_kan_zijn_adres_niet_zelf_kiezen(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        $this->vanaf('198.51.100.9', self::BEZOEKER)->get('/_test/ip')->assertSee('198.51.100.9');
    }

    public function test_zonder_lijst_blijft_het_adres_van_de_afzender_staan(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->vanaf(self::PROXY, self::BEZOEKER)->get('/_test/ip')->assertSee(self::PROXY);
    }

    public function test_een_subnet_in_cidr_notatie_wordt_vertrouwd(): void
    {
        config(['trustedproxy.proxies' => ['172.16.0.0/12']]);

        $this->vanaf('172.18.0.1', self::BEZOEKER)->get('/_test/ip')->assertSee(self::BEZOEKER);
    }

    /**
     * Een geloofde X-Forwarded-Host zou de host laten kiezen door wie de kop
     * zet; de host hoort uit APP_URL te komen (bootstrap/app.php).
     */
    public function test_ook_van_een_vertrouwde_proxy_wordt_alleen_het_adres_geloofd(): void
    {
        config(['trustedproxy.proxies' => [self::PROXY]]);

        Route::get('/_test/host', fn () => request()->getHost().'|'.(request()->isSecure() ? 'https' : 'http'));

        $this->vanaf(self::PROXY, self::BEZOEKER)
            ->withHeader('X-Forwarded-Host', 'aanvaller.voorbeeld')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/_test/host')
            ->assertDontSee('aanvaller.voorbeeld')
            ->assertSee('|http', false);
    }

    /**
     * `*` betekent voor Laravel "vertrouw wie er ook belt". Het configbestand
     * filtert dat weg, anders is de lijst in één regel een open deur.
     */
    public function test_een_ster_in_de_instelling_vertrouwt_niemand(): void
    {
        $this->assertSame(null, $this->configMet('*'));
        $this->assertSame(null, $this->configMet(' ** , '));
        $this->assertSame([self::PROXY, '10.0.0.0/8'], $this->configMet(self::PROXY.', *, 10.0.0.0/8'));
        $this->assertSame(null, $this->configMet(''));
    }

    /** @return list<string>|null */
    private function configMet(string $waarde): ?array
    {
        $vorige = $_SERVER['ISMS_VERTROUWDE_PROXIES'] ?? null;
        $_SERVER['ISMS_VERTROUWDE_PROXIES'] = $waarde;

        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            if ($vorige === null) {
                unset($_SERVER['ISMS_VERTROUWDE_PROXIES']);
            } else {
                $_SERVER['ISMS_VERTROUWDE_PROXIES'] = $vorige;
            }
        }
    }
}
