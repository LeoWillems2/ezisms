<?php

namespace App\Livewire;

use App\Models\Gebruiker;
use App\Models\Taaksjabloon;
use App\Rules\KiesbareGebruiker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TaaksjablonenOverzicht extends Component
{
    private const BLOK = 'taken-workflow-engine';

    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $naam = '';

    public string $omschrijving = '';

    public string $herhaling = 'jaarlijks';

    public ?int $intervalDagen = null;

    public string $bronBlok = 'risico-soa';

    public string $standaardEigenaarId = '';

    public int $aanmakenDagenVooraf = 14;

    public bool $actief = true;

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', [self::BLOK, 'muteren']), 403);
    }

    public function nieuwSjabloon(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'naam', 'omschrijving', 'intervalDagen', 'standaardEigenaarId']);
        $this->herhaling = 'jaarlijks';
        $this->aanmakenDagenVooraf = 14;
        $this->actief = true;
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $sjabloonId): void
    {
        $this->vereisMuteren();

        $sjabloon = Taaksjabloon::findOrFail($sjabloonId);
        $this->bewerktId = $sjabloon->id;
        $this->naam = $sjabloon->naam;
        $this->omschrijving = $sjabloon->omschrijving ?? '';
        $this->herhaling = $sjabloon->herhaling;
        $this->intervalDagen = $sjabloon->interval_dagen;
        $this->bronBlok = $sjabloon->bron_blok;
        $this->standaardEigenaarId = (string) $sjabloon->standaard_eigenaar_id;
        $this->aanmakenDagenVooraf = $sjabloon->aanmaken_dagen_vooraf;
        $this->actief = $sjabloon->actief;

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

        // De al opgeslagen standaard-eigenaar mag blijven, ook als die inmiddels
        // gedeactiveerd is; een nieuwe keuze moet actief zijn.
        $behoudEigenaar = $this->bewerktId
            ? Taaksjabloon::whereKey($this->bewerktId)->value('standaard_eigenaar_id')
            : null;

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            'herhaling' => ['required', Rule::in(['eenmalig', 'maandelijks', 'per_kwartaal', 'jaarlijks', 'aangepast'])],
            // 'aangepast' zonder interval is niet uitvoerbaar; de generator zou
            // dan geen volgende deadline kunnen bepalen.
            'intervalDagen' => [Rule::requiredIf($this->herhaling === 'aangepast'), 'nullable', 'integer', 'min:1', 'max:3650'],
            'bronBlok' => ['required', 'string', 'max:50'],
            'standaardEigenaarId' => ['nullable', new KiesbareGebruiker($behoudEigenaar)],
            'aanmakenDagenVooraf' => ['required', 'integer', 'min:0', 'max:365'],
        ], attributes: [
            'naam' => 'naam',
            'intervalDagen' => 'interval in dagen',
            'aanmakenDagenVooraf' => 'dagen vooraf',
        ]);

        Taaksjabloon::updateOrCreate(['id' => $this->bewerktId], [
            'naam' => $gevalideerd['naam'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'herhaling' => $gevalideerd['herhaling'],
            'interval_dagen' => $this->herhaling === 'aangepast' ? $gevalideerd['intervalDagen'] : null,
            'bron_blok' => $gevalideerd['bronBlok'],
            'standaard_eigenaar_id' => $gevalideerd['standaardEigenaarId'] !== '' ? (int) $gevalideerd['standaardEigenaarId'] : null,
            'aanmaken_dagen_vooraf' => $gevalideerd['aanmakenDagenVooraf'],
            'actief' => $this->actief,
        ]);

        $this->toontFormulier = false;
        session()->flash('melding', 'Sjabloon opgeslagen.');
    }

    public function render()
    {
        return view('livewire.taaksjablonen-overzicht', [
            'sjablonen' => Taaksjabloon::with('standaardEigenaar')->withCount('taken')->orderBy('naam')->get(),
            'gebruikers' => Gebruiker::kiesbaar($this->standaardEigenaarId),
        ]);
    }
}
