<?php

namespace App\Livewire;

use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Sjabloonstap;
use App\Models\Taak;
use App\Models\Wijzigingssjabloon;
use App\Support\Wijzigingsroutes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer van de wijzigingssjablonen (implementatie/15 §8).
 *
 * Dit is de plek waar de belofte uit deelproduct 15 §2 wordt ingelost: de
 * stappenreeks is data, aanpasbaar zonder release. De vijf staptypen zijn dat
 * niet — die zijn code, want elk type heeft eigen gedrag.
 */
#[Layout('components.layouts.app')]
class WijzigingssjablonenBeheer extends Component
{
    private const BLOK = 'wijzigingsbeheer';

    public ?int $geopendSjabloonId = null;

    // Sjabloonformulier. Eigen veldnamen naast die van het stapformulier: één
    // gedeelde `naam`/`omschrijving` zou de twee modalen door elkaar laten lopen.
    public bool $toontSjabloon = false;

    public ?int $bewerktSjabloonId = null;

    public string $sjabloonNaam = '';

    public string $sjabloonOmschrijving = '';

    public string $sjabloonSoort = 'leveranciersrelease';

    public string $sjabloonZwaarte = 'standaard';

    // Stapformulier.
    public bool $toontStap = false;

    public ?int $stapId = null;

    public string $titel = '';

    public string $omschrijving = '';

    public string $staptype = 'analyse';

    public int $volgorde = 1;

    public int $deadlineOffsetDagen = 0;

    public bool $bewijsVerplicht = false;

    public string $standaardEigenaarId = '';

    public string $doelgroepId = '';

    public string $bijAfkeurenTerugNaar = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    public function open(int $sjabloonId): void
    {
        $this->geopendSjabloonId = $this->geopendSjabloonId === $sjabloonId ? null : $sjabloonId;
    }

    // --- Sjablonen ---------------------------------------------------------

    public function nieuwSjabloon(): void
    {
        abort_unless($this->magMuteren(), 403);

        $this->reset(['bewerktSjabloonId', 'sjabloonNaam', 'sjabloonOmschrijving']);
        $this->sjabloonSoort = 'leveranciersrelease';
        $this->sjabloonZwaarte = 'standaard';
        $this->resetValidation();
        $this->toontSjabloon = true;
    }

    public function bewerkSjabloon(int $sjabloonId): void
    {
        abort_unless($this->magMuteren(), 403);

        $sjabloon = Wijzigingssjabloon::findOrFail($sjabloonId);

        $this->bewerktSjabloonId = $sjabloon->id;
        $this->sjabloonNaam = $sjabloon->naam;
        $this->sjabloonOmschrijving = $sjabloon->omschrijving ?? '';
        $this->sjabloonSoort = $sjabloon->soort;
        $this->sjabloonZwaarte = $sjabloon->zwaarte;
        $this->resetValidation();
        $this->toontSjabloon = true;
    }

    public function sjabloonOpslaan(): void
    {
        abort_unless($this->magMuteren(), 403);

        $gevalideerd = $this->validate([
            'sjabloonNaam' => [
                'required', 'string', 'max:255',
                Rule::unique('wijzigingssjablonen', 'naam')->ignore($this->bewerktSjabloonId),
            ],
            'sjabloonOmschrijving' => ['nullable', 'string'],
            'sjabloonSoort' => ['required', 'in:'.implode(',', WijzigingenOverzicht::SOORTEN)],
            'sjabloonZwaarte' => ['required', 'in:standaard,ingrijpend,spoed'],
        ], attributes: [
            'sjabloonNaam' => 'naam',
            'sjabloonSoort' => 'soort',
            'sjabloonZwaarte' => 'zwaarte',
        ]);

        $waarden = [
            'naam' => $gevalideerd['sjabloonNaam'],
            'omschrijving' => $gevalideerd['sjabloonOmschrijving'] ?: null,
            'soort' => $gevalideerd['sjabloonSoort'],
            'zwaarte' => $gevalideerd['sjabloonZwaarte'],
        ];

        if ($this->bewerktSjabloonId) {
            Wijzigingssjabloon::findOrFail($this->bewerktSjabloonId)->update($waarden);
            $melding = 'Sjabloon bijgewerkt. Lopende dossiers houden hun eigen soort en zwaarte.';
        } else {
            // Meteen openklappen: een sjabloon zonder stappen is nog niet
            // bruikbaar, en de volgende handeling is dus stappen toevoegen.
            $this->geopendSjabloonId = Wijzigingssjabloon::create($waarden)->id;
            $melding = 'Sjabloon aangemaakt. Voeg nu de stappen toe; zonder stappen is de route niet te kiezen.';
        }

        $this->toontSjabloon = false;
        session()->flash('melding', $melding);
    }

