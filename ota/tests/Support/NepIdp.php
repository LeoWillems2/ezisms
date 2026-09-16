<?php

namespace Tests\Support;

use App\Support\Oidc\Aanvraag;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Een identiteitsprovider die alleen in de test bestaat (implementatie/01j §13).
 *
 * Zet de configuratie, beantwoordt discovery, JWKS en het tokeneindpunt via
 * `Http::fake()`, en ondertekent tokens met een eigen RSA-sleutel. Geen echte IdP
 * in de suite: de handtoets tegen Entra en Google staat daar los van.
 */
final class NepIdp
{
    public const ISSUER = 'https://idp.voorbeeld.test/tenant';

    public const CLIENT_ID = 'isms-test-client';

    public const KID = 'sleutel-1';

    private static ?OpenSSLAsymmetricKey $sleutel = null;

    /** Wat het tokeneindpunt bij de volgende aanroep teruggeeft. */
    private ?string $idToken = null;

    /** @var array<string, mixed>|null */
    private ?array $tokenfout = null;

    /** @var list<array<string, mixed>> */
    private array $extraSleutels = [];

    private string $kid = self::KID;

    /** Wat het discovery-document als issuer noemt; null = de ingestelde. */
    public ?string $discoveryIssuer = null;

    public function __construct(array $configuratie = [])
    {
        config(array_merge([
            'extern_inloggen.issuer' => self::ISSUER,
            'extern_inloggen.client_id' => self::CLIENT_ID,
            'extern_inloggen.client_secret' => 'geheim',
            'extern_inloggen.weergavenaam' => 'Voorbeeld-IdP',
            'extern_inloggen.subject_claim' => 'sub',
            'extern_inloggen.domein' => null,
        ], $configuratie));

        Cache::flush();

        Http::fake(function ($request) {
            $url = $request->url();
            $issuer = rtrim((string) config('extern_inloggen.issuer'), '/');

            if ($url === $issuer.'/.well-known/openid-configuration') {
                return Http::response([
                    'issuer' => $this->discoveryIssuer ?? $issuer,
                    'authorization_endpoint' => 'https://idp.voorbeeld.test/autoriseer',
                    'token_endpoint' => 'https://idp.voorbeeld.test/token',
                    'jwks_uri' => 'https://idp.voorbeeld.test/jwks',
                    'id_token_signing_alg_values_supported' => ['RS256'],
                ]);
            }

            if ($url === 'https://idp.voorbeeld.test/jwks') {
                return Http::response(['keys' => [$this->jwk(), ...$this->extraSleutels]]);
            }

            if ($url === 'https://idp.voorbeeld.test/token') {
                if ($this->tokenfout !== null) {
                    return Http::response($this->tokenfout, 400);
                }

                return Http::response(['access_token' => 'x', 'token_type' => 'Bearer', 'id_token' => $this->idToken]);
            }

            return Http::response('', 404);
        });
    }

    /**
     * Een ondertekend id_token dat standaard volledig klopt bij `$aanvraag`.
     *
     * @param  array<string, mixed>  $claims  overschrijft of voegt toe; null verwijdert
     * @param  array<string, mixed>  $kop
     */
    public function token(Aanvraag $aanvraag, array $claims = [], array $kop = [], ?string $alg = 'RS256'): string
    {
        $payload = array_merge([
            'iss' => rtrim((string) config('extern_inloggen.issuer'), '/'),
            'aud' => self::CLIENT_ID,
            'sub' => 'onderwerp-123',
            'nonce' => $aanvraag->nonce,
            'iat' => time(),
            'exp' => time() + 300,
            'email' => 'jan@voorbeeld.test',
            'name' => 'Jan Jansen',
        ], $claims);

        $payload = array_filter($payload, fn ($waarde) => $waarde !== null);

        return JWT::encode($payload, self::sleutel(), $alg, $kop['kid'] ?? $this->kid, $kop);
    }

    /** Laat het tokeneindpunt bij de volgende callback dit token uitgeven. */
    public function geeftUit(string $idToken): self
    {
        $this->idToken = $idToken;
        $this->tokenfout = null;

        return $this;
    }

    /** @param  array<string, mixed>  $fout */
    public function weigertCode(array $fout = ['error' => 'invalid_grant']): self
    {
        $this->tokenfout = $fout;

        return $this;
    }

    /** Publiceer de sleutel voortaan onder een andere kid (rotatie). */
    public function roteerNaar(string $kid): self
    {
        $this->kid = $kid;

        return $this;
    }

    /** @return array<string, string> de query van een geslaagde terugkeer */
    public function terugkeer(Aanvraag $aanvraag, array $extra = []): array
    {
        return array_merge(['state' => $aanvraag->state, 'code' => 'code-abc'], $extra);
    }

    /** @return array<string, mixed> */
    private function jwk(): array
    {
        $details = openssl_pkey_get_details(self::sleutel());

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            // Bewust zonder `alg`, zoals Entra ID het doet.
            'kid' => $this->kid,
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }

    /** De PEM van de publieke sleutel, voor de verwisselingsaanval met HS256. */
    public static function publiekePem(): string
    {
        return openssl_pkey_get_details(self::sleutel())['key'];
    }

    /** Eén sleutel per proces: RSA genereren kost merkbaar tijd. */
    private static function sleutel(): OpenSSLAsymmetricKey
    {
        return self::$sleutel ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }
}
