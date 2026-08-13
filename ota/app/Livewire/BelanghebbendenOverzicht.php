<?php

namespace App\Livewire;

use App\Models\Belanghebbende;
use App\Models\Eis;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BelanghebbendenOverzicht extends Component
{
    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $naam = '';

    public string $aard = 'intern';

    public string $relevantieVoorIsms = '';

    // Inline-formulier voor een eis onder een uitgeklapte belanghebbende.
    public ?int $eisVoorBelanghebbendeId = null;

    public string $eisOmschrijving = '';

    public string $eisBron = 'contractueel';

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['context-scope', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'muteren']);
    }

    public function nieuweBelanghebbende(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'naam', 'aard', 'relevantieVoorIsms']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(Belanghebbende $belanghebbende): void
    {
        $this->vereisMuteren();
        $this->resetValidation();
        $this->bewerktId = $belanghebbende->id;
        $this->naam = $belanghebbende->naam;
        $this->aard = $belanghebbende->aard;
        $this->relevantieVoorIsms = $belanghebbende->relevantie_voor_isms ?? '';
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
            'aard' => ['required', Rule::in(['intern', 'extern'])],
            'relevantieVoorIsms' => ['nullable', 'string'],
        ], attributes: [
            'naam' => 'naam',
            'relevantieVoorIsms' => 'relevantie voor het ISMS',
        ]);

        Belanghebbende::updateOrCreate(['id' => $this->bewerktId], [
            'naam' => $gevalideerd['naam'],
            'aard' => $gevalideerd['aard'],
            'relevantie_voor_isms' => $gevalideerd['relevantieVoorIsms'] ?: null,
        ]);

        $this->toontFormulier = false;
        session()->flash('melding', $this->bewerktId ? 'Belanghebbende bijgewerkt.' : 'Belanghebbende toegevoegd.');
        $this->reset(['bewerktId', 'naam', 'aard', 'relevantieVoorIsms']);
    }

    public function verwijderen(Belanghebbende $belanghebbende): void
    {
        $this->vereisMuteren();
        // cascadeOnDelete op eisen ruimt de bijbehorende eisen mee op.
        $belanghebbende->delete();
        session()->flash('melding', 'Belanghebbende verwijderd.');
    }

    public function eisToevoegenAan(int $belanghebbendeId): void
    {
        $this->vereisMuteren();
        $this->reset(['eisOmschrijving', 'eisBron']);
        $this->resetValidation();
        $this->eisVoorBelanghebbendeId = $belanghebbendeId;
    }

    public function annuleerEis(): void
    {
        $this->eisVoorBelanghebbendeId = null;
    }

    public function eisOpslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'eisVoorBelanghebbendeId' => ['required', Rule::exists('belanghebbenden', 'id')],
            'eisOmschrijving' => ['required', 'string'],
            'eisBron' => ['required', Rule::in(['contractueel', 'wettelijk', 'verwachting'])],
        ], attributes: [
            'eisOmschrijving' => 'omschrijving',
            'eisBron' => 'bron',
        ]);

        Eis::create([
            'belanghebbende_id' => $gevalideerd['eisVoorBelanghebbendeId'],
            'omschrijving' => $gevalideerd['eisOmschrijving'],
            'bron' => $gevalideerd['eisBron'],
        ]);

        $this->reset(['eisVoorBelanghebbendeId', 'eisOmschrijving', 'eisBron']);
        session()->flash('melding', 'Eis toegevoegd.');
    }

    public function eisVerwijderen(Eis $eis): void
    {
        $this->vereisMuteren();
        $eis->delete();
        session()->flash('melding', 'Eis verwijderd.');
    }

    public function render()
    {
        return view('livewire.belanghebbenden-overzicht', [
            'belanghebbenden' => Belanghebbende::with('eisen')->orderBy('naam')->get(),
        ]);
    }
}
