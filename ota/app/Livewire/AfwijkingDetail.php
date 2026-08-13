<?php

namespace App\Livewire;

use App\Models\Afwijking;
use App\Models\CorrigerendeMaatregel;
use App\Models\Effectiviteitstoets;
use App\Models\Gebruiker;
use App\Models\Grondoorzaak;
use App\Rules\KiesbareGebruiker;
use App\Support\Afwijkingafsluiting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AfwijkingDetail extends Component
{
    public Afwijking $afwijking;

    public string $eigenaarId = '';

    // Nieuwe grondoorzaak.
    public string $oorzaakOmschrijving = '';

    public string $oorzaakMethodiek = '';

    // Nieuwe maatregel.
    public string $maatregelOmschrijving = '';

    public string $maatregelEigenaarId = '';

    public ?string $maatregelDeadline = null;

    // Nieuwe toets.
    // String en geen ?int: de select/placeholder-afspraak uit de README. Een
    // select die aan een ?int hangt, verliest na een Livewire-morph de
    // koppeling tussen wat de browser toont en wat de state bevat.
    public string $toetsMaatregelId = '';

    public string $toetsResultaat = 'effectief';

    public string $toetsToelichting = '';

    public function mount(Afwijking $afwijking): void
    {
        $this->afwijking = $afwijking;
        $this->eigenaarId = (string) $afwijking->eigenaar_id;
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function opslaanKop(): void
    {
        $this->vereisMuteren();

        $this->validate(['eigenaarId' => ['nullable', new KiesbareGebruiker($this->afwijking->eigenaar_id)]]);

        $this->afwijking->update([
            'eigenaar_id' => $this->eigenaarId !== '' ? (int) $this->eigenaarId : null,
        ]);

        session()->flash('melding', 'Afwijking bijgewerkt.');
    }

    public function voegOorzaakToe(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'oorzaakOmschrijving' => ['required', 'string'],
            'oorzaakMethodiek' => ['nullable', 'string', 'max:255'],
        ], attributes: ['oorzaakOmschrijving' => 'omschrijving']);

        Grondoorzaak::create([
            'afwijking_id' => $this->afwijking->id,
            'omschrijving' => $this->oorzaakOmschrijving,
            'methodiek' => $this->oorzaakMethodiek ?: null,
        ]);

        $this->reset(['oorzaakOmschrijving', 'oorzaakMethodiek']);
        $this->afwijking->refresh();
    }

    public function voegMaatregelToe(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'maatregelOmschrijving' => ['required', 'string'],
            'maatregelEigenaarId' => ['nullable', new KiesbareGebruiker],
            'maatregelDeadline' => ['nullable', 'date'],
        ], attributes: ['maatregelOmschrijving' => 'omschrijving']);

        CorrigerendeMaatregel::create([
            'afwijking_id' => $this->afwijking->id,
            'omschrijving' => $this->maatregelOmschrijving,
            'eigenaar_id' => $this->maatregelEigenaarId !== '' ? (int) $this->maatregelEigenaarId : null,
            'deadline' => $this->maatregelDeadline ?: null,
            'status' => 'open',
        ]);

        $this->reset(['maatregelOmschrijving', 'maatregelEigenaarId', 'maatregelDeadline']);
        $this->afwijking->refresh();
    }

    /**
     * Een maatregel afwerken mag ook de eigenaar zelf, met `uitvoeren`. Dat is
     * dat recht in zijn zuiverste vorm: je eigen werk afmelden, zonder dat je
     * daarmee de afwijking kunt sluiten (implementatie/08 §9).
     */
    public function werkMaatregelBij(int $maatregelId, string $status): void
    {
        $maatregel = $this->afwijking->maatregelen()->findOrFail($maatregelId);

        abort_unless(
            $this->magMuteren() || $maatregel->eigenaar_id === auth()->id(),
            403
        );

        abort_unless(in_array($status, ['open', 'in_uitvoering', 'voltooid'], true), 422);

        $maatregel->update([
            'status' => $status,
            'voltooid_op' => $status === 'voltooid' ? now() : null,
        ]);

        $this->afwijking->refresh();
    }

    public function legToetsVast(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'toetsMaatregelId' => ['required', 'integer'],
            'toetsResultaat' => ['required', Rule::in(['effectief', 'niet_effectief'])],
            'toetsToelichting' => ['nullable', 'string'],
        ]);

        $maatregel = $this->afwijking->maatregelen()->findOrFail((int) $this->toetsMaatregelId);

        // Toetsen wat nog niet af is, is geen toets: er valt dan niets vast te
        // stellen over de werking.
        if ($maatregel->status !== 'voltooid') {
            $this->addError('toetsMaatregelId', 'Toets pas nadat de maatregel is voltooid.');

            return;
        }

        Effectiviteitstoets::create([
            'corrigerende_maatregel_id' => $maatregel->id,
            'uitgevoerd_op' => now(),
            'resultaat' => $this->toetsResultaat,
            'toelichting' => $this->toetsToelichting ?: null,
            'uitgevoerd_door_id' => auth()->id(),
        ]);

        $this->reset(['toetsMaatregelId', 'toetsToelichting']);
        $this->toetsResultaat = 'effectief';
        $this->afwijking->refresh();
    }

    public function sluiten(): void
    {
        $this->vereisMuteren();

        try {
            Afwijkingafsluiting::sluit($this->afwijking, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('afsluiting', $e->getMessage());

            return;
        }

        $this->afwijking->refresh();
        session()->flash('melding', 'Afwijking gesloten.');
    }

    public function render()
    {
        return view('livewire.afwijking-detail', [
            'grondoorzaken' => $this->afwijking->grondoorzaken()->orderBy('id')->get(),
            'maatregelen' => $this->afwijking->maatregelen()
                ->with(['eigenaar', 'toetsen.uitvoerder'])->orderBy('id')->get(),
            // Kop-eigenaar houdt zijn eigen (evt. gedeactiveerde) huidige waarde;
            // de maatregel-eigenaar is een nieuwe keuze en toont alleen actief.
            'gebruikers' => Gebruiker::kiesbaar($this->eigenaarId)->pluck('naam', 'id')->all(),
            'maatregelGebruikers' => Gebruiker::kiesbaar()->pluck('naam', 'id')->all(),
            // De reden waarom sluiten niet kan, in plaats van een grijze knop.
            'belemmering' => Afwijkingafsluiting::belemmering($this->afwijking),
        ]);
    }
}