    /**
     * Zet een geleverde route terug naar de stappen zoals wij ze leveren
     * (implementatie/15 §19). Reversibiliteit in plaats van een verbod: het
     * bewerken blijft vrij, maar een vergissing is met één knop te herstellen.
     */
    public function zetTerug(int $sjabloonId): void
    {
        abort_unless($this->magMuteren(), 403);

        $sjabloon = Wijzigingssjabloon::with('stappen')->findOrFail($sjabloonId);

        if (! $sjabloon->geleverd) {
            session()->flash('belemmering', 'Dit is een eigen route; er is geen geleverde versie om naar terug te gaan.');

            return;
        }

        Wijzigingsroutes::zetTerug($sjabloon);

        session()->flash('melding', 'Route teruggezet naar de geleverde stappen. '
            .'Lopende dossiers houden de reeks waarmee ze zijn gestart.');
    }

    /**
     * Verwijderen mag alleen bij een eigen route waar nooit een dossier op heeft
     * gedraaid.
     *
     * Een gebruikt sjabloon is historie: `wijzigingen.wijzigingssjabloon_id` is
     * `nullOnDelete`, dus het dossier zou blijven bestaan zonder te tonen welke
     * route het volgde. Voor een route die niet meer gekozen moet worden is er
     * de inactief-schakelaar.
     */
    public function verwijderSjabloon(int $sjabloonId): void
    {
        abort_unless($this->magMuteren(), 403);

        $sjabloon = Wijzigingssjabloon::withCount('wijzigingen')->findOrFail($sjabloonId);

        // Een geleverde route blijft bestaan, ook ongebruikt: hij hoort bij wat
        // het product levert, en de kennisbank verwijst ernaar. Niet meer nodig?
        // Dan op inactief.
        if ($sjabloon->geleverd) {
            session()->flash('belemmering', 'Dit is een meegeleverde route. Zet hem op inactief als hij niet '
                .'gekozen moet worden; verwijderen kan alleen bij eigen routes.');

            return;
        }

        if ($sjabloon->wijzigingen_count > 0) {
            session()->flash('belemmering', sprintf(
                'Dit sjabloon is gebruikt in %d dossier(s) en blijft daarom bestaan. Zet het op inactief '
                .'als het niet meer gekozen moet worden.',
                $sjabloon->wijzigingen_count,
            ));

            return;
        }

        // Zie de toelichting bij verwijderStap(): `deleteGeaudit()` op een model
        // is een query-builder-macro en verwijdert alles.
        $sjabloon->delete();

        session()->flash('melding', 'Sjabloon verwijderd.');
    }

    // --- Stappen -----------------------------------------------------------

    public function nieuweStap(int $sjabloonId): void
    {
        abort_unless($this->magMuteren(), 403);

        $this->reset(['stapId', 'titel', 'omschrijving', 'standaardEigenaarId', 'doelgroepId', 'bijAfkeurenTerugNaar']);
        $this->staptype = 'analyse';
        $this->deadlineOffsetDagen = 0;
        $this->bewijsVerplicht = false;
        $this->geopendSjabloonId = $sjabloonId;
        $this->volgorde = (Sjabloonstap::where('wijzigingssjabloon_id', $sjabloonId)->max('volgorde') ?? 0) + 1;
        $this->resetValidation();
        $this->toontStap = true;
    }

