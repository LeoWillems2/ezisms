<?php

namespace App\Observers;

use App\Models\Asset;

class AssetObserver
{
    /**
     * Automatische overgang naar 'actief' zodra alle drie de dimensies zijn
     * ingevuld. Dit is een afgeleide status (puur een functie van de ingevulde
     * velden), daarom een observer — in tegenstelling tot de bewuste,
     * multi-effect activering van een scope-verklaring in blok 2, die een
     * expliciete action class is (implementatie/03-asset-classificatie.md §4).
     */
    public function saving(Asset $asset): void
    {
        if ($asset->isGeclassificeerd() && $asset->status === 'geregistreerd') {
            $asset->status = 'actief';
            $asset->laatst_geclassificeerd_op = now();
        }
    }
}
