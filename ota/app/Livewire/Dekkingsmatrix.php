<?php

namespace App\Livewire;

use App\Models\Auditprogramma;
use App\Support\Dekkingsmatrixstand;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De dekkingsmatrix (plan 11b §5): per auditobject over de jaren van een cyclus
 * of het gepland/uitgevoerd/gat is, plus de coverage-KPI. Read-only — dit is
 * §9.1-meting en input voor de directiebeoordeling (§9.3), geen mutatie.
 *
 * "Uitgevoerd" telt uitsluitend een **afgeronde** ronde met uitvoerdatum die aan
 * een jaarplan van dít programma hangt — geen ijdelheidscijfer van afgevinkte
 * vakjes.
 */
#[Layout('components.layouts.app')]
class Dekkingsmatrix extends Component
{
    public ?int $programmaId = null;

    public function mount(): void
    {
        // Standaard het actieve programma, anders het meest recente.
        $this->programmaId = Dekkingsmatrixstand::standaardProgrammaId();
    }

    public function render()
    {
        $programma = $this->programmaId !== null
            ? Auditprogramma::find($this->programmaId)
            : null;

        $programmas = Auditprogramma::orderByDesc('start_datum')->get();

        if ($programma === null) {
            return view('livewire.dekkingsmatrix', [
                'programma' => null,
                'programmas' => $programmas,
                'programmajaren' => [],
                'groepen' => collect(),
                'cellen' => [],
                'kpi' => null,
            ]);
        }

        // De afleiding zelf staat in Dekkingsmatrixstand, zodat de
        // assistenttool `audit-dekking` dezelfde matrix geeft (14b §4.3).
        $stand = Dekkingsmatrixstand::voor($programma);

        return view('livewire.dekkingsmatrix', [
            'programma' => $programma,
            'programmas' => $programmas,
            'programmajaren' => $stand->programmajaren,
            'groepen' => $stand->objecten->groupBy('groep'),
            'cellen' => $stand->cellen,
            'kpi' => $stand->kpi,
        ]);
    }
}
