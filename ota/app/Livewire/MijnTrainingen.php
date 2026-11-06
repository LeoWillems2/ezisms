<?php

namespace App\Livewire;

use App\Models\Trainingsmodule;
use App\Support\Trainingsstand;
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
        // De afleiding staat in Trainingsstand, zodat de assistenttool dezelfde
        // eigen-trainingenlijst geeft (14b §4.3).
        $gebruiker = auth()->user();

        return view('livewire.mijn-trainingen', [
            'rijen' => Trainingsstand::eigenModules($gebruiker),
            // Toetsen die zonder module zijn uitgezet (`/toetsen/uitzetten`, bron
            // 'los'). Zonder deze lijst is zo'n toets op deze pagina onvindbaar:
            // de modulelijst loopt via de doelgroepen, en een losse toets hangt
            // per definitie niet aan een module.
            'losseToetsen' => Trainingsstand::losseToetsen($gebruiker),
        ]);
    }
}
