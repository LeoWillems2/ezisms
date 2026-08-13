<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Afwijking;
use App\Support\Schermkopie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AfwijkingenOverzicht extends Component
{
    use LevertSchermkopie;

    /** Eén plek voor de labels, zodat scherm en schermkopie niet uiteenlopen. */
    private const STATUS_LABELS = [
        'open' => 'Open',
        'analyse' => 'Analyse',
        'actie_lopend' => 'Actie lopend',
        'gesloten' => 'Gesloten',
    ];

    #[Url]
    public string $filterStatus = '';

    public bool $toontFormulier = false;

    public string $omschrijving = '';

    public string $bron = 'interne_signalering';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren']);
    }

    public function nieuweAfwijking(): void
    {
        abort_unless($this->magMuteren(), 403);
        $this->reset(['omschrijving']);
        $this->bron = 'interne_signalering';
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        abort_unless($this->magMuteren(), 403);

        $gevalideerd = $this->validate([
            'omschrijving' => ['required', 'string'],
            // `audit_bevinding` staat er al in, maar wordt hier niet aangeboden:
            // die stroom komt uit blok 11 en hoort niet met de hand gezet te
            // worden (implementatie/08 §13).
            'bron' => ['required', Rule::in(['interne_signalering', 'incident'])],
        ], attributes: ['omschrijving' => 'omschrijving']);

        Afwijking::create($gevalideerd + ['status' => 'open']);

        $this->toontFormulier = false;
        session()->flash('melding', 'Afwijking vastgelegd.');
    }

    public function render()
    {
        return view('livewire.afwijkingen-overzicht', [
            'afwijkingen' => $this->gefilterdeAfwijkingen(),
        ]);
    }

    /**
     * De rijen zoals het scherm ze toont, inclusief het actieve filter.
     *
     * Eén methode voor zowel `render()` als de schermkopie: die laatste mag geen
     * eigen query doen (implementatie/12h §6). De rollen komen mee voor de
     * anonimisering van de eigenaar.
     *
     * @return Collection<int, Afwijking>
     */
    private function gefilterdeAfwijkingen(): Collection
    {
        return Afwijking::query()
            ->with(['eigenaar.rollen', 'maatregelen.laatsteToets'])
            ->withCount('maatregelen')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at')
            ->get();
    }

    // --- Kopie voor de auditor (implementatie/12h) -------------------------

    protected function kopieBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    protected function schermkopie(): Schermkopie
    {
        $afwijkingen = $this->gefilterdeAfwijkingen();

        return new Schermkopie(
            scherm: 'Afwijkingen',
            kolommen: ['Omschrijving', 'Bron', 'Status', 'Eigenaar', 'Maatregelen', 'Nog te toetsen'],
            rijen: $afwijkingen->map(fn (Afwijking $afwijking) => [
                // De volledige tekst, niet de afgekapte versie van het scherm:
                // die afkapping is een kolombreedte, geen inhoudelijke keuze —
                // en juist de omschrijving is waar een §10.2-afwijking om draait.
                $afwijking->omschrijving,
                self::label($afwijking->bron),
                self::STATUS_LABELS[$afwijking->status] ?? self::label($afwijking->status),
                // Het anonimiseringsschema staat op Gebruiker: initialen + rol.
                $afwijking->eigenaar?->anoniemLabel() ?? '—',
                (string) $afwijking->maatregelen_count,
                // "n.v.t." wanneer er geen maatregelen zijn: in een document
                // zonder kleur is een nul daar niet van een nul te onderscheiden.
                $afwijking->nogTeToetsenLabel(),
            ])->all(),
            // Het hele register, ook als er gefilterd is: dát verschil is nu juist
            // wat de kop van het document moet noemen (§4).
            totaalRijen: Afwijking::count(),
            filters: $this->filterStatus === ''
                ? []
                : ['Status' => self::STATUS_LABELS[$this->filterStatus] ?? $this->filterStatus],
            toelichting: 'De afwijkingen uit §10.2 met hun corrigerende maatregelen. '
                .'"Nog te toetsen" is het aantal afgeronde maatregelen waarvan de effectiviteit '
                .'nog niet is vastgesteld; zolang dat getal niet nul is, is de afwijking niet '
                .'aantoonbaar verholpen. "n.v.t." betekent daar dat er nog geen corrigerende '
                .'maatregel is vastgelegd — dus niet dat er niets meer open staat. '
                .'De eigenaar staat als initialen en rol, niet als naam — '
                .'wie de afwijking belegd heeft is voor de beoordeling van dit register de '
                .'functie, niet de persoon. De volledige namen staan op het scherm.',
            // Initialen + rol is geen naam. Zie de toelichting hierboven en §8.
            metPersoonsgegevens: false,
        );
    }

    private static function label(string $waarde): string
    {
        return ucfirst(str_replace('_', ' ', $waarde));
    }
}
