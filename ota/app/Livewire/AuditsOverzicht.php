<?php

namespace App\Livewire;

use App\Models\Auditplan;
use App\Models\Auditronde;
use App\Models\Bevinding;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Auditplannen per jaar met hun rondes en de rapportagesignalen (§9/§11).
 * De administratie (plan/ronde aanmaken, vaststellen) is `muteren` (CISO); het
 * vastleggen van bevindingen gebeurt in de rondedetail achter de record-guard.
 */
#[Layout('components.layouts.app')]
class AuditsOverzicht extends Component
{
    private const TYPES = ['intern', 'intern_nulmeting', 'extern_certificering', 'extern_surveillance'];

    // Plan-formulier.
    public bool $toontPlanFormulier = false;

    public string $jaar = '';

    // Ronde-formulier.
    public bool $toontRondeFormulier = false;

    public ?int $rondePlanId = null;

    public string $rondeType = 'intern';

    public ?string $rondeGeplandOp = null;

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuwPlan(): void
    {
        $this->vereisMuteren();
        $this->reset('jaar');
        $this->resetValidation();
        $this->jaar = (string) now()->year;
        $this->toontPlanFormulier = true;
    }

    public function slaPlanOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'jaar' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('auditplannen', 'jaar')],
        ], attributes: ['jaar' => 'jaar']);

        Auditplan::create(['jaar' => (int) $gevalideerd['jaar']]);

        $this->toontPlanFormulier = false;
        $this->reset('jaar');
        session()->flash('melding', 'Auditplan aangemaakt.');
    }

    public function stelVast(int $planId): void
    {
        $this->vereisMuteren();
        $plan = Auditplan::findOrFail($planId);
        // Vaststellen is eenrichting: een concept wordt definitief.
        if ($plan->status === 'concept') {
            $plan->update(['status' => 'vastgesteld']);
            session()->flash('melding', "Auditplan {$plan->jaar} vastgesteld.");
        }
    }

    public function nieuweRonde(int $planId): void
    {
        $this->vereisMuteren();
        $this->reset(['rondeType', 'rondeGeplandOp']);
        $this->resetValidation();
        $this->rondePlanId = $planId;
        $this->rondeType = 'intern';
        $this->toontRondeFormulier = true;
    }

    public function slaRondeOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'rondePlanId' => ['required', Rule::exists('auditplannen', 'id')],
            'rondeType' => ['required', Rule::in(self::TYPES)],
            'rondeGeplandOp' => ['nullable', 'date'],
        ], attributes: ['rondeType' => 'type', 'rondeGeplandOp' => 'geplande datum']);

        // De scope, de auditor en de externe naam zet de CISO in het
        // rondedossier; hier alleen het skelet, daarna direct erheen.
        $ronde = Auditronde::create([
            'auditplan_id' => $gevalideerd['rondePlanId'],
            'type' => $gevalideerd['rondeType'],
            'gepland_op' => $gevalideerd['rondeGeplandOp'] ?: null,
        ]);

        $this->toontRondeFormulier = false;
        $this->redirectRoute('audits.ronde', $ronde, navigate: true);
    }

    public function render()
    {
        $plannen = Auditplan::query()
            ->with(['rondes' => fn ($q) => $q->with('auditor')->orderByDesc('gepland_op')->orderByDesc('id')])
            ->orderByDesc('jaar')
            ->get();

        // Rapportagesignalen (§11), over alle rondes heen.
        $openPerType = Bevinding::query()
            ->where('status', '!=', 'gesloten')
            ->selectRaw('type, count(*) as aantal')
            ->groupBy('type')
            ->pluck('aantal', 'type');

        // Een nulmeting telt mee als "laatste interne audit": hij is er een, en
        // voor het signaal "hoe lang geleden auditten we onszelf" is dat wat
        // telt. Alleen de dekkingstelling behandelt hem anders.
        $laatsteIntern = Auditronde::query()
            ->whereIn('type', Auditronde::INTERNE_TYPEN)
            ->where('status', 'afgerond')
            ->orderByDesc('uitgevoerd_op')
            ->first();

        return view('livewire.audits-overzicht', [
            'plannen' => $plannen,
            'types' => self::TYPES,
            'openPerType' => $openPerType,
            'openTotaal' => $openPerType->sum(),
            // De filterwaarde waarmee het bevindingenregister hetzelfde toont
            // als deze telling; zo staat de FQCN niet in de view.
            'openstaandFilter' => BevindingenOverzicht::OPENSTAAND,
            'dagenSindsInterneAudit' => $laatsteIntern?->uitgevoerd_op
                ? (int) $laatsteIntern->uitgevoerd_op->diffInDays(now())
                : null,
        ]);
    }
}
