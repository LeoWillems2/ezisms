<?php

namespace App\Livewire;

use App\Models\Incident;
use App\Support\Incidentmelding;
use App\Support\Recordscope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class IncidentenOverzicht extends Component
{
    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterErnst = '';

    public bool $toontFormulier = false;

    public string $titel = '';

    public string $omschrijving = '';

    public string $ernst = 'midden';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren']);
    }

    public function magAllesZien(): bool
    {
        return Recordscope::magAllesZien('incident-afwijkingenbeheer');
    }

    public function nieuwIncident(): void
    {
        $this->reset(['titel', 'omschrijving']);
        $this->ernst = 'midden';
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    /**
     * Melden vereist alleen `uitvoeren` — bewust niet `muteren`. Een incident
     * melden is de handeling die je van iedereen wilt zien gebeuren; drempels
     * daarop leveren minder meldingen op, niet minder incidenten.
     */
    public function melden(): void
    {
        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            'ernst' => ['required', Rule::in(['laag', 'midden', 'hoog', 'kritiek'])],
        ], attributes: ['titel' => 'titel', 'ernst' => 'ernst']);

        $incident = Incident::create([
            'titel' => $gevalideerd['titel'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'ernst' => $gevalideerd['ernst'],
            'gemeld_door_id' => auth()->id(),
            'gemeld_op' => now(),
            'status' => 'gemeld',
        ]);

        Incidentmelding::meldAanCiso($incident);

        $this->toontFormulier = false;
        session()->flash('melding', 'Incident gemeld. De CISO is per e-mail geïnformeerd.');
    }

    public function render()
    {
        $incidenten = Incident::query()
            ->zichtbaar()
            ->with(['melder', 'afwijkingen'])
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterErnst !== '', fn ($q) => $q->where('ernst', $this->filterErnst))
            ->orderByDesc('gemeld_op')
            ->get();

        return view('livewire.incidenten-overzicht', ['incidenten' => $incidenten]);
    }
}
