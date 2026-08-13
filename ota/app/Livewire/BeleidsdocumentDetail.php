<?php

namespace App\Livewire;

use App\Models\Beleidsdocument;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\OrganisatieEenheid;
use App\Models\Raadpleging;
use App\Models\SoaRegel;
use App\Rules\KiesbareGebruiker;
use App\Support\Beleidspublicatie;
use App\Support\Bewijsopslag;
use App\Support\Koppeling;
use App\Support\Recordscope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class BeleidsdocumentDetail extends Component
{
    use WithFileUploads;

    public Beleidsdocument $beleidsdocument;

    // Kopgegevens.
    public string $titel = '';

    public string $omschrijving = '';

    public string $eigenaarId = '';

    public bool $leesbevestigingVereist = false;

    /** @var array<int, int|string> Aangevinkte afdelingen (doelgroep). */
    public array $afdelingIds = [];

    // Nieuwe versie.
    public bool $toontVersieFormulier = false;

    public string $wijzigingsreden = '';

    public ?string $volgendeHerzieningGepland = null;

    public $bestand;

    /** @var array<int, int> */
    public array $geselecteerdeSoaRegels = [];

    public function mount(Beleidsdocument $beleidsdocument): void
    {
        // 404 en niet 403: zonder volledige inzage bestaat een concept voor
        // deze gebruiker simpelweg niet — hetzelfde patroon als bij /taken.
        abort_unless(
            Recordscope::magAllesZien('beleid-maatregelbeheer') || $beleidsdocument->status === 'actief',
            404
        );

        $this->beleidsdocument = $beleidsdocument;
        $this->titel = $beleidsdocument->titel;
        $this->omschrijving = $beleidsdocument->omschrijving ?? '';
        $this->eigenaarId = (string) $beleidsdocument->eigenaar_id;
        $this->leesbevestigingVereist = $beleidsdocument->leesbevestiging_vereist;
        $this->afdelingIds = $beleidsdocument->afdelingen->pluck('id')->all();
        $this->geselecteerdeSoaRegels = $beleidsdocument->soaRegels->pluck('id')->all();
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']);
    }

    public function magGoedkeuren(): bool
    {
        return Gate::allows('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren']);
    }

    public function magAllesZien(): bool
    {
        return Recordscope::magAllesZien('beleid-maatregelbeheer');
    }

    /** Koppelen vereist muteerrecht op beide blokken, net als in BewijsPaneel. */
    public function magSoaKoppelen(): bool
    {
        return $this->magMuteren() && Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    // --- Kopgegevens -------------------------------------------------------

    public function opslaanKop(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string'],
            'eigenaarId' => ['nullable', new KiesbareGebruiker($this->beleidsdocument->eigenaar_id)],
            'leesbevestigingVereist' => ['boolean'],
            // Zelfde regel als in het aanmaakformulier: minstens één afdeling
            // zodra de plicht aan staat (§6).
            'afdelingIds' => [$this->leesbevestigingVereist ? 'required' : 'nullable', 'array'],
            'afdelingIds.*' => [Rule::exists('organisatie_eenheden', 'id')->where('type', OrganisatieEenheid::TYPE_AFDELING)],
        ], attributes: ['titel' => 'titel', 'afdelingIds' => 'afdelingen']);

        $this->beleidsdocument->update([
            'titel' => $gevalideerd['titel'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'eigenaar_id' => $gevalideerd['eigenaarId'] !== '' ? (int) $gevalideerd['eigenaarId'] : null,
            'leesbevestiging_vereist' => $this->leesbevestigingVereist,
        ]);

        Koppeling::sync(
            $this->beleidsdocument->afdelingen(),
            'afdelingen',
            $this->leesbevestigingVereist ? array_map('intval', $this->afdelingIds) : []
        );
        $this->beleidsdocument->refresh();

        session()->flash('melding', 'Documentgegevens opgeslagen.');
    }

    public function intrekken(): void
    {
        $this->vereisMuteren();

        $this->beleidsdocument->update(['ingetrokken_op' => now()]);

        // De actieve versie meetrekken; de observer leidt de documentstatus af
        // en zet hem daarna op 'ingetrokken'.
        $this->beleidsdocument->versies()
            ->where('status', 'actief')
            ->get()
            ->each->update(['status' => 'vervangen']);

        $this->beleidsdocument->refresh();
        session()->flash('melding', 'Document ingetrokken.');
    }

    // --- Versies -----------------------------------------------------------

    public function nieuweVersie(): void
    {
        $this->vereisMuteren();
        $this->reset(['wijzigingsreden', 'volgendeHerzieningGepland', 'bestand']);
        $this->resetValidation();
        $this->toontVersieFormulier = true;
    }

    public function sluitVersieFormulier(): void
    {
        $this->toontVersieFormulier = false;
    }

    public function opslaanVersie(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'wijzigingsreden' => ['nullable', 'string'],
            'volgendeHerzieningGepland' => ['nullable', 'date'],
            'bestand' => ['nullable', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,docx,xlsx,txt,rtf,odt'],
        ], attributes: ['bestand' => 'bestand']);

        // Het versienummer is afgeleid, geen invoerveld: max + 1.
        $volgnummer = (int) $this->beleidsdocument->versies()->max('versienummer') + 1;

        $bewijsstuk = $this->bestand
            ? Bewijsopslag::bewaar(
                $this->bestand,
                $this->beleidsdocument->titel.' v'.$volgnummer,
                'Documentbestand bij beleidsversie '.$volgnummer,
            )
            : null;

        $this->beleidsdocument->versies()->create([
            'versienummer' => $volgnummer,
            'bewijsstuk_id' => $bewijsstuk?->id,
            'status' => 'concept',
            'wijzigingsreden' => $this->wijzigingsreden ?: null,
            'volgende_herziening_gepland' => $this->volgendeHerzieningGepland ?: null,
        ]);

        $this->beleidsdocument->refresh();
        $this->toontVersieFormulier = false;
        session()->flash('melding', "Versie {$volgnummer} aangemaakt.");
    }

    public function terGoedkeuring(int $versieId): void
    {
        $this->vereisMuteren();

        $versie = $this->beleidsdocument->versies()->findOrFail($versieId);
        abort_unless($versie->status === 'concept', 422);

        $versie->update(['status' => 'ter_goedkeuring']);
        $this->beleidsdocument->refresh();
        session()->flash('melding', 'Versie ter goedkeuring aangeboden.');
    }

    public function publiceren(int $versieId): void
    {
        // Bewust `goedkeuren` en niet `muteren`: dit is de enige actie waarvoor
        // het bovenste niveau van de ladder bestaat (implementatie/05 §9).
        abort_unless($this->magGoedkeuren(), 403);

        $versie = $this->beleidsdocument->versies()->findOrFail($versieId);

        Beleidspublicatie::publiceer($versie, auth()->user());

        $this->beleidsdocument->refresh();
        session()->flash('melding', 'Versie gepubliceerd. Openstaande leesbevestigingen worden vannacht als taak uitgezet.');
    }

    // --- SoA-koppeling -----------------------------------------------------

    public function opslaanSoaKoppeling(): void
    {
        abort_unless($this->magSoaKoppelen(), 403);

        $this->validate([
            'geselecteerdeSoaRegels' => ['array'],
            'geselecteerdeSoaRegels.*' => ['integer', 'exists:soa_regels,id'],
        ]);

        Koppeling::sync($this->beleidsdocument->soaRegels(), 'maatregelen', $this->geselecteerdeSoaRegels);
        $this->beleidsdocument->refresh();

        session()->flash('melding', 'SoA-koppelingen opgeslagen.');
    }

    // --- Leesbevestiging ---------------------------------------------------

    public function bevestig(): void
    {
        $versie = $this->beleidsdocument->actieveVersie;

        abort_if($versie === null, 422);
        abort_unless($this->beleidsdocument->leesbevestiging_vereist, 403);
        // Alleen de doelgroep bevestigt: buiten de afdeling telt de bevestiging
        // niet mee, dus hoort de handeling er niet te zijn (§6).
        abort_unless($this->beleidsdocument->isInDoelgroep(auth()->user()), 403);

        Leesbevestiging::firstOrCreate(
            ['beleidsversie_id' => $versie->id, 'gebruiker_id' => auth()->id()],
            ['bevestigd_op' => now()],
        );

        $this->beleidsdocument->refresh();
        session()->flash('melding', 'Uw leesbevestiging is vastgelegd.');
    }

    /**
     * Bevestigingen waarvan niet blijkt dat de bevestiger het bestand had.
     *
     * Bewust geen poort vóór de knop maar een signaal achteraf: iemand die het
     * beleid in een teamsessie of op papier heeft gelezen, bevestigt terecht
     * zonder download, en een verplichte download zou daar een vals negatief
     * van maken. Het getal is stuurinformatie voor de CISO — een leesbevestiging
     * blijft een verklaring, geen meting (implementatie/05 §14).
     *
     * @param  Collection<int, Leesbevestiging>  $bevestigingen
     * @return array<int, bool> gebruiker_id => bevestigd zonder het bestand op te halen
     */
    private function bevestigdZonderRaadpleging($bevestigingen, ?int $bewijsstukId): array
    {
        $eerste = Raadpleging::eersteRaadplegingPerGebruiker($bewijsstukId);

        return $bevestigingen
            ->mapWithKeys(fn (Leesbevestiging $b) => [
                // Ophalen ná het bevestigen telt niet: dan is er bevestigd op
                // het moment dat het document nog niet was opgehaald.
                $b->gebruiker_id => ! isset($eerste[$b->gebruiker_id])
                    || $eerste[$b->gebruiker_id]->greaterThan($b->bevestigd_op),
            ])
            ->all();
    }

    public function render()
    {
        $versies = $this->beleidsdocument->versies()
            ->with(['goedkeurder', 'bewijsstuk'])
            // Zonder volledige inzage alleen de geldende versie: historie is
            // auditmateriaal, een concept is nog geen beleid (§9).
            ->unless($this->magAllesZien(), fn ($q) => $q->where('status', 'actief'))
            ->orderByDesc('versienummer')
            ->get();

        $actieve = $this->beleidsdocument->actieveVersie;

        $bevestigingen = $actieve && $this->magAllesZien()
            ? $actieve->bevestigingen()->with('gebruiker')->get()
            : collect();

        return view('livewire.beleidsdocument-detail', [
            'versies' => $versies,
            'actieveVersie' => $actieve,
            'gebruikers' => Gebruiker::kiesbaar($this->eigenaarId)->pluck('naam', 'id')->all(),
            'afdelingen' => $this->magMuteren()
                ? OrganisatieEenheid::afdelingen()->orderBy('naam')->pluck('naam', 'id')->all()
                : [],
            // Hoort de ingelogde gebruiker tot de doelgroep? Stuurt of de
            // bevestigknop verschijnt (§6).
            'inDoelgroep' => $this->beleidsdocument->isInDoelgroep(auth()->user()),
            'soaRegels' => $this->magSoaKoppelen()
                ? SoaRegel::with('maatregel')->where('van_toepassing', true)->get()
                    ->sortBy(fn (SoaRegel $regel) => $regel->maatregel->annex_a_referentie)
                : collect(),
            'bevestigingen' => $bevestigingen,
            'zonderRaadpleging' => $this->bevestigdZonderRaadpleging($bevestigingen, $actieve?->bewijsstuk_id),
            // Heeft de ingelogde gebruiker het bestand zelf al opgehaald? Stuurt
            // alleen de toonzetting van de bevestigingsdialoog.
            'zelfGeraadpleegd' => $actieve?->bewijsstuk_id !== null && Raadpleging::query()
                ->where('bewijsstuk_id', $actieve->bewijsstuk_id)
                ->where('gebruiker_id', auth()->id())
                ->exists(),
            // Alleen de doelgroep telt als "nog niet bevestigd": iemand buiten
            // de afdeling hoeft niet te bevestigen (§6).
            'nietBevestigd' => $actieve && $this->magAllesZien()
                ? Gebruiker::whereIn('id', $this->beleidsdocument->doelgroepGebruikerIds())
                    ->whereNotIn('id', $actieve->bevestigingen()->pluck('gebruiker_id'))
                    ->orderBy('naam')
                    ->get()
                : collect(),
            'zelfBevestigd' => $actieve?->isBevestigdDoor(auth()->id()) ?? false,
        ]);
    }
}
