<?php

namespace App\Support\Demo\Handlers;

use App\Models\Asset;
use App\Models\Systeem;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;

/**
 * Systemen en het assetregister met de C/I/B-classificatie.
 */
final class AssetHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'systemen_registreren' => $this->systemenRegistreren(...),
            'assets_registreren' => $this->assetsRegistreren(...),
            'assets_classificeren' => $this->assetsClassificeren(...),
        ];
    }

    private function systemenRegistreren(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['asset-classificatie', 'muteren'])
            ->bij("M{$maand}/systemen_registreren")
            ->doe(function () use ($sim) {
                foreach ($sim->fixtures()->lijst('assets', 'systemen') as $def) {
                    // De leverancier bestaat pas vanaf M2; tot die tijd hangt het
                    // systeem er los bij en wordt de koppeling later gelegd.
                    $leverancier = $sim->fixtures()->kent($def['leverancier'])
                        ? $sim->fixtures()->model($def['leverancier'])->id
                        : null;

                    $sim->fixtures()->onthoud($def['sleutel'], Systeem::create([
                        'naam' => $def['naam'],
                        'hostingtype' => $def['hostingtype'],
                        'leverancier_id' => $leverancier,
                        'status' => $def['status'],
                        'beschikbaarheidseis' => $def['beschikbaarheidseis'],
                        'redundant' => $def['redundant'],
                        'redundantie_toelichting' => $def['redundantie_toelichting'],
                    ]));
                }
            });
    }

    private function assetsRegistreren(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['asset-classificatie', 'muteren'])
            ->bij("M{$maand}/assets_registreren")
            ->doe(function () use ($sim) {
                foreach ($sim->fixtures()->lijst('assets', 'assets') as $def) {
                    $asset = Asset::create([
                        'naam' => $def['naam'],
                        'type' => $def['type'],
                        'omschrijving' => $def['omschrijving'],
                        'organisatie_eenheid_id' => $sim->fixtures()->model($def['eenheid'])->id,
                        'accountable_id' => $sim->fixtures()->model($def['accountable'])->id,
                        'responsible_id' => $sim->fixtures()->model($def['responsible'])->id,
                        'status' => 'actief',
                        'binnen_scope' => $def['binnen_scope'],
                    ]);

                    Koppeling::sync($asset->systemen(), 'systemen', collect($def['systemen'])
                        ->map(fn (string $s) => $sim->fixtures()->model($s)->id)->all());

                    $sim->fixtures()->onthoud($def['sleutel'], $asset);
                }
            });
    }

    /**
     * Bewust een aparte stap: registreren en classificeren zijn in het echt ook
     * twee handelingen, en de audit trail hoort dat te laten zien.
     */
    private function assetsClassificeren(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['asset-classificatie', 'muteren'])
            ->bij("M{$maand}/assets_classificeren")
            ->doe(function () use ($sim) {
                foreach ($sim->fixtures()->lijst('assets', 'assets') as $def) {
                    // `persoonsgegevens` mag ontbreken in de fixture: niet elk
                    // asset is beoordeeld, en dat gat hoort zichtbaar te zijn in
                    // de demo — anders toont het filter "nog niet beoordeeld"
                    // nooit iets (implementatie/03b §9).
                    $sim->fixtures()->model($def['sleutel'])->update([
                        'vertrouwelijkheidsniveau' => $def['vertrouwelijkheidsniveau'],
                        'integriteitsniveau' => $def['integriteitsniveau'],
                        'beschikbaarheidsniveau' => $def['beschikbaarheidsniveau'],
                        'laatst_geclassificeerd_op' => now(),
                        'persoonsgegevens' => $def['persoonsgegevens'] ?? null,
                        'privacy_beoordeeld_op' => isset($def['persoonsgegevens']) ? now() : null,
                    ]);
                }
            });
    }
}
