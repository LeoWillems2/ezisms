<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">Leveranciers &amp; derdenrisico</flux:heading>
            <flux:subheading>
                Leveranciers met een securityrelevante rol, hun risiconiveau en de
                herbeoordelingscyclus ({{ $norm->bijlage }} 5.19&ndash;5.23).
            </flux:subheading>
        </div>
        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweLeverancier">Nieuwe leverancier</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Rapportagesignalen (§11): niet blokkerend, wel zichtbaar. --}}
    @if ($verstrekenBeoordeling > 0)
        <flux:callout variant="warning" icon="clock"
            heading="{{ $verstrekenBeoordeling }} leverancier(s) met een verstreken herbeoordeling" />
    @endif
    @if ($hoogRisicoZonderAudit > 0)
        <flux:callout variant="danger" icon="exclamation-triangle"
            heading="{{ $hoogRisicoZonderAudit }} hoog-risicoleverancier(s) zonder aantoonbaar recht op audit">
            <flux:callout.text>
                Hoog risico, maar geen aanwezige recht-op-audit-clausule én geen geldig eigen
                {{ $norm->leverancierscertificaat }}-certificaat.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-56">
            <flux:select.option value="">Alle statussen</flux:select.option>
            @foreach ($statussen as $status)
                <flux:select.option value="{{ $status }}">{{ ucfirst($status) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterNiveau" label="Risiconiveau" class="max-w-56">
            <flux:select.option value="">Alle niveaus</flux:select.option>
            @foreach ($niveaus as $niveau)
                <flux:select.option value="{{ $niveau }}">{{ ucfirst($niveau) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Risiconiveau</flux:table.column>
            <flux:table.column>Volgende beoordeling</flux:table.column>
            <flux:table.column align="end">Diensten</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($leveranciers as $leverancier)
                <flux:table.row wire:key="leverancier-{{ $leverancier->id }}">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('leveranciers.detail', $leverancier)" wire:navigate>
                            {{ $leverancier->naam }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($leverancier->status) {
                                'actief' => 'green',
                                'beeindigd' => 'zinc',
                                default => 'amber',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">{{ ucfirst($leverancier->status) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($leverancier->risiconiveau)
                            @php
                                $niveauKleur = match ($leverancier->risiconiveau) {
                                    'hoog' => 'red',
                                    'midden' => 'amber',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$niveauKleur">{{ ucfirst($leverancier->risiconiveau) }}</flux:badge>
                        @else
                            <flux:text>&mdash;</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @php $volgende = $leverancier->volgendeBeoordelingGepland(); @endphp
                        @if ($volgende)
                            <span @class([
                                'text-red-600 dark:text-red-400 font-medium' => $volgende->isPast(),
                            ])>{{ $volgende->format('d-m-Y') }}</span>
                        @else
                            <flux:text>&mdash;</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $leverancier->diensten_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>Geen leveranciers met deze filters.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Toevoegformulier --}}
    <flux:modal wire:model.self="toontFormulier" class="md:w-96">
        <form wire:submit="opslaan" class="space-y-6">
            <flux:heading size="lg">Nieuwe leverancier</flux:heading>

            <flux:input wire:model="naam" label="Naam" required />

            <flux:select wire:model="risiconiveau" label="Risiconiveau">
                <flux:select.option value="">— nog niet bepaald —</flux:select.option>
                <flux:select.option value="laag">Laag</flux:select.option>
                <flux:select.option value="midden">Midden</flux:select.option>
                <flux:select.option value="hoog">Hoog</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
