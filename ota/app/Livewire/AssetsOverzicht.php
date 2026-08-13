<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\OrganisatieEenheid;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AssetsOverzicht extends Component
{
    // Filters (in de URL, zodat een gedeelde link dezelfde selectie toont).
    #[Url]
    public string $filterStatus = '';

    #[Url]
    public bool $alleenNietGeclassificeerd = false;

    #[Url]
    public string $filterScope = '';

    /**
     * Soort persoonsgegevens, plus de pseudowaarde `onbeoordeeld`. Die keuze is
     * de reden dat dit filter bestaat: zonder haar is het gat — assets waarover
     * niemand de AVG-vraag heeft gesteld — niet te vinden (implementatie/03b §3).
     */
    #[Url]
    public string $filterPersoonsgegevens = '';

    // Toevoegformulier.
    public bool $toontFormulier = false;

    public string $naam = '';

    public string $type = 'informatie';

    public string $organisatieEenheidId = '';

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['asset-classificatie', 'muteren']);
    }

    public function nieuwAsset(): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'type', 'organisatieEenheidId']);
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
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['informatie', 'systeem_of_dienst', 'hardware'])],
            'organisatieEenheidId' => ['required', Rule::exists('organisatie_eenheden', 'id')],
        ], attributes: [
            'naam' => 'naam',
            'type' => 'type',
            'organisatieEenheidId' => 'organisatie-eenheid',
        ]);

        $asset = Asset::create([
            'naam' => $gevalideerd['naam'],
            'type' => $gevalideerd['type'],
            'organisatie_eenheid_id' => (int) $gevalideerd['organisatieEenheidId'],
        ]);

        // Direct doorsturen naar het detailscherm om te classificeren.
        return $this->redirectRoute('assets.detail', $asset, navigate: true);
    }

    public function render()
    {
        $assets = Asset::query()
            ->with('organisatieEenheid')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterScope === 'binnen', fn ($q) => $q->where('binnen_scope', true))
            ->when($this->filterScope === 'buiten', fn ($q) => $q->where('binnen_scope', false))
            ->when($this->alleenNietGeclassificeerd, fn ($q) => $q->where(fn ($sub) => $sub
                ->whereNull('vertrouwelijkheidsniveau')
                ->orWhereNull('integriteitsniveau')
                ->orWhereNull('beschikbaarheidsniveau')))
            ->when($this->filterPersoonsgegevens === 'onbeoordeeld',
                fn ($q) => $q->whereNull('persoonsgegevens'))
            ->when(in_array($this->filterPersoonsgegevens, Asset::PERSOONSGEGEVENSSOORTEN, true),
                fn ($q) => $q->where('persoonsgegevens', $this->filterPersoonsgegevens))
            ->orderBy('naam')
            ->get();

        return view('livewire.assets-overzicht', [
            'assets' => $assets,
            'eenheden' => OrganisatieEenheid::orderBy('naam')->get(),
            'persoonsgegevensFilterOpties' => [
                'onbeoordeeld' => 'Nog niet beoordeeld',
                'geen' => 'Geen persoonsgegevens',
                'gewoon' => 'Gewone persoonsgegevens',
                'bijzonder' => 'Bijzondere persoonsgegevens',
                'strafrechtelijk' => 'Strafrechtelijke gegevens',
            ],
            // Gap-signaal: een RACI-verantwoordelijkheid bij een niet-actief
            // account is niet belegd. Bewaakt, niet geblokkeerd.
            'metNietActieveEigenaar' => Asset::query()
                ->where(fn ($q) => $q
                    ->whereHas('accountable', fn ($u) => $u->where('status', '!=', 'actief'))
                    ->orWhereHas('responsible', fn ($u) => $u->where('status', '!=', 'actief')))
                ->count(),
        ]);
    }
}
