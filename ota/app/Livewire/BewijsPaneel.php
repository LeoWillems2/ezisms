<?php

namespace App\Livewire;

use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Support\Bewijsopslag;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Herbruikbaar paneel dat andere blokken in hun detailscherm opnemen — de
 * reden dat blok 6 cross-cutting is (implementatie/06 §9):
 *
 *   <livewire:bewijs-paneel blok-naam="risico-soa" entiteit-type="risico"
 *       :entiteit-id="$risico->id" />
 *
 * Uploaden en koppelen zijn twee losse handelingen. In de eerste versie was
 * koppelen alleen een bijproduct van uploaden; daardoor was een bewijsstuk dat
 * via /bewijsstukken was opgevoerd nergens meer aan te hangen, terwijl dat
 * scherm "ongekoppeld" wél als tekortkoming meldde.
 *
 * Let op: het uploadrecht hangt aan blok 6, maar het koppelen aan een entiteit
 * vereist muteerrecht op HET BLOK VAN DIE ENTITEIT. Anders zou een Medewerker
 * met upload-recht bewijs kunnen hangen aan een risico dat hij niet eens mag
 * inzien.
 */
class BewijsPaneel extends Component
{
    use WithFileUploads;

    public string $blokNaam;

    public string $entiteitType;

    public int $entiteitId;

    // Nieuw bewijsstuk uploaden.
    public bool $toontFormulier = false;

    public string $naam = '';

    public $bestand;

    // Bestaand bewijsstuk koppelen.
    public bool $toontKoppelen = false;

    public string $zoekterm = '';

    public function magKoppelen(): bool
    {
        return Gate::allows('heeft-niveau', [$this->blokNaam, 'muteren'])
            && Gate::allows('heeft-niveau', ['bewijsrepository-audit-trail', 'uitvoeren']);
    }

    public function nieuwBewijsstuk(): void
    {
        abort_unless($this->magKoppelen(), 403);
        $this->reset(['naam', 'bestand']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
    }

    public function koppelBestaand(): void
    {
        abort_unless($this->magKoppelen(), 403);
        $this->reset('zoekterm');
        $this->toontKoppelen = true;
    }

    public function sluitKoppelen(): void
    {
        $this->toontKoppelen = false;
    }

    public function opslaan(): void
    {
        abort_unless($this->magKoppelen(), 403);

        $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'bestand' => ['required', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,docx,xlsx,txt'],
        ], attributes: ['naam' => 'naam', 'bestand' => 'bestand']);

        $this->koppel(Bewijsopslag::bewaar($this->bestand, $this->naam));

        $this->reset(['naam', 'bestand']);
        $this->toontFormulier = false;
        session()->flash('melding', 'Bewijsstuk gekoppeld.');
    }

    public function koppelBestaandBewijsstuk(int $bewijsstukId): void
    {
        abort_unless($this->magKoppelen(), 403);

        // Zichtbaar-scope: je kunt alleen koppelen wat je zelf mag inzien.
        $this->koppel(Bewijsstuk::query()->zichtbaar()->findOrFail($bewijsstukId));

        $this->toontKoppelen = false;
        session()->flash('melding', 'Bewijsstuk gekoppeld.');
    }

    private function koppel(Bewijsstuk $bewijsstuk): void
    {
        // firstOrCreate, want de unique op (bewijsstuk, type, id) maakt een
        // dubbele koppeling anders een 500 in plaats van een no-op.
        $bewijsstuk->koppelingen()->firstOrCreate([
            'blok_naam' => $this->blokNaam,
            'entiteit_type' => $this->entiteitType,
            'entiteit_id' => $this->entiteitId,
        ]);
    }

    public function ontkoppel(int $koppelingId): void
    {
        abort_unless($this->magKoppelen(), 403);

        BewijsKoppeling::query()
            ->whereKey($koppelingId)
            ->where('entiteit_type', $this->entiteitType)
            ->where('entiteit_id', $this->entiteitId)
            // deleteGeaudit: een massa-delete omzeilt de audit trail, en juist
            // het lóskoppelen van bewijs hoort herleidbaar te zijn.
            ->deleteGeaudit();
    }

    /** De bewijsstukken die al aan deze entiteit hangen. */
    private function gekoppeldeQuery()
    {
        return Bewijsstuk::query()->whereHas('koppelingen', fn ($q) => $q
            ->where('entiteit_type', $this->entiteitType)
            ->where('entiteit_id', $this->entiteitId));
    }

    public function render()
    {
        $gekoppeld = $this->gekoppeldeQuery()
            ->with(['uploader', 'koppelingen' => fn ($q) => $q
                ->where('entiteit_type', $this->entiteitType)
                ->where('entiteit_id', $this->entiteitId)])
            ->orderByDesc('geupload_op')
            ->get();

        return view('livewire.bewijs-paneel', [
            'bewijsstukken' => $gekoppeld,
            // Kandidaten: alles wat je mag zien, nog niet aan déze entiteit
            // hangt en actief is. Gearchiveerde stukken koppelen zou een
            // verlopen bewaartermijn stilzwijgend weer in gebruik nemen.
            'kandidaten' => $this->toontKoppelen
                ? Bewijsstuk::query()
                    ->zichtbaar()
                    ->where('status', 'actief')
                    ->whereKeyNot($gekoppeld->modelKeys())
                    ->when($this->zoekterm !== '', fn ($q) => $q
                        ->where(fn ($sub) => $sub
                            ->where('naam', 'like', '%'.$this->zoekterm.'%')
                            ->orWhere('bestandsnaam', 'like', '%'.$this->zoekterm.'%')))
                    ->with('koppelingen')
                    ->orderByDesc('geupload_op')
                    ->limit(25)
                    ->get()
                : collect(),
        ]);
    }
}
