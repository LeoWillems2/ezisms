<?php

namespace App\Actions;

use App\Models\KpiDefinitie;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\Bandverschuiving;
use App\Support\TaakPlanner;
use Illuminate\Support\Facades\DB;

/**
 * Stelt één criteriaversie vast en zet de vorige op 'vervangen'
 * (implementatie/04g §4.3).
 *
 * Bewust een expliciete invokable action en geen observer, om dezelfde reden als
 * bij `ActiveerScopeVerklaring`: het effect is meervoudig — een vorige versie
 * wordt vervangen, risico's schuiven van band, er ontstaan herbeoordelingstaken
 * en een KPI-reeks breekt — en dat hoort zichtbaar te zijn op de plek waar het
 * gebeurt.
 *
 * Retourneert de bandverschuiving, zodat het scherm kan melden wat er zojuist
 * met het register is gebeurd.
 */
class ActiveerRisicocriteria
{
    /**
     * KPI's waarvan de berekening op de acceptatiedrempel steunt.
     *
     * Verschuift die drempel, dan is de reeks vóór en ná niet vergelijkbaar en
     * moet `definitie_versie` omhoog — precies waarvoor dat veld bestaat. De
     * automatische bump in `KpiDefinitie` kijkt naar `meetbron` en `richting`, en
     * geen van beide wijzigt hier; zonder deze lijst tekent het dashboard dus een
     * verbetering die alleen een verzette drempel is (04g §8.2).
     *
     * **Komt er een meetbron bij die `Risico::drempelwaarde()` aanroept, dan
     * hoort hij hier ook in.**
     *
     * @var list<string>
     */
    public const DREMPELAFHANKELIJKE_KPIS = ['risico_boven_drempel_met_plan'];

    /** Standaard herzieningstermijn, gelijk aan die van scope en beleid. */
    private const HERZIENINGSTERMIJN_JAREN = 1;

    /** Hoeveel tijd een eigenaar krijgt voor een herbeoordeling na een aanscherping. */
    private const HERBEOORDELINGSTERMIJN_DAGEN = 30;

    public function __invoke(RisicocriteriaVersie $nieuweVersie, string $goedgekeurdDoor): Bandverschuiving
    {
        return DB::transaction(function () use ($nieuweVersie, $goedgekeurdDoor) {
            // Eerst rekenen, dan wisselen: na de wissel zijn de oude drempels
            // niet meer als "de actieve" te lezen.
            $verschuiving = Bandverschuiving::tussen(
                Risico::drempelwaarde(),
                Risico::waarschuwingsdrempel(),
                $nieuweVersie->drempelwaarde_score,
                $nieuweVersie->waarschuwingsdrempel_score,
            );

            // updateGeaudit: het vervangen van de vorige actieve versie is juist
            // de overgang die auditbewijs vormt, en een massa-update zou hem
            // buiten de audit trail houden.
            RisicocriteriaVersie::where('status', 'actief')
                ->whereKeyNot($nieuweVersie->getKey())
                ->updateGeaudit(['status' => 'vervangen']);

            $nieuweVersie->update([
                'status' => 'actief',
                'geldig_vanaf' => now(),
                'goedgekeurd_door' => $goedgekeurdDoor,
                'volgende_herziening_gepland' => $nieuweVersie->volgende_herziening_gepland
                    ?? now()->addYears(self::HERZIENINGSTERMIJN_JAREN),
            ]);

            RisicocriteriaVersie::vergeet();

            $this->planHerbeoordelingen($verschuiving);
            $this->breekDeDrempelafhankelijkeReeksen();

            return $verschuiving;
        });
    }

    /**
     * Risico's die zwaarder gaan wegen krijgen een herbeoordelingstaak.
     *
     * Eigen soort `risico-herbeoordeling-criteria` en nadrukkelijk niet
     * `risico-herbeoordeling`: die is idempotent op (entiteit, soort) en zou de
     * bestaande, veldgestuurde planning uit `RisicoObserver` overschrijven — de
     * eigenaar zou dan zijn eerder geplande herbeoordeling zien verspringen.
     *
     * Alleen omhoog: zie {@see Bandverschuiving::omlaag()}.
     */
    private function planHerbeoordelingen(Bandverschuiving $verschuiving): void
    {
        $deadline = now()->addDays(self::HERBEOORDELINGSTERMIJN_DAGEN);

        foreach ($verschuiving->omhoog() as $risico) {
            TaakPlanner::planVoorEntiteit(
                $risico,
                'risico-herbeoordeling-criteria',
                'Herbeoordelen na aangescherpte risicocriteria: '.$risico->titel,
                $deadline,
                'risico-soa',
                $risico->risico_eigenaar_id,
            );
        }
    }

    /**
     * De meetreeksen die op de drempel rekenen krijgen een zichtbare breuk.
     *
     * `definitie_versie` expliciet zetten schakelt de automatische bump in
     * `KpiDefinitie` uit — dat is de afspraak daar: wie het getal zelf meegeeft,
     * weet wat hij doet.
     */
    private function breekDeDrempelafhankelijkeReeksen(): void
    {
        $definities = KpiDefinitie::whereIn('meetbron', self::DREMPELAFHANKELIJKE_KPIS)->get();

        foreach ($definities as $definitie) {
            $definitie->update(['definitie_versie' => $definitie->definitie_versie + 1]);
        }
    }
}
