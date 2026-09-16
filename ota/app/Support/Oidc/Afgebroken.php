<?php

namespace App\Support\Oidc;

use RuntimeException;

/**
 * De IdP kwam terug met een `error`: de gebruiker annuleerde, of de IdP weigert
 * deze app (implementatie/01j §3.4 stap 2). Geen aanval en dus geen loginpoging.
 */
class Afgebroken extends RuntimeException {}
