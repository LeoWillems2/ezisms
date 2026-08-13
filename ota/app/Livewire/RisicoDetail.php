<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\SoaRegel;
use App\Rules\KiesbareGebruiker;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class RisicoDetail extends Component
{
    public Risico $risico;

    // Basisgegevens.
    public string $titel = '';

    public string $dreiging = '';

    public string $kwetsbaarheid = '';

    public string $gekoppeldAssetId = '';

    public string $risicoEigenaarId = '';

    /**
     * De §4.1-kwesties waaruit dit risico voortkomt (plan 02b). Hoort bij de
     * basisgegevens en niet bij de behandeling: dit is identificatie, geen
     * antwoord. Leeg laten mag — lang niet elk risico komt uit een issue.
     *
     * @var array<int, int>
     */
    public array $geselecteerdeAanleidingen = [];

    // Beoordeling.
    public string $kansNiveau = '';

    public string $impactNiveau = '';

    public ?string $volgendeBeoordelingGepland = null;

    // Behandelplan (één formulier; null = nieuw plan, anders bewerken).
    public ?int $behandelingId = null;

    public string $behandeloptie = 'mitigeren';

    public ?int $restrisicoScore = null;

    public string $geaccepteerdDoor = '';

    public ?string $geaccepteerdOp = null;

    /** @var array<int, int> */
    public array $geselecteerdeSoaRegels = [];

    private const STATUSSEN = [
        'geidentificeerd', 'beoordeeld', 'behandelplan_opgesteld',
        'geaccepteerd', 'in_uitvoering', 'gemitigeerd',
    ];

    public function mount(Risico $risico): void
    {
        $this->risico = $risico;
        $this->laadVelden();
        $this->laadBehandeling($risico->behandelingen()->latest('id')->first());

        // Wie accepteert, tekent doorgaans namens zichzelf; het veld blijft vrij
        // tekst omdat een directeur ook "namens de directie" kan vastleggen.
        if ($this->geaccepteerdDoor === '') {
            $this->geaccepteerdDoor = auth()->user()?->naam ?? '';
        }
    }

    private function laadVelden(): void
    {
        $this->titel = $this->risico->titel;
        $this->dreiging = $this->risico->dreiging ?? '';
        $this->kwetsbaarheid = $this->risico->kwetsbaarheid ?? '';
        $this->gekoppeldAssetId = (string) $this->risico->gekoppeld_asset_id;
        $this->risicoEigenaarId = (string) $this->risico->risico_eigenaar_id;
        $this->kansNiveau = (string) $this->risico->kans_niveau;
        $this->impactNiveau = (string) $this->risico->impact_niveau;
        $this->volgendeBeoordelingGepland = $this->risico->volgende_beoordeling_gepland?->format('Y-m-d');
        $this->geselecteerdeAanleidingen = $this->risico->aanleidingen->pluck('id')->all();
    }

    private function laadBehandeling(?Risicobehandeling $behandeling): void
    {
        $this->behandelingId = $behandeling?->id;
        $this->behandeloptie = $behandeling?->behandeloptie ?? 'mitigeren';
        $this->restrisicoScore = $behandeling?->restrisico_score;
        $this->geaccepteerdDoor = $behandeling?->geaccepteerd_door ?? '';
        $this->geaccepteerdOp = $behandeling?->geaccepteerd_op?->format('Y-m-d');
        $this->geselecteerdeSoaRegels = $behandeling?->soaRegels->pluck('id')->all() ?? [];
    }

    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['risico-soa', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    /**
     * Een restrisico boven de acceptatiedrempel accepteren is vaststellen, niet
     * bewerken (§6.1.3, implementatie/01c §4): de CISO stelt het behandelplan
     * op, de directie tekent voor het restrisico dat overblijft.
     */
    public function magGoedkeuren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'goedkeuren']);
    }

    public function opslaanBasis(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'dreiging' => ['nullable', 'string'],
            'kwetsbaarheid' => ['nullable', 'string'],
            'gekoppeldAssetId' => ['nullable', Rule::exists('assets', 'id')],
            'risicoEigenaarId' => ['nullable', new KiesbareGebruiker($this->risico->risico_eigenaar_id)],
            'geselecteerdeAanleidingen' => ['array'],
            'geselecteerdeAanleidingen.*' => ['integer', 'exists:issues,id'],
        ], attributes: ['titel' => 'titel']);

        $this->risico->update([
            'titel' => $gevalideerd['titel'],
            'dreiging' => $gevalideerd['dreiging'] ?: null,
            'kwetsbaarheid' => $gevalideerd['kwetsbaarheid'] ?: null,
            // Lege string = bewust geen koppeling; naar null, niet naar 0.
            'gekoppeld_asset_id' => $gevalideerd['gekoppeldAssetId'] !== '' ? (int) $gevalideerd['gekoppeldAssetId'] : null,
            'risico_eigenaar_id' => $gevalideerd['risicoEigenaarId'] !== '' ? (int) $gevalideerd['risicoEigenaarId'] : null,
        ]);

        Koppeling::sync($this->risico->aanleidingen(), 'aanleidingen', $gevalideerd['geselecteerdeAanleidingen'] ?? []);

        session()->flash('melding', 'Basisgegevens opgeslagen.');
    }

    public function opslaanBeoordeling(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'kansNiveau' => ['nullable', 'integer', 'between:1,5'],
            'impactNiveau' => ['nullable', 'integer', 'between:1,5'],
            'volgendeBeoordelingGepland' => ['nullable', 'date'],
        ], attributes: [
            'kansNiveau' => 'kans',
            'impactNiveau' => 'impact',
            'volgendeBeoordelingGepland' => 'volgende beoordeling',
        ]);

        // De observer leidt de risicoscore af uit kans x impact.
        $this->risico->update([
            'kans_niveau' => $gevalideerd['kansNiveau'] !== '' ? (int) $gevalideerd['kansNiveau'] : null,
            'impact_niveau' => $gevalideerd['impactNiveau'] !== '' ? (int) $gevalideerd['impactNiveau'] : null,
            'volgende_beoordeling_gepland' => $gevalideerd['volgendeBeoordelingGepland'] ?: null,
        ]);

        // Een volledig beoordeeld risico schuift één stap op in de levenscyclus;
        // verder gevorderde statussen blijven ongemoeid.
        if ($this->risico->risicoscore !== null && $this->risico->status === 'geidentificeerd') {
            $this->risico->update(['status' => 'beoordeeld']);
        }

        $this->risico->refresh();
        session()->flash('melding', 'Beoordeling opgeslagen.');
    }

    public function nieuwBehandelplan(): void
    {
        $this->vereisMuteren();
        $this->laadBehandeling(null);
        $this->resetValidation();
    }

    public function bewerkBehandeling(int $behandelingId): void
    {
        $this->vereisMuteren();
        $this->laadBehandeling($this->risico->behandelingen()->with('soaRegels')->findOrFail($behandelingId));
        $this->resetValidation();
    }

    public function opslaanBehandeling(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'behandeloptie' => ['required', Rule::in(['mitigeren', 'accepteren', 'overdragen', 'vermijden'])],
            'restrisicoScore' => ['nullable', 'integer', 'between:0,25'],
            'geselecteerdeSoaRegels' => ['array'],
            'geselecteerdeSoaRegels.*' => ['integer', 'exists:soa_regels,id'],
        ], attributes: [
            'behandeloptie' => 'behandeloptie',
            'restrisicoScore' => 'restrisicoscore',
        ]);

        $attributen = [
            'behandeloptie' => $gevalideerd['behandeloptie'],
            'restrisico_score' => $gevalideerd['restrisicoScore'],
        ];

        $behandeling = $this->behandelingId
            ? tap($this->risico->behandelingen()->findOrFail($this->behandelingId))->update($attributen)
            : $this->risico->behandelingen()->create($attributen);

        Koppeling::sync($behandeling->soaRegels(), 'maatregelen', $gevalideerd['geselecteerdeSoaRegels']);

        // Statusovergang. Accepteren onder de acceptatiedrempel valt binnen het
        // mandaat van de CISO en is meteen rond; boven de drempel eist
        // deelproducten/04-risico-soa.md §3 een expliciet vastgelegde beslisser,
        // en die legt de directie vast via accepteerRestrisico(). Tot dat moment
        // is er alleen een behandelplan. Al verder gevorderde statussen (in
        // uitvoering, gemitigeerd) worden niet teruggezet.
        if ($gevalideerd['behandeloptie'] === 'accepteren' && ! $this->risico->boventDrempel()) {
            $this->risico->update(['status' => 'geaccepteerd']);
        } elseif (in_array($this->risico->status, ['geidentificeerd', 'beoordeeld'], true)) {
            $this->risico->update(['status' => 'behandelplan_opgesteld']);
        }

        $this->risico->refresh();
        $this->laadBehandeling($behandeling->fresh('soaRegels'));
        session()->flash('melding', 'Behandelplan opgeslagen.');
    }

    /**
     * De directie tekent voor een restrisico boven de drempel. Bewust een eigen
     * actie en niet een veld in het behandelformulier: anders zou wie het plan
     * mag schrijven ook zijn eigen restrisico accepteren.
     */
    public function accepteerRestrisico(int $behandelingId): void
    {
        abort_unless($this->magGoedkeuren(), 403);

        $behandeling = $this->risico->behandelingen()->findOrFail($behandelingId);
        abort_unless($behandeling->behandeloptie === 'accepteren', 422);

        $gevalideerd = $this->validate([
            'geaccepteerdDoor' => ['required', 'string', 'max:255'],
            'geaccepteerdOp' => ['nullable', 'date'],
        ], messages: [
            'geaccepteerdDoor.required' => 'Risico\'s boven de acceptatiedrempel (score > '
                .Risico::drempelwaarde().') vereisen een expliciete acceptatie: vul in wie accepteert.',
        ], attributes: ['geaccepteerdDoor' => 'geaccepteerd door']);

        $behandeling->update([
            'geaccepteerd_door' => $gevalideerd['geaccepteerdDoor'],
            'geaccepteerd_op' => $gevalideerd['geaccepteerdOp'] ?: now()->toDateString(),
        ]);

        $this->risico->update(['status' => 'geaccepteerd']);
        $this->risico->refresh();
        session()->flash('melding', 'Restrisico geaccepteerd.');
    }

    public function statusWijzigen(string $status): void
    {
        $this->vereisMuteren();

        abort_unless(in_array($status, self::STATUSSEN, true), 422);

        $this->risico->update(['status' => $status]);
        $this->risico->refresh();
        session()->flash('melding', 'Status bijgewerkt.');
    }

    public function render()
    {
        return view('livewire.risico-detail', [
            'assets' => Asset::orderBy('naam')->get(),
            'gebruikers' => Gebruiker::kiesbaar($this->risicoEigenaarId),
            'eigenaarGebruiker' => $this->risico->eigenaar,
            // Externe kwesties eerst: die zijn bij risico-identificatie het
            // vaakst de aanleiding, en de aard is hier het enige ordenende veld
            // dat de lijst leesbaar houdt.
            'issues' => Issue::orderByDesc('aard')->orderBy('categorie')->get()->groupBy('aard'),
            // Alleen maatregelen die van toepassing zijn verklaard kunnen als
            // behandelmaatregel dienen (implementatie/04 §8).
            'soaRegels' => SoaRegel::with('maatregel')
                ->where('van_toepassing', true)
                ->get()
                ->sortBy(function (SoaRegel $regel) {
                    [$hoofdstuk, $sub] = array_pad(explode('.', $regel->maatregel->annex_a_referentie, 2), 2, '0');

                    return (int) $hoofdstuk * 1000 + (int) $sub;
                }),
            'behandelingen' => $behandelingen = $this->risico->behandelingen()
                ->with('soaRegels.maatregel')->orderBy('id')->get(),
            // Acceptaties die nog op een handtekening van de directie wachten.
            'teAccepteren' => $behandelingen->filter(
                fn (Risicobehandeling $b) => $b->behandeloptie === 'accepteren' && blank($b->geaccepteerd_door)
            ),
            'drempel' => Risico::drempelwaarde(),
        ]);
    }
}
