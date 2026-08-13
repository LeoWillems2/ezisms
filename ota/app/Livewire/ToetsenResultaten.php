<?php

namespace App\Livewire;

use App\Models\Toetsopdracht;
use App\Support\Recordscope;
use App\Support\ToetsBestanden;
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
        $opdrachten = Toetsopdracht::query()
            ->with(['taak.eigenaar'])
            ->when($this->filterToets !== '', fn ($q) => $q->where('toets_bestand', $this->filterToets))
            ->when($this->filterStatus === 'openstaand', fn ($q) => $q->where('status', '!=', 'geslaagd'))
            ->when(! in_array($this->filterStatus, ['', 'openstaand'], true),
                fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('id')
            ->get();

        return view('livewire.toetsen-resultaten', [
            'opdrachten' => $opdrachten,
            'toetsen' => ToetsBestanden::beschikbaar(),
        ]);
    }
}
