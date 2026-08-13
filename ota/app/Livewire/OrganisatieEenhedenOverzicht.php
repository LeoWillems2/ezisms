<?php

namespace App\Livewire;

use App\Models\OrganisatieEenheid;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrganisatieEenhedenOverzicht extends Component
{
    public bool $toontFormulier = false;

    public string $naam = '';

    public string $type = 'afdeling';

    public ?int $bovenliggendeEenheidId = null;

    /**
     * Herhaalt de check ondanks de route-middleware: de pagina is bereikbaar met
     * 'lezen', maar muteren mag alleen met 'muteren' (conventies §4).
     */
    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['context-scope', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'muteren']);
    }

    public function nieuweEenheid(?int $bovenliggendeId = null): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'type', 'bovenliggendeEenheidId']);
        $this->resetValidation();
        $this->bovenliggendeEenheidId = $bovenliggendeId;
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['afdeling', 'locatie', 'proces'])],
            'bovenliggendeEenheidId' => ['nullable', Rule::exists('organisatie_eenheden', 'id')],
        ], attributes: [
            'naam' => 'naam',
            'type' => 'type',
            'bovenliggendeEenheidId' => 'bovenliggende eenheid',
        ]);

        OrganisatieEenheid::create([
            'naam' => $this->naam,
            'type' => $this->type,
            'bovenliggende_eenheid_id' => $this->bovenliggendeEenheidId,
        ]);

        $this->toontFormulier = false;
        $this->reset(['naam', 'type', 'bovenliggendeEenheidId']);
        session()->flash('melding', 'Organisatie-eenheid toegevoegd.');
    }

    public function verwijderen(OrganisatieEenheid $eenheid): void
    {
        $this->vereisMuteren();

        // nullOnDelete op de zelfverwijzing tilt sub-eenheden naar de wortel op
        // in plaats van ze mee te verwijderen — bewust, om geen data stil te
        // verliezen.
        $eenheid->delete();
        session()->flash('melding', "Eenheid '{$eenheid->naam}' is verwijderd.");
    }

    public function render()
    {
        return view('livewire.organisatie-eenheden-overzicht', [
            // Alleen de wortels ophalen; de Blade-partial rendert de boom recursief.
            'wortels' => OrganisatieEenheid::with('subEenheden')
                ->whereNull('bovenliggende_eenheid_id')
                ->orderBy('naam')
                ->get(),
        ]);
    }
}
