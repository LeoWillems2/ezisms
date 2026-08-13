<?php

namespace App\Livewire;

use App\Models\Blok;
use App\Models\Gebruiker;
use App\Models\Reviewsessie;
use App\Models\Verbeteractie;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het reviewdossier: basisgegevens, de negen verplichte §9.3-agendapunten (met de
 * volledigheidscheck vóór 'gehouden'), en de besluiten met hun verbeteracties.
 */
#[Layout('components.layouts.app')]
class ReviewsessieDetail extends Component
{
    public Reviewsessie $reviewsessie;

    // Basisgegevens.
    public string $datum = '';

    public string $deelnemers = '';

    // Agenda: per verplichte categorie een samenvatting + optioneel bronblok.
    /** @var array<string, string> */
    public array $samenvattingen = [];

    /** @var array<string, string> */
    public array $agendaBlokken = [];

    // Nieuw besluit.
    public string $besluitOmschrijving = '';

    // Verbeteractie-formulier.
    public bool $toontVerbeteractieFormulier = false;

    public ?int $vaBesluitId = null;

    public ?int $bewerktVerbeteractieId = null;

    public string $vaOmschrijving = '';

    public ?int $vaEigenaarId = null;

    public ?string $vaDeadline = null;

    public function mount(Reviewsessie $reviewsessie): void
    {
        $this->reviewsessie = $reviewsessie;
        $this->datum = $reviewsessie->datum->format('Y-m-d');
        $this->deelnemers = $reviewsessie->deelnemers ?? '';

        foreach ($reviewsessie->agendapunten as $punt) {
            $this->samenvattingen[$punt->categorie] = $punt->samenvatting;
            $this->agendaBlokken[$punt->categorie] = $punt->gekoppeld_blok_naam ?? '';
        }
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['management-review-verbetercyclus', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    /**
     * De review afsluiten is de handtekening van de directie onder §9.3 en
     * vraagt daarom `goedkeuren`, niet `muteren` (implementatie/01c §4). De
     * CISO bereidt de sessie voor en vult de agenda; alleen Management legt
     * vast dat hij gehouden is.
     */
    public function magGoedkeuren(): bool
    {
        return Gate::allows('heeft-niveau', ['management-review-verbetercyclus', 'goedkeuren']);
    }

    // --- Basisgegevens -----------------------------------------------------

    public function slaBasisgegevensOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'datum' => ['required', 'date'],
            'deelnemers' => ['nullable', 'string'],
        ], attributes: ['datum' => 'datum']);

        $this->reviewsessie->update([
            'datum' => $gevalideerd['datum'],
            'deelnemers' => $gevalideerd['deelnemers'] ?: null,
        ]);

