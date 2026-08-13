<?php

namespace App\Livewire;

use App\Models\Afwijking;
use App\Models\Auditobject;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\OrganisatieEenheid;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het rondedossier: planning (scope, uitvoerder, status) en de bevindingen.
 * De scheiding tussen `magPlannen`/`magUitvoeren` (administratie/status) en
 * `magBevindingBewerken` (de record-guard uit implementatie/11 §4) is hier de kern.
 */
#[Layout('components.layouts.app')]
class AuditrondeDetail extends Component
{
    private const TYPES = ['intern', 'intern_nulmeting', 'extern_certificering', 'extern_surveillance'];

    private const BEVINDING_TYPES = [
        'non_conformiteit_major', 'non_conformiteit_minor', 'observatie', 'verbeterkans',
    ];

    public Auditronde $auditronde;

    // Planning (administratief, alleen zolang 'gepland').
    public string $type = 'intern';

    public ?string $geplandOp = null;

    public ?int $auditorGebruikerId = null;

    public string $externAuditorNaam = '';

    /** @var array<int, int> geselecteerde organisatie-eenheden (organisatorische scope) */
    public array $scopeEenheden = [];

    /** @var array<int, int> geselecteerde auditobjecten (normatieve scope, plan 11b) */
    public array $scopeObjecten = [];

    // Bevinding-formulier.
    public bool $toontBevindingFormulier = false;

    public ?int $bewerktBevindingId = null;

    public string $bevindingType = 'observatie';

    public string $bevindingOmschrijving = '';

    public ?int $bevindingMaatregelId = null;

    public function mount(Auditronde $auditronde): void
    {
        $this->auditronde = $auditronde->load(['auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten']);
        $this->type = $auditronde->type;
        $this->geplandOp = $auditronde->gepland_op?->format('Y-m-d');
        $this->auditorGebruikerId = $auditronde->auditor_gebruiker_id;
        $this->externAuditorNaam = $auditronde->extern_auditor_naam ?? '';
        $this->scopeEenheden = $auditronde->organisatieEenheden->pluck('id')->all();
        $this->scopeObjecten = $auditronde->auditobjecten->pluck('id')->all();
    }

    // --- Autorisatie -------------------------------------------------------

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    /** Planning is bewerkbaar zolang de ronde nog niet is gestart. */
    public function magPlannen(): bool
    {
        return $this->magMuteren() && $this->auditronde->status === 'gepland';
    }

    public function magUitvoeren(): bool
    {
        return $this->auditronde->magUitvoerenDoor(auth()->user());
    }

    public function magBevindingBewerken(): bool
    {
        return $this->auditronde->magBevindingBewerkenDoor(auth()->user());
    }

    private function vereisPlannen(): void
    {
        abort_unless($this->magPlannen(), 403);
    }

    private function vereisUitvoeren(): void
    {
        abort_unless($this->magUitvoeren(), 403);
    }

    private function vereisBevindingBewerken(): void
    {
        abort_unless($this->magBevindingBewerken(), 403);
    }

    /** Opvolging (non-conformiteit starten, sluiten) is CISO-muteren (§4b). */
    private function vereisOpvolgen(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    // --- Planning ----------------------------------------------------------

    public function slaPlanningOp(): void
    {
        $this->vereisPlannen();

        $gevalideerd = $this->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'geplandOp' => ['nullable', 'date'],
            'auditorGebruikerId' => ['nullable', Rule::exists('gebruikers', 'id')],
            'externAuditorNaam' => ['nullable', 'string', 'max:255'],
            'scopeEenheden' => ['array'],
            'scopeEenheden.*' => [Rule::exists('organisatie_eenheden', 'id')],
            'scopeObjecten' => ['array'],
            'scopeObjecten.*' => [Rule::exists('auditobjecten', 'id')],
        ], attributes: [
            'type' => 'type',
            'geplandOp' => 'geplande datum',
            'auditorGebruikerId' => 'auditor',
            'externAuditorNaam' => 'externe auditor',
        ]);

        $intern = in_array($gevalideerd['type'], Auditronde::INTERNE_TYPEN, true);

