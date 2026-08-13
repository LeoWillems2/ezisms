<?php

namespace App\Support\Demo\Handlers;

use App\Models\Leverancier;
use App\Models\Systeem;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use App\Support\Stappenreeks;
use App\Support\Wijzigingsdossier;

/**
 * Het wijzigingenregister van de demo (blok 15, A.8.32).
 *
 * Eén gebeurtenistype in plaats van vijf: de fixture beschrijft hoe ver een
 * dossier komt (`afronden_tot_volgorde`), en de motor loopt de reeks tot daar
 * af. Vijf losse types zouden vijf keer dezelfde opzoekhulpjes vragen voor een
 * verschil dat volledig in data uit te drukken is.
 *
 * Het aanmelden gebeurt door de applicatiebeheerder en het in behandeling nemen
 * door de CISO — dat verschil in rol is precies wat de trail moet laten zien
 * (implementatie/15 §5).
 */
final class WijzigingHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'wijziging_doorlopen' => $this->wijzigingDoorlopen(...),
        ];
    }

    private function wijzigingDoorlopen(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->definitie('wijzigingen', 'wijzigingen', $g['sleutel']);

        $wijziging = $this->meldAan($def, $maand, $sim);

        $this->neemInBehandeling($wijziging, $def, $maand, $sim);
        $this->loopReeksAf($wijziging, $def, $maand, $sim);
        $this->sluit($wijziging, $def, $maand, $sim);
    }

    /** De applicatiebeheerder meldt de aankondiging van zijn leverancier aan. */
    private function meldAan(array $def, int $maand, Simulatie $sim): Wijziging
    {
        $melder = $sim->gebruiker($def['aangemeld_door']);

        return Handelt::als($melder)
            ->mits('heeft-niveau', ['wijzigingsbeheer', 'uitvoeren'])
            ->bij("M{$maand}/wijziging_aanmelden/{$def['sleutel']}")
            ->doe(function () use ($def, $melder, $sim) {
                $sjabloon = $this->sjabloon($def);

                $wijziging = Wijziging::create([
                    'titel' => $def['titel'],
                    // Soort en zwaarte komen bij het in behandeling nemen uit het
                    // sjabloon; bij het aanmelden staat alleen de soort vast.
                    'soort' => $sjabloon->soort,
                    'leverancier_id' => $def['leverancier']
                        ? $this->leverancier($def['leverancier'], $sim)->id
                        : null,
                    'aangemeld_door_id' => $melder->id,
                    'aangekondigd_op' => $sim->klok()->datum($def['aangekondigd_in_maand']),
                    'externe_referentie' => $def['externe_referentie'],
                    'impact_toelichting' => $def['impact_toelichting'],
                    'status' => 'aangemeld',
                ]);

                return $sim->fixtures()->onthoud($def['sleutel'], $wijziging);
            });
    }

    private function neemInBehandeling(Wijziging $wijziging, array $def, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['wijzigingsbeheer', 'muteren'])
            ->bij("M{$maand}/wijziging_in_behandeling/{$def['sleutel']}")
            ->doe(function () use ($wijziging, $def, $sim) {
                // Vóór de reeks: een uitvoerstap weigert zonder terugvalplan
                // (A.8.32 f), en dat is hier geen theorie maar de motor die
                // vastloopt als de fixture het vergeet.
                $wijziging->update(['terugvalplan' => $def['terugvalplan']]);

                Koppeling::sync(
                    $wijziging->systemen(),
                    'geraakte systemen',
                    collect($def['systemen'])->map(fn (string $s) => $this->systeem($s, $sim)->id)->all(),
                );

                Wijzigingsdossier::neemInBehandeling(
                    $wijziging,
                    $this->sjabloon($def),
                    $sim->klok()->datum($def['gepland_in_maand'], 15),
                );

                // Stappen met `bewijs_verplicht` weigeren zonder onderbouwing.
                // De release notes zijn hier dus geen versiering maar de reden
                // dat de reeks überhaupt vooruitkomt (A.8.32 d).
                if (isset($def['bewijs'])) {
                    $sim->bewijs()->maak($def['bewijs']['titel'], $def['bewijs']['omschrijving'], $wijziging);
                }
            });
    }

    /**
     * Rondt de stappen af tot en met `afronden_tot_volgorde`.
     *
     * Wie de stap doet hangt af van het staptype: de applicatiebeheerder
     * beoordeelt, informeert en voert uit; de CISO autoriseert en evalueert.
     * Dat verschil is de reden dat de trail hier iets waard is — een reeks die
     * volledig op naam van één persoon staat toont geen functiescheiding.
     */
    private function loopReeksAf(Wijziging $wijziging, array $def, int $maand, Simulatie $sim): void
    {
        $tot = $def['afronden_tot_volgorde'];

        foreach (Stappenreeks::voorEntiteit($wijziging) as $stap) {
            if ($stap->volgorde > $tot) {
                break;
            }

            $uitkomst = $def['goedkeuringen'][(string) $stap->volgorde] ?? null;
            $doorCiso = in_array($stap->staptype, ['goedkeuring', 'evaluatie'], true);

            Handelt::als($sim->gebruiker($doorCiso ? 'ciske' : $def['aangemeld_door']))
                ->mits('heeft-niveau', ['wijzigingsbeheer', 'uitvoeren'])
                ->bij("M{$maand}/wijziging_stap/{$def['sleutel']}/{$stap->volgorde}")
                ->doe(function () use ($wijziging, $stap, $uitkomst) {
                    if ($stap->vraagt_uitkomst) {
                        // Een goedkeuringsstap zonder uitkomst blijft staan; dat
                        // is bij de spoedwijziging precies de bedoeling.
                        if ($uitkomst !== null) {
                            Wijzigingsdossier::legUitkomstVast($wijziging, $stap, $uitkomst);
                        }

                        return;
                    }

                    $stap->update(['status' => 'voltooid', 'voltooid_op' => now()]);
                });
        }

        Wijzigingsdossier::werkStatusBij($wijziging->refresh());
    }

    private function sluit(Wijziging $wijziging, array $def, int $maand, Simulatie $sim): void
    {
        if ($def['sluiten'] === null) {
            return;
        }

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['wijzigingsbeheer', 'muteren'])
            ->bij("M{$maand}/wijziging_sluiten/{$def['sleutel']}")
            ->doe(fn () => Wijzigingsdossier::sluit(
                $wijziging->refresh(),
                $def['sluiten']['geslaagd'],
                $def['sluiten']['teruggedraaid'],
                $def['sluiten']['evaluatie'],
            ));
    }

    private function sjabloon(array $def): Wijzigingssjabloon
    {
        return Wijzigingssjabloon::with('stappen')->where('naam', $def['sjabloon'])->firstOrFail();
    }

    private function leverancier(string $sleutel, Simulatie $sim): Leverancier
    {
        /** @var Leverancier */
        return $sim->fixtures()->model($sleutel);
    }

    private function systeem(string $sleutel, Simulatie $sim): Systeem
    {
        /** @var Systeem */
        return $sim->fixtures()->model($sleutel);
    }
}
