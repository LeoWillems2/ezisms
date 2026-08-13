<?php

namespace App\Livewire;

use App\Models\Reviewsessie;
use App\Models\Verbeteractie;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reviewsessies met de rapportagesignalen (§11): loopt de reviewcyclus op schema,
 * en staan er nog verbeteracties open uit eerdere reviews.
 */
#[Layout('components.layouts.app')]
class ManagementReviewOverzicht extends Component
{
    /** Jaarlijkse review — §9.3 "op geplande tijdstippen"; termijn is een eigen keuze. */
    private const INTERVAL_MAANDEN = 12;

    public bool $toontFormulier = false;

    public string $datum = '';

    public string $deelnemers = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['management-review-verbetercyclus', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuweReview(): void
    {
        $this->vereisMuteren();
        $this->reset(['deelnemers']);
        $this->resetValidation();
        $this->datum = now()->format('Y-m-d');
        $this->toontFormulier = true;
    }

    public function slaOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'datum' => ['required', 'date'],
            'deelnemers' => ['nullable', 'string'],
        ], attributes: ['datum' => 'datum']);

        $sessie = Reviewsessie::create([
            'datum' => $gevalideerd['datum'],
            'deelnemers' => $gevalideerd['deelnemers'] ?: null,
        ]);

        $this->toontFormulier = false;
        $this->redirectRoute('management-review.detail', $sessie, navigate: true);
    }

    public function render()
    {
        $sessies = Reviewsessie::query()
            ->withCount(['agendapunten', 'besluiten'])
            ->orderByDesc('datum')
            ->get();

        $laatsteGehouden = Reviewsessie::where('status', 'gehouden')
            ->orderByDesc('datum')
            ->first();

        return view('livewire.management-review-overzicht', [
            'sessies' => $sessies,
            'laatsteGehouden' => $laatsteGehouden,
            // Achterstallig: nog nooit gehouden, of de laatste ligt buiten het interval.
            'reviewAchterstallig' => $laatsteGehouden === null
                || $laatsteGehouden->datum->lt(now()->subMonths(self::INTERVAL_MAANDEN)),
            'openVerbeteracties' => Verbeteractie::where('status', 'open')->count(),
            'verstrekenVerbeteracties' => Verbeteractie::where('status', 'open')
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now())
                ->count(),
        ]);
    }
}
