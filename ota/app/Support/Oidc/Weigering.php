<?php

namespace App\Support\Oidc;

use RuntimeException;

/**
 * De callback is niet te vertrouwen (implementatie/01j §3.4): verkeerde state,
 * handtekening, issuer, audience, nonce, domein. Wordt een loginpoging met reden
 * `extern_geweigerd`. `stap` gaat naar het log, niet naar de gebruiker.
 */
class Weigering extends RuntimeException
{
    public function __construct(public readonly string $stap, string $toelichting = '')
    {
        parent::__construct($toelichting !== '' ? "{$stap}: {$toelichting}" : $stap);
    }
}
