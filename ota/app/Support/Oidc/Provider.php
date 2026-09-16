<?php

namespace App\Support\Oidc;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * De OpenID Connect-client (implementatie/01j §3): authorization code flow met
 * PKCE, `state` en `nonce`.
 *
 * Eén generieke client en geen provider per merk. Entra ID en Google spreken
 * hetzelfde protocol; wat verschilt, is configuratie (§1.3).
 *
 * Alleen het `id_token` wordt gebruikt. Het access token wordt weggegooid:
 * er is geen userinfo-aanroep en geen Graph, want het ISMS wil weten wie er zit
 * en niet namens die persoon iets opvragen.
 */
final class Provider
{
    /**
     * Algoritmen die ooit geaccepteerd worden, ongeacht wat discovery belooft.
     * Geen `none` en geen `HS*`: bij een symmetrisch algoritme is de publieke
     * sleutel het geheim, en dat is precies de klassieke verwisselingsaanval.
     */
    private const ASYMMETRISCH = ['RS256', 'RS384', 'RS512', 'PS256', 'ES256', 'ES384'];

    private const TIMEOUT_SECONDEN = 5;

    /** De URL waar de browser naartoe gaat. */
    public static function autorisatieUrl(Aanvraag $aanvraag): string
    {
        $parameters = [
            'response_type' => 'code',
            'client_id' => config('extern_inloggen.client_id'),
            'redirect_uri' => self::redirectUri(),
            'scope' => 'openid email profile',
            'state' => $aanvraag->state,
            'nonce' => $aanvraag->nonce,
            'code_challenge' => $aanvraag->codeChallenge(),
            'code_challenge_method' => 'S256',
        ];

        // Herbevestigen (§7.5) moet echt opnieuw om het wachtwoord vragen, en
        // niet stil de lopende sessie bij de IdP hergebruiken.
        if ($aanvraag->doel === Aanvraag::HERBEVESTIGEN) {
            $parameters['prompt'] = 'login';
        }

        $eindpunt = (string) self::discovery()['authorization_endpoint'];

        return $eindpunt.(str_contains($eindpunt, '?') ? '&' : '?').http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public static function redirectUri(): string
    {
        return route('extern.callback');
    }

    /**
     * De callback controleren en de claims opleveren, in de volgorde van §3.4.
     * De eerste controle die faalt, bepaalt de uitkomst.
     *
     * @throws Weigering
     * @throws Afgebroken
     * @throws Storing
     */
    public static function verwerkCallback(Request $request, Aanvraag $aanvraag): Claims
    {
        // 1. state — vóór alles, ook vóór een `error`: een callback die niet bij
        //    deze sessie hoort, verdient geen andere behandeling omdat hij een
        //    foutmelding meedraagt.
        if (! is_string($request->query('state')) || ! hash_equals($aanvraag->state, $request->query('state'))) {
            throw new Weigering('state');
        }

        if ($aanvraag->isVerlopen()) {
            throw new Weigering('aanvraag_verlopen');
        }

        // 2. error
        if ($request->filled('error')) {
            throw new Afgebroken((string) $request->query('error'));
        }

        if (! is_string($request->query('code')) || $request->query('code') === '') {
            throw new Weigering('code');
        }

        $idToken = self::wisselCodeIn((string) $request->query('code'), $aanvraag);

        // 3. handtekening, 6. exp/iat
        $payload = self::decodeer($idToken);

        // 4. issuer
        if (! Configuratie::issuerKlopt($payload['iss'] ?? null)) {
            throw new Weigering('issuer', (string) json_encode($payload['iss'] ?? null));
        }

        // 5. audience
        $clientId = (string) config('extern_inloggen.client_id');
        $aud = $payload['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];

        if (! in_array($clientId, $audiences, true)) {
            throw new Weigering('audience');
        }

        if (count($audiences) > 1 && ($payload['azp'] ?? null) !== $clientId) {
            throw new Weigering('azp');
        }

        // 7. nonce
        if (! is_string($payload['nonce'] ?? null) || ! hash_equals($aanvraag->nonce, $payload['nonce'])) {
            throw new Weigering('nonce');
        }

        // 8. subject
        $subject = $payload[(string) config('extern_inloggen.subject_claim')] ?? null;

        if (! is_string($subject) || $subject === '') {
            throw new Weigering('subject');
        }

        // 9. domein
        $domein = config('extern_inloggen.domein');

        if (filled($domein) && ($payload['hd'] ?? null) !== $domein) {
            throw new Weigering('domein', (string) json_encode($payload['hd'] ?? null));
        }

        return new Claims(
            issuer: Configuratie::issuer(),
            subject: $subject,
            gebruikersnaam: self::tekstOfNull($payload['email'] ?? null) ?? self::tekstOfNull($payload['preferred_username'] ?? null),
            naam: self::tekstOfNull($payload['name'] ?? null),
        );
    }

    /**
     * Het discovery-document, een etmaal in de cache. Een mislukte ophaalpoging
     * wordt niet gecachet.
     *
     * @return array<string, mixed>
     *
     * @throws Storing
     */
    public static function discovery(): array
    {
        $issuer = Configuratie::issuer();

        return Cache::remember('oidc.discovery.'.md5($issuer), now()->addDay(), function () use ($issuer) {
            $document = self::haalJson($issuer.'/.well-known/openid-configuration', 'discovery');

            // De exacte vergelijking uit de OIDC-specificatie. Een verkeerd
            // getypte of verouderde issuer valt hier op, en niet pas als elke
            // login stukloopt op stap 4.
            if (rtrim((string) ($document['issuer'] ?? ''), '/') !== $issuer) {
                throw new Storing('Het discovery-document noemt issuer '.json_encode($document['issuer'] ?? null).', ingesteld is '.$issuer.'.');
            }

            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $sleutel) {
                if (! is_string($document[$sleutel] ?? null)) {
                    throw new Storing("Het discovery-document mist {$sleutel}.");
                }
            }

            return $document;
        });
    }

    /**
     * De sleutelset van de IdP, een uur in de cache.
     *
     * @return array<string, mixed>
     *
     * @throws Storing
     */
    public static function sleutelset(bool $vers = false): array
    {
        $uri = (string) self::discovery()['jwks_uri'];
        $sleutel = 'oidc.jwks.'.md5($uri);

        if ($vers) {
            Cache::forget($sleutel);
        }

        return Cache::remember($sleutel, now()->addHour(), function () use ($uri) {
            $set = self::haalJson($uri, 'jwks');

            if (! is_array($set['keys'] ?? null) || $set['keys'] === []) {
                throw new Storing('De sleutelset van de IdP is leeg.');
            }

            return $set;
        });
    }

    /**
     * @throws Weigering
     * @throws Storing
     */
    private static function wisselCodeIn(string $code, Aanvraag $aanvraag): string
    {
        try {
            $antwoord = Http::asForm()
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDEN)
                ->post((string) self::discovery()['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => self::redirectUri(),
                    'client_id' => config('extern_inloggen.client_id'),
                    'client_secret' => config('extern_inloggen.client_secret'),
                    'code_verifier' => $aanvraag->verifier,
                ]);
        } catch (ConnectionException $e) {
            throw new Storing('Het tokeneindpunt is niet bereikbaar: '.$e->getMessage());
        }

        // Een 4xx is hier meestal `invalid_grant` (code al gebruikt of verlopen)
        // of `invalid_client` (secret verlopen). Het eerste is een weigering van
        // deze callback, het tweede een storing; het verschil zit in de code.
        if ($antwoord->failed()) {
            $fout = (string) ($antwoord->json('error') ?? $antwoord->status());

            if ($fout === 'invalid_client' || $antwoord->serverError()) {
                throw new Storing("Het tokeneindpunt weigert: {$fout}");
            }

            throw new Weigering('token', $fout);
        }

        $idToken = $antwoord->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new Weigering('token', 'geen id_token in het antwoord');
        }

