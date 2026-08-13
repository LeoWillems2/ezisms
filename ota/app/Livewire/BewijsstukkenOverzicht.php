<?php

namespace App\Livewire;

use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Support\Bewijsopslag;
use App\Support\Koppelbaar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class BewijsstukkenOverzicht extends Component
{
    use WithFileUploads;

    #[Url]
    public string $filterBlok = '';

    #[Url]
    public string $filterStatus = 'actief';

    /** Gap-signaal: een bewijsstuk zonder koppeling heeft geen bewijswaarde. */
    #[Url]
    public bool $alleenOngekoppeld = false;

    public bool $toontFormulier = false;

    public string $naam = '';

    public string $omschrijving = '';

    public $bestand;

    // Koppelformulier.
    public bool $toontKoppelen = false;

    public ?int $koppelBewijsstukId = null;

    public string $koppelType = '';

    public string $koppelEntiteitId = '';

    private function vereisUploadrecht(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['bewijsrepository-audit-trail', 'uitvoeren']), 403);
    }

    public function magUploaden(): bool
    {
        return Gate::allows('heeft-niveau', ['bewijsrepository-audit-trail', 'uitvoeren']);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['bewijsrepository-audit-trail', 'muteren']);
    }

    public function nieuwBewijsstuk(): void
    {
        $this->vereisUploadrecht();
        $this->reset(['naam', 'omschrijving', 'bestand']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisUploadrecht();

        $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            // Geen archieven of uitvoerbare bestanden: een zip die een auditor
            // uitpakt is een aanvalspad zonder compenserende waarde hier. rtf/odt
            // horen bij de previewbare documenttypen (net als docx).
            'bestand' => ['required', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,docx,xlsx,txt,rtf,odt'],
        ], attributes: ['naam' => 'naam', 'bestand' => 'bestand']);

        Bewijsopslag::bewaar($this->bestand, $this->naam, $this->omschrijving ?: null);

        $this->reset(['naam', 'omschrijving', 'bestand']);
        $this->toontFormulier = false;
        session()->flash('melding', 'Bewijsstuk opgeslagen.');
    }

    // --- Koppelen aan een entiteit ----------------------------------------

    public function koppel(int $bewijsstukId): void
    {
        $this->koppelBewijsstukId = $bewijsstukId;
        $this->reset(['koppelType', 'koppelEntiteitId']);
        $this->resetValidation();
        $this->toontKoppelen = true;
    }

    public function sluitKoppelen(): void
    {
        $this->toontKoppelen = false;
        $this->koppelBewijsstukId = null;
    }

    /** Type gewijzigd: de gekozen entiteit hoort niet bij het oude type. */
    public function updatedKoppelType(): void
    {
        $this->koppelEntiteitId = '';
    }

    public function koppelOpslaan(): void
    {
        $this->validate([
            'koppelType' => ['required', Rule::in(array_keys(Koppelbaar::toegestaneTypes()))],
            'koppelEntiteitId' => ['required'],
        ], attributes: ['koppelType' => 'type', 'koppelEntiteitId' => 'entiteit']);

        // Dubbele check: de validatieregel hierboven leunt op dezelfde lijst,
        // maar koppelen is een muteeractie op het bronblok en dat hoort ook
        // hier expliciet te staan.
        abort_unless(Koppelbaar::magKoppelenAan($this->koppelType), 403);
        abort_unless(array_key_exists((int) $this->koppelEntiteitId, Koppelbaar::opties($this->koppelType)), 403);

        Bewijsstuk::query()
            ->zichtbaar()
            ->findOrFail($this->koppelBewijsstukId)
            ->koppelingen()
            ->firstOrCreate([
                'blok_naam' => Koppelbaar::blokVan($this->koppelType),
                'entiteit_type' => $this->koppelType,
                'entiteit_id' => (int) $this->koppelEntiteitId,
            ]);

        $this->sluitKoppelen();
        session()->flash('melding', 'Bewijsstuk gekoppeld.');
    }

    /** Ontkoppelen mag wie het blok van de entiteit mag muteren, niet blok 6. */
    public function magOntkoppelen(BewijsKoppeling $koppeling): bool
    {
        return Gate::allows('heeft-niveau', [$koppeling->blok_naam, 'muteren']);
    }

    public function ontkoppel(int $koppelingId): void
    {
        $koppeling = BewijsKoppeling::query()
            ->whereKey($koppelingId)
            ->whereHas('bewijsstuk', fn ($q) => $q->zichtbaar())
            ->firstOrFail();

        // Ontkoppelen is een muteeractie op het blok van de entiteit, net als
        // koppelen — niet op blok 6.
        abort_unless(Gate::allows('heeft-niveau', [$koppeling->blok_naam, 'muteren']), 403);

        $koppeling->delete();

        session()->flash('melding', 'Koppeling verwijderd.');
    }

    public function render()
    {
        $bewijsstukken = Bewijsstuk::query()
            // koppelingen.entiteit wordt meegeladen zodat de kolom
            // "Gekoppeld aan" een naam kan tonen in plaats van "asset #3".
            ->with(['uploader', 'koppelingen.entiteit'])
            // Record-scoped: `uitvoeren` betekent "eigen bewijs uploaden", niet
            // "alle bewijsstukken inzien" (implementatie/06 §8).
            ->zichtbaar()
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterBlok !== '', fn ($q) => $q->whereHas(
                'koppelingen',
                fn ($sub) => $sub->where('blok_naam', $this->filterBlok)
            ))
            ->when($this->alleenOngekoppeld, fn ($q) => $q->whereDoesntHave('koppelingen'))
            ->orderByDesc('geupload_op')
            ->get();

        return view('livewire.bewijsstukken-overzicht', [
            'bewijsstukken' => $bewijsstukken,
            'ongekoppeld' => Bewijsstuk::query()
                ->zichtbaar()
                ->where('status', 'actief')
                ->whereDoesntHave('koppelingen')
                ->count(),
            'koppelbareTypes' => Koppelbaar::toegestaneTypes(),
            'koppelOpties' => $this->koppelType !== '' ? Koppelbaar::opties($this->koppelType) : [],
        ]);
    }
}
