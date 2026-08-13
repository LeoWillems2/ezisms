<?php

namespace App\Livewire;

use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MijnTrainingen extends Component
{
    /**
     * Zelfregistratie: de ingelogde gebruiker meldt een module zonder toets als
     * voltooid. Alleen voor zichzelf en alleen binnen de eigen doelgroep, en niet
     * voor een module mét toets — die voltooiing loopt via de toets (§6).
     */
    public function meldVoltooid(int $moduleId): void
    {
        $gebruiker = auth()->user();
        $module = Trainingsmodule::where('actief', true)->findOrFail($moduleId);

        // Binnen de eigen doelgroep? Zo niet: geen recht om te melden.
        abort_unless(in_array($gebruiker->id, $module->doelgroepGebruikerIds(), true), 403);

        if ($module->heeftToets()) {
            session()->flash('fout', 'Deze module rond je af via de toets, niet met een handmatige melding.');

            return;
        }

        // Al een geldige voltooiing? Dan niets doen — voorkomt dubbele rijen bij
        // een dubbelklik.
        if ($module->statusVoor($gebruiker) === 'voltooid') {
            return;
        }

        $module->registreerVoltooiing($gebruiker, 'zelfregistratie');

        session()->flash('melding', 'Training als voltooid geregistreerd.');
    }

    public function render()
    {
        $gebruiker = auth()->user();
        $doelgroepIds = $gebruiker->doelgroepen()->pluck('doelgroepen.id');

        $modules = Trainingsmodule::query()
            ->where('actief', true)
            ->whereHas('doelgroepen', fn ($q) => $q->whereIn('doelgroepen.id', $doelgroepIds))
            ->orderBy('titel')
            ->get();

        $rijen = $modules->map(fn (Trainingsmodule $module) => [
            'module' => $module,
            'status' => $module->statusVoor($gebruiker),
            'toets' => $module->toetsopdrachten()
                ->whereHas('taak', fn ($q) => $q->where('eigenaar_id', $gebruiker->id))
                ->latest('id')
                ->first(),
        ]);

        return view('livewire.mijn-trainingen', [
            'rijen' => $rijen,
            'losseToetsen' => $this->losseToetsen(),
        ]);
    }

    /**
     * Toetsen die zonder module zijn uitgezet (`/toetsen/uitzetten`, bron 'los').
     *
     * Zonder deze lijst is zo'n toets op deze pagina onvindbaar: de modulelijst
     * hierboven loopt via de doelgroepen, en een losse toets hangt per definitie
     * niet aan een module. De ontvanger zag hem dan alleen nog op /taken, tussen
     * al het andere werk.
     *
     * Eén rij per toetsbestand — de nieuwste. Een toets kan namelijk opnieuw
     * worden uitgezet zodra de vorige is afgerond, en dan hoort de lijst de
     * huidige stand te tonen en geen historie.
     *
     * @return Collection<int, Toetsopdracht>
     */
    private function losseToetsen(): Collection
    {
        return Toetsopdracht::query()
            ->with('taak')
            ->whereNull('trainingsmodule_id')
            ->whereHas('taak', fn ($q) => $q->where('eigenaar_id', auth()->id()))
            ->latest('id')
            ->get()
            ->unique('toets_bestand')
            ->sortBy(fn (Toetsopdracht $opdracht) => $opdracht->taak?->deadline)
            ->values();
    }
}
