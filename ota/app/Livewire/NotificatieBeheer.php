<?php

namespace App\Livewire;

use App\Models\Notificatie;
use App\Models\Notificatieregel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De notificatieregels (welke gebeurtenis mailt wie, aan/uit) plus de recente
 * verzendlog als gezondheidsbeeld (implementatie/14 §9). CISO muteert; de
 * Auditor leest.
 */
#[Layout('components.layouts.app')]
class NotificatieBeheer extends Component
{
    /**
     * De event-types die de code daadwerkelijk uitzendt (§5), als helptekst bij
     * het scherm. Een regel met een ander type is geen fout — hij vuurt alleen
     * nooit. `review_termijn_verstreken` staat erbij: de haak is er, de uitzender
     * komt uit blok 13.
     *
     * @var array<string, string>
     */
    private const BEKENDE_TYPES = [
        'incident_gemeld' => 'Een nieuw incident is gemeld.',
        'taak_geescaleerd' => 'Een verstreken taak is naar escalatieniveau 2 getild.',
        'training_verloopt' => 'Een verplichte training verloopt of staat open (naar de betrokkene).',
        'review_termijn_verstreken' => 'De termijn voor een managementreview is verstreken.',
        'tweefactor_deadline' => 'De termijn om de tweede factor in te stellen loopt af of is verstreken (naar de betrokkene).',
        'stap_actueel' => 'Een stap in een reeks is aan de beurt (naar de eigenaar van de stap).',
    ];

    public bool $toontFormulier = false;

    public ?int $regelId = null;

    public string $gebeurtenisType = '';

    public string $ontvangerRol = '';

    public bool $actief = true;

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['notificatie-integratielaag', 'muteren']);
    }

    private function vereisMuteren(): void
    {
        abort_unless($this->magMuteren(), 403);
    }

    public function nieuw(): void
    {
        $this->vereisMuteren();
        $this->reset(['regelId', 'gebeurtenisType', 'ontvangerRol', 'actief']);
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $regelId): void
    {
        $this->vereisMuteren();
        $regel = Notificatieregel::findOrFail($regelId);
        $this->regelId = $regel->id;
        $this->gebeurtenisType = $regel->gebeurtenis_type;
        $this->ontvangerRol = $regel->ontvanger_rol ?? '';
        $this->actief = $regel->actief;
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function slaOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'gebeurtenisType' => [
                'required', 'string', 'max:255',
                // Geen exacte dubbele regels (het unieke paar uit de migratie).
                Rule::unique('notificatieregels', 'gebeurtenis_type')
                    ->where('ontvanger_rol', $this->ontvangerRol === '' ? null : $this->ontvangerRol)
                    ->ignore($this->regelId),
            ],
            'ontvangerRol' => ['nullable', 'string', 'max:255'],
            'actief' => ['boolean'],
        ], attributes: [
            'gebeurtenisType' => 'gebeurtenis-type',
            'ontvangerRol' => 'ontvanger-rol',
        ]);

        Notificatieregel::updateOrCreate(
            ['id' => $this->regelId],
            [
                'gebeurtenis_type' => $gevalideerd['gebeurtenisType'],
                // Leeg = de betrokkene uit de gebeurtenis (§3).
                'ontvanger_rol' => $this->ontvangerRol === '' ? null : $this->ontvangerRol,
                'actief' => $this->actief,
            ],
        );

        $this->toontFormulier = false;
        session()->flash('melding', 'Notificatieregel opgeslagen.');
    }

    public function schakel(int $regelId): void
    {
        $this->vereisMuteren();
        $regel = Notificatieregel::findOrFail($regelId);
        $regel->update(['actief' => ! $regel->actief]);
    }

    public function render()
    {
        return view('livewire.notificatie-beheer', [
            'regels' => Notificatieregel::orderBy('gebeurtenis_type')->orderBy('ontvanger_rol')->get(),
            'bekendeTypes' => self::BEKENDE_TYPES,
            'log' => Notificatie::query()
                ->with('gebruiker')
                ->latest('gegenereerd_op')
                ->limit(50)
                ->get(),
            'aantalMislukt' => Notificatie::where('resultaat', 'fout')->count(),
        ]);
    }
}
