<?php

namespace Tests\Unit;

use App\Support\Oidc\Aanvraag;
use App\Support\Oidc\Afgebroken;
use App\Support\Oidc\Configuratie;
use App\Support\Oidc\Provider;
use App\Support\Oidc\Storing;
use App\Support\Oidc\Weigering;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\NepIdp;
use Tests\TestCase;

/** implementatie/01j §3 en §13, punten 1–5. */
class OidcProviderTest extends TestCase
{
    private NepIdp $idp;

    private Aanvraag $aanvraag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idp = new NepIdp;
        $this->aanvraag = Aanvraag::inloggen();
    }

    private function verwerk(array $query = []): mixed
    {
        $request = Request::create('/auth/extern/callback', 'GET', $query ?: $this->idp->terugkeer($this->aanvraag));

        return Provider::verwerkCallback($request, $this->aanvraag);
    }

    private function verwachtWeigering(string $stap, callable $actie): void
    {
        try {
            $actie();
            $this->fail("Verwachtte een weigering op {$stap}.");
        } catch (Weigering $e) {
            $this->assertSame($stap, $e->stap);
        }
    }

    public function test_een_geldig_token_levert_de_claims(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag));

        $claims = $this->verwerk();

        $this->assertSame(NepIdp::ISSUER, $claims->issuer);
        $this->assertSame('onderwerp-123', $claims->subject);
        $this->assertSame('jan@voorbeeld.test', $claims->gebruikersnaam);
        $this->assertSame('Jan Jansen', $claims->naam);
    }

    public function test_de_autorisatie_url_draagt_pkce_state_en_nonce(): void
    {
        $url = Provider::autorisatieUrl($this->aanvraag);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://idp.voorbeeld.test/autoriseer?', $url);
        $this->assertSame($this->aanvraag->state, $query['state']);
        $this->assertSame($this->aanvraag->nonce, $query['nonce']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($this->aanvraag->codeChallenge(), $query['code_challenge']);
        $this->assertSame(route('extern.callback'), $query['redirect_uri']);
        $this->assertArrayNotHasKey('prompt', $query);

        parse_str((string) parse_url(Provider::autorisatieUrl(Aanvraag::herbevestigen('/x')), PHP_URL_QUERY), $query);
        $this->assertSame('login', $query['prompt']);
    }

    public function test_state_die_niet_klopt_wordt_geweigerd_nog_voor_een_error(): void
    {
        $this->verwachtWeigering('state', fn () => $this->verwerk(['state' => 'anders', 'error' => 'access_denied']));
    }

    public function test_een_error_van_de_idp_is_afgebroken(): void
    {
        $this->expectException(Afgebroken::class);

        $this->verwerk(['state' => $this->aanvraag->state, 'error' => 'access_denied']);
    }

    public function test_een_verlopen_aanvraag_wordt_geweigerd(): void
    {
        $this->travel(11)->minutes();

        $this->verwachtWeigering('aanvraag_verlopen', fn () => $this->verwerk());
    }

    public function test_een_verkeerde_handtekening_wordt_geweigerd(): void
    {
        $token = $this->idp->token($this->aanvraag);
        [$kop, $body] = explode('.', $token);
        $vervalst = $kop.'.'.$body.'.'.JWT::urlsafeB64Encode(str_repeat('x', 256));

        $this->idp->geeftUit($vervalst);

        $this->verwachtWeigering('handtekening', fn () => $this->verwerk());
    }

    public function test_alg_none_wordt_geweigerd(): void
    {
        $kop = JWT::urlsafeB64Encode(json_encode(['alg' => 'none', 'kid' => NepIdp::KID]));
        $body = JWT::urlsafeB64Encode(json_encode(['iss' => NepIdp::ISSUER, 'aud' => NepIdp::CLIENT_ID, 'sub' => 'x', 'nonce' => $this->aanvraag->nonce, 'iat' => time(), 'exp' => time() + 60]));

        $this->idp->geeftUit($kop.'.'.$body.'.');

        $this->verwachtWeigering('algoritme', fn () => $this->verwerk());
    }

    public function test_hs256_met_de_publieke_sleutel_als_geheim_wordt_geweigerd(): void
    {
        // De klassieke verwisselingsaanval: wie de publieke sleutel als
        // HMAC-geheim gebruikt, maakt een token dat een naïeve controle doorstaat.
        $payload = ['iss' => NepIdp::ISSUER, 'aud' => NepIdp::CLIENT_ID, 'sub' => 'x', 'nonce' => $this->aanvraag->nonce, 'iat' => time(), 'exp' => time() + 60];
        $this->idp->geeftUit(JWT::encode($payload, NepIdp::publiekePem(), 'HS256', NepIdp::KID));

        $this->verwachtWeigering('algoritme', fn () => $this->verwerk());
    }

    public function test_een_andere_issuer_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['iss' => 'https://idp.voorbeeld.test/ander-tenant']));

        $this->verwachtWeigering('issuer', fn () => $this->verwerk());
    }

    public function test_een_andere_audience_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['aud' => 'een-andere-app']));

        $this->verwachtWeigering('audience', fn () => $this->verwerk());
    }

    public function test_meerdere_audiences_eisen_azp(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['aud' => [NepIdp::CLIENT_ID, 'nog-een']]));
        $this->verwachtWeigering('azp', fn () => $this->verwerk());

        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['aud' => [NepIdp::CLIENT_ID, 'nog-een'], 'azp' => NepIdp::CLIENT_ID]));
        $this->assertSame('onderwerp-123', $this->verwerk()->subject);
    }

    public function test_een_verlopen_token_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['iat' => time() - 900, 'exp' => time() - 300]));

        $this->verwachtWeigering('exp', fn () => $this->verwerk());
    }

    public function test_een_token_zonder_exp_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['exp' => null]));

        $this->verwachtWeigering('exp', fn () => $this->verwerk());
    }

    public function test_een_verkeerde_nonce_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['nonce' => 'hergebruikt']));

        $this->verwachtWeigering('nonce', fn () => $this->verwerk());
    }

    public function test_een_ontbrekend_subject_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['sub' => null]));

        $this->verwachtWeigering('subject', fn () => $this->verwerk());
    }

    public function test_de_subject_claim_is_instelbaar(): void
    {
        config(['extern_inloggen.subject_claim' => 'oid']);
        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['oid' => 'object-id-9']));

        $this->assertSame('object-id-9', $this->verwerk()->subject);
    }

    public function test_het_domein_wordt_tegen_hd_gecontroleerd(): void
    {
        config(['extern_inloggen.domein' => 'voorbeeld.test']);

        $this->idp->geeftUit($this->idp->token($this->aanvraag));
        $this->verwachtWeigering('domein', fn () => $this->verwerk());

        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['hd' => 'ander.test']));
        $this->verwachtWeigering('domein', fn () => $this->verwerk());

        $this->idp->geeftUit($this->idp->token($this->aanvraag, ['hd' => 'voorbeeld.test']));
        $this->assertSame('onderwerp-123', $this->verwerk()->subject);
    }

    public function test_een_onbekende_kid_haalt_de_sleutelset_een_keer_opnieuw_op(): void
    {
        // Eerst de oude sleutelset in de cache krijgen.
        Provider::sleutelset();

        // De IdP roteert: tokens en sleutelset dragen voortaan een nieuwe kid.
        $this->idp->roteerNaar('sleutel-2');
        $this->idp->geeftUit($this->idp->token($this->aanvraag));

        $this->assertSame('onderwerp-123', $this->verwerk()->subject);

        $jwksAanroepen = collect(Http::recorded())->filter(fn ($paar) => str_ends_with($paar[0]->url(), '/jwks'))->count();
        $this->assertSame(2, $jwksAanroepen);
    }

    public function test_een_kid_die_ook_na_verversen_onbekend_is_wordt_geweigerd(): void
    {
        $this->idp->geeftUit($this->idp->token($this->aanvraag, kop: ['kid' => 'bestaat-niet']));

        $this->verwachtWeigering('sleutel', fn () => $this->verwerk());
    }

    public function test_een_geweigerde_code_is_een_weigering_en_een_verlopen_secret_een_storing(): void
    {
        $this->idp->weigertCode(['error' => 'invalid_grant']);
        $this->verwachtWeigering('token', fn () => $this->verwerk());

        $this->idp->weigertCode(['error' => 'invalid_client']);
        $this->expectException(Storing::class);
        $this->verwerk();
    }

    public function test_google_zonder_schema_in_iss_wordt_geaccepteerd_een_andere_issuer_niet(): void
    {
        config(['extern_inloggen.issuer' => 'https://accounts.google.com']);
        $this->assertTrue(Configuratie::issuerKlopt('accounts.google.com'));
        $this->assertTrue(Configuratie::issuerKlopt('https://accounts.google.com'));

        config(['extern_inloggen.issuer' => 'https://idp.voorbeeld.test']);
        $this->assertFalse(Configuratie::issuerKlopt('idp.voorbeeld.test'));
        $this->assertFalse(Configuratie::issuerKlopt('accounts.google.com'));
    }

    public function test_discovery_met_een_andere_issuer_is_een_storing(): void
    {
        config(['extern_inloggen.issuer' => NepIdp::ISSUER.'/']);
        $this->assertSame(NepIdp::ISSUER, Configuratie::issuer());

        $this->idp->discoveryIssuer = 'https://elders.test';

        $this->expectException(Storing::class);
        Provider::discovery();
    }

    public function test_configuratiefouten(): void
    {
        config(['extern_inloggen.issuer' => null]);
        $this->assertFalse(Configuratie::isIngesteld());
        $this->assertSame([], Configuratie::fouten());

        config(['extern_inloggen.issuer' => 'https://login.microsoftonline.com/common/v2.0']);
        $this->assertStringContainsString('tenant-specifieke', implode(' ', Configuratie::fouten()));

        config(['extern_inloggen.issuer' => 'https://login.microsoftonline.com/0f0e0d0c-aaaa-bbbb-cccc-111122223333/v2.0']);
        $this->assertSame([], Configuratie::fouten());

        config(['extern_inloggen.issuer' => 'https://accounts.google.com', 'extern_inloggen.domein' => null]);
        $this->assertStringContainsString('OIDC_DOMEIN', implode(' ', Configuratie::fouten()));

        config(['extern_inloggen.domein' => 'voorbeeld.test']);
        $this->assertSame([], Configuratie::fouten());

        config(['extern_inloggen.issuer' => 'http://idp.voorbeeld.test']);
        $this->assertStringContainsString('https', implode(' ', Configuratie::fouten()));
    }
}
