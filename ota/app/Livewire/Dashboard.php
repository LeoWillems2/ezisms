<?php

namespace App\Livewire;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use App\Models\KpiDefinitie;
use App\Models\Taak;
use App\Support\Dashboardsignalen;
use App\Support\Kpitrend;
use App\Support\Maatregelverdeling;
use App\Support\Risicoverdeling;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het dashboard (implementatie/12c). Vijf panelen boven de eigen takenlijst:
 * KPI-strip, signalen, PDCA-trend, risico's + maatregelen, aantallen.
 *
 * **Elk paneel hangt achter de autorisatiecheck van het scherm waar het naar
 * verwijst** (12c §2 en §8, besluit 30-07-2026). Niet één check voor het geheel:
 * een Medewerker heeft geen risico-inzage, en een lege risicomatrix tonen is
 * slechter dan geen matrix. Gevolg, en dat is bedoeld: het dashboard ziet er per
 * rol anders uit.
 *
 * Er wordt niets opgehaald voor een paneel dat de kijker niet mag zien — de
 * check staat vóór de query, niet in de view.
 */
#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    /** Hoeveel tegels de strip toont. */
    private const STRIP_TEGELS = 4;

    /**
     * De vaste eerste tegel. De overige drie worden op status gekozen (zie
     * `strip()`), maar één anker blijft staan: een strip die elke maand volledig
     * van samenstelling wisselt is niet te volgen, en dit is het cijfer waar de
     * implementatievoortgang aan af te lezen is.
     */
    private const STRIP_ANKER = 'soa_geimplementeerd';

    /**
     * Slechtste status eerst (implementatie/12d §6). Blok 12 §4 waarschuwt tegen
     * een dashboard dat alleen groene pijlen omhoog toont — drie geslaagde
     * tegels naast elkaar is een reclamefolder, niet een meting.
     *
     * `onbepaald` staat vóór `goed` en niet erachter: een KPI zonder vastgestelde
     * streefwaarde is geen prestatie om te tonen, het is een openstaande keuze.
     */
    private const STATUS_VOLGORDE = [
        Kpitrend::STATUS_SLECHT => 0,
        Kpitrend::STATUS_AANDACHT => 1,
        Kpitrend::STATUS_ONBEPAALD => 2,
        Kpitrend::STATUS_GOED => 3,
    ];

    /** PDCA-volgorde. Act staat erbij ook als hij leeg is — zie render(). */
    private const FASE_LABELS = [
        'plan' => 'Plan',
        'do' => 'Do',
        'check' => 'Check',
        'act' => 'Act',
    ];

    public function magMeten(): bool
    {
        return Gate::allows('heeft-niveau', ['management-review-verbetercyclus', 'lezen']);
    }

    public function magRisicoSoa(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'lezen']);
    }

    public function magBeleid(): bool
    {
        return Gate::allows('heeft-niveau', ['beleid-maatregelbeheer', 'lezen']);
    }

    public function magTaken(): bool
    {
        return Gate::allows('heeft-niveau', ['taken-workflow-engine', 'uitvoeren']);
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'mijnTaken' => $this->magTaken() ? $this->mijnTaken() : null,
            'strip' => $this->magMeten() ? $this->strip() : null,
            'signalen' => $this->magMeten() ? $this->signalen() : null,
            'perFase' => $this->magMeten() ? $this->perFase() : null,
            'faseLabels' => self::FASE_LABELS,
            'verdeling' => $this->magRisicoSoa() ? Risicoverdeling::huidige() : null,
            'maatregelen' => $this->magRisicoSoa() ? Maatregelverdeling::huidige() : null,
            'aantallen' => $this->magBeleid() ? $this->aantallen() : null,
        ]);
    }

    /** @return Collection<int, Taak> */
    private function mijnTaken()
    {
        return Taak::query()
            ->where('eigenaar_id', auth()->id())
            ->whereIn('status', Taak::OPENSTAAND)
            ->orderBy('deadline')
            ->limit(5)
            ->get();
    }

    /**
     * Alle KPI's met hun volledige reeks. Eén query voor de definities en één
     * voor de metingen (eager load); het register is klein genoeg om in PHP te
     * groeperen.
     *
     * @return Collection<int, Kpitrend>
     */
    private function trends()
    {
        return KpiDefinitie::query()
            ->where('actief', true)
            ->with(['metingen' => fn ($q) => $q->orderBy('gemeten_op')])
            ->orderBy('naam')
            ->get()
            ->map(fn (KpiDefinitie $d) => Kpitrend::van($d, $d->metingen));
    }

    /**
     * De strip: één vaste tegel plus de slechtst staande KPI's (12d §6).
     *
     * Vóór de streefwaarden was deze selectie een handmatig lijstje met de
     * aantekening "de laatste staat er bewust bij, die staat het minst goed".
     * Dat was een oordeel dat de bouwer had geveld en dat verouderde zodra de
     * cijfers bewogen. Nu bepaalt de norm het, en dat is precies waar hij voor is.
     *
     * @return Collection<int, Kpitrend>
     */
    private function strip()
    {
        $trends = $this->trends();
        $anker = $trends->firstWhere(fn (Kpitrend $t) => $t->definitie->sleutel === self::STRIP_ANKER);

        $rest = $trends
            ->reject(fn (Kpitrend $t) => $anker !== null && $t->definitie->is($anker->definitie))
            // Op sleutel als tweede sorteersleutel, niet op uitkomst: anders
            // wisselt de strip van samenstelling bij elke meting die een cijfer
            // een half procent verschuift.
            ->sortBy(fn (Kpitrend $t) => [
                self::STATUS_VOLGORDE[$t->status()] ?? count(self::STATUS_VOLGORDE),
                $t->definitie->sleutel,
            ])
            ->values();

        return collect([$anker])
            ->filter()
            ->concat($rest)
            ->take(self::STRIP_TEGELS)
            ->values();
    }

    /** @return list<array{vlag: string, tekst: string, uitleg: string, getal: string}> */
    private function signalen(): array
    {
        return Dashboardsignalen::stel(
            $this->trends()->all(),
            magRisicoLezen: $this->magRisicoSoa(),
            magSoaLezen: $this->magRisicoSoa(),
        )->alle();
    }

    /**
     * De trends gegroepeerd per PDCA-fase. Anders dan `MeetaanpakOverzicht`
     * blijven **lege fasen staan**: op een trendpaneel is een lege Act-fase
     * informatie — het ISMS meet zijn eigen bijsturing dan nog niet (12c §3.3).
     *
     * @return array<string, Collection<int, Kpitrend>>
     */
    private function perFase(): array
    {
        $trends = $this->trends();

        $perFase = [];
        foreach (array_keys(self::FASE_LABELS) as $fase) {
            $perFase[$fase] = $trends
                ->filter(fn (Kpitrend $t) => $t->definitie->fase === $fase)
                ->values();
        }

        return $perFase;
    }

    /** @return array<string, array{getal: int, label: string, bij: string}> */
    private function aantallen(): array
    {
        $documenten = Beleidsdocument::count();
        $actief = Beleidsdocument::where('status', 'actief')->count();
        $versies = Beleidsversie::count();
        $herzien = Beleidsversie::selectRaw('beleidsdocument_id')
            ->groupBy('beleidsdocument_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();
        $bewijs = Bewijsstuk::where('status', 'actief')->count();
        $bewijsTotaal = Bewijsstuk::count();
        $takenOpen = Taak::whereIn('status', Taak::OPENSTAAND)->count();
        $takenTotaal = Taak::count();

        // De derde regel per tegel is waar de informatie zit: "16 versies" is een
        // boekhoudfeit, "3 documenten herzien" zegt dat de cyclus loopt (12c §3.5).
        return [
            'documenten' => [
                'getal' => $documenten,
                'label' => 'Beleidsdocumenten',
                'bij' => $actief.' actief · '.($documenten - $actief).' niet actief',
            ],
            'versies' => [
                'getal' => $versies,
                'label' => 'Versies',
                'bij' => $herzien === 1 ? '1 document herzien' : $herzien.' documenten herzien',
            ],
            'bewijs' => [
                'getal' => $bewijs,
                'label' => 'Bewijsstukken',
                'bij' => $bewijs === $bewijsTotaal
                    ? 'alle '.$bewijsTotaal.' actief'
                    : 'van '.$bewijsTotaal.' in totaal',
            ],
            'taken' => [
                'getal' => $takenOpen,
                'label' => 'Openstaande taken',
                'bij' => 'van '.$takenTotaal.' in totaal',
            ],
        ];
    }
}
