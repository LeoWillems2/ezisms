<?php

namespace App\Support\Assistent;

/**
 * Wat een tool tijdens één MCP-verzoek over zichzelf meldt, voor de aanroeplog
 * (implementatie/14b §7.1). De middleware ziet alleen de JSON-RPC-berichten;
 * of een fout een weigering was en hoeveel rijen er teruggingen, weet alleen
 * de tool.
 *
 * Gebonden als `scoped`: één exemplaar per verzoek, ook onder Octane.
 */
final class Aanroepcontext
{
    public const OK = 'ok';

    public const GEWEIGERD = 'geweigerd';

    public const FOUT = 'fout';

    public ?string $uitkomst = null;

    public ?int $aantalResultaten = null;

    public ?int $bewijsstukId = null;

    public function weigering(): void
    {
        $this->uitkomst = self::GEWEIGERD;
    }

    public function resultaten(int $aantal): void
    {
        $this->aantalResultaten = $aantal;
    }
}
