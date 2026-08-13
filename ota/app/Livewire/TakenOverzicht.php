<?php

namespace App\Livewire;

use App\Models\Gebruiker;
use App\Models\Taak;
use App\Rules\KiesbareGebruiker;
use App\Support\Recordscope;
use App\Support\StapGeblokkeerd;
use App\Support\Stappenreeks;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TakenOverzicht extends Component
{
    private const BLOK = 'taken-workflow-engine';

    // Standaard op openstaand: een takenlijst die opent op "alles, inclusief
    // voltooid" wordt niet gebruikt.
    #[Url]
    public string $filterStatus = 'openstaand';

    #[Url]
    public bool $alleenVanMij = false;

    #[Url]
    public string $filterBlok = '';

    // Formulier: nieuw (bewerktId = null) of bewerken van een bestaande taak.
    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $titel = '';

    public string $omschrijving = '';

    /**
     * String en niet ?int: een <select> kan alleen tonen wat een optie is, en
     * PHP-null komt met geen enkele optie overeen. Zie x-keuzelijst.
     */
    public string $eigenaarId = '';

    public ?string $deadline = null;

    public function mount(): void
    {
        // Zonder volledige inzage is "alleen van mij" geen filter maar de
        // werkelijkheid; de toggle staat dan vast aan.
        $this->alleenVanMij = $this->alleenVanMij || ! $this->magAllesZien();
        $this->deadline = now()->addDays(14)->format('Y-m-d');
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'muteren']);
    }

    public function magAllesZien(): bool
    {
        return Recordscope::magAllesZien(self::BLOK);
    }

    public function getBewerkteTaakProperty(): ?Taak
    {
        return $this->bewerktId ? Taak::query()->zichtbaar()->find($this->bewerktId) : null;
    }

    public function nieuweTaak(): void
    {
        abort_unless($this->magMuteren(), 403);
        $this->reset(['bewerktId', 'titel', 'omschrijving', 'eigenaarId']);
        $this->deadline = now()->addDays(14)->format('Y-m-d');
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $taakId): void
    {
        abort_unless($this->magMuteren(), 403);

        $taak = Taak::query()->zichtbaar()->findOrFail($taakId);

        $this->bewerktId = $taak->id;
        $this->titel = $taak->titel;
        $this->omschrijving = $taak->omschrijving ?? '';
        $this->eigenaarId = (string) $taak->eigenaar_id;
        $this->deadline = $taak->deadline->format('Y-m-d');

        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
        $this->bewerktId = null;
    }

    public function opslaan(): void
    {
        abort_unless($this->magMuteren(), 403);

        // Bij bewerken mag de al opgeslagen eigenaar blijven, ook als die
        // inmiddels gedeactiveerd is; een nieuwe keuze moet actief zijn.
        $behoudEigenaar = $this->bewerktId
            ? Taak::whereKey($this->bewerktId)->value('eigenaar_id')
            : null;

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            // Verplicht: een taak zonder eigenaar verschijnt bij niemand onder
            // "Mijn openstaande taken" en herinnert dus niemand ergens aan.
            'eigenaarId' => ['required', new KiesbareGebruiker($behoudEigenaar)],
            'deadline' => ['required', 'date'],
        ], attributes: ['titel' => 'titel', 'eigenaarId' => 'eigenaar', 'deadline' => 'deadline']);

        $taak = $this->bewerkteTaak;

        if ($taak) {
            $taak->update([
                'titel' => $gevalideerd['titel'],
                'omschrijving' => $gevalideerd['omschrijving'] ?: null,
                'eigenaar_id' => (int) $gevalideerd['eigenaarId'],
                // De deadline van een door TaakPlanner beheerde taak komt uit
                // het bronblok; hier wijzigen zou de volgende observer-run toch
                // overschrijven.
                'deadline' => $taak->deadlineWordtBeheerd() ? $taak->deadline : $gevalideerd['deadline'],
            ]);

            $this->sluitFormulier();
            session()->flash('melding', 'Taak bijgewerkt.');

            return;
        }

        // Losse taak: bewust zonder taaksjabloon_id. De unique-index raakt dit
        // niet, want NULL-waarden botsen daar niet.
        Taak::create([
            'titel' => $gevalideerd['titel'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'eigenaar_id' => (int) $gevalideerd['eigenaarId'],
            'deadline' => $gevalideerd['deadline'],
        ]);

        $this->sluitFormulier();
        session()->flash('melding', 'Taak aangemaakt.');
    }

    /** Oppakken en voltooien mag de eigenaar zelf, of een CISO. */
    private function vereisZeggenschap(Taak $taak): void
    {
        abort_unless($taak->isVanMij() || $this->magMuteren(), 403);
    }

    public function oppakken(int $taakId): void
    {
        $taak = Taak::query()->zichtbaar()->findOrFail($taakId);
        $this->vereisZeggenschap($taak);

        abort_unless(in_array($taak->status, ['open', 'verlopen'], true), 422);

        $taak->update(['status' => 'in_uitvoering']);
        session()->flash('melding', 'Taak opgepakt.');
    }

    public function voltooien(int $taakId): void
    {
        $taak = Taak::query()->zichtbaar()->findOrFail($taakId);
        $this->vereisZeggenschap($taak);

        if ($taak->status === 'voltooid') {
            return;
        }

        // Een wachtende stap is nog niet aan de beurt, en een goedkeuringsstap
        // heeft een uitkomst nodig. Het scherm verbergt die knoppen al; dit
        // vangt de aanroep die er langs komt.
        abort_if($taak->status === 'wachtend', 422);
        abort_if($taak->vraagt_uitkomst, 422);

        try {
            $taak->update(['status' => 'voltooid', 'voltooid_op' => now()]);
        } catch (StapGeblokkeerd $e) {
            // Het dossier achter de reeks houdt deze stap tegen
            // (implementatie/15 §6). De reden is voor de gebruiker bedoeld.
            session()->flash('belemmering', $e->getMessage());

            return;
        }

        $vertraging = $taak->fresh()->vertragingInDagen();
        session()->flash('melding', $vertraging > 0
            ? "Taak voltooid, {$vertraging} dag(en) na de deadline."
            : 'Taak voltooid.');
    }

    /**
     * Een goedkeuringsstap afronden mét resultaat (implementatie/07b §10). Het
     * afkeuren zet de reeks stil; wat er daarna gebeurt, beslist het bronblok.
     */
    public function legUitkomstVast(int $taakId, string $uitkomst): void
    {
        $taak = Taak::query()->zichtbaar()->findOrFail($taakId);
        $this->vereisZeggenschap($taak);

        abort_unless($taak->vraagt_uitkomst && $taak->status !== 'voltooid', 422);
        abort_unless(in_array($uitkomst, ['goedgekeurd', 'afgekeurd'], true), 422);

        try {
            Stappenreeks::legUitkomstVast($taak, $uitkomst);
        } catch (StapGeblokkeerd $e) {
            session()->flash('belemmering', $e->getMessage());

            return;
        }

        session()->flash('melding', $uitkomst === 'goedgekeurd'
            ? 'Stap goedgekeurd.'
            : 'Stap afgekeurd; de reeks staat stil tot er een besluit is.');
    }

    /**
     * Een voltooide taak terug op onvoltooid zetten — een toezichtsactie voor de
     * CISO die een afmelding afkeurt, niet iets wat de eigenaar zelf doet. Alleen
     * op taken waarvan de status zelf de waarheid is (zie Taak::isHeropenbaar).
     * De vorige voltooiing blijft dankzij de append-only audit trail herleidbaar.
     */
    public function heropenen(int $taakId): void
    {
        abort_unless($this->magMuteren(), 403);

        $taak = Taak::query()->zichtbaar()->with('toetsopdracht')->findOrFail($taakId);

        if ($taak->status !== 'voltooid') {
            return;
        }

        abort_unless($taak->isHeropenbaar(), 422);

        // Terug naar 'open'; ligt de deadline in het verleden, dan zet
        // isms:verloop-taken hem bij de eerstvolgende run weer op 'verlopen'.
        $taak->update(['status' => 'open', 'voltooid_op' => null]);
        session()->flash('melding', 'Taak heropend.');
    }

    public function render()
    {
        $taken = Taak::query()
            ->with(['eigenaar', 'sjabloon', 'entiteit', 'toetsopdracht'])
            ->zichtbaar()
            ->when($this->filterStatus === 'openstaand', fn ($q) => $q->whereIn('status', Taak::OPENSTAAND))
            ->when(! in_array($this->filterStatus, ['', 'openstaand'], true),
                fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->alleenVanMij, fn ($q) => $q->where('eigenaar_id', auth()->id()))
            ->when($this->filterBlok !== '', fn ($q) => $q->where('gekoppeld_blok_naam', $this->filterBlok))
            ->orderBy('deadline')
            ->get();

        return view('livewire.taken-overzicht', [
            'taken' => $taken,
            'reeksen' => $this->reeksen($taken),
            'gebruikers' => Gebruiker::kiesbaar($this->eigenaarId),
            // Openstaande taken bij een niet-actief account herinneren niemand:
            // die eigenaar kan niet meer inloggen. Binnen de eigen record-scope.
            'metNietActieveEigenaar' => Taak::query()
                ->zichtbaar()
                ->whereIn('status', Taak::OPENSTAAND)
                ->whereHas('eigenaar', fn ($u) => $u->where('status', '!=', 'actief'))
                ->count(),
        ]);
    }

    /**
     * De volgordes per reeks, voor de aanduiding "stap 2 van 4" op een rij.
     *
     * Gerekend in groepen en niet in rijen: parallelle stappen delen hun
     * volgorde, en een geschrapte stap laat een gat achter — het ruwe
     * volgorde-nummer zou dan "stap 5 van 3" opleveren.
     *
     * Bewust buiten de record-scope geteld. Een Medewerker ziet alleen zijn
     * eigen stappen, maar zonder de lengte van de reeks zegt "stap 2" niets, en
     * dat aantal verraadt niets over de inhoud van de andere stappen.
     *
     * @param  Collection<int, Taak>  $taken
     * @return array<string, list<int>>
     */
    private function reeksen(Collection $taken): array
    {
        $stappen = $taken->filter(fn (Taak $taak) => $taak->isStap());

        if ($stappen->isEmpty()) {
            return [];
        }

        return Taak::query()
            ->whereNotNull('volgorde')
            ->whereIn('gekoppeld_entiteit_type', $stappen->pluck('gekoppeld_entiteit_type')->unique()->all())
            ->whereIn('gekoppeld_entiteit_id', $stappen->pluck('gekoppeld_entiteit_id')->unique()->all())
            ->get(['gekoppeld_entiteit_type', 'gekoppeld_entiteit_id', 'volgorde'])
            ->groupBy(fn (Taak $stap) => $stap->gekoppeld_entiteit_type.':'.$stap->gekoppeld_entiteit_id)
            ->map(fn ($rijen) => $rijen->pluck('volgorde')->unique()->sort()->values()->all())
            ->all();
    }
}
