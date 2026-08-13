<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Afwijkingen</flux:heading>
            <flux:subheading>
                Afwijkingen en corrigerende maatregelen (&sect;10.2), met effectiviteitstoets.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-start justify-end gap-3">
            @include('partials.kopieknop')

            @if ($this->magMuteren())
                <flux:button variant="primary" icon="plus" wire:click="nieuweAfwijking">Nieuwe afwijking</flux:button>
            @endif
        </div>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <x-keuzelijst wire:model.live="filterStatus" label="Status" class="max-w-56"
            leeg="Alle statussen"
            :opties="['open' => 'Open', 'analyse' => 'Analyse', 'actie_lopend' => 'Actie lopend', 'gesloten' => 'Gesloten']" />
    </div>

    @if ($toontFormulier)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">Nieuwe afwijking</flux:heading>

            <div class="grid gap-4">
                <flux:textarea wire:model="omschrijving" label="Wat wijkt af?" required />

                <x-keuzelijst wire:model="bron" label="Bron" class="max-w-72"
                    :opties="['interne_signalering' => 'Interne signalering', 'incident' => 'Incident']" />
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="opslaan">Opslaan</flux:button>
                <flux:button variant="ghost" wire:click="sluitFormulier">Annuleren</flux:button>
            </div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Omschrijving</flux:table.column>
            <flux:table.column>Bron</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Eigenaar</flux:table.column>
            <flux:table.column>Maatregelen</flux:table.column>
            <flux:table.column>Nog te toetsen</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($afwijkingen as $afwijking)
                {{-- Voltooide maatregelen zonder effectieve toets: precies het
                     gat waar §10.2 naar vraagt. De telling staat op het model,
                     omdat de schermkopie hetzelfde getal moet noemen. `null` =
                     geen maatregelen, en dat is iets anders dan nul. --}}
                @php $teToetsen = $afwijking->nogTeToetsen(); @endphp
                <flux:table.row wire:key="afw-{{ $afwijking->id }}">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('afwijkingen.detail', $afwijking)" wire:navigate>
                            {{ $afwijking->auditOmschrijving() }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst(str_replace('_', ' ', $afwijking->bron)) }}</flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($afwijking->status) {
                                'gesloten' => 'green',
                                'actie_lopend' => 'blue',
                                'analyse' => 'amber',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">
                            {{ ucfirst(str_replace('_', ' ', $afwijking->status)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $afwijking->eigenaar?->naam ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $afwijking->maatregelen_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($teToetsen === null)
                            {{-- Bewust "n.v.t." en niet 0: een nul leest hier als
                                 "de behandeling is rond", terwijl er juist nog
                                 geen corrigerende maatregel is vastgelegd. --}}
                            <flux:text>n.v.t.</flux:text>
                        @elseif ($teToetsen > 0)
                            <flux:badge size="sm" color="amber">{{ $teToetsen }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">0</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text>Geen afwijkingen gevonden.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
