<?php

namespace App\Support;

use RuntimeException;

/**
 * De preview kon niet worden gemaakt: pandoc ontbreekt of is te oud, de
 * conversie faalde, of het bestand is niet van een previewbaar type. Geen fout
 * om op te vallen — de download blijft de echte weg.
 */
class PreviewNietBeschikbaar extends RuntimeException {}
