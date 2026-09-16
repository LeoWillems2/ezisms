<?php

namespace App\Support\Oidc;

use RuntimeException;

/**
 * De IdP is niet te bereiken of antwoordt onbruikbaar: time-out, geen
 * discovery-document, een issuer die niet klopt met de configuratie
 * (implementatie/01j §3.2). Een fout aan onze kant of bij de IdP, niet van de
 * gebruiker — geen loginpoging, wel een logregel.
 */
class Storing extends RuntimeException {}
