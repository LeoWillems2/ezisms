<?php

namespace App\Support\Demo\Handlers;

use App\Actions\ActiveerRisicocriteria;
use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\RisicocriteriaVersie;
use App\Models\SoaRegel;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use App\Support\TaakPlanner;

/**
 * Het risicoregister: identificeren, beoordelen, behandelen, accepteren en
 * herbeoordelen.
 *
 * De acceptatiedrempel bepaalt hier wie mag tekenen. Onder de drempel valt het
 * binnen het mandaat van de CISO (`muteren`); erboven is het vaststellen en
 * hoort het bij de directie (`goedkeuren`) — implementatie/01c §4 en
 * `RisicoDetail::accepteerRestrisico()`.
 */
final class RisicoHandlers
{
    /**
     * Termijn tot de volgende beoordeling. Gelijk aan de termijn die
     * `isms:genereer-taken` en `isms:meet-kpis` hanteren; anders loopt de
     * herbeoordelings-KPI niet gelijk met de taken die hem zouden moeten sturen.
     */
    private const HERBEOORDELINGSTERMIJN_MAANDEN = 12;

    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'risicos_identificeren' => $this->risicosIdentificeren(...),
            'risicos_beoordelen' => $this->risicosBeoordelen(...),
            'risico_behandelplan' => $this->risicoBehandelplan(...),
            'risico_behandelplannen' => $this->risicoBehandelplannen(...),
            'risico_accepteren' => $this->risicoAccepteren(...),
            'risico_herbeoordelen' => $this->risicoHerbeoordelen(...),
            'risico_eigenaar_wijzigen' => $this->risicoEigenaarWijzigen(...),
            'risicocriteria_concept' => $this->risicocriteriaConcept(...),
            'risicocriteria_vaststellen' => $this->risicocriteriaVaststellen(...),
        ];
    }

    private function risicosIdentificeren(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risicos_identificeren")
            ->doe(function () use ($g, $sim) {
                foreach ($g['sleutels'] as $sleutel) {
                    $def = $sim->fixtures()->definitie('risicos', 'risicos', $sleutel);

                    // Bewust nog zonder kans en impact: 'geidentificeerd' is een
                    // eigen toestand in het register en niet een halve
                    // beoordeling. De KPI hoort dat verschil te zien.
                    $risico = Risico::create([
                        'titel' => $def['titel'],
                        'dreiging' => $def['dreiging'],
                        'kwetsbaarheid' => $def['kwetsbaarheid'],
                        'risico_eigenaar_id' => $sim->gebruiker($def['eigenaar'])->id,
                        'gekoppeld_asset_id' => isset($def['asset'])
                            ? $sim->fixtures()->model($def['asset'])->id : null,
                        'gekoppeld_leverancier_id' => isset($def['leverancier'])
                            ? $sim->fixtures()->model($def['leverancier'])->id : null,
                        'status' => 'geidentificeerd',
                    ]);

                    // De §4.1-kwestie waaruit dit risico voortkomt (plan 02b).
                    // Hier en niet bij het behandelplan: de aanleiding is bekend
                    // op het moment van identificeren.
                    Koppeling::sync($risico->aanleidingen(), 'aanleidingen', array_map(
                        fn (string $issue) => $sim->fixtures()->model($issue)->id,
                        $def['issues'] ?? [],
                    ));

                    $sim->fixtures()->onthoud($sleutel, $risico);
                }
            });
    }

    private function risicosBeoordelen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risicos_beoordelen")
            ->doe(function () use ($g, $sim) {
                foreach ($g['sleutels'] as $sleutel) {
                    $this->beoordeel($sim, $sleutel);
                }
            });
    }

    private function risicoBehandelplan(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risico_behandelplan/{$g['sleutel']}")
            ->doe(fn () => $this->behandelplan($sim, $g['sleutel']));
    }

    private function risicoBehandelplannen(array $g, int $maand, Simulatie $sim): void
    {
        $sleutels = $sim->fixtures()->sleutels($g['sleutels'], Risico::class, "M{$maand}/risico_behandelplannen");

        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risico_behandelplannen")
            ->doe(function () use ($sleutels, $sim) {
                foreach ($sleutels as $sleutel) {
                    $this->behandelplan($sim, $sleutel);
                }
            });
    }

    /**
     * De acceptatie. Boven de drempel is dit een goedkeuractie van de directie,
     * eronder een besluit binnen het mandaat van de CISO — dezelfde tweedeling
     * die het risicoscherm maakt. De autorisatiecheck toetst hem: wijst de
     * tijdlijn de verkeerde persoon aan, dan valt dat hier om.
     */
    private function risicoAccepteren(array $g, int $maand, Simulatie $sim): void
    {
        $risico = $sim->fixtures()->model($g['sleutel']);
        $accepteerder = $sim->gebruiker($g['door']);

        Handelt::als($accepteerder)
            ->mits('heeft-niveau', ['risico-soa', $risico->boventDrempel() ? 'goedkeuren' : 'muteren'])
            ->bij("M{$maand}/risico_accepteren/{$g['sleutel']}")
            ->doe(function () use ($risico, $accepteerder) {
                $behandeling = $risico->behandelingen()->where('behandeloptie', 'accepteren')->latest('id')->first()
                    ?? $risico->behandelingen()->latest('id')->firstOrFail();

                $behandeling->update([
                    'geaccepteerd_door' => $accepteerder->naam,
                    'geaccepteerd_op' => now()->toDateString(),
                ]);

                $risico->update(['status' => 'geaccepteerd']);
            });
    }

    private function risicoHerbeoordelen(array $g, int $maand, Simulatie $sim): void
    {
        $sleutels = isset($g['sleutel'])
            ? [$g['sleutel']]
            : $sim->fixtures()->sleutels($g['sleutels'], Risico::class, "M{$maand}/risico_herbeoordelen");

        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risico_herbeoordelen")
            ->doe(function () use ($sleutels, $maand, $sim) {
                foreach ($sleutels as $sleutel) {
                    $this->herbeoordeel($sim, $sleutel, $maand);
                }
            });
    }

    private function risicoEigenaarWijzigen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risico_eigenaar_wijzigen/{$g['sleutel']}")
            ->doe(fn () => $sim->fixtures()->model($g['sleutel'])->update([
                'risico_eigenaar_id' => $sim->gebruiker($g['naar'])->id,
            ]));
    }

    /**
     * De CISO stelt een nieuwe versie van de risicocriteria op en dient hem in
     * (implementatie/04g §6). Volledige kopie van de actieve versie als
     * startpunt, met daaroverheen wat de fixture wijzigt — precies wat het
     * scherm doet.
     */
    private function risicocriteriaConcept(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->definitie('risicos', 'criteria', $g['sleutel']);

        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/risicocriteria_concept/{$g['sleutel']}")
            ->doe(function () use ($def, $g, $sim) {
                $vorige = RisicocriteriaVersie::with('niveaus')->where('status', 'actief')->firstOrFail();

                $nieuw = RisicocriteriaVersie::create([
                    'versienummer' => $def['versienummer'],
                    'status' => 'ter_goedkeuring',
                    'omschrijving' => $def['omschrijving'],
                    'drempelwaarde_score' => $def['drempelwaarde_score'],
                    'waarschuwingsdrempel_score' => $def['waarschuwingsdrempel_score'],
                    'leidraad_kans' => $vorige->leidraad_kans,
                    'leidraad_impact' => $vorige->leidraad_impact,
                    'wijzigingsreden' => $def['wijzigingsreden'] ?? null,
                ]);

                foreach ($vorige->niveaus as $niveau) {
                    $nieuw->niveaus()->create([
                        ...$niveau->only(['as', 'niveau', 'naam', 'omschrijving']),
                        // De kwantitatieve band is wat deze versie toevoegt: het
                        // veld waar een op cijfers sturende auditor naar kijkt.
                        'kwantitatieve_band' => $def['kwantitatieve_banden'][$niveau->as][(string) $niveau->niveau] ?? null,
                    ]);
                }

                $sim->fixtures()->onthoud($g['sleutel'], $nieuw);
            });
    }

    /**
     * Goedkeuractie: de directie stelt de risicocriteria vast. Hier schuiven de
     * risico's die door de scherpere drempel zwaarder gaan wegen naar rood, en
     * krijgen hun eigenaren een herbeoordelingstaak.
     */
    private function risicocriteriaVaststellen(array $g, int $maand, Simulatie $sim): void
    {
        $directeur = $sim->gebruiker($g['door']);
        $versie = $sim->fixtures()->model($g['sleutel']);

        Handelt::als($directeur)
            ->mits('heeft-niveau', ['risico-soa', 'goedkeuren'])
            ->bij("M{$maand}/risicocriteria_vaststellen/{$g['sleutel']}")
            ->doe(fn () => app(ActiveerRisicocriteria::class)($versie, $directeur->naam));
    }

    // --- De stappen zelf ---------------------------------------------------

    /** Kans en impact uit `start`; de score volgt uit de RisicoObserver. */
    private function beoordeel(Simulatie $sim, string $sleutel): Risico
    {
        $risico = $sim->fixtures()->model($sleutel);
        $def = $sim->fixtures()->definitie('risicos', 'risicos', $sleutel);

        if ($risico->kans_niveau !== null) {
            return $risico;
        }

        $risico->update([
            'kans_niveau' => $def['start']['kans'],
            'impact_niveau' => $def['start']['impact'],
            'status' => 'beoordeeld',
            'volgende_beoordeling_gepland' => now()->addMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN),
        ]);

        return $risico;
    }

    /**
     * Het behandelplan. Een plan zonder beoordeling bestaat niet, dus die wordt
     * hier zo nodig alsnog gedaan — dat gebeurt bij de twee risico's die in M17
     * binnenkomen en geen eigen beoordelingsgebeurtenis hebben.
     */
    private function behandelplan(Simulatie $sim, string $sleutel): void
    {
        $risico = $this->beoordeel($sim, $sleutel);
        $def = $sim->fixtures()->definitie('risicos', 'risicos', $sleutel);

        if ($risico->behandelingen()->exists()) {
            return;
        }

        $behandeling = Risicobehandeling::create([
            'risico_id' => $risico->id,
            'behandeloptie' => $def['behandeloptie'],
            'restrisico_score' => $def['restrisico'] ?? null,
        ]);

        // De koppeling naar de maatregelen die de behandeling waarmaken. Zonder
        // die koppeling is er geen restrisico per control, en dus ook geen
        // jaarlijkse snapshot om een trend op te bouwen (plan 04c). Alleen
        // van-toepassing-verklaarde regels, zoals het risicoscherm ook afdwingt.
        Koppeling::sync(
            $behandeling->soaRegels(),
            'maatregelen',
            SoaRegel::where('van_toepassing', true)
                ->whereHas('maatregel', fn ($q) => $q->whereIn('annex_a_referentie', $def['soa_regels'] ?? []))
                ->pluck('id')
                ->all()
        );

        // Accepteren onder de drempel is meteen rond (mandaat van de CISO);
        // erboven blijft het op 'behandelplan_opgesteld' staan tot de directie
        // tekent. Dat is precies het verschil dat de demo moet laten zien.
        $risico->update([
            'status' => $def['behandeloptie'] === 'accepteren' && ! $risico->boventDrempel()
                ? 'geaccepteerd'
                : 'behandelplan_opgesteld',
        ]);
    }

    /**
     * Herbeoordelen: eerst de openstaande taak afronden (dat is wat de
     * takenKPI meet), dan de nieuwe stand vastleggen.
     */
    private function herbeoordeel(Simulatie $sim, string $sleutel, int $maand): void
    {
        $risico = $this->beoordeel($sim, $sleutel);
        $def = $sim->fixtures()->definitie('risicos', 'risicos', $sleutel);

        TaakPlanner::voltooiVoorEntiteit($risico, 'risico-herbeoordeling');

        $stap = collect($def['verloop'] ?? [])->firstWhere('maand', $maand);

        $risico->update([
            'kans_niveau' => $stap['kans'] ?? $risico->kans_niveau,
            'impact_niveau' => $stap['impact'] ?? $risico->impact_niveau,
            'volgende_beoordeling_gepland' => now()->addMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN),
        ]);

        // Een behandelplan kan later komen dan de eerste beoordeling; de
        // fixture zegt in welke maand. Dit is wat risico 16 zijn plan geeft.
        if (($def['behandelplan_in_maand'] ?? null) <= $maand) {
            $this->behandelplan($sim, $sleutel);
        }

        $this->werkStatusBij($risico->refresh(), $def);
    }

    /**
     * Een risico dat zijn eindstand haalt is gemitigeerd. Een geaccepteerd
     * risico blijft geaccepteerd: dat is een besluit, geen meting.
     */
    private function werkStatusBij(Risico $risico, array $def): void
    {
        $eind = ($def['eind']['kans'] ?? 0) * ($def['eind']['impact'] ?? 0);

        if ($risico->status !== 'geaccepteerd'
            && in_array($def['behandeloptie'], ['mitigeren', 'overdragen'], true)
            && $risico->risicoscore === $eind) {
            $risico->update(['status' => 'gemitigeerd']);
        }
    }
}
