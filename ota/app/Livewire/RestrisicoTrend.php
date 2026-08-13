<?php

namespace App\Livewire;

use App\Models\Maatregel;
use App\Models\RestrisicoSnapshot;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Jaartrend van het restrisico per control (plan 04c §3). Toont de onveranderlijke
 * jaarsnapshots (isms:leg-restrisico-vast) gegroepeerd per control, oplopend per
 * peiljaar — zodat de ontwikkeling over de jaren zichtbaar wordt.
 *
 * De cijfers zijn bevroren; alléén de `toelichting` mag achteraf bewerkt worden,
 * met de reden van de beweging (gemitigeerd / herscoord / risico afgevoerd). Dat
 * is de enige uitzondering op de onveranderlijkheid, en vraagt muteer-recht op
 * het blok (CISO); de Auditor leest mee.
 */
#[Layout('components.layouts.app')]
class RestrisicoTrend extends Component
{
    public ?int $bewerktSnapshotId = null;

    public bool $toontFormulier = false;

    public string $toelichting = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['risico-soa', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function bewerk(int $snapshotId): void
    {
        $this->vereisMuteren();

        $snapshot = RestrisicoSnapshot::findOrFail($snapshotId);

        $this->bewerktSnapshotId = $snapshot->id;
        $this->toelichting = $snapshot->toelichting ?? '';
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function opslaan(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'toelichting' => ['nullable', 'string', 'max:1000'],
        ]);

        // Bewust alléén de toelichting: de cijfers blijven bevroren.
        RestrisicoSnapshot::findOrFail($this->bewerktSnapshotId)
            ->update(['toelichting' => $this->toelichting ?: null]);

        $this->sluitFormulier();
        session()->flash('melding', 'Toelichting bijgewerkt.');
    }

    public function sluitFormulier(): void
    {
        $this->toontFormulier = false;
        $this->bewerktSnapshotId = null;
    }

    public function render()
    {
        $snapshots = RestrisicoSnapshot::query()
            ->with(['soaRegel.maatregel', 'criteriaVersie'])
            ->orderBy('peiljaar')
            ->get();

        // Groeperen per control; alleen controls met snapshots verschijnen. Binnen
        // een control oplopend op peiljaar, en de controls onderling numeriek op
        // hun Annex A-referentie (A.5.9 vóór A.5.10) — zelfde ordening als de SoA.
        $perControl = $snapshots
            ->groupBy('soa_regel_id')
            ->map(fn ($rijen) => $rijen->sortBy('peiljaar')->values())
            ->sortBy(fn ($rijen) => $this->annexSorteersleutel($rijen->first()->soaRegel->maatregel))
            ->values();

        return view('livewire.restrisico-trend', [
            'perControl' => $perControl,
            'aantalSnapshots' => $snapshots->count(),
            // Vallen de peiljaren onder verschillende risicocriteria, dan is de
            // trend geen zuivere vergelijking: een restrisico van 12 betekent
            // iets anders zodra de betekenis van impact 4 opnieuw is
            // vastgesteld. Melden en niet verbergen (04g §8.3).
            'criteriaversiesInBeeld' => $snapshots
                ->pluck('criteriaVersie')
                ->filter()
                ->unique('id')
                ->sortBy('versienummer')
                ->values(),
        ]);
    }

    /** Numerieke sorteersleutel voor '5.10' vs '5.9' — zoals in SoaOverzicht. */
    private function annexSorteersleutel(Maatregel $maatregel): int
    {
        [$hoofdstuk, $sub] = array_pad(explode('.', $maatregel->annex_a_referentie, 2), 2, '0');

        return (int) $hoofdstuk * 1000 + (int) $sub;
    }
}
