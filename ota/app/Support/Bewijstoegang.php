<?php

namespace App\Support;

use App\Models\Bewijsstuk;

/**
 * De leescontrole voor een bewijsstukbestand, op één plek zodat de download én
 * de preview dezelfde poort delen. Wie het bestand mag downloaden, mag het ook
 * previewen — en omgekeerd — zonder dat de regel op twee plekken kan gaan
 * afwijken.
 *
 * Niet te verwarren met {@see Beleidstoegang}: dat is de specifieke blok5->6-
 * uitzondering (een actief beleidsbestand). Dit is de volledige regel eromheen:
 * volledige inzage OF eigen upload OF dat beleidsbestand.
 */
final class Bewijstoegang
{
    public static function magLezen(Bewijsstuk $bewijsstuk): bool
    {
        return Recordscope::magAllesZien('bewijsrepository-audit-trail')
            || $bewijsstuk->geupload_door === auth()->id()
            || Beleidstoegang::magBestandLezen($bewijsstuk);
    }
}