    public function bewerkStap(int $stapId): void
    {
        abort_unless($this->magMuteren(), 403);

        $stap = Sjabloonstap::findOrFail($stapId);

        $this->stapId = $stap->id;
        $this->geopendSjabloonId = $stap->wijzigingssjabloon_id;
        $this->titel = $stap->titel;
        $this->omschrijving = $stap->omschrijving ?? '';
        $this->staptype = $stap->staptype;
        $this->volgorde = $stap->volgorde;
        $this->deadlineOffsetDagen = $stap->deadline_offset_dagen;
        $this->bewijsVerplicht = $stap->bewijs_verplicht;
        $this->standaardEigenaarId = (string) $stap->standaard_eigenaar_id;
        $this->doelgroepId = (string) $stap->doelgroep_id;
        $this->bijAfkeurenTerugNaar = (string) $stap->bij_afkeuren_terug_naar;
        $this->resetValidation();
        $this->toontStap = true;
    }

    public function stapOpslaan(): void
    {
        abort_unless($this->magMuteren(), 403);

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            'staptype' => ['required', 'in:'.implode(',', Sjabloonstap::STAPTYPEN)],
            'volgorde' => ['required', 'integer', 'min:1'],
            'deadlineOffsetDagen' => ['required', 'integer', 'between:-365,365'],
            'standaardEigenaarId' => ['nullable', 'exists:gebruikers,id'],
            'doelgroepId' => ['nullable', 'exists:doelgroepen,id'],
            'bijAfkeurenTerugNaar' => ['nullable', 'integer', 'min:1'],
        ], attributes: ['titel' => 'titel', 'volgorde' => 'volgorde']);

        $waarden = [
            'wijzigingssjabloon_id' => $this->geopendSjabloonId,
            'titel' => $gevalideerd['titel'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'staptype' => $gevalideerd['staptype'],
            'volgorde' => $gevalideerd['volgorde'],
            'deadline_offset_dagen' => $gevalideerd['deadlineOffsetDagen'],
            'bewijs_verplicht' => $this->bewijsVerplicht,
            'standaard_eigenaar_id' => $gevalideerd['standaardEigenaarId'] ?: null,
            'doelgroep_id' => $gevalideerd['doelgroepId'] ?: null,
            'bij_afkeuren_terug_naar' => $gevalideerd['bijAfkeurenTerugNaar'] ?: null,
        ];

        if ($this->stapId) {
            Sjabloonstap::findOrFail($this->stapId)->update($waarden);
        } else {
            Sjabloonstap::create($waarden);
        }

        $this->toontStap = false;
        session()->flash('melding', 'Stap opgeslagen. Lopende dossiers veranderen hier niet van.');
    }

    public function verwijderStap(int $stapId): void
    {
        abort_unless($this->magMuteren(), 403);

        // Geen grendel meer op stappen die in een lopend dossier zitten. Die
        // bestond omdat `taken.sjabloonstap_id` nullOnDelete is en de stap
        // daarmee zijn staptype verloor — inclusief de terugvalplancontrole.
        // Sinds migratie `000055` staan staptype, bewijs_verplicht en
        // bij_afkeuren_terug_naar bevroren op de taak zelf, dus een verwijderde
        // sjabloonstap raakt een lopend dossier niet meer (§17). Alleen de
        // herkomstverwijzing gaat verloren.
        //
        // `delete()` en niet `deleteGeaudit()`: die macro hangt aan de
        // query-builder, en op een model valt hij via __call door naar een verse
        // query zónder sleutel — dat verwijdert alle sjabloonstappen. Het
        // model-event is hier genoeg, want `Auditeerbaar` schrijft de trailregel
        // op `deleted`.
        Sjabloonstap::findOrFail($stapId)->delete();

        session()->flash('melding', 'Stap verwijderd. Lopende dossiers houden de stap waarmee ze zijn gestart.');
    }

    public function zetActief(int $sjabloonId, bool $actief): void
    {
        abort_unless($this->magMuteren(), 403);

        Wijzigingssjabloon::findOrFail($sjabloonId)->update(['actief' => $actief]);

        session()->flash('melding', $actief ? 'Sjabloon geactiveerd.' : 'Sjabloon op inactief gezet.');
    }

    public function render()
    {
        return view('livewire.wijzigingssjablonen-beheer', [
            'sjablonen' => Wijzigingssjabloon::with(['stappen.standaardEigenaar', 'stappen.doelgroep'])
                ->orderBy('naam')->get(),
            'gebruikers' => Gebruiker::kiesbaar($this->standaardEigenaarId),
            'doelgroepen' => Doelgroep::orderBy('naam')->pluck('naam', 'id'),
        ]);
    }
}
