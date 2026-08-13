<?php

namespace App\Livewire;

use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Support\Koppeling;
use App\Support\Recordscope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DoelgroepenOverzicht extends Component
{
    private const BLOK = 'bewustzijn-training';

    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $naam = '';

    public string $omschrijving = '';

    /** @var array<int, int> */
    public array $leden = [];

    public function mount(): void
    {
        abort_unless(Recordscope::magAllesZien(self::BLOK), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuweDoelgroep(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'naam', 'omschrijving', 'leden']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $doelgroepId): void
    {
        $this->vereisMuteren();

        $doelgroep = Doelgroep::with('gebruikers')->findOrFail($doelgroepId);

        $this->bewerktId = $doelgroep->id;
        $this->naam = $doelgroep->naam;
        $this->omschrijving = $doelgroep->omschrijving ?? '';
        $this->leden = $doelgroep->gebruikers->pluck('id')->all();

        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
        $this->bewerktId = null;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string', 'max:255'],
            'leden' => ['array'],
            // Alleen actieve accounts als nieuw lid; een bestaand, inmiddels
            // gedeactiveerd lid blijft via sync gewoon behouden.
            'leden.*' => ['integer', Rule::exists('gebruikers', 'id')],
        ], attributes: ['naam' => 'naam']);

        $doelgroep = $this->bewerktId
            ? tap(Doelgroep::findOrFail($this->bewerktId))->update([
                'naam' => $gevalideerd['naam'],
                'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            ])
            : Doelgroep::create([
                'naam' => $gevalideerd['naam'],
                'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            ]);

        Koppeling::sync($doelgroep->gebruikers(), 'leden', $gevalideerd['leden'] ?? []);

        $this->sluitFormulier();
        session()->flash('melding', 'Doelgroep opgeslagen.');
    }

    public function render()
    {
        return view('livewire.doelgroepen-overzicht', [
            'doelgroepen' => Doelgroep::withCount(['gebruikers', 'modules'])->orderBy('naam')->get(),
            'kiesbareGebruikers' => Gebruiker::selecteerbaar()->orderBy('naam')->get(['id', 'naam']),
        ]);
    }
}
