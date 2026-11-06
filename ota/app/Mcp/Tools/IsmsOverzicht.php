<?php

namespace App\Mcp\Tools;

use App\Models\Beleidsdocument;
use App\Models\Incident;
use App\Models\KpiDefinitie;
use App\Models\Taak;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Kpitrend;
use App\Support\Maatregelverdeling;
use App\Support\Normprofiel;
use App\Support\Risicoverdeling;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Het startpunt voor een assistent (implementatie/14b §5). Dezelfde bronnen
 * als het dashboard, elk achter de check van het scherm waar het naar
 * verwijst. Wat de gebruiker niet mag zien, staat onder `weggelaten`.
 */
#[IsReadOnly]
#[Description('Stand van zaken van het ISMS: normprofiel, risico\'s, implementatie van maatregelen, KPI\'s met hun status, openstaande incidenten en eigen taken. Gebruik dit als eerste stap om te zien wat er is en waar het knelt.')]
class IsmsOverzicht extends IsmsTool
{
    protected function vereist(): ?array
    {
        return null;
    }

    protected function beantwoord(Request $request): Response|ResponseFactory
    {
        $uitvoer = [
            'organisatie' => trim((string) config('app.organisatie')) ?: null,
            'normprofiel' => Normprofiel::label('naam'),
            'peildatum' => now()->toIso8601String(),
            'url' => route('dashboard'),
        ];
        $weggelaten = [];

        if ($this->mag('risico-soa', 'lezen')) {
            $uitvoer['risicos'] = $this->risicos();
            $uitvoer['maatregelen'] = $this->maatregelen();
        } else {
            $weggelaten[] = 'risico\'s en maatregelen (geen leesrecht op Risicomanagement & SoA)';
        }

        if ($this->mag('management-review-verbetercyclus', 'lezen')) {
            $uitvoer['kpis'] = $this->kpis();
        } else {
            $weggelaten[] = 'KPI\'s (geen leesrecht op Management Review & Verbetercyclus)';
        }

        if ($this->mag('incident-afwijkingenbeheer', 'uitvoeren')) {
            $uitvoer['incidenten'] = [
                // Via de scope van het scherm: een Medewerker ziet alleen wat hij zelf meldde.
                'openstaand' => Incident::query()->zichtbaar()->where('status', '!=', 'gesloten')->count(),
                'url' => route('incidenten.index'),
            ];
        } else {
            $weggelaten[] = 'incidenten (geen toegang tot Incident- & Afwijkingenbeheer)';
        }

        if ($this->mag('beleid-maatregelbeheer', 'uitvoeren')) {
            $uitvoer['beleid'] = [
                'actieve_documenten' => Beleidsdocument::query()->zichtbaar()->where('status', 'actief')->count(),
                'url' => route('beleid.index'),
            ];
        } else {
            $weggelaten[] = 'beleid (geen toegang tot Beleid & Maatregelbeheer)';
        }

        if ($this->mag('taken-workflow-engine', 'uitvoeren')) {
            $uitvoer['mijn_taken'] = $this->mijnTaken();
        } else {
            $weggelaten[] = 'taken (geen toegang tot Taken & Workflow)';
        }

        $uitvoer['weggelaten'] = $weggelaten;

        app(Aanroepcontext::class)->resultaten(1);

        return Response::structured($uitvoer);
    }

    private function mag(string $blok, string $niveau): bool
    {
        return Gate::allows('heeft-niveau', [$blok, $niveau]);
    }

    /** @return array<string, mixed> */
    private function risicos(): array
    {
        $verdeling = Risicoverdeling::huidige();

        return [
            'beoordeeld' => $verdeling->beoordeeld,
            'niet_beoordeeld' => $verdeling->nietBeoordeeld,
            'boven_acceptatiedrempel' => $verdeling->bovenDrempel(),
            'url' => route('risicos.index'),
        ];
    }

    /** @return array<string, mixed> */
    private function maatregelen(): array
    {
        $verdeling = Maatregelverdeling::huidige();

        $perStatus = [];
        foreach (Maatregelverdeling::statussen() as $status) {
            $perStatus[] = [
                ...self::enum($status, Maatregelverdeling::STATUS_LABELS),
                'aantal' => array_sum(array_column($verdeling->perThema, $status)),
            ];
        }

        return [
            'totaal' => $verdeling->totaal,
            'van_toepassing' => $verdeling->toepasselijk(),
            'nog_niet_geimplementeerd' => $verdeling->nogNietGeimplementeerd(),
            'per_status' => $perStatus,
            'url' => route('soa.index'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function kpis(): array
    {
        return KpiDefinitie::query()
            ->where('actief', true)
            ->with(['metingen' => fn ($q) => $q->orderBy('gemeten_op')])
            ->orderBy('naam')
            ->get()
            ->map(function (KpiDefinitie $definitie): array {
                $trend = Kpitrend::van($definitie, $definitie->metingen);
                $laatste = $trend->laatste();

                return [
                    'sleutel' => $definitie->sleutel,
                    'naam' => $definitie->naam,
                    'fase' => $definitie->fase,
                    'eenheid' => $definitie->eenheid,
                    'laatste_uitkomst' => $laatste !== null ? $trend->uitkomst($laatste) : null,
                    'gemeten_op' => $laatste?->gemeten_op?->toIso8601String(),
                    'streefwaarde' => $laatste?->streefwaarde,
                    'status' => $trend->status(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function mijnTaken(): array
    {
        $taken = Taak::query()
            ->where('eigenaar_id', auth()->id())
            ->whereIn('status', Taak::OPENSTAAND)
            ->orderBy('deadline')
            ->limit(10)
            ->get(['id', 'titel', 'status', 'deadline']);

        return [
            'openstaand' => $taken->map(fn (Taak $taak): array => [
                'titel' => $taak->titel,
                'status' => self::enum($taak->status),
                'deadline' => $taak->deadline?->toDateString(),
            ])->all(),
            'url' => route('taken.index'),
        ];
    }
}
