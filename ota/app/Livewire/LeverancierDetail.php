<?php

namespace App\Livewire;

use App\Models\Contractclausule;
use App\Models\Dienst;
use App\Models\Leverancier;
use App\Models\Systeem;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class LeverancierDetail extends Component
{
    private const NIVEAUS = ['laag', 'midden', 'hoog'];

    public Leverancier $leverancier;

    // Basisgegevens (kop).
    public string $naam = '';

    public string $risiconiveau = '';

    public ?string $eigenCertificeringGeldigTot = null;

    // Beëindigen.
    public bool $dataTeruggaveBevestigd = false;

    // Nieuwe dienst.
    public string $dienstOmschrijving = '';

    /** @var array<int, int> geselecteerde systeem-id's bij het toevoegen */
    public array $dienstSystemen = [];

    // Nieuwe beoordeling.
    public string $beoordelingUitgevoerdOp = '';

    public string $beoordelingBevindingen = '';

    public ?string $beoordelingVolgende = null;

    public function mount(Leverancier $leverancier): void
    {
        $this->leverancier = $leverancier;
        $this->naam = $leverancier->naam;
        $this->risiconiveau = $leverancier->risiconiveau ?? '';
        $this->eigenCertificeringGeldigTot = $leverancier->eigen_certificering_geldig_tot?->format('Y-m-d');
        $this->beoordelingUitgevoerdOp = now()->format('Y-m-d');
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['leveranciers-derdenrisico', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    /**
     * Bewerken mag alleen bij een niet-beëindigde leverancier: 'beeindigd' is een
     * eindstand (§5) en daarmee read-only, tot een expliciete heractivering.
     * Losstaand van het muteerrecht zelf, zodat de heractiveer-knop wél op
     * muteren blijft checken.
     */
    public function magBewerken(): bool
    {
        return $this->magMuteren() && $this->leverancier->status !== 'beeindigd';
    }

    private function vereisBewerken(): void
    {
        abort_unless($this->magBewerken(), 403);
    }

    public function slaBasisgegevensOp(): void
    {
        $this->vereisBewerken();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'risiconiveau' => ['nullable', Rule::in(self::NIVEAUS)],
            'eigenCertificeringGeldigTot' => ['nullable', 'date'],
        ], attributes: [
            'naam' => 'naam',
            'risiconiveau' => 'risiconiveau',
            'eigenCertificeringGeldigTot' => 'certificaatdatum',
        ]);

        $this->leverancier->update([
            'naam' => $gevalideerd['naam'],
            'risiconiveau' => $gevalideerd['risiconiveau'] ?: null,
            'eigen_certificering_geldig_tot' => $gevalideerd['eigenCertificeringGeldigTot'] ?: null,
        ]);

        session()->flash('melding', 'Basisgegevens bijgewerkt.');
    }

    public function beeindig(): void
    {
        $this->vereisBewerken();

        // Geen 403 maar een validatiefout: dit is een volgordekwestie, geen
        // rechtenkwestie, en de gebruiker moet de reden zien (§5).
        $belemmering = $this->leverancier->belemmeringVoorBeeindigen($this->dataTeruggaveBevestigd);

        if ($belemmering !== null) {
            $this->addError('dataTeruggaveBevestigd', $belemmering);

            return;
        }

        // De beëindigingsgegevens worden bij de overgang gezet en niet
        // overschreven bij een latere save (§5).
        $this->leverancier->update([
            'status' => 'beeindigd',
            'beeindigd_op' => $this->leverancier->beeindigd_op ?? now(),
            'data_teruggave_bevestigd_op' => $this->leverancier->data_teruggave_bevestigd_op ?? now(),
            'data_teruggave_door_id' => $this->leverancier->data_teruggave_door_id ?? auth()->id(),
        ]);

        session()->flash('melding', 'Leverancier beëindigd.');
    }

    /**
     * Een (foutief) beëindigde leverancier weer in gebruik nemen — de
     * sanctioneerde manier om uit de eindstand te komen, zoals het heractiveren
     * van een afgevoerd systeem. De teruggavebevestiging gold de beëindiging en
     * vervalt daarmee.
     */
    public function heractiveren(): void
    {
        $this->vereisMuteren();
        abort_unless($this->leverancier->status === 'beeindigd', 404);

        $this->leverancier->update([
            'status' => 'actief',
            'beeindigd_op' => null,
            'data_teruggave_bevestigd_op' => null,
            'data_teruggave_door_id' => null,
        ]);

        $this->dataTeruggaveBevestigd = false;
        session()->flash('melding', 'Leverancier opnieuw geactiveerd.');
    }

    // --- Diensten ----------------------------------------------------------

    public function voegDienstToe(): void
    {
        $this->vereisBewerken();

        $gevalideerd = $this->validate([
            'dienstOmschrijving' => ['required', 'string', 'max:255'],
            'dienstSystemen' => ['array'],
            'dienstSystemen.*' => [Rule::exists('systemen', 'id')],
        ], attributes: ['dienstOmschrijving' => 'omschrijving']);

        $dienst = $this->leverancier->diensten()->create([
            'omschrijving' => $gevalideerd['dienstOmschrijving'],
        ]);

        // De dienst zelf is niet auditeerbaar; de handeling vond plaats op het
        // scherm van de leverancier, dus daar hangt de regel aan (06b §4).
        Koppeling::sync($dienst->systemen(), 'systemen bij '.$dienst->omschrijving, $this->dienstSystemen, logOp: $this->leverancier);

        $this->reset(['dienstOmschrijving', 'dienstSystemen']);
        session()->flash('melding', 'Dienst toegevoegd.');
    }

    public function verwijderDienst(Dienst $dienst): void
    {
        $this->vereisBewerken();
        $this->vereisEigenDienst($dienst);
        $dienst->delete();
        session()->flash('melding', 'Dienst verwijderd.');
    }

    public function koppelSysteem(Dienst $dienst, int $systeemId): void
    {
        $this->vereisBewerken();
        $this->vereisEigenDienst($dienst);
        Koppeling::koppelErbij($dienst->systemen(), 'systemen bij '.$dienst->omschrijving, [$systeemId], logOp: $this->leverancier);
    }

    public function ontkoppelSysteem(Dienst $dienst, int $systeemId): void
    {
        $this->vereisBewerken();
        $this->vereisEigenDienst($dienst);
        Koppeling::detach($dienst->systemen(), 'systemen bij '.$dienst->omschrijving, $systeemId, logOp: $this->leverancier);
    }

    /** Een dienst-id uit het verzoek mag niet bij een andere leverancier horen. */
    private function vereisEigenDienst(Dienst $dienst): void
    {
        abort_unless($dienst->leverancier_id === $this->leverancier->id, 404);
    }

    // --- Contractclausules -------------------------------------------------

    public function wisselClausule(string $type): void
    {
        $this->vereisBewerken();
        abort_unless(array_key_exists($type, Contractclausule::TYPES), 404);

        $clausule = $this->leverancier->contractclausules()->firstOrNew(['type' => $type]);
        $clausule->aanwezig = ! $clausule->aanwezig;
        $clausule->save();
    }

    // --- Beoordelingen -----------------------------------------------------

    public function voegBeoordelingToe(): void
    {
        $this->vereisBewerken();

        $gevalideerd = $this->validate([
            'beoordelingUitgevoerdOp' => ['required', 'date'],
            'beoordelingBevindingen' => ['nullable', 'string'],
            'beoordelingVolgende' => ['nullable', 'date', 'after_or_equal:beoordelingUitgevoerdOp'],
        ], attributes: [
            'beoordelingUitgevoerdOp' => 'uitvoerdatum',
            'beoordelingVolgende' => 'volgende beoordeling',
        ]);

        // De observer promoveert kandidaat → actief en (ver)plant de
        // herbeoordelingstaak (§5–6).
        $this->leverancier->beoordelingen()->create([
            'uitgevoerd_op' => $gevalideerd['beoordelingUitgevoerdOp'],
            'bevindingen' => $gevalideerd['beoordelingBevindingen'] ?: null,
            'volgende_beoordeling_gepland' => $gevalideerd['beoordelingVolgende'] ?: null,
            'uitgevoerd_door_id' => auth()->id(),
        ]);

        $this->leverancier->refresh();
        $this->reset(['beoordelingBevindingen', 'beoordelingVolgende']);
        $this->beoordelingUitgevoerdOp = now()->format('Y-m-d');
        session()->flash('melding', 'Beoordeling toegevoegd.');
    }

    public function render()
    {
        // Een beëindigde leverancier is read-only: alle bewerk-controls in de view
        // hangen aan $magMuteren, dus daar geven we het bewerkrecht door.
        $bewerken = $this->magBewerken();

        return view('livewire.leverancier-detail', [
            'clausuletypes' => Contractclausule::TYPES,
            'clausuleAanwezig' => $this->leverancier->contractclausules()->pluck('aanwezig', 'type'),
            'diensten' => $this->leverancier->diensten()->with('systemen')->orderBy('omschrijving')->get(),
            'beoordelingen' => $this->leverancier->beoordelingen()
                ->with('uitvoerder')->orderByDesc('uitgevoerd_op')->orderByDesc('id')->get(),
            'risicos' => $this->leverancier->risicos()->orderBy('titel')->get(),
            'systemen' => $bewerken ? Systeem::inGebruik()->orderBy('naam')->get() : collect(),
            'belemmering' => $this->leverancier->belemmeringVoorBeeindigen($this->dataTeruggaveBevestigd),
            'magMuteren' => $bewerken,
            // De heractiveer-knop hoort bij het echte muteerrecht, niet bij
            // 'bewerken' (dat juist false is zodra beëindigd).
            'magHeractiveren' => $this->magMuteren() && $this->leverancier->status === 'beeindigd',
        ]);
    }
}
