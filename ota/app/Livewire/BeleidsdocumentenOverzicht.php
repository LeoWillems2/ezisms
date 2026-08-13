<?php

namespace App\Livewire;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Rules\KiesbareGebruiker;
use App\Support\Koppeling;
use App\Support\Recordscope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BeleidsdocumentenOverzicht extends Component
{
    // Standaardfilter 'actief': concepten en ingetrokken documenten staan
    // achter het filter, niet in de standaardweergave (implementatie/05 §10).
    #[Url]
    public string $filterStatus = 'actief';

    #[Url]
    public string $filterType = '';

    public bool $toontFormulier = false;

    public ?int $bewerktId = null;

    public string $titel = '';

    public string $type = 'beleid';

    public string $omschrijving = '';

    public string $eigenaarId = '';

    public bool $leesbevestigingVereist = true;

    /**
     * De aangevinkte afdelingen (organisatie-eenheden van type 'afdeling') die
     * de doelgroep vormen. Alleen van belang zolang de bevestigingsplicht aan
     * staat.
     *
     * @var array<int, int|string>
     */
    public array $afdelingIds = [];

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']);
    }

    public function magAllesZien(): bool
    {
        return Recordscope::magAllesZien('beleid-maatregelbeheer');
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuwDocument(): void
    {
        $this->vereisMuteren();
        $this->reset(['bewerktId', 'titel', 'omschrijving', 'eigenaarId', 'afdelingIds']);
        $this->type = 'beleid';
        $this->leesbevestigingVereist = Beleidsdocument::standaardLeesbevestiging($this->type);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $id): void
    {
        $this->vereisMuteren();

        $document = Beleidsdocument::findOrFail($id);

        $this->bewerktId = $document->id;
        $this->titel = $document->titel;
        $this->type = $document->type;
        $this->omschrijving = $document->omschrijving ?? '';
        $this->eigenaarId = (string) $document->eigenaar_id;
        $this->leesbevestigingVereist = $document->leesbevestiging_vereist;
        $this->afdelingIds = $document->afdelingen->pluck('id')->all();
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    /**
     * Het type stuurt alleen de **default** van de bevestigingsplicht (§6). Bij
     * een bestaand document laten we die keuze met rust: die is dan bewust
     * gemaakt en mag niet stilzwijgend terugspringen.
     */
    public function updatedType(string $waarde): void
    {
        if ($this->bewerktId === null) {
            $this->leesbevestigingVereist = Beleidsdocument::standaardLeesbevestiging($waarde);
        }
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        // De al opgeslagen eigenaar mag blijven staan, ook als die inmiddels
        // gedeactiveerd is; een nieuwe keuze moet een actief account zijn.
        $behoudEigenaar = $this->bewerktId
            ? Beleidsdocument::whereKey($this->bewerktId)->value('eigenaar_id')
            : null;

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['beleid', 'procedure'])],
            'omschrijving' => ['nullable', 'string'],
            'eigenaarId' => ['nullable', new KiesbareGebruiker($behoudEigenaar)],
            'leesbevestigingVereist' => ['boolean'],
            // Minstens één afdeling zodra de plicht aan staat: anders is de
            // doelgroep leeg en betekent "vereist" niets (§6). Zonder plicht
            // is een afdeling niet van toepassing.
            'afdelingIds' => [$this->leesbevestigingVereist ? 'required' : 'nullable', 'array'],
            'afdelingIds.*' => [Rule::exists('organisatie_eenheden', 'id')->where('type', OrganisatieEenheid::TYPE_AFDELING)],
        ], attributes: [
            'titel' => 'titel',
            'type' => 'type',
            'afdelingIds' => 'afdelingen',
        ]);

        $attributen = [
            'titel' => $gevalideerd['titel'],
            'type' => $gevalideerd['type'],
            'omschrijving' => $gevalideerd['omschrijving'] ?: null,
            'eigenaar_id' => $gevalideerd['eigenaarId'] !== '' ? (int) $gevalideerd['eigenaarId'] : null,
            'leesbevestiging_vereist' => $this->leesbevestigingVereist,
        ];

        $document = $this->bewerktId
            ? tap(Beleidsdocument::findOrFail($this->bewerktId))->update($attributen)
            : Beleidsdocument::create($attributen);

        // Zonder bevestigingsplicht horen er geen afdelingen bij: leegmaken, zodat
        // een later heraanzetten niet stilzwijgend een oude doelgroep terugbrengt.
        Koppeling::sync(
            $document->afdelingen(),
            'afdelingen',
            $this->leesbevestigingVereist ? array_map('intval', $this->afdelingIds) : []
        );

        $this->toontFormulier = false;
        session()->flash('melding', 'Beleidsdocument opgeslagen.');
    }

    /**
     * Actieve versies die de ingelogde gebruiker nog moet bevestigen.
     *
     * Dezelfde `zichtbaar()`-scope als de lijst zelf: een waarschuwing die
     * telt wat er in de lijst niet staat, laat de gebruiker zoeken naar een
     * regel die er niet is.
     */
    public function openstaandeBevestigingen(): int
    {
        // Alleen documenten die op de eigen afdeling gericht zijn (§6). Zonder
        // afdeling is er geen doelgroep om in te vallen.
        $afdelingId = auth()->user()?->organisatie_eenheid_id;

        if ($afdelingId === null) {
            return 0;
        }

        return Beleidsversie::where('status', 'actief')
            ->whereHas('document', fn ($q) => $q->zichtbaar()
                ->where('leesbevestiging_vereist', true)
                ->whereHas('afdelingen', fn ($a) => $a->where('organisatie_eenheden.id', $afdelingId)))
            ->whereDoesntHave('bevestigingen', fn ($q) => $q->where('gebruiker_id', auth()->id()))
            ->count();
    }

    public function render()
    {
        $documenten = Beleidsdocument::query()
            ->zichtbaar()
            ->with(['eigenaar', 'actieveVersie'])
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== '', fn ($q) => $q->where('type', $this->filterType))
            ->orderBy('titel')
            ->get();

        return view('livewire.beleidsdocumenten-overzicht', [
            'documenten' => $documenten,
            'gebruikers' => $this->magMuteren()
                ? Gebruiker::kiesbaar($this->eigenaarId)->pluck('naam', 'id')->all()
                : [],
            'afdelingen' => $this->magMuteren()
                ? OrganisatieEenheid::afdelingen()->orderBy('naam')->pluck('naam', 'id')->all()
                : [],
            'metNietActieveEigenaar' => Beleidsdocument::query()
                ->zichtbaar()
                ->whereHas('eigenaar', fn ($u) => $u->where('status', '!=', 'actief'))
                ->count(),
        ]);
    }
}
