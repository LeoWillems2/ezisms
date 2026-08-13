<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.notificatie-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Integraties</flux:heading>
            <flux:subheading>
                Het register van externe koppelingen: dát een koppeling bestaat en of de laatste synchronisatie lukte.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuw">Nieuwe adapter</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Voorkomt de misvatting dat hier daadwerkelijk gesynchroniseerd wordt. --}}
    <flux:callout icon="information-circle">
        <flux:callout.heading>Handmatig register — er wordt hier niets gesynchroniseerd</flux:callout.heading>
        <flux:callout.text>
            Dit scherm houdt alleen bij <em>dát</em> een koppeling bestaat en of de laatste synchronisatie lukte.
            De synchronisatie zelf gebeurt buiten dit systeem; met &ldquo;Sync vastleggen&rdquo; noteert u het
            resultaat (tijdstip, gelukt of mislukt, aantal verwerkte records) achteraf met de hand.
        </flux:callout.text>
    </flux:callout>

    @forelse ($adapters as $adapter)
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="mb-2 flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">
                        {{ $adapter->naam }}
                        @php
                            $statusKleur = match ($adapter->status) {
                                'actief' => 'green',
                                'inactief' => 'amber',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">
                            {{ ucfirst(str_replace('_', ' ', $adapter->status)) }}
                        </flux:badge>
                    </flux:heading>
                    <flux:text class="text-sm">
                        Type: {{ ucfirst($adapter->type) }} ·
                        Laatste sync: {{ $adapter->laatste_synchronisatie_op?->lokaal()->format('d-m-Y H:i') ?? 'nog geen' }}
                    </flux:text>
                </div>

                @if ($this->magMuteren())
                    <div class="flex gap-1">
                        <flux:button size="sm" variant="ghost" icon="arrow-path"
                            wire:click="nieuweSync({{ $adapter->id }})">Sync vastleggen</flux:button>
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            wire:click="bewerk({{ $adapter->id }})">Bewerken</flux:button>
                    </div>
                @endif
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Tijdstip</flux:table.column>
                    <flux:table.column>Resultaat</flux:table.column>
                    <flux:table.column align="end">Records</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($adapter->synchronisatieLogs as $log)
                        <flux:table.row wire:key="synclog-{{ $log->id }}">
                            <flux:table.cell>{{ $log->tijdstip?->lokaal()->format('d-m-Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$log->resultaat === 'succes' ? 'green' : 'red'">
                                    {{ ucfirst($log->resultaat) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $log->aantal_verwerkte_records }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3"><flux:text>Nog geen synchronisaties vastgelegd.</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <flux:text>Er zijn nog geen integratie-adapters geregistreerd.</flux:text>
    @endforelse

    {{-- Adapter-modal --}}
    <flux:modal wire:model.self="toontFormulier" class="md:w-[30rem]">
        <form wire:submit="slaOp" class="space-y-6">
            <flux:heading size="lg">{{ $adapterId ? 'Adapter bewerken' : 'Nieuwe adapter' }}</flux:heading>
            <flux:input wire:model="naam" label="Naam" required />
            <flux:select wire:model="type" label="Type" required>
                @foreach ($types as $t)
                    <flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="status" label="Status" required>
                @foreach ($statussen as $s)
                    <flux:select.option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Sync-modal --}}
    <flux:modal wire:model.self="toontSyncFormulier" class="md:w-96">
        <form wire:submit="legSyncVast" class="space-y-6">
            <flux:heading size="lg">Synchronisatie-resultaat vastleggen</flux:heading>
            <flux:select wire:model="syncResultaat" label="Resultaat" required>
                <flux:select.option value="succes">Succes</flux:select.option>
                <flux:select.option value="fout">Fout</flux:select.option>
            </flux:select>
            <flux:input wire:model="syncAantal" type="number" min="0" label="Aantal verwerkte records" required />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontSyncFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Vastleggen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