        session()->flash('melding', 'Basisgegevens bijgewerkt.');
    }

    // --- Agenda (§9.3) -----------------------------------------------------

    public function slaAgendaOp(): void
    {
        $this->vereisMuteren();

        foreach (Reviewsessie::VERPLICHTE_CATEGORIEEN as $categorie) {
            $samenvatting = trim($this->samenvattingen[$categorie] ?? '');
            $blok = $this->agendaBlokken[$categorie] ?? '';

            if ($samenvatting === '') {
                // Leeg = onderwerp (nog) niet behandeld: verwijder het agendapunt,
                // zodat de volledigheidscheck het weer als ontbrekend ziet.
                $this->reviewsessie->agendapunten()->where('categorie', $categorie)->delete();

                continue;
            }

            $this->reviewsessie->agendapunten()->updateOrCreate(
                ['categorie' => $categorie],
                ['samenvatting' => $samenvatting, 'gekoppeld_blok_naam' => $blok ?: null],
            );
        }

        $this->reviewsessie->load('agendapunten');
        session()->flash('melding', 'Agenda opgeslagen.');
    }

    public function markeerGehouden(): void
    {
        abort_unless($this->magGoedkeuren(), 403);

        // Volgordekwestie, geen rechtenkwestie: toon welke onderwerpen ontbreken (§4).
        $belemmering = $this->reviewsessie->belemmeringVoorHouden();
        if ($belemmering !== null) {
            $this->addError('status', $belemmering);

            return;
        }

        $this->reviewsessie->update(['status' => 'gehouden']);
        session()->flash('melding', 'Review als gehouden vastgelegd.');
    }

    // --- Besluiten & verbeteracties ---------------------------------------

    public function voegBesluitToe(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate(
            ['besluitOmschrijving' => ['required', 'string']],
            attributes: ['besluitOmschrijving' => 'besluit'],
        );

        $this->reviewsessie->besluiten()->create(['omschrijving' => $gevalideerd['besluitOmschrijving']]);
        $this->reset('besluitOmschrijving');
        session()->flash('melding', 'Besluit toegevoegd.');
    }

    public function nieuweVerbeteractie(int $besluitId): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktVerbeteractieId', 'vaOmschrijving', 'vaEigenaarId', 'vaDeadline']);
        $this->resetValidation();
        $this->vaBesluitId = $besluitId;
        $this->toontVerbeteractieFormulier = true;
    }

    public function bewerkVerbeteractie(int $verbeteractieId): void
    {
        $this->vereisMuteren();
        $actie = $this->verbeteractieVanSessie($verbeteractieId);
        $this->vaBesluitId = $actie->besluit_id;
        $this->bewerktVerbeteractieId = $actie->id;
        $this->vaOmschrijving = $actie->omschrijving;
        $this->vaEigenaarId = $actie->eigenaar_id;
        $this->vaDeadline = $actie->deadline?->format('Y-m-d');
        $this->resetValidation();
        $this->toontVerbeteractieFormulier = true;
    }

    public function slaVerbeteractieOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'vaBesluitId' => ['required'],
            'vaOmschrijving' => ['required', 'string'],
            'vaEigenaarId' => ['nullable', 'exists:gebruikers,id'],
            'vaDeadline' => ['nullable', 'date'],
        ], attributes: ['vaOmschrijving' => 'omschrijving', 'vaEigenaarId' => 'eigenaar', 'vaDeadline' => 'deadline']);

        // Het besluit moet bij déze sessie horen — geen vreemde id injecteren.
        $besluit = $this->reviewsessie->besluiten()->findOrFail($gevalideerd['vaBesluitId']);

        $attributen = [
            'omschrijving' => $gevalideerd['vaOmschrijving'],
            'eigenaar_id' => $gevalideerd['vaEigenaarId'] ?: null,
            'deadline' => $gevalideerd['vaDeadline'] ?: null,
        ];

        if ($this->bewerktVerbeteractieId !== null) {
            $this->verbeteractieVanSessie($this->bewerktVerbeteractieId)->update($attributen);
        } else {
            $besluit->verbeteracties()->create($attributen);
        }

        $this->toontVerbeteractieFormulier = false;
        session()->flash('melding', 'Verbeteractie opgeslagen.');
    }

    public function toggleVerbeteractie(int $verbeteractieId): void
    {
        $this->vereisMuteren();
        $actie = $this->verbeteractieVanSessie($verbeteractieId);

        // De observer plant/sluit de herinneringstaak op basis van deze status.
        if ($actie->isVoltooid()) {
            $actie->update(['status' => 'open', 'voltooid_op' => null]);
        } else {
            $actie->update(['status' => 'voltooid', 'voltooid_op' => now()->toDateString()]);
        }
    }

    /** Alleen een verbeteractie die onder een besluit van déze sessie valt. */
    private function verbeteractieVanSessie(int $verbeteractieId): Verbeteractie
    {
        return Verbeteractie::whereHas(
            'besluit',
            fn ($q) => $q->where('reviewsessie_id', $this->reviewsessie->id),
        )->findOrFail($verbeteractieId);
    }

    public function render()
    {
        return view('livewire.reviewsessie-detail', [
            'categorieen' => Reviewsessie::VERPLICHTE_CATEGORIEEN,
            'besluiten' => $this->reviewsessie->besluiten()
                ->with(['verbeteracties.eigenaar'])
                ->orderBy('id')
                ->get(),
            'gebruikers' => $this->magMuteren()
                ? Gebruiker::where('status', 'actief')->orderBy('naam')->pluck('naam', 'id')->all()
                : [],
            'blokopties' => Blok::orderBy('naam')->pluck('naam', 'code')->all(),
            'belemmering' => $this->reviewsessie->belemmeringVoorHouden(),
        ]);
    }
}
