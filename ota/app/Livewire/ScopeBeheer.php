<?php

namespace App\Livewire;

use App\Actions\ActiveerScopeVerklaring;
use App\Models\Belanghebbende;
use App\Models\Issue;
use App\Models\OrganisatieEenheid;
use App\Models\ScopeVerklaring;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ScopeBeheer extends Component
{
    // Bewerkbare velden van de werkversie (alleen actief zolang die 'concept' is).
    public string $scopetekst = '';

    /** @var array<int, int> */
    public array $geselecteerdeEenheden = [];

    /** @var array<int, int> */
    public array $geselecteerdeIssues = [];

    /** @var array<int, int> */
    public array $geselecteerdeBelanghebbenden = [];

    // Inline-formulier uitsluiting (motivatie verplicht, matcht de NOT NULL-kolom).
    public string $nieuweUitsluitingOmschrijving = '';

    public string $nieuweUitsluitingMotivatie = '';

    // Inline-formulier interface.
    public string $nieuweInterfaceOmschrijving = '';

    public string $nieuweInterfaceRisico = '';

    public string $goedgekeurdDoor = '';

    // Id van de versie die read-only in de detail-modal wordt getoond.
    public ?int $bekekenVersieId = null;

    // Aparte boolean voor de zichtbaarheid: Flux zet de gebonden waarde bij
    // sluiten op `false`, wat niet op een nullable-int-property past.
    public bool $toontDetail = false;

    public function mount(): void
    {
        $this->laadWerkversieVelden();
    }

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['context-scope', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'muteren']);
    }

    /**
     * Een scope-versie activeren is vaststellen, niet bewerken: §4.3/§5.2 legt
     * dat bij de directie (implementatie/01c §4). De CISO stelt op en dient in,
     * Management activeert.
     */
    public function magGoedkeuren(): bool
    {
        return Gate::allows('heeft-niveau', ['context-scope', 'goedkeuren']);
    }

    /** De versie waaraan gewerkt wordt: 'concept' of 'ter_goedkeuring' (max. één). */
    public function getWerkVersieProperty(): ?ScopeVerklaring
    {
        return ScopeVerklaring::with(['organisatieEenheden', 'issues', 'belanghebbenden', 'uitsluitingen', 'interfaces'])
            ->whereIn('status', ['concept', 'ter_goedkeuring'])
            ->latest('versienummer')
            ->first();
    }

    public function getActieveVersieProperty(): ?ScopeVerklaring
    {
        return ScopeVerklaring::with('organisatieEenheden')->where('status', 'actief')->first();
    }

    /** De versie die read-only wordt ingezien (elke versie, ongeacht status). */
    public function getBekekenVersieProperty(): ?ScopeVerklaring
    {
        if ($this->bekekenVersieId === null) {
            return null;
        }

        return ScopeVerklaring::with(['organisatieEenheden', 'issues', 'belanghebbenden', 'uitsluitingen', 'interfaces'])
            ->find($this->bekekenVersieId);
    }

    public function bekijkVersie(int $versieId): void
    {
        // Alleen-lezen inzage; geen autorisatie boven 'lezen' nodig (de route
        // dekt dat al af) en geen mutatie mogelijk.
        $this->bekekenVersieId = $versieId;
        $this->toontDetail = true;
    }

    public function sluitBekekenVersie(): void
    {
        $this->toontDetail = false;
        $this->bekekenVersieId = null;
    }

    private function laadWerkversieVelden(): void
    {
        $werk = $this->werkVersie;

        if (! $werk) {
            $this->reset(['scopetekst', 'geselecteerdeEenheden', 'geselecteerdeIssues', 'geselecteerdeBelanghebbenden']);

            return;
        }

        $this->scopetekst = $werk->scopetekst;
        $this->geselecteerdeEenheden = $werk->organisatieEenheden->pluck('id')->all();
        $this->geselecteerdeIssues = $werk->issues->pluck('id')->all();
        $this->geselecteerdeBelanghebbenden = $werk->belanghebbenden->pluck('id')->all();
    }

    /** Eerste scope-versie: er bestaat nog geen enkele verklaring. */
    public function eersteVersieOpstellen(): void
    {
        $this->vereisMuteren();

        if (ScopeVerklaring::exists()) {
            return;
        }

        ScopeVerklaring::create([
            'versienummer' => 1,
            'scopetekst' => '',
            'status' => 'concept',
        ]);

        $this->laadWerkversieVelden();
    }

    public function nieuweConceptversieStarten(): void
    {
        $this->vereisMuteren();

        // Alleen zinvol vanuit een actieve versie zonder lopende werkversie.
        if ($this->werkVersie) {
            return;
        }

        $vorige = ScopeVerklaring::where('status', 'actief')->firstOrFail();

        $nieuw = ScopeVerklaring::create([
            'versienummer' => $vorige->versienummer + 1,
            'scopetekst' => $vorige->scopetekst, // startpunt: volledige kopie van de huidige versie
            'status' => 'concept',
        ]);

        // De nieuwe versie start als complete kopie van de vorige: koppelingen,
        // uitsluitingen én interfaces. De CISO past daarna aan wat gewijzigd is,
        // in plaats van alles opnieuw te moeten invoeren.
        Koppeling::sync($nieuw->organisatieEenheden(), 'organisatie-eenheden', $vorige->organisatieEenheden->pluck('id'));
        Koppeling::sync($nieuw->issues(), 'issues', $vorige->issues->pluck('id'));
        Koppeling::sync($nieuw->belanghebbenden(), 'belanghebbenden', $vorige->belanghebbenden->pluck('id'));

        foreach ($vorige->uitsluitingen as $uitsluiting) {
            $nieuw->uitsluitingen()->create($uitsluiting->only(['omschrijving', 'motivatie']));
        }

        foreach ($vorige->interfaces as $interface) {
            $nieuw->interfaces()->create($interface->only(['omschrijving', 'risico_implicatie']));
        }

        $this->laadWerkversieVelden();
    }

    public function conceptOpslaan(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        $gevalideerd = $this->validate([
            'scopetekst' => ['required', 'string'],
            'geselecteerdeEenheden' => ['array'],
            'geselecteerdeEenheden.*' => ['integer', 'exists:organisatie_eenheden,id'],
            'geselecteerdeIssues' => ['array'],
            'geselecteerdeIssues.*' => ['integer', 'exists:issues,id'],
            'geselecteerdeBelanghebbenden' => ['array'],
            'geselecteerdeBelanghebbenden.*' => ['integer', 'exists:belanghebbenden,id'],
        ], attributes: ['scopetekst' => 'scopetekst']);

        $werk->update(['scopetekst' => $gevalideerd['scopetekst']]);
        Koppeling::sync($werk->organisatieEenheden(), 'organisatie-eenheden', $this->geselecteerdeEenheden);
        Koppeling::sync($werk->issues(), 'issues', $this->geselecteerdeIssues);
        Koppeling::sync($werk->belanghebbenden(), 'belanghebbenden', $this->geselecteerdeBelanghebbenden);

        session()->flash('melding', 'Conceptversie opgeslagen.');
    }

    public function uitsluitingToevoegen(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        // motivatie verplicht: front-end validatie vóór de NOT NULL-kolom, zodat
        // een lege motivatie een nette melding geeft i.p.v. een 500 (impl. 02 §6).
        $gevalideerd = $this->validate([
            'nieuweUitsluitingOmschrijving' => ['required', 'string'],
            'nieuweUitsluitingMotivatie' => ['required', 'string'],
        ], attributes: [
            'nieuweUitsluitingOmschrijving' => 'omschrijving',
            'nieuweUitsluitingMotivatie' => 'motivatie',
        ]);

        $werk->uitsluitingen()->create([
            'omschrijving' => $gevalideerd['nieuweUitsluitingOmschrijving'],
            'motivatie' => $gevalideerd['nieuweUitsluitingMotivatie'],
        ]);

        $this->reset(['nieuweUitsluitingOmschrijving', 'nieuweUitsluitingMotivatie']);
    }

    public function uitsluitingVerwijderen(int $uitsluitingId): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        // deleteGeaudit: een uitsluiting verwijderen wijzigt de scope zelf
        // (§4.3) en moet dus in de audit trail komen.
        $werk->uitsluitingen()->whereKey($uitsluitingId)->deleteGeaudit();
    }

    public function interfaceToevoegen(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        $gevalideerd = $this->validate([
            'nieuweInterfaceOmschrijving' => ['required', 'string'],
            'nieuweInterfaceRisico' => ['nullable', 'string'],
        ], attributes: [
            'nieuweInterfaceOmschrijving' => 'omschrijving',
            'nieuweInterfaceRisico' => 'risico-implicatie',
        ]);

        $werk->interfaces()->create([
            'omschrijving' => $gevalideerd['nieuweInterfaceOmschrijving'],
            'risico_implicatie' => $gevalideerd['nieuweInterfaceRisico'] ?: null,
        ]);

        $this->reset(['nieuweInterfaceOmschrijving', 'nieuweInterfaceRisico']);
    }

    public function interfaceVerwijderen(int $interfaceId): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422);

        $werk->interfaces()->whereKey($interfaceId)->deleteGeaudit();
    }

    public function indienenTerGoedkeuring(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->isBewerkbaar(), 422); // alleen vanuit concept

        if (trim($werk->scopetekst) === '') {
            session()->flash('fout', 'Vul eerst de scopetekst in voordat u ter goedkeuring indient.');

            return;
        }

        $werk->update(['status' => 'ter_goedkeuring']);
        session()->flash('melding', 'Versie ingediend ter goedkeuring.');
    }

    public function terugNaarConcept(): void
    {
        $this->vereisMuteren();

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->status === 'ter_goedkeuring', 422);

        $werk->update(['status' => 'concept']);
        $this->laadWerkversieVelden();
        session()->flash('melding', 'Versie teruggezet naar concept.');
    }

    public function activeren(): void
    {
        abort_unless($this->magGoedkeuren(), 403);

        $werk = $this->werkVersie;
        abort_unless($werk && $werk->status === 'ter_goedkeuring', 422);

        $this->validate([
            'goedgekeurdDoor' => ['required', 'string', 'max:255'],
        ], attributes: ['goedgekeurdDoor' => 'goedgekeurd door']);

        app(ActiveerScopeVerklaring::class)($werk, $this->goedgekeurdDoor);

        $this->reset('goedgekeurdDoor');
        $this->laadWerkversieVelden();
        session()->flash('melding', 'Nieuwe scope-versie is nu actief.');
    }

    public function render()
    {
        return view('livewire.scope-beheer', [
            'eenheden' => OrganisatieEenheid::orderBy('naam')->get(),
            'issues' => Issue::orderBy('categorie')->get(),
            'belanghebbenden' => Belanghebbende::orderBy('naam')->get(),
            'historie' => ScopeVerklaring::where('status', 'vervangen')
                ->orderByDesc('versienummer')
                ->get(),
        ]);
    }
}
