<?php

namespace App\Support\Demo\Handlers;

use App\Models\Contractclausule;
use App\Models\Dienst;
use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use App\Support\TaakPlanner;

/**
 * Het leveranciersregister: opvoeren, beoordelen en het risiconiveau bijstellen.
 *
 * De status blijft bij het opvoeren bewust op `kandidaat`: de
 * LeveranciersbeoordelingObserver zet hem op `actief` zodra er een eerste
 * beoordeling ligt (implementatie/09 §5). Hem hier meteen op `actief` zetten zou
 * dat mechanisme onzichtbaar maken.
 */
final class LeverancierHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'leveranciers_opvoeren' => $this->leveranciersOpvoeren(...),
            'leveranciersbeoordelingen' => $this->leveranciersbeoordelingen(...),
            'leverancier_risiconiveau_wijzigen' => $this->risiconiveauWijzigen(...),
        ];
    }

    private function leveranciersOpvoeren(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['leveranciers-derdenrisico', 'muteren'])
            ->bij("M{$maand}/leveranciers_opvoeren")
            ->doe(function () use ($g, $sim) {
                foreach ($g['sleutels'] as $sleutel) {
                    $def = $sim->fixtures()->definitie('leveranciers', 'leveranciers', $sleutel);

                    $leverancier = Leverancier::create([
                        'naam' => $def['naam'],
                        'status' => 'kandidaat',
                        // Het niveau bij opvoer, niet de eindstand: SnijBoon
                        // begint op 'laag' en zakt pas in M15 door de mand.
                        'risiconiveau' => $def['risiconiveau_bij_opvoer'] ?? $def['risiconiveau'],
                        'eigen_certificering_geldig_tot' => isset($def['certificering_geldig_tot_maand'])
                            ? $sim->klok()->datum($def['certificering_geldig_tot_maand'])
                            : null,
                    ]);

                    $sim->fixtures()->onthoud($sleutel, $leverancier);

                    foreach ($def['clausules'] as $type => $aanwezig) {
                        Contractclausule::create([
                            'leverancier_id' => $leverancier->id,
                            'type' => $type,
                            'aanwezig' => $aanwezig,
                        ]);
                    }

                    $this->borgDiensten($leverancier, $def, $sleutel, $sim);
                }
            });
    }

    /**
     * De diensten, en de koppeling naar de systemen die erop draaien.
     *
     * De systemen zijn in M1 aangemaakt toen er nog geen leverancier bestond
     * (`systemen.leverancier_id` bleef leeg); die koppeling wordt hier alsnog
     * gelegd. Alle systemen van deze leverancier hangen aan de eerste dienst:
     * dat is bij FruitBV de dragende dienst, en een fijnere verdeling zou een
     * detail suggereren dat de fixtures niet vastleggen.
     */
    private function borgDiensten(Leverancier $leverancier, array $def, string $sleutel, Simulatie $sim): void
    {
        $eerste = null;

        foreach ($def['diensten'] as $omschrijving) {
            $dienst = Dienst::create([
                'leverancier_id' => $leverancier->id,
                'omschrijving' => $omschrijving,
            ]);

            $eerste ??= $dienst;
        }

        $systeemIds = [];

        foreach ($sim->fixtures()->lijst('assets', 'systemen') as $systeemDef) {
            if (($systeemDef['leverancier'] ?? null) !== $sleutel
                || ! $sim->fixtures()->kent($systeemDef['sleutel'])) {
                continue;
            }

            $systeem = $sim->fixtures()->model($systeemDef['sleutel']);
            $systeem->update(['leverancier_id' => $leverancier->id]);
            $systeemIds[] = $systeem->id;
        }

        // De dienst is niet auditeerbaar; de regel hangt aan de leverancier,
        // net als op het leveranciersscherm (06b §4).
        if ($eerste !== null) {
            Koppeling::sync($eerste->systemen(), 'systemen bij '.$eerste->omschrijving, $systeemIds, logOp: $leverancier);
        }
    }

    private function leveranciersbeoordelingen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['leveranciers-derdenrisico', 'muteren'])
            ->bij("M{$maand}/leveranciersbeoordelingen ronde {$g['ronde']}")
            ->doe(function () use ($maand, $sim) {
                foreach ($sim->fixtures()->lijst('leveranciers', 'beoordelingen') as $def) {
                    if ((int) $def['maand'] !== $maand) {
                        continue;
                    }

                    $leverancier = $sim->fixtures()->model($def['leverancier']);

                    // De herbeoordelingstaak is afgerond doordát de beoordeling
                    // er is; de observer plant daarna meteen de volgende.
                    TaakPlanner::voltooiVoorEntiteit($leverancier, 'leverancier-herbeoordeling');

                    Leveranciersbeoordeling::create([
                        'leverancier_id' => $leverancier->id,
                        'uitgevoerd_op' => now(),
                        'bevindingen' => $def['bevindingen'],
                        'uitgevoerd_door_id' => $sim->gebruiker($def['uitgevoerd_door'])->id,
                        'volgende_beoordeling_gepland' => now()->addMonths($def['volgende_na_maanden']),
                    ]);
                }
            });
    }

    private function risiconiveauWijzigen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['leveranciers-derdenrisico', 'muteren'])
            ->bij("M{$maand}/leverancier_risiconiveau_wijzigen/{$g['sleutel']}")
            ->doe(fn () => $sim->fixtures()->model($g['sleutel'])->update([
                'risiconiveau' => $g['naar_risiconiveau'],
            ]));
    }
}
