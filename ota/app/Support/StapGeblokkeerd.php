<?php

namespace App\Support;

use RuntimeException;

/**
 * Het dossier houdt deze stap tegen (implementatie/15 §6). Wordt door de
 * schermen gevangen en als melding getoond; de opslag is op dat moment al
 * afgebroken, want `TaakObserver` gooit vanuit `updating`.
 */
class StapGeblokkeerd extends RuntimeException {}
