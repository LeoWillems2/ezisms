<?php

namespace App\Livewire;

use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Models\Rol;
use App\Models\Taak;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use App\Support\ToetsBestanden;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ToetsenUitzetten extends Component
{
    private const BLOK = 'bewustzijn-training';

    /**
     * Wat wordt uitgezet: een module-toets (bestand + koppeling komen uit de
     * module) of een losse, niet aan een module gekoppelde toets. Zo is er één
     * bron van waarheid voor "welke toets hoort bij deze module" (§8).
     */
    public string $bron = 'module';

    public string $moduleId = '';

    public string $losseToets = '';

    /** @var array<int, int> */
    public array $geselecteerdeGebruikers = [];

    public int $weken = 4;

    // Filters op de gebruikerslijst.
    public string $filterEenheid = '';

    public string $filterRol = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    /** Actieve modules die een (bestaand) toetsbestand hebben. */
    private function toetsModules(): Collection
    {
        return Trainingsmodule::query()
            ->where('actief', true)
            ->whereNotNull('toets_bestand')
            ->orderBy('titel')
            ->get(['id', 'titel', 'toets_bestand']);
    }

    /** @return Collection<int, Gebruiker> */
    private function zichtbareGebruikers()
    {
        return Gebruiker::selecteerbaar()
            ->when($this->filterEenheid !== '', fn ($q) => $q->where('organisatie_eenheid_id', $this->filterEenheid))
            ->when($this->filterRol !== '', fn ($q) => $q
                ->whereHas('rollen', fn ($r) => $r->where('rollen.id', $this->filterRol)))
            ->orderBy('naam')
            ->get(['id', 'naam', 'organisatie_eenheid_id']);
    }

    public function selecteerAlleZichtbare(): void
    {
        $this->geselecteerdeGebruikers = $this->zichtbareGebruikers()->pluck('id')->all();
    }

    public function uitzetten(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'bron' => ['required', Rule::in(['module', 'los'])],
            'moduleId' => [Rule::requiredIf($this->bron === 'module'), Rule::exists('trainingsmodules', 'id')],
            'losseToets' => [Rule::requiredIf($this->bron === 'los'), Rule::in(array_keys(ToetsBestanden::beschikbaar()))],
            'geselecteerdeGebruikers' => ['required', 'array', 'min:1'],
            'geselecteerdeGebruikers.*' => ['integer', Rule::exists('gebruikers', 'id')],
            'weken' => ['required', 'integer', 'min:1', 'max:52'],
        ]);

        // Eén plek bepaalt bestand + koppeling; bij een module komen beide uit de
        // module, niet uit een losse keuze die ermee kan divergeren.
        if ($this->bron === 'module') {
            $module = Trainingsmodule::findOrFail((int) $this->moduleId);
            $bestand = (string) $module->toets_bestand;
            $moduleId = $module->id;
        } else {
            $bestand = $this->losseToets;
            $moduleId = null;
        }

        if ($bestand === '' || ! ToetsBestanden::bestaat($bestand)) {
            $this->addError('moduleId', 'Het toetsbestand bestaat niet (meer) op de schijf.');

            return;
        }

        $titel = ToetsBestanden::titelVoor($bestand);
        $deadline = Carbon::today()->addWeeks($this->weken);

        $aangemaakt = 0;
        $overgeslagen = 0;

        foreach ($this->geselecteerdeGebruikers as $gebruikerId) {
            // Idempotent: al een openstaande toets voor dit bestand? Overslaan —
            // niemand krijgt dezelfde toets twee keer in zijn lijst (§8).
            $bestaat = Toetsopdracht::where('toets_bestand', $bestand)
                ->whereHas('taak', fn ($q) => $q
                    ->where('eigenaar_id', $gebruikerId)
                    ->whereIn('status', Taak::OPENSTAAND))
                ->exists();

            if ($bestaat) {
                $overgeslagen++;

                continue;
            }

            $taak = Taak::create([
                'titel' => 'Toets maken: '.$titel,
                'eigenaar_id' => $gebruikerId,
                'deadline' => $deadline,
                'gekoppeld_blok_naam' => self::BLOK,
            ]);

            $opdracht = Toetsopdracht::create([
                'taak_id' => $taak->id,
                'trainingsmodule_id' => $moduleId,
                'toets_bestand' => $bestand,
                'toets_titel' => $titel,
                'token' => Str::random(64),
            ]);

            // De taak wijst naar de opdracht, zodat het takenscherm de "Start
            // toets"-knop kan bouwen zonder de token in vrije tekst te zetten.
            $taak->update([
                'gekoppeld_entiteit_type' => $opdracht->getMorphClass(),
                'gekoppeld_entiteit_id' => $opdracht->id,
            ]);

            $aangemaakt++;
        }

        $this->reset(['moduleId', 'losseToets', 'geselecteerdeGebruikers']);

        session()->flash('melding', "{$aangemaakt} taken aangemaakt, {$overgeslagen} overgeslagen (al openstaand).");
    }

    public function render()
    {
        return view('livewire.toetsen-uitzetten', [
            'modules' => $this->toetsModules(),
            'losseToetsen' => ToetsBestanden::beschikbaar(),
            'gebruikers' => $this->zichtbareGebruikers(),
            'eenheden' => OrganisatieEenheid::orderBy('naam')->get(['id', 'naam']),
            'rollen' => Rol::orderBy('naam')->get(['id', 'naam']),
        ]);
    }
}
