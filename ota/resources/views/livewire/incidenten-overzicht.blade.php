<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Incidenten</flux:heading>
            <flux:subheading>
                Beveiligingsincidenten en meldingen (A.5.24&ndash;5.28). Melden kan iedereen.
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="nieuwIncident">Incident melden</flux:button>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @unless ($this->magAllesZien())
        <flux:callout icon="information-circle"
            heading="U ziet hier uw eigen meldingen. De CISO ziet ze allemaal." />
    @endunless

    <div class="flex flex-wrap items-end gap-4">
        <x-keuzelijst wire:model.live="filterStatus" label="Status" class="max-w-56"
            leeg="Alle statussen"
            :opties="['gemeld' => 'Gemeld', 'in_onderzoek' => 'In onderzoek', 'opgelost' => 'Opgelost', 'gesloten' => 'Gesloten']" />

        <x-keuzelijst wire:model.live="filterErnst" label="Ernst" class="max-w-56"
            leeg="Alle niveaus"
            :opties="['laag' => 'Laag', 'midden' => 'Midden', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek']" />
    </div>

    @if ($toontFormulier)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">Incident melden</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="titel" label="Titel" required />

                <x-keuzelijst wire:model="ernst" label="Ernst" required
                    :opties="['laag' => 'Laag', 'midden' => 'Midden', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek']" />

                <flux:textarea wire:model="omschrijving" label="Wat is er gebeurd?" class="md:col-span-2" />
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="melden">Melden</flux:button>
                <flux:button variant="ghost" wire:click="sluitFormulier">Annuleren</flux:button>
            </div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Ernst</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Gemeld door</flux:table.column>
            <flux:table.column>Gemeld op</flux:table.column>
            <flux:table.column>Afwijking</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($incidenten as $incident)
                <flux:table.row wire:key="inc-{{ $incident->id }}">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('incidenten.detail', $incident)" wire:navigate>
                            {{ $incident->titel }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $ernstKleur = match ($incident->ernst) {
                                'kritiek' => 'red',
                                'hoog' => 'amber',
                                'midden' => 'zinc',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$ernstKleur">{{ ucfirst($incident->ernst) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $incident->melder?->naam ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $incident->gemeld_op->lokaal()->format('d-m-Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($incident->afwijkingen->isEmpty())
                            <flux:text>—</flux:text>
                        @else
                            <flux:badge size="sm" color="blue">{{ $incident->afwijkingen->count() }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text>Geen incidenten gevonden.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
