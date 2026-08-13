<?php

namespace App\Livewire;

use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het interne-audit-programma (plan 11b §4): de meerjarige cyclus als eigen
 * entiteit, de koppeling van jaarplannen eronder, en de risicogebaseerde
 * dekkingsplanning per auditobject. Administratief plannen = CISO muteren; de
 * Auditor leest mee. De feitelijke dekking (welke ronde welk object dekte) en de
 * matrix zitten in Dekkingsmatrix.
 */
#[Layout('components.layouts.app')]
class AuditProgrammaBeheer extends Component
{
    // Programma-formulier.
    public bool $toontFormulier = false;

    public string $naam = '';

    public string $startDatum = '';

    public string $aard = 'certificeringscyclus';

    public string $aantalJaren = '3';

    // Welk programma is uitgeklapt voor planning.
    public ?int $geselecteerdId = null;

    public function mount(): void
    {
        $this->geselecteerdId = Auditprogramma::orderByDesc('start_datum')->value('id');
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    // --- Programma ---------------------------------------------------------

    public function nieuw(): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'startDatum', 'aantalJaren', 'aard']);
        $this->aantalJaren = '3';
        $this->aard = 'certificeringscyclus';
        $this->startDatum = now()->toDateString();
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function slaOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'startDatum' => ['required', 'date'],
            'aantalJaren' => ['required', 'integer', 'min:1', 'max:6'],
            'aard' => ['required', Rule::in(['voorbereiding', 'certificeringscyclus'])],
        ], attributes: [
            'naam' => 'naam',
            'startDatum' => 'startdatum',
            'aantalJaren' => 'aantal jaren',
            'aard' => 'aard',
        ]);

        $programma = Auditprogramma::create([
            'naam' => $gevalideerd['naam'],
            'start_datum' => $gevalideerd['startDatum'],
            'aantal_jaren' => (int) $gevalideerd['aantalJaren'],
            'aard' => $gevalideerd['aard'],
        ]);

        $this->toontFormulier = false;
        $this->geselecteerdId = $programma->id;
        session()->flash('melding', 'Auditprogramma aangemaakt.');
    }

    public function activeer(int $programmaId): void
    {
        $this->vereisMuteren();
        $programma = Auditprogramma::findOrFail($programmaId);
        if ($programma->status === 'concept') {
            $programma->update(['status' => 'actief']);
            session()->flash('melding', "Programma '{$programma->naam}' geactiveerd.");
        }
    }

    public function sluitAf(int $programmaId): void
    {
        $this->vereisMuteren();
        $programma = Auditprogramma::findOrFail($programmaId);
        if ($programma->status === 'actief') {
            $programma->update(['status' => 'afgesloten']);
            session()->flash('melding', "Programma '{$programma->naam}' afgesloten.");
        }
    }

    public function selecteer(int $programmaId): void
    {
        $this->geselecteerdId = $programmaId;
    }

    // --- Jaarplan-koppeling ------------------------------------------------

    /**
     * Koppelt een jaarplan aan de geselecteerde cyclus. Het kalenderjaar van het
     * plan doet er niet meer toe (plan 11c): het plan krijgt het eerstvolgende
     * vrije programmajaar en het bijbehorende venster. Zit de cyclus vol, dan
     * 422 — er zijn niet meer programmajaren dan `aantal_jaren`.
     */
    public function koppelPlan(int $planId): void
    {
        $this->vereisMuteren();
        $programma = $this->geselecteerdProgramma();
        $plan = Auditplan::findOrFail($planId);

        abort_unless($programma !== null, 422);

        $bezet = $programma->auditplannen()->pluck('programmajaar')->filter()->all();
        $vrij = collect($programma->programmajaren())
            ->reject(fn (array $jaar) => in_array($jaar['nummer'], $bezet, true))
            ->first();

        abort_if($vrij === null, 422);

        $plan->update([
            'auditprogramma_id' => $programma->id,
            'programmajaar' => $vrij['nummer'],
            'periode_start' => $vrij['start'],
            'periode_eind' => $vrij['eind'],
        ]);
    }

    public function ontkoppelPlan(int $planId): void
    {
        $this->vereisMuteren();
        $plan = Auditplan::findOrFail($planId);

        // Ook de verankering weg: een los plan hoort geen programmajaar of
        // venster te houden, anders blijft het de matrix beïnvloeden.
        $plan->update([
            'auditprogramma_id' => null,
            'programmajaar' => null,
            'periode_start' => null,
            'periode_eind' => null,
        ]);
    }

    // --- Dekkingsplanning --------------------------------------------------

    /**
     * Zet elk actief auditobject dat nog geen dekkingsregel heeft op een
     * default-interval (eenmaal per cyclus). Verlaagt het werk; de CISO stelt
     * daarna per object bij naar risico (plan 11b §4).
     */
    public function vulStandaardplanning(): void
    {
        $this->vereisMuteren();
        $programma = $this->geselecteerdProgramma();
        if ($programma === null) {
            return;
        }

        $bestaande = $programma->dekkingen()->pluck('auditobject_id')->all();

        $nieuw = Auditobject::actief()
            ->whereNotIn('id', $bestaande)
            ->get()
            ->map(fn (Auditobject $object) => [
                'auditprogramma_id' => $programma->id,
                'auditobject_id' => $object->id,
                'interval_jaren' => $programma->aantal_jaren, // eenmaal per cyclus
                'gepland_start_programmajaar' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        if ($nieuw !== []) {
            AuditprogrammaDekking::insert($nieuw);
        }

        session()->flash('melding', count($nieuw).' object(en) toegevoegd aan de dekkingsplanning.');
    }

    public function stelInterval(int $auditobjectId, int $interval): void
    {
        $this->vereisMuteren();
        $programma = $this->geselecteerdProgramma();
        if ($programma === null) {
            return;
        }

        $interval = max(1, min($interval, $programma->aantal_jaren));

        AuditprogrammaDekking::updateOrCreate(
            ['auditprogramma_id' => $programma->id, 'auditobject_id' => $auditobjectId],
            ['interval_jaren' => $interval, 'gepland_start_programmajaar' => 1],
        );
    }

    public function verwijderDekking(int $auditobjectId): void
    {
        $this->vereisMuteren();
        $programma = $this->geselecteerdProgramma();
        if ($programma === null) {
            return;
        }

        $programma->dekkingen()->where('auditobject_id', $auditobjectId)->delete();
    }

    private function geselecteerdProgramma(): ?Auditprogramma
    {
        return $this->geselecteerdId !== null
            ? Auditprogramma::find($this->geselecteerdId)
            : null;
    }

    public function render()
    {
        $programmas = Auditprogramma::withCount(['auditplannen', 'dekkingen'])
            ->orderByDesc('start_datum')
            ->get();

        $programma = $this->geselecteerdProgramma();

        $objecten = collect();
        $dekkingen = collect();
        $plannenInVenster = collect();

        if ($programma !== null) {
            $objecten = Auditobject::actief()
                ->with('maatregel')
                ->orderBy('groep')
                ->orderBy('volgorde')
                ->get();

            $dekkingen = $programma->dekkingen()->get()->keyBy('auditobject_id');

            // Het kalenderjaar bepaalt niet meer wat bij deze cyclus hoort: de
            // plannen van dit programma plus de nog vrije plannen zijn de
            // kandidaten. Gekoppelde eerst, op programmajaar.
            $plannenInVenster = Auditplan::query()
                ->where('auditprogramma_id', $programma->id)
                ->orWhereNull('auditprogramma_id')
                ->orderByRaw('programmajaar is null')
                ->orderBy('programmajaar')
                ->orderBy('jaar')
                ->get();
        }

        return view('livewire.audit-programma-beheer', [
            'programmas' => $programmas,
            'programma' => $programma,
            'objecten' => $objecten,
            'dekkingen' => $dekkingen,
            'plannenInVenster' => $plannenInVenster,
        ]);
    }
}
