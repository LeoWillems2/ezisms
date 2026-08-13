<?php

namespace App\Livewire;

use App\Livewire\Concerns\LevertSchermkopie;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\Risico;
use App\Support\Schermkopie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class RisicosOverzicht extends Component
{
    use LevertSchermkopie;

    /** Eén plek voor de statuslabels, zodat scherm en schermkopie niet uiteenlopen. */
    private const STATUS_LABELS = [
        'geidentificeerd' => 'Geïdentificeerd',
        'beoordeeld' => 'Beoordeeld',
        'behandelplan_opgesteld' => 'Behandelplan opgesteld',
        'geaccepteerd' => 'Geaccepteerd',
        'in_uitvoering' => 'In uitvoering',
        'gemitigeerd' => 'Gemitigeerd',
    ];

    #[Url]
    public string $filterStatus = '';

    /** Alleen risico's boven de acceptatiedrempel. */
    #[Url]
    public bool $alleenBovenDrempel = false;

    /**
     * Alleen risico's met dit issue als aanleiding (plan 02b §5) — waar de
     * risicokolom op /issues naartoe wijst.
     */
    #[Url]
    public string $filterIssue = '';

    // Toevoegformulier: bewust minimaal — beoordelen gebeurt op het detailscherm.
    public bool $toontFormulier = false;

    public string $titel = '';

    public string $dreiging = '';

    public string $kwetsbaarheid = '';

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['risico-soa', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    public function nieuwRisico(): void
    {
        $this->vereisMuteren();
        $this->reset(['titel', 'dreiging', 'kwetsbaarheid']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan()
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'dreiging' => ['nullable', 'string'],
            'kwetsbaarheid' => ['nullable', 'string'],
        ], attributes: ['titel' => 'titel']);

        $risico = Risico::create([
            'titel' => $gevalideerd['titel'],
            'dreiging' => $gevalideerd['dreiging'] ?: null,
            'kwetsbaarheid' => $gevalideerd['kwetsbaarheid'] ?: null,
        ]);

        // Direct door naar het detailscherm om kans/impact te beoordelen.
        return $this->redirectRoute('risicos.detail', $risico, navigate: true);
    }

    public function render()
    {
        $drempel = Risico::drempelwaarde();

        return view('livewire.risicos-overzicht', [
            'risicos' => $this->gefilterdeRisicos(),
            'drempel' => $drempel,
            'gefilterdIssue' => $this->filterIssue !== '' ? Issue::find($this->filterIssue) : null,
            'metNietActieveEigenaar' => Risico::query()
                ->whereHas('eigenaar', fn ($u) => $u->where('status', '!=', 'actief'))
                ->count(),
        ]);
    }

    /**
     * De rijen zoals het scherm ze toont, inclusief de actieve filters.
     *
     * Eén methode voor zowel `render()` als de schermkopie: die laatste mag geen
     * eigen query doen (implementatie/12h §6). De rollen worden meegeladen voor
     * de anonimisering hieronder; het scherm zelf gebruikt ze niet, maar een
     * tweede query per eigenaar tijdens het bouwen van de kopie is een N+1 die
     * niemand ziet.
     *
     * @return Collection<int, Risico>
     */
    private function gefilterdeRisicos(): Collection
    {
        $drempel = Risico::drempelwaarde();

        return Risico::query()
            ->with(['eigenaar.rollen'])
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->alleenBovenDrempel, fn ($q) => $q->where('risicoscore', '>', $drempel))
            ->when($this->filterIssue !== '', fn ($q) => $q->whereHas(
                'aanleidingen', fn ($i) => $i->whereKey($this->filterIssue)
            ))
            ->orderByDesc('risicoscore')
            ->orderBy('titel')
            ->get();
    }

    // --- Kopie voor de auditor (implementatie/12h) -------------------------

    protected function kopieBlok(): string
    {
        return 'risico-soa';
    }

    protected function schermkopie(): Schermkopie
    {
        $risicos = $this->gefilterdeRisicos();

        return new Schermkopie(
            scherm: 'Risicoregister',
            kolommen: ['Ref.', 'Titel', 'Score', 'Status', 'Eigenaar', 'Volgende beoordeling'],
            rijen: $risicos->map(fn (Risico $risico) => [
                $risico->referentie(),
                $risico->titel,
                $risico->risicoscore === null ? 'Niet beoordeeld' : (string) $risico->risicoscore,
                self::STATUS_LABELS[$risico->status] ?? ucfirst(str_replace('_', ' ', $risico->status)),
                // Het anonimiseringsschema staat op Gebruiker: initialen + rol.
                $risico->eigenaar?->anoniemLabel() ?? '—',
                $this->beoordelingLabel($risico),
            ])->all(),
            // Het hele register, ook als er gefilterd is: dát verschil is nu juist
            // wat de kop van het document moet noemen (§4).
            totaalRijen: Risico::count(),
            filters: $this->actieveFilters(),
            toelichting: 'De geïdentificeerde risico\'s met hun score (kans × impact) en status. '
                .'De acceptatiedrempel staat op '.Risico::drempelwaarde().'; een score daarboven '
                .'ligt buiten de risicobereidheid. De eigenaar staat als initialen en rol, niet '
                .'als naam — wie het risico belegd heeft is voor de beoordeling van dit register '
                .'de functie, niet de persoon. De volledige namen staan op het scherm.',
            // Initialen + rol is geen naam, en dat is hier de bedoeling. Zie de
            // toelichting hierboven en 12h §8.
            metPersoonsgegevens: false,
        );
    }

    private function beoordelingLabel(Risico $risico): string
    {
        if ($risico->volgende_beoordeling_gepland === null) {
            return '—';
        }

        return $risico->volgende_beoordeling_gepland->format('d-m-Y')
            .($risico->herbeoordelingVerstreken() ? ' (verstreken)' : '');
    }

    /** @return array<string, string> */
    private function actieveFilters(): array
    {
        $filters = [];

        if ($this->filterStatus !== '') {
            $filters['Status'] = self::STATUS_LABELS[$this->filterStatus] ?? $this->filterStatus;
        }

        if ($this->alleenBovenDrempel) {
            $filters['Score'] = 'boven de drempel ('.Risico::drempelwaarde().')';
        }

        if ($this->filterIssue !== '') {
            $filters['Aanleiding'] = 'issue #'.$this->filterIssue;
        }

        return $filters;
    }
}
