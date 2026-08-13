<?php

namespace App\Livewire;

use App\Models\SchermkopieRegistratie;
use App\Support\Recordscope;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Wat er als schermkopie is meegegeven (implementatie/12h §9).
 *
 * Op een auditdag gaan er makkelijk tien schermen mee. Deze lijst **ís** het
 * overdrachtsdossier: de vraag "wat hebben wij die auditor gegeven" krijgt hier
 * zijn antwoord, zonder dat er ergens een pakket bewaard hoeft te worden.
 *
 * Read-only, net als de audit trail ernaast: er is geen handeling die een
 * vastlegging wijzigt of verwijdert.
 */
#[Layout('components.layouts.app')]
class SchermkopieenOverzicht extends Component
{
    use WithPagination;

    /**
     * Dezelfde afweging als bij de audit trail: de route-middleware checkt
     * `lezen`, maar Medewerker heeft `uitvoeren` op dit blok en zou daarmee
     * kunnen doorlezen wat een ander heeft meegegeven.
     */
    public function mount(): void
    {
        abort_unless(Recordscope::magAllesZien('bewijsrepository-audit-trail'), 403);
    }

    public function render()
    {
        return view('livewire.schermkopieen-overzicht', [
            'kopieen' => SchermkopieRegistratie::with('gebruiker')
                ->orderByDesc('gemaakt_op')
                ->paginate(25),
        ]);
    }
}
