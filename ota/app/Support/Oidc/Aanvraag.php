<?php

namespace App\Support\Oidc;

use App\Models\Gebruiker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Eén doorverwijzing naar de identiteitsprovider (implementatie/01j §3.3).
 *
 * Staat in de sessie onder `extern.aanvraag` en wordt er bij de callback met
 * `pull()` uit gehaald: eenmalig bruikbaar. Als array en niet als object in de
 * sessie, zodat een gewijzigde klasse een lopende sessie niet onleesbaar maakt.
 */
final class Aanvraag
{
    public const SESSIESLEUTEL = 'extern.aanvraag';

    public const INLOGGEN = 'inloggen';

    public const KOPPELEN = 'koppelen';

    public const HERBEVESTIGEN = 'herbevestigen';

    private function __construct(
        public readonly string $doel,
        public readonly string $state,
        public readonly string $nonce,
        public readonly string $verifier,
        public readonly CarbonImmutable $aangemaaktOp,
        public readonly ?int $gebruikerId = null,
        public readonly ?string $token = null,
        public readonly ?string $terugNaar = null,
    ) {}

    public static function inloggen(): self
    {
        return self::nieuw(self::INLOGGEN);
    }

    /**
     * Koppelen vanuit een uitnodigings- of koppellink. Het token gaat mee zodat
     * de callback opnieuw kan controleren of de link nog geldig is (§6.3).
     */
    public static function koppelen(Gebruiker $gebruiker, string $token): self
    {
        return self::nieuw(self::KOPPELEN, gebruikerId: $gebruiker->id, token: $token);
    }

    public static function herbevestigen(string $terugNaar): self
    {
        return self::nieuw(self::HERBEVESTIGEN, terugNaar: $terugNaar);
    }

    private static function nieuw(string $doel, ?int $gebruikerId = null, ?string $token = null, ?string $terugNaar = null): self
    {
        return new self(
            doel: $doel,
            state: Str::random(64),
            nonce: Str::random(64),
            verifier: Str::random(64),
            aangemaaktOp: CarbonImmutable::now(),
            gebruikerId: $gebruikerId,
            token: $token,
            terugNaar: $terugNaar,
        );
    }

    /** De PKCE-uitdaging bij de verifier (S256). */
    public function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');
    }

    public function isVerlopen(): bool
    {
        return $this->aangemaaktOp->addMinutes((int) config('extern_inloggen.aanvraag_minuten'))->isPast();
    }

    public function bewaar(): void
    {
        session()->put(self::SESSIESLEUTEL, $this->naarArray());
    }

    /** De lopende aanvraag, zonder hem te verbruiken. */
    public static function inSessie(): ?self
    {
        $gegevens = session(self::SESSIESLEUTEL);

        return is_array($gegevens) ? self::vanArray($gegevens) : null;
    }

    /** Uit de sessie halen én verwijderen. Null als er geen (leesbare) aanvraag is. */
    public static function neemUitSessie(): ?self
    {
        $gegevens = session()->pull(self::SESSIESLEUTEL);

        return is_array($gegevens) ? self::vanArray($gegevens) : null;
    }

    /** @return array<string, mixed> */
    private function naarArray(): array
    {
        return [
            'doel' => $this->doel,
            'state' => $this->state,
            'nonce' => $this->nonce,
            'verifier' => $this->verifier,
            'aangemaakt_op' => $this->aangemaaktOp->getTimestamp(),
            'gebruiker_id' => $this->gebruikerId,
            'token' => $this->token,
            'terug_naar' => $this->terugNaar,
        ];
    }

    /** @param  array<string, mixed>  $gegevens */
    private static function vanArray(array $gegevens): ?self
    {
        foreach (['doel', 'state', 'nonce', 'verifier', 'aangemaakt_op'] as $sleutel) {
            if (! isset($gegevens[$sleutel])) {
                return null;
            }
        }

        return new self(
            doel: (string) $gegevens['doel'],
            state: (string) $gegevens['state'],
            nonce: (string) $gegevens['nonce'],
            verifier: (string) $gegevens['verifier'],
            aangemaaktOp: CarbonImmutable::createFromTimestamp((int) $gegevens['aangemaakt_op']),
            gebruikerId: isset($gegevens['gebruiker_id']) ? (int) $gegevens['gebruiker_id'] : null,
            token: $gegevens['token'] ?? null,
            terugNaar: $gegevens['terug_naar'] ?? null,
        );
    }
}