        $this->auditronde->update([
            'type' => $gevalideerd['type'],
            'gepland_op' => $gevalideerd['geplandOp'] ?: null,
            // Elk type houdt alleen zijn eigen uitvoerdersveld; het andere wordt
            // leeggemaakt zodat er geen verweesde toewijzing blijft staan.
            'auditor_gebruiker_id' => $intern ? ($gevalideerd['auditorGebruikerId'] ?: null) : null,
            'extern_auditor_naam' => $intern ? null : ($gevalideerd['externAuditorNaam'] ?: null),
        ]);

        Koppeling::sync($this->auditronde->organisatieEenheden(), 'organisatie-eenheden', $this->scopeEenheden);
        Koppeling::sync($this->auditronde->auditobjecten(), 'auditobjecten', $this->scopeObjecten);
        $this->auditronde->refresh()->load(['auditplan', 'auditor', 'organisatieEenheden', 'auditobjecten']);

        session()->flash('melding', 'Planning bijgewerkt.');
    }

    /**
     * De dekkingsvlag omzetten (plan 11c fase 1). Bewust los van `magPlannen()`:
     * of een ronde meetelt is een planningsbeslissing die ook ná afronding nog
     * juist moet kunnen worden gezet — je ziet vaak pas achteraf dat een
     * her-audit de matrix zou vertekenen.
     *
     * Bewust `magMuteren()` en niet de record-guard: een auditor mag zijn eigen
     * ronde niet uit de dekkingsmatrix schrijven (plan 11c §9).
     */
    public function wisselDekkingsvlag(): void
    {
        abort_unless($this->magMuteren(), 403);

        $nieuw = ! $this->auditronde->telt_mee_voor_dekking;
        $this->auditronde->update(['telt_mee_voor_dekking' => $nieuw]);
        $this->auditronde->refresh();

        session()->flash('melding', $nieuw
            ? 'Ronde telt weer mee voor de dekkingsmatrix.'
            : 'Ronde telt niet mee voor de dekkingsmatrix; hij blijft wel volledig in het dossier.');
    }

    public function startUitvoering(): void
    {
        $this->vereisUitvoeren();
        if ($this->auditronde->status === 'gepland') {
            $this->auditronde->update(['status' => 'in_uitvoering']);
        }
    }

    public function rondAf(): void
    {
        $this->vereisUitvoeren();
        if ($this->auditronde->status === 'in_uitvoering') {
            // Afronden bevriest de bevindingen (§4a/§5) — eenrichtingsverkeer.
            $this->auditronde->update([
                'status' => 'afgerond',
                'uitgevoerd_op' => $this->auditronde->uitgevoerd_op ?? now()->toDateString(),
            ]);
        }
    }

    // --- Bevindingen -------------------------------------------------------

    public function nieuweBevinding(): void
    {
        $this->vereisBevindingBewerken();
        $this->reset(['bewerktBevindingId', 'bevindingType', 'bevindingOmschrijving', 'bevindingMaatregelId']);
        $this->bevindingType = 'observatie';
        $this->resetValidation();
        $this->toontBevindingFormulier = true;
    }

    public function bewerkBevinding(int $bevindingId): void
    {
        $this->vereisBevindingBewerken();
        $bevinding = $this->auditronde->bevindingen()->findOrFail($bevindingId);
        $this->bewerktBevindingId = $bevinding->id;
        $this->bevindingType = $bevinding->type;
        $this->bevindingOmschrijving = $bevinding->omschrijving;
        $this->bevindingMaatregelId = $bevinding->maatregel_id;
        $this->resetValidation();
        $this->toontBevindingFormulier = true;
    }

    public function sluitBevindingFormulier(): void
    {
        $this->toontBevindingFormulier = false;
    }

    public function slaBevindingOp(): void
    {
        $this->vereisBevindingBewerken();

        $gevalideerd = $this->validate([
            'bevindingType' => ['required', Rule::in(self::BEVINDING_TYPES)],
            'bevindingOmschrijving' => ['required', 'string'],
            'bevindingMaatregelId' => ['nullable', Rule::exists('maatregelen', 'id')],
        ], attributes: [
            'bevindingType' => 'type',
            'bevindingOmschrijving' => 'omschrijving',
            'bevindingMaatregelId' => 'maatregel',
        ]);

        $attributen = [
            'type' => $gevalideerd['bevindingType'],
            'omschrijving' => $gevalideerd['bevindingOmschrijving'],
            'maatregel_id' => $gevalideerd['bevindingMaatregelId'] ?: null,
        ];

        if ($this->bewerktBevindingId !== null) {
            // Alleen de inhoud; status/opvolging blijft ongemoeid.
            $this->auditronde->bevindingen()->findOrFail($this->bewerktBevindingId)->update($attributen);
        } else {
            $this->auditronde->bevindingen()->create($attributen);
        }

        $this->toontBevindingFormulier = false;
        $this->reset(['bewerktBevindingId', 'bevindingOmschrijving', 'bevindingMaatregelId']);
        session()->flash('melding', 'Bevinding opgeslagen.');
    }

    // --- Opvolging (blok 8) ------------------------------------------------

    public function opvolgenAlsNonConformiteit(int $bevindingId): void
    {
        $this->vereisOpvolgen();
        $bevinding = $this->auditronde->bevindingen()->with('afwijking')->findOrFail($bevindingId);

        // Alleen zinnig voor een non-conformiteit die er nog geen heeft.
        if (! $bevinding->isNonConformiteit() || $bevinding->afwijking !== null) {
            return;
        }

        $afwijking = Afwijking::create([
            'bron' => 'audit_bevinding',
            'bevinding_id' => $bevinding->id,
            'omschrijving' => $bevinding->omschrijving,
        ]);

        $bevinding->update(['status' => 'non_conformiteit_gestart']);

        $this->redirectRoute('afwijkingen.detail', $afwijking, navigate: true);
    }

    public function sluitBevinding(int $bevindingId): void
    {
        $this->vereisOpvolgen();
        $bevinding = $this->auditronde->bevindingen()->with('afwijking')->findOrFail($bevindingId);

        if ($bevinding->isGesloten()) {
            return;
        }

        // Volgordekwestie, geen rechtenkwestie: toon de reden (§6).
        $belemmering = $bevinding->belemmeringVoorSluiten();
        if ($belemmering !== null) {
            session()->flash('fout', $belemmering);

            return;
        }

        $bevinding->update([
            'status' => 'gesloten',
            'gesloten_op' => now()->toDateString(),
            'gesloten_door_id' => auth()->id(),
        ]);

        session()->flash('melding', 'Bevinding gesloten.');
    }

    public function render()
    {
        $bevindingen = $this->auditronde->bevindingen()
            ->with(['maatregel', 'afwijking'])
            ->orderBy('id')
            ->get();

        // Voor de normatieve scope: de aangevinkte controls apart van de rest,
        // zodat de view de gekozen bovenaan zet en de overige (90+) onder een
        // uitklap wegbergt. Splitsen op de opgeslagen scope; het herschikken
        // volgt bij de volgende render (na 'Planning opslaan').
        $auditobjecten = Auditobject::actief()->with('maatregel')
            ->orderBy('groep')->orderBy('volgorde')
            ->get()
            ->mapWithKeys(fn (Auditobject $o) => [$o->id => $o->refCode().' '.$o->omschrijving()]);
        $gekozenIds = array_map('intval', $this->scopeObjecten);

        return view('livewire.auditronde-detail', [
            'bevindingen' => $bevindingen,
            'types' => self::TYPES,
            'bevindingTypes' => self::BEVINDING_TYPES,
            'auditors' => $this->magPlannen()
                ? Gebruiker::where('status', 'actief')->orderBy('naam')->pluck('naam', 'id')->all()
                : [],
            'eenheden' => OrganisatieEenheid::orderBy('naam')->pluck('naam', 'id')->all(),
            'heeftObjecten' => $auditobjecten->isNotEmpty(),
            'gekozenObjecten' => $auditobjecten->filter(fn ($l, $id) => in_array($id, $gekozenIds, true))->all(),
            'overigeObjecten' => $auditobjecten->reject(fn ($l, $id) => in_array($id, $gekozenIds, true))->all(),
            'maatregelen' => Maatregel::orderBy('annex_a_referentie')
                ->get()
                ->mapWithKeys(fn (Maatregel $m) => [$m->id => 'A.'.$m->annex_a_referentie.' '.$m->naam])
                ->all(),
        ]);
    }
}
