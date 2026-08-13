<?php

namespace App\Livewire;

use App\Models\Issue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class IssuesOverzicht extends Component
{
    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $aard = 'intern';

    public string $categorie = '';

    public string $omschrijving = '';

    public ?string $laatstBeoordeeldOp = null;

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['context-scope', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'muteren']);
    }

    /**
     * Mag deze bezoeker de doorvertaling naar risico's zien? (plan 02b §5)
     *
     * Dit is géén cosmetische controle. De rechten lopen hier niet gelijk: de
     * Medewerker heeft `lezen` op context-scope maar staat helemaal niet in de
     * rij voor risico-soa (RolPermissieSeeder). Zonder deze blokkade lekken
     * risicoaantallen — en via de link de titels — naar een rol die de
     * risicomodule niet mag inzien.
     */
    public function magRisicosZien(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'lezen']);
    }

    public function nieuwIssue(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'aard', 'categorie', 'omschrijving', 'laatstBeoordeeldOp']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(Issue $issue): void
    {
        $this->vereisMuteren();
        $this->resetValidation();
        $this->bewerktId = $issue->id;
        $this->aard = $issue->aard;
        $this->categorie = $issue->categorie;
        $this->omschrijving = $issue->omschrijving;
        $this->laatstBeoordeeldOp = $issue->laatst_beoordeeld_op?->format('Y-m-d');
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'aard' => ['required', Rule::in(['intern', 'extern'])],
            'categorie' => ['required', 'string', 'max:255'],
            'omschrijving' => ['required', 'string'],
            'laatstBeoordeeldOp' => ['nullable', 'date'],
        ], attributes: [
            'categorie' => 'categorie',
            'omschrijving' => 'omschrijving',
            'laatstBeoordeeldOp' => 'datum laatst beoordeeld',
        ]);

        Issue::updateOrCreate(['id' => $this->bewerktId], [
            'aard' => $gevalideerd['aard'],
            'categorie' => $gevalideerd['categorie'],
            'omschrijving' => $gevalideerd['omschrijving'],
            'laatst_beoordeeld_op' => $gevalideerd['laatstBeoordeeldOp'],
        ]);

        $this->toontFormulier = false;
        session()->flash('melding', $this->bewerktId ? 'Issue bijgewerkt.' : 'Issue toegevoegd.');
        $this->reset(['bewerktId', 'aard', 'categorie', 'omschrijving', 'laatstBeoordeeldOp']);
    }

    public function verwijderen(Issue $issue): void
    {
        $this->vereisMuteren();
        $issue->delete();
        session()->flash('melding', 'Issue verwijderd.');
    }

    public function render()
    {
        $magRisicosZien = $this->magRisicosZien();

        $issues = Issue::query()
            ->when($magRisicosZien, fn ($q) => $q->withCount('risicos'))
            ->orderBy('aard')
            ->orderBy('categorie')
            ->get();

        return view('livewire.issues-overzicht', [
            'issues' => $issues,
            // Het dekkingssignaal (plan 02b §6). Alleen deze richting telt: een
            // issue dat nergens landt is óf niet relevant, óf een gat in de
            // risicobeoordeling. Andersom — een risico zonder aanleiding — is
            // geen tekortkoming en levert dus bewust geen melding op.
            'zonderRisico' => $magRisicosZien ? $issues->where('risicos_count', 0)->count() : null,
        ]);
    }
}
