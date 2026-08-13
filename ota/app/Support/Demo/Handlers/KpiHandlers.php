<?php

namespace App\Support\Demo\Handlers;

use App\Models\KpiDefinitie;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use Illuminate\Support\Carbon;

/**
 * Wat FruitBV zélf met de meetaanpak doet (implementatie/12e §9).
 *
 * De KPI-catalogus is productkennis en komt uit `KpiDefinitieSeeder`; de
 * streefwaarden die daar meekomen zijn een **voorstel**. Welke normen een
 * organisatie daarvan overneemt is een bestuurlijk besluit, en wat ze buiten het
 * ISMS meet is organisatie-eigen. Beide horen dus in de demo en niet in de
 * seeder — dat is precies de splitsing uit §9.
 *
 * Geen eigen gebeurtenistype in `tijdlijn.json`: het moment staat in
 * `kpis.json` (`vanaf_maand`) en de maandafsluiting doet de rest. Een handmatige
 * KPI vraagt hoe dan ook een maandelijkse hand, dus een tweede plek waar de
 * timing staat zou alleen uit de pas kunnen lopen.
 */
final class KpiHandlers
{
    private bool $normenVastgesteld = false;

    /** @return array<string, callable> */
    public function register(): array
    {
        return [];
    }

    public function maandafsluiting(int $maand, Simulatie $sim): void
    {
        $this->stelNormenVast($maand, $sim);
        $this->legHandmatigeMeetpuntenVast($maand, $sim);
    }

    /**
     * FruitBV neemt een deel van de meegeleverde voorstellen over als eigen
     * streefwaarde. Vanaf dat moment kleuren de meetpunten; wat ervóór ligt blijft
     * `onbepaald`, want die punten droegen nog geen norm (12d §2b).
     */
    private function stelNormenVast(int $maand, Simulatie $sim): void
    {
        $normen = $sim->fixtures()->bestand('kpis')['normen'] ?? [];

        if ($this->normenVastgesteld || $maand < ($normen['vanaf_maand'] ?? PHP_INT_MAX)) {
            return;
        }

        $this->normenVastgesteld = true;

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['management-review-verbetercyclus', 'muteren'])
            ->bij("M{$maand}/kpi-normen vaststellen")
            ->doe(function () use ($normen) {
                foreach ($normen['sleutels'] ?? [] as $sleutel) {
                    $definitie = KpiDefinitie::where('sleutel', $sleutel)->first()
                        ?? throw DemoFixtureFout::bij('kpis/normen', "geen KPI '{$sleutel}'");

                    if ($definitie->streefwaarde === null) {
                        throw DemoFixtureFout::bij(
                            'kpis/normen',
                            "KPI '{$sleutel}' heeft geen voorstel om vast te stellen"
                        );
                    }

                    $definitie->update(['streefwaarde_vastgesteld_op' => Carbon::today()]);
                }
            });
    }

    /**
     * De KPI die FruitBV buiten het ISMS meet. `isms:meet-kpis` slaat hem stil
     * over (geen meetbron); de CISO voert hem per ronde met de hand in.
     */
    private function legHandmatigeMeetpuntenVast(int $maand, Simulatie $sim): void
    {
        foreach ($sim->fixtures()->bestand('kpis')['handmatig'] ?? [] as $def) {
            $meting = $def['reeks'][(string) $maand] ?? null;

            if ($meting === null) {
                continue;
            }

            Handelt::als($sim->gebruiker('ciske'))
                ->mits('heeft-niveau', ['management-review-verbetercyclus', 'muteren'])
                ->bij("M{$maand}/handmatig meetpunt {$def['sleutel']}")
                ->doe(fn () => $this->legVast($def, $meting));
        }
    }

    /** @param array<string, mixed> $def */
    private function legVast(array $def, array $meting): void
    {
        $definitie = KpiDefinitie::firstOrCreate(
            ['sleutel' => $def['sleutel']],
            [
                // Geen meetbron: dit is een handmatige KPI.
                'meetbron' => null,
                'naam' => $def['naam'],
                'fase' => $def['fase'],
                'eenheid' => $def['eenheid'],
                'richting' => $def['richting'],
                'berekeningswijze' => $def['berekeningswijze'],
                'streefwaarde' => $def['streefwaarde'] ?? null,
                'signaalwaarde' => $def['signaalwaarde'] ?? null,
                // Zelf aangemaakt is zelf gekozen: dit is geen voorstel.
                'streefwaarde_vastgesteld_op' => ($def['streefwaarde'] ?? null) === null ? null : Carbon::today(),
                'definitie_versie' => 1,
                'actief' => true,
            ]
        );

        $definitie->metingen()->create([
            'gemeten_op' => Carbon::today(),
            'teller' => $meting[0],
            'noemer' => $meting[1],
            // Dezelfde kopieerregel als het commando en het scherm.
            'definitie_versie' => $definitie->definitie_versie,
            'streefwaarde' => $definitie->vastgesteldeStreefwaarde(),
            'signaalwaarde' => $definitie->vastgesteldeSignaalwaarde(),
            'toelichting' => $def['toelichting'] ?? null,
            'ingevoerd_door_id' => auth()->id(),
        ]);
    }
}
