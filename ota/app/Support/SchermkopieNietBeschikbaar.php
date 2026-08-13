<?php

namespace App\Support;

use RuntimeException;

/**
 * De schermkopie kon niet naar Word worden omgezet: pandoc ontbreekt of de
 * conversie faalde.
 *
 * Eigen uitzondering en niet {@see PreviewNietBeschikbaar}: daar is de download
 * de echte weg en is de preview een extraatje, hier stáát de gebruiker met een
 * auditor naast zich. Het scherm hoort dat verschil te kunnen tonen.
 */
class SchermkopieNietBeschikbaar extends RuntimeException {}