        return $idToken;
    }

    /**
     * Handtekening, algoritme en tijdclaims (§3.4 stap 3 en 6).
     *
     * @return array<string, mixed>
     *
     * @throws Weigering
     * @throws Storing
     */
    private static function decodeer(string $idToken): array
    {
        $kop = self::kop($idToken);
        $alg = $kop['alg'] ?? null;

        if (! is_string($alg) || ! in_array($alg, self::toegestaneAlgoritmen(), true)) {
            throw new Weigering('algoritme', (string) json_encode($alg));
        }

        $kid = is_string($kop['kid'] ?? null) ? $kop['kid'] : null;
        $sleutels = self::sleutelsVoor($alg, self::sleutelset());

        // De IdP roteert sleutels. Een onbekende kid is dan eerst een verouderde
        // cache, en pas na één verse ophaalpoging een weigering.
        if ($kid !== null && ! isset($sleutels[$kid])) {
            $sleutels = self::sleutelsVoor($alg, self::sleutelset(vers: true));
        }

        if ($sleutels === [] || ($kid !== null && ! isset($sleutels[$kid]))) {
            throw new Weigering('sleutel', (string) json_encode($kid));
        }

        // Zonder kid mag alleen een set van precies één sleutel; anders kiest
        // de bibliotheek niet en wij ook niet.
        if ($kid === null) {
            if (count($sleutels) !== 1) {
                throw new Weigering('sleutel', 'geen kid bij meerdere sleutels');
            }
            $sleutels = reset($sleutels);
        }

        $vorigeSpeling = JWT::$leeway;
        JWT::$leeway = (int) config('extern_inloggen.klokspeling_seconden');

        try {
            $payload = (array) JWT::decode($idToken, $sleutels);
        } catch (SignatureInvalidException) {
            throw new Weigering('handtekening');
        } catch (ExpiredException) {
            throw new Weigering('exp');
        } catch (BeforeValidException) {
            throw new Weigering('iat');
        } catch (Throwable $e) {
            throw new Weigering('token_onleesbaar', $e->getMessage());
        } finally {
            JWT::$leeway = $vorigeSpeling;
        }

        // De bibliotheek controleert exp en iat alleen als ze er zijn. Een
        // id_token zonder hoort volgens OIDC niet te bestaan, en een token dat
        // nooit verloopt, hoort hier niet binnen te komen.
        if (! is_numeric($payload['exp'] ?? null) || ! is_numeric($payload['iat'] ?? null)) {
            throw new Weigering('exp', 'exp of iat ontbreekt');
        }

        return $payload;
    }

    /** @return list<string> */
    private static function toegestaneAlgoritmen(): array
    {
        $belofte = self::discovery()['id_token_signing_alg_values_supported'] ?? ['RS256'];

        return array_values(array_intersect(self::ASYMMETRISCH, is_array($belofte) ? $belofte : ['RS256']));
    }

    /**
     * De sleutels uit de set die bij dit algoritme passen, geïndexeerd op kid.
     *
     * Entra zet geen `alg` op zijn sleutels, en de bibliotheek eist er een. Die
     * wordt hier ingevuld vanuit het sleuteltype, zodat een RSA-sleutel nooit
     * als EC-sleutel wordt gelezen of andersom — de bibliotheek vergelijkt
     * daarna het algoritme van de sleutel met dat in de kop.
     *
     * @param  array<string, mixed>  $set
     * @return array<string, Key>
     */
    private static function sleutelsVoor(string $alg, array $set): array
    {
        $kty = str_starts_with($alg, 'ES') ? 'EC' : 'RSA';
        $geschikt = [];

        foreach ($set['keys'] as $index => $jwk) {
            if (! is_array($jwk) || ($jwk['kty'] ?? null) !== $kty || ($jwk['use'] ?? 'sig') !== 'sig') {
                continue;
            }

            if (isset($jwk['alg']) && $jwk['alg'] !== $alg) {
                continue;
            }

            $jwk['alg'] = $alg;
            $geschikt[(string) ($jwk['kid'] ?? $index)] = $jwk;
        }

        if ($geschikt === []) {
            return [];
        }

        try {
            return JWK::parseKeySet(['keys' => array_values($geschikt)]);
        } catch (Throwable $e) {
            throw new Storing('De sleutelset van de IdP is onleesbaar: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private static function kop(string $jwt): array
    {
        $delen = explode('.', $jwt);

        if (count($delen) !== 3) {
            throw new Weigering('token_onleesbaar', 'geen drie delen');
        }

        $kop = json_decode(JWT::urlsafeB64Decode($delen[0]), true);

        if (! is_array($kop)) {
            throw new Weigering('token_onleesbaar', 'kop is geen JSON');
        }

        return $kop;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Storing
     */
    private static function haalJson(string $url, string $wat): array
    {
        try {
            $antwoord = Http::acceptJson()->timeout(self::TIMEOUT_SECONDEN)->get($url);
        } catch (ConnectionException $e) {
            throw new Storing("De IdP is niet bereikbaar ({$wat}): ".$e->getMessage());
        }

        if (! $antwoord->successful() || ! is_array($antwoord->json())) {
            throw new Storing("De IdP gaf geen bruikbaar antwoord ({$wat}): HTTP {$antwoord->status()}");
        }

        return $antwoord->json();
    }

    private static function tekstOfNull(mixed $waarde): ?string
    {
        return is_string($waarde) && $waarde !== '' ? $waarde : null;
    }
}
