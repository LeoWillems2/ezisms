<?php

namespace App\Livewire;

use App\Models\Leverancier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class LeveranciersOverzicht extends Component
{
    private const NIVEAUS = ['laag', 'midden', 'hoog'];

    private const STATUSSEN = ['kandidaat', 'actief', 'beeindigd'];

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterNiveau = '';

    // Toevoegformulier.
    public bool $toontFormulier = false;

    public string $naam = '';

    public string $risiconiveau = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['leveranciers-derdenrisico', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuweLeverancier(): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'risiconiveau']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'risiconiveau' => ['nullable', Rule::in(self::NIVEAUS)],
        ], attributes: ['naam' => 'naam', 'risiconiveau' => 'risiconiveau']);

        // Nieuwe leverancier start als 'kandidaat' (schema-default); de eerste
        // beoordeling promoveert hem naar 'actief' (§5).
        $leverancier = Leverancier::create([
            'naam' => $gevalideerd['naam'],
            'risiconiveau' => $gevalideerd['risiconiveau'] ?: null,
        ]);

        $this->toontFormulier = false;
        $this->reset(['naam', 'risiconiveau']);

        // Direct door naar het dossier: diensten, clausules en de eerste
        // beoordeling horen daar thuis, niet in deze lijst.
        $this->redirectRoute('leveranciers.detail', $leverancier, navigate: true);
    }

    public function render()
    {
        // Eén query: het register is klein, dus de rapportagesignalen (over het
        // hele register) en de gefilterde lijst komen uit dezelfde set.
        $alle = Leverancier::query()
            ->with(['nieuwsteBeoordeling', 'contractclausules'])
            ->withCount('diensten')
            ->orderBy('naam')
            ->get();

        $lijst = $alle
            ->when($this->filterStatus !== '', fn ($c) => $c->where('status', $this->filterStatus))
            ->when($this->filterNiveau !== '', fn ($c) => $c->where('risiconiveau', $this->filterNiveau))
            ->values();

        return view('livewire.leveranciers-overzicht', [
            'leveranciers' => $lijst,
            'statussen' => self::STATUSSEN,
            'niveaus' => self::NIVEAUS,
            // Rapportagesignalen (§11) — over het hele register, niet de filter.
            'verstrekenBeoordeling' => $alle->filter->herbeoordelingVerstreken()->count(),
            'hoogRisicoZonderAudit' => $alle->filter->isHoogRisicoZonderAuditrecht()->count(),
        ]);
    }
}
