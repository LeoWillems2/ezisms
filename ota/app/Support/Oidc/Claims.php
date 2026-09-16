<?php

namespace App\Support\Oidc;

/**
 * Wat er na de controle van het `id_token` over is (implementatie/01j §3.4).
 *
 * Bewust weinig: groepen en rollen uit de IdP worden niet gelezen (§15). De
 * identiteit is `issuer` + `subject`; `gebruikersnaam` en `naam` zijn weergave.
 */
final class Claims
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $subject,
        public readonly ?string $gebruikersnaam,
        public readonly ?string $naam,
    ) {}
}
