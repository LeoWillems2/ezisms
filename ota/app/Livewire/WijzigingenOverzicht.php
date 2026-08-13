<?php

namespace App\Livewire;

use App\Models\Leverancier;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Wijziging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Het wijzigingenregister (implementatie/15 §8). Dit is het scherm waarop een
 * auditor de A.8.32-vraag beantwoordt: welke wijzigingen zijn er geweest, en
 * met welke goedkeuring.
 *
 * Géén record-scoping — zie §5: A.8.32 c) verplicht juist tot het informeren
 * van belanghebbenden, en een wijzigingskalender die alleen de CISO ziet werkt
 * daar tegenin.
 */
#[Layout('components.layouts.app')]
class WijzigingenOverzicht extends Component
{
    private const BLOK = 'wijzigingsbeheer';

    // Een register dat opent op de volledige historie wordt niet gebruikt;
    // zelfde regel als bij taken.
    #[Url]
    public string $filterStatus = 'lopend';

    #[Url]
    public string $filterSoort = '';

    #[Url]
    public string $filterZwaarte = '';

    public bool $toontFormulier = false;

    public string $titel = '';

    public string $soort = 'leveranciersrelease';

    public string $leverancierId = '';

    public ?string $aangekondigdOp = null;

    public string $externeReferentie = '';

    public string $impactToelichting = '';

    public function mount(): void
    {
        $this->aangekondigdOp = now()->format('Y-m-d');
    }

    public function magMelden(): bool
    {
        return Gate::allows('heeft-niveau', [self::BLOK, 'uitvoeren']);
    }

    public function nieuweWijziging(): void
    {
        abort_unless($this->magMelden(), 403);

        $this->reset(['titel', 'leverancierId', 'externeReferentie', 'impactToelichting']);
        $this->soort = 'leveranciersrelease';
        $this->aangekondigdOp = now()->format('Y-m-d');
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    /**
     * Aanmelden mag iedereen met `uitvoeren` — in de praktijk de
     * applicatiebeheerder die de aankondiging van de leverancier binnenkrijgt.
     * De CISO kiest daarna het sjabloon en de datum; dat is het in behandeling
     * nemen op het detailscherm.
     */
    public function opslaan(): void
    {
        abort_unless($this->magMelden(), 403);

        $gevalideerd = $this->validate([
            'titel' => ['required', 'string', 'max:255'],
            'soort' => ['required', 'in:'.implode(',', self::SOORTEN)],
            'leverancierId' => ['nullable', 'exists:leveranciers,id'],
            'aangekondigdOp' => ['nullable', 'date'],
            'externeReferentie' => ['nullable', 'string', 'max:255'],
            'impactToelichting' => ['nullable', 'string'],
        ], attributes: ['titel' => 'titel', 'soort' => 'soort']);

        $wijziging = Wijziging::create([
            'titel' => $gevalideerd['titel'],
            'soort' => $gevalideerd['soort'],
            'leverancier_id' => $gevalideerd['leverancierId'] ?: null,
            'aangemeld_door_id' => auth()->id(),
            'aangekondigd_op' => $gevalideerd['aangekondigdOp'] ?: null,
            'externe_referentie' => $gevalideerd['externeReferentie'] ?: null,
            'impact_toelichting' => $gevalideerd['impactToelichting'] ?: null,
            'status' => 'aangemeld',
        ]);

        $this->toontFormulier = false;
        $this->redirectRoute('wijzigingen.detail', $wijziging, navigate: true);
    }

    /** @var list<string> */
    public const SOORTEN = ['leveranciersrelease', 'configuratie', 'infrastructuur', 'ingebruikname', 'afvoer'];

    public function render()
    {
        $wijzigingen = Wijziging::query()
            ->with(['leverancier', 'systemen', 'sjabloon'])
            ->when($this->filterStatus === 'lopend',
                fn (Builder $q) => $q->whereIn('status', Wijziging::LOPEND))
            ->when(! in_array($this->filterStatus, ['', 'lopend'], true),
                fn (Builder $q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSoort !== '', fn (Builder $q) => $q->where('soort', $this->filterSoort))
            ->when($this->filterZwaarte !== '', fn (Builder $q) => $q->where('zwaarte', $this->filterZwaarte))
            ->orderByRaw('gepland_op is null')
            ->orderBy('gepland_op')
            ->get();

        return view('livewire.wijzigingen-overzicht', [
            'wijzigingen' => $wijzigingen,
            'voortgang' => $this->voortgang($wijzigingen),
            'leveranciers' => Leverancier::orderBy('naam')->pluck('naam', 'id'),
            'systemen' => Systeem::inGebruik()->orderBy('naam')->pluck('naam', 'id'),
            // Hoort nul te zijn: uitvoeren zonder vangnet is precies wat
            // A.8.32 f) verbiedt.
            'zonderTerugvalplan' => Wijziging::query()->uitgevoerdZonderTerugvalplan()->count(),
            'spoedZonderGoedkeuring' => $this->spoedZonderGoedkeuring(),
            // Systemen die buiten dit register om zijn uitgefaseerd. Blok 3 kent
            // blok 15 niet, dus de afvoer daar maakt geen dossier aan; dit is de
            // plek waar dat gat zichtbaar wordt.
            'afgevoerdZonderDossier' => Systeem::query()->afgevoerdZonderDossier()->get(),
        ]);
    }

    /**
     * Voltooide en totale stappen per dossier, voor de kolom "stap 3 van 5".
     * Eén query in plaats van een telling per rij.
     *
     * @param  Collection<int, Wijziging>  $wijzigingen
     * @return array<int, array{klaar: int, totaal: int}>
     */
    private function voortgang($wijzigingen): array
    {
        if ($wijzigingen->isEmpty()) {
            return [];
        }

        return Taak::query()
            ->where('gekoppeld_entiteit_type', 'wijziging')
            ->whereIn('gekoppeld_entiteit_id', $wijzigingen->pluck('id'))
            ->whereNotNull('volgorde')
            ->get(['gekoppeld_entiteit_id', 'status'])
            ->groupBy('gekoppeld_entiteit_id')
            ->map(fn ($rijen) => [
                'klaar' => $rijen->where('status', 'voltooid')->count(),
                'totaal' => $rijen->count(),
            ])
            ->all();
    }

    /**
     * Spoedwijzigingen waarvan de goedkeuring achteraf nog openstaat. De
     * spoedroute is legitiem (A.8.32 f), het overslaan van de goedkeuring niet.
     */
    private function spoedZonderGoedkeuring(): int
    {
        return Wijziging::query()
            ->where('zwaarte', 'spoed')
            ->whereIn('status', Wijziging::LOPEND)
            ->whereExists(fn ($q) => $q->from('taken')
                ->whereColumn('taken.gekoppeld_entiteit_id', 'wijzigingen.id')
                ->where('taken.gekoppeld_entiteit_type', 'wijziging')
                ->where('taken.vraagt_uitkomst', true)
                ->whereNull('taken.uitkomst'))
            ->count();
    }
}
