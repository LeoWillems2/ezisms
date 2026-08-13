<?php

namespace App\Livewire;

use App\Models\Beleidsdocument;
use App\Models\Doelgroep;
use App\Models\Trainingsmodule;
use App\Support\Koppeling;
use App\Support\Recordscope;
use App\Support\ToetsBestanden;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrainingenOverzicht extends Component
{
    private const BLOK = 'bewustzijn-training';

    #[Url]
    public string $filterActief = 'actief';

    #[Url]
    public string $filterDoelgroep = '';

    // Formulier.
    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $titel = '';

    public string $geldigheidsduurMaanden = '';

    public string $toetsBestand = '';

    /** @var array<int, int> */
    public array $geselecteerdeDoelgroepen = [];

    /** @var array<int, int> */
    public array $geselecteerdeBeleidsdocumenten = [];

    public function mount(): void
    {
        // 'lezen' laat ook de Medewerker (uitvoeren) door de route; programma-
        // beheer is echter CISO/Auditor-werk (§11).
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

    public function nieuweModule(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'titel', 'geldigheidsduurMaanden', 'toetsBestand',
            'geselecteerdeDoelgroepen', 'geselecteerdeBeleidsdocumenten']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $moduleId): void
    {
        $this->vereisMuteren();

        $module = Trainingsmodule::with(['doelgroepen', 'beleidsdocumenten'])->findOrFail($moduleId);

        $this->bewerktId = $module->id;
        $this->titel = $module->titel;
        $this->geldigheidsduurMaanden = (string) ($module->geldigheidsduur_maanden ?? '');
        $this->toetsBestand = (string) ($module->toets_bestand ?? '');
        $this->geselecteerdeDoelgroepen = $module->doelgroepen->pluck('id')->all();
        $this->geselecteerdeBeleidsdocumenten = $module->beleidsdocumenten->pluck('id')->all();

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
            'titel' => ['required', 'string', 'max:255'],
            'geldigheidsduurMaanden' => ['nullable', 'integer', 'min:1', 'max:120'],
            'toetsBestand' => ['nullable', Rule::in(array_keys(ToetsBestanden::beschikbaar()))],
            'geselecteerdeDoelgroepen' => ['array'],
            'geselecteerdeDoelgroepen.*' => ['integer', Rule::exists('doelgroepen', 'id')],
            'geselecteerdeBeleidsdocumenten' => ['array'],
            'geselecteerdeBeleidsdocumenten.*' => ['integer', Rule::exists('beleidsdocumenten', 'id')],
        ], attributes: ['titel' => 'titel', 'geldigheidsduurMaanden' => 'geldigheidsduur']);

        $attributen = [
            'titel' => $gevalideerd['titel'],
            'geldigheidsduur_maanden' => $gevalideerd['geldigheidsduurMaanden'] !== null
                ? (int) $gevalideerd['geldigheidsduurMaanden'] : null,
            'toets_bestand' => $gevalideerd['toetsBestand'] ?: null,
        ];

        $module = $this->bewerktId
            ? tap(Trainingsmodule::findOrFail($this->bewerktId))->update($attributen)
            : Trainingsmodule::create($attributen);

        Koppeling::sync($module->doelgroepen(), 'doelgroepen', $this->geselecteerdeDoelgroepen);
        Koppeling::sync($module->beleidsdocumenten(), 'beleidsdocumenten', $this->geselecteerdeBeleidsdocumenten);

        $this->sluitFormulier();
        session()->flash('melding', 'Trainingsmodule opgeslagen.');
    }

    public function wisselActief(int $moduleId): void
    {
        $this->vereisMuteren();

        $module = Trainingsmodule::findOrFail($moduleId);
        $module->update(['actief' => ! $module->actief]);

        session()->flash('melding', $module->actief ? 'Module heractiveerd.' : 'Module ingetrokken.');
    }

    public function render()
    {
        $modules = Trainingsmodule::query()
            ->with(['doelgroepen'])
            ->when($this->filterActief !== '', fn ($q) => $q->where('actief', $this->filterActief === 'actief'))
            ->when($this->filterDoelgroep !== '', fn ($q) => $q
                ->whereHas('doelgroepen', fn ($d) => $d->where('doelgroepen.id', $this->filterDoelgroep)))
            ->orderBy('titel')
            ->get();

        // De toetsbestanden op schijf: hergebruikt voor zowel de keuzelijst als
        // de preview-check, zodat de map de enige waarheid blijft (§8).
        $beschikbareToetsen = ToetsBestanden::beschikbaar();

        // Afgeleide kolommen per module (§5): graad en aantal verlopen leden.
        // Verlopen = doelgroepleden met wél een voltooiing maar geen geldige.
        $rijen = $modules->map(function (Trainingsmodule $module) use ($beschikbareToetsen) {
            $doelgroepIds = $module->doelgroepGebruikerIds();
            $grootte = count($doelgroepIds);

            $geldigeIds = $grootte
                ? $module->voltooiingen()->geldig()->whereIn('gebruiker_id', $doelgroepIds)
                    ->distinct()->pluck('gebruiker_id')
                : collect();
            $ooitIds = $grootte
                ? $module->voltooiingen()->whereIn('gebruiker_id', $doelgroepIds)
                    ->distinct()->pluck('gebruiker_id')
                : collect();

            return [
                'module' => $module,
                'graad' => $grootte ? (int) round($geldigeIds->count() / $grootte * 100) : null,
                'doelgroepGrootte' => $grootte,
                'verlopen' => $ooitIds->diff($geldigeIds)->count(),
                // Preview alleen als het toetsbestand ook echt op schijf staat: een
                // opgeslagen naam kan verwijzen naar een inmiddels verwijderd
                // bestand, en dan zou de preview een 404 opleveren.
                'toetsPreviewUrl' => $module->heeftToets() && array_key_exists($module->toets_bestand, $beschikbareToetsen)
                    ? route('toetsen.voorbeeld', $module->toets_bestand)
                    : null,
            ];
        });

        return view('livewire.trainingen-overzicht', [
            'rijen' => $rijen,
            'doelgroepen' => Doelgroep::orderBy('naam')->get(),
            'beleidsdocumenten' => Beleidsdocument::orderBy('titel')->get(['id', 'titel']),
            'toetsen' => $beschikbareToetsen,
            // Rapportagesignaal (§15): een verplichting zonder publiek is een gap.
            'modulesZonderDoelgroep' => Trainingsmodule::where('actief', true)
                ->whereDoesntHave('doelgroepen')->count(),
            // Een toets-module die nooit is uitgezet is niet af te ronden
            // (geen zelfregistratie, geen toets) — het gat dat de enkele bron
            // van waarheid nog openlaat (§8).
            'toetsModulesNietUitgezet' => Trainingsmodule::where('actief', true)
                ->whereNotNull('toets_bestand')
                ->whereDoesntHave('toetsopdrachten')->count(),
        ]);
    }
}
