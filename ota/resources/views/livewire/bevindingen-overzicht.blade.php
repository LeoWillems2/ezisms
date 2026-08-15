<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.audits-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Bevindingen</flux:heading>
            <flux:subheading>
                Alle auditbevindingen over de rondes heen. Vastleggen, opvolgen en sluiten gebeurt in het rondedossier.
            </flux:subheading>
        </div>

        @include('partials.kopieknop')
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <x-keuzelijst wire:model.live="filterType" label="Type" class="max-w-64"
            leeg="Alle typen" :opties="$typeOpties" />

        <x-keuzelijst wire:model.live="filterStatus" label="Status" class="max-w-64"
            leeg="Alle statussen" :opties="$statusOpties" />

        <x-keuzelijst wire:model.live="filterRonde" label="Auditronde" class="max-w-72"
            leeg="Alle rondes" :opties="$rondeOpties" />
    </div>

    {{-- Het getoonde aantal naast het totaal: dit scherm filtert standaard op
         "openstaand", en dan mag een lege lijst nooit als "er is niets" lezen. --}}
    <flux:text variant="subtle">
        {{ $bevindingen->count() }} van {{ $totaalRijen }} {{ $totaalRijen === 1 ? 'bevinding' : 'bevindingen' }}
    </flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Omschrijving</flux:table.column>
            <flux:table.column>Maatregel</flux:table.column>
            <flux:table.column>Auditronde</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($bevindingen as $bevinding)
                <flux:table.row wire:key="bevinding-{{ $bevinding->id }}">
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ str_contains($bevinding->type, 'major') ? 'red' : (str_contains($bevinding->type, 'minor') ? 'amber' : 'zinc') }}">
                            {{ $this->typeLabel($bevinding->type) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ Str::limit($bevinding->omschrijving, 160) }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $bevinding->maatregel ? 'A.'.$bevinding->maatregel->annex_a_referentie : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $bevinding->auditronde?->auditOmschrijving() ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $kleur = match ($bevinding->status) {
                                'gesloten' => 'green',
                                'non_conformiteit_gestart' => 'blue',
                                default => 'amber',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$kleur">{{ $this->statusLabel($bevinding->status) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @if ($bevinding->afwijking)
                                <flux:button size="sm" variant="ghost" icon="exclamation-triangle"
                                    :href="route('afwijkingen.detail', $bevinding->afwijking)" wire:navigate>Afwijking</flux:button>
                            @endif
                            @if ($bevinding->auditronde)
                                <flux:button size="sm" variant="ghost" icon="arrow-right"
                                    :href="route('audits.ronde', $bevinding->auditronde)" wire:navigate>Ronde</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text>
                            @if ($totaalRijen === 0)
                                Er zijn nog geen bevindingen vastgelegd.
                            @else
                                Geen bevindingen die aan dit filter voldoen.
                            @endif
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
