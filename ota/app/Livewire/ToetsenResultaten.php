<?php

namespace App\Livewire;

use App\Support\Recordscope;
use App\Support\ToetsBestanden;
use App\Support\Trainingsstand;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ToetsenResultaten extends Component
{
    private const BLOK = 'bewustzijn-training';

    #[Url]
    public string $filterToets = '';

    #[Url]
    public string $filterStatus = 'openstaand';

    public function mount(): void
    {
        // De route staat op 'lezen', maar `uitvoeren` (Medewerker) impliceert
        // lezen. Alleen wie alles mag zien (CISO/Auditor) hoort dit overzicht te
        // zien; de Medewerker komt bij een toets via zijn taak (§11).
        abort_unless(Recordscope::magAllesZien(self::BLOK), 403);
    }

    public function render()
    {
        $opdrachten = Trainingsstand::toetsresultaten($this->filterToets, $this->filterStatus);

        return view('livewire.toetsen-resultaten', [
            'opdrachten' => $opdrachten,
            'toetsen' => ToetsBestanden::beschikbaar(),
        ]);
    }
}
