<?php

namespace App\Support;

use App\Models\Gebruiker;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use Illuminate\Support\Collection;

/**
 * De afleidingen van de trainingsschermen (implementatie/10), losgetrokken uit
 * `TrainingenOverzicht`, `MijnTrainingen` en `ToetsenResultaten` voor de
 * assistenttool `training-en-bewustzijn` (implementatie/14b §4.3). Scherm en
 * tool rekenen zo met dezelfde regels.
 *
 * Wie wat mag zien, beslist de aanroeper: het programmaoverzicht en de
 * toetsresultaten zijn voor wie `Recordscope::magAllesZien` heeft, de
 * eigen-trainingenlijst voor wie `uitvoeren` heeft. Deze klasse rekent alleen.
 */
final class Trainingsstand
{
    /**
     * Afgeleide kolommen per module (§5): graad en aantal verlopen leden.
     * Verlopen = doelgroepleden met wél een voltooiing maar geen geldige.
     *
     * @return array{graad: int|null, doelgroepGrootte: int, verlopen: int}
     */
    public static function moduleCijfers(Trainingsmodule $module): array
    {
        $doelgroepIds = $module->doelgroepGebruikerIds();
        $grootte = count($doelgroepIds);

        $geldigeIds = $grootte
            ? $module->voltooiingen()->geldig()->whereIn('gebruiker_id', $doelgroepIds)
                ->distinct()->pluck('gebruiker_id')
            : collect();
        $ooitIds = $grootte
            ? $module->voltooiingen()->whereIn('gebruiker_id', $doelgroepIds)
                ->distinct()->pluck('gebruiker_id')
            : collect();

        return [
            'graad' => $grootte ? (int) round($geldigeIds->count() / $grootte * 100) : null,
            'doelgroepGrootte' => $grootte,
            'verlopen' => $ooitIds->diff($geldigeIds)->count(),
        ];
    }

    /** Rapportagesignaal (§15): een verplichting zonder publiek is een gap. */
    public static function modulesZonderDoelgroep(): int
    {
        return Trainingsmodule::where('actief', true)
            ->whereDoesntHave('doelgroepen')->count();
    }

    /**
     * Een toets-module die nooit is uitgezet is niet af te ronden (geen
     * zelfregistratie, geen toets) — het gat dat de enkele bron van waarheid
     * nog openlaat (§8).
     */
    public static function toetsModulesNietUitgezet(): int
    {
        return Trainingsmodule::where('actief', true)
            ->whereNotNull('toets_bestand')
            ->whereDoesntHave('toetsopdrachten')->count();
    }

    /**
     * De actieve modules in de doelgroepen van deze gebruiker, met zijn eigen
     * status en de nieuwste toets die aan hem is uitgezet.
     *
     * @return Collection<int, array{module: Trainingsmodule, status: string, toets: Toetsopdracht|null}>
     */
    public static function eigenModules(Gebruiker $gebruiker): Collection
    {
        $doelgroepIds = $gebruiker->doelgroepen()->pluck('doelgroepen.id');

        $modules = Trainingsmodule::query()
            ->where('actief', true)
            ->whereHas('doelgroepen', fn ($q) => $q->whereIn('doelgroepen.id', $doelgroepIds))
            ->orderBy('titel')
            ->get();

        return $modules->map(fn (Trainingsmodule $module) => [
            'module' => $module,
            'status' => $module->statusVoor($gebruiker),
            'toets' => $module->toetsopdrachten()
                ->whereHas('taak', fn ($q) => $q->where('eigenaar_id', $gebruiker->id))
                ->latest('id')
                ->first(),
        ]);
    }

    /**
     * Toetsen die zonder module aan deze gebruiker zijn uitgezet (bron 'los').
     *
     * Eén rij per toetsbestand — de nieuwste. Een toets kan opnieuw worden
     * uitgezet zodra de vorige is afgerond, en dan hoort de lijst de huidige
     * stand te tonen en geen historie.
     *
     * @return Collection<int, Toetsopdracht>
     */
    public static function losseToetsen(Gebruiker $gebruiker): Collection
    {
        return Toetsopdracht::query()
            ->with('taak')
            ->whereNull('trainingsmodule_id')
            ->whereHas('taak', fn ($q) => $q->where('eigenaar_id', $gebruiker->id))
            ->latest('id')
            ->get()
            ->unique('toets_bestand')
            ->sortBy(fn (Toetsopdracht $opdracht) => $opdracht->taak?->deadline)
            ->values();
    }

    /**
     * De toetsresultaten over iedereen (`/toetsen/resultaten`). Status
     * 'openstaand' is alles wat nog niet geslaagd is; '' is alles.
     *
     * @return Collection<int, Toetsopdracht>
     */
    public static function toetsresultaten(string $toets = '', string $status = 'openstaand'): Collection
    {
        return Toetsopdracht::query()
            ->with(['taak.eigenaar'])
            ->when($toets !== '', fn ($q) => $q->where('toets_bestand', $toets))
            ->when($status === 'openstaand', fn ($q) => $q->where('status', '!=', 'geslaagd'))
            ->when(! in_array($status, ['', 'openstaand'], true),
                fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->get();
    }
}
