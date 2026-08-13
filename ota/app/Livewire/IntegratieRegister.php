<?php

namespace App\Livewire;

use App\Models\IntegratieAdapter;
use App\Models\SynchronisatieLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het integratieregister: welke koppelingen bestaan, van welk type, en of de
 * laatste sync lukte (implementatie/14 §6/§9). Zonder echte sync-motor legt de
 * CISO adapters en sync-resultaten handmatig vast; de Auditor leest.
 */
#[Layout('components.layouts.app')]
class IntegratieRegister extends Component
{
    private const TYPES = ['identiteit', 'ticketing', 'scanning', 'overig'];

    private const STATUSSEN = ['niet_geconfigureerd', 'actief', 'inactief'];

    // Adapter-formulier.
    public bool $toontFormulier = false;

    public ?int $adapterId = null;

    public string $naam = '';

    public string $type = 'overig';

    public string $status = 'niet_geconfigureerd';

    // Sync-log-formulier.
    public bool $toontSyncFormulier = false;

    public ?int $syncAdapterId = null;

    public string $syncResultaat = 'succes';

    public string $syncAantal = '0';

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
        $this->reset(['adapterId', 'naam', 'type', 'status']);
        $this->type = 'overig';
        $this->status = 'niet_geconfigureerd';
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function bewerk(int $adapterId): void
    {
        $this->vereisMuteren();
        $adapter = IntegratieAdapter::findOrFail($adapterId);
        $this->adapterId = $adapter->id;
        $this->naam = $adapter->naam;
        $this->type = $adapter->type;
        $this->status = $adapter->status;
        $this->resetValidation();
        $this->toontFormulier = true;
    }

    public function slaOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'status' => ['required', Rule::in(self::STATUSSEN)],
        ], attributes: ['naam' => 'naam', 'type' => 'type', 'status' => 'status']);

        IntegratieAdapter::updateOrCreate(
            ['id' => $this->adapterId],
            $gevalideerd,
        );

        $this->toontFormulier = false;
        session()->flash('melding', 'Integratie-adapter opgeslagen.');
    }

    public function nieuweSync(int $adapterId): void
    {
        $this->vereisMuteren();
        $this->reset(['syncResultaat', 'syncAantal']);
        $this->syncAdapterId = $adapterId;
        $this->syncResultaat = 'succes';
        $this->syncAantal = '0';
        $this->resetValidation();
        $this->toontSyncFormulier = true;
    }

    public function legSyncVast(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'syncAdapterId' => ['required', Rule::exists('integratie_adapters', 'id')],
            'syncResultaat' => ['required', Rule::in(['succes', 'fout'])],
            'syncAantal' => ['required', 'integer', 'min:0'],
        ], attributes: [
            'syncResultaat' => 'resultaat',
            'syncAantal' => 'aantal verwerkte records',
        ]);

        $adapter = IntegratieAdapter::findOrFail($gevalideerd['syncAdapterId']);
        $tijdstip = now();

        SynchronisatieLog::create([
            'integratie_adapter_id' => $adapter->id,
            'tijdstip' => $tijdstip,
            'resultaat' => $gevalideerd['syncResultaat'],
            'aantal_verwerkte_records' => (int) $gevalideerd['syncAantal'],
        ]);

        // Het vastleggen van een sync-resultaat werkt de adapter bij (§6): het
        // tijdstip van de laatste sync, en — als de adapter nog niet actief was —
        // de status. Een geslaagde sync betekent dat de koppeling werkt.
        $adapter->update([
            'laatste_synchronisatie_op' => $tijdstip,
            'status' => $gevalideerd['syncResultaat'] === 'succes' && $adapter->status === 'niet_geconfigureerd'
                ? 'actief'
                : $adapter->status,
        ]);

        $this->toontSyncFormulier = false;
        session()->flash('melding', 'Synchronisatie-resultaat vastgelegd.');
    }

    public function render()
    {
        return view('livewire.integratie-register', [
            'adapters' => IntegratieAdapter::query()
                ->with(['synchronisatieLogs' => fn ($q) => $q->latest('tijdstip')->limit(5)])
                ->orderBy('naam')
                ->get(),
            'types' => self::TYPES,
            'statussen' => self::STATUSSEN,
        ]);
    }
}
