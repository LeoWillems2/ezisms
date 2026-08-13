<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.audits-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Audits</flux:heading>
            <flux:subheading>
                Interne audits (§9.2) en de externe certificeringsaudit: plannen, rondes en bevindingen.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwPlan">Nieuw auditplan</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Rapportagesignalen (§11). --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Open bevindingen</flux:text>
            <flux:heading size="lg">{{ $openTotaal }}</flux:heading>
            @if ($openTotaal > 0)
                <div class="mt-1 flex flex-wrap gap-1">
                    @foreach ($openPerType as $type => $aantal)
                        <flux:badge size="sm" color="{{ str_contains($type, 'major') ? 'red' : (str_contains($type, 'minor') ? 'amber' : 'zinc') }}">
                            {{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $aantal }}
                        </flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Sinds laatste interne audit</flux:text>
            <flux:heading size="lg">
                {{ $dagenSindsInterneAudit === null ? 'nog geen' : $dagenSindsInterneAudit.' dagen' }}
            </flux:heading>
            @if ($dagenSindsInterneAudit === null)
                <flux:text class="text-xs">Er is nog geen interne audit afgerond.</flux:text>
            @endif
        </div>
    </div>

    @forelse ($plannen as $plan)
        <div>
            <div class="mb-2 flex items-center justify-between gap-4">
                <flux:heading size="lg">
                    Auditplan {{ $plan->jaar }}
                    <flux:badge size="sm" :color="$plan->status === 'vastgesteld' ? 'green' : 'zinc'">
                        {{ ucfirst($plan->status) }}
                    </flux:badge>
                </flux:heading>

                @if ($this->magMuteren())
                    <div class="flex gap-1">
                        @if ($plan->status === 'concept')
                            <flux:button size="sm" variant="ghost" icon="check-badge"
                                wire:click="stelVast({{ $plan->id }})"
                                wire:confirm="Auditplan {{ $plan->jaar }} vaststellen? Een vastgesteld plan is definitief en kan niet terug naar concept.">Vaststellen</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="plus"
                            wire:click="nieuweRonde({{ $plan->id }})">Nieuwe ronde</flux:button>
                    </div>
                @endif
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column>Gepland</flux:table.column>
                    <flux:table.column>Uitvoerder</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($plan->rondes as $ronde)
                        <flux:table.row wire:key="ronde-{{ $ronde->id }}">
                            <flux:table.cell variant="strong">
                                {{ $ronde->typeLabel() }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $ronde->gepland_op?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($ronde->isIntern())
                                    {{ $ronde->auditor?->naam ?? '—' }}
                                @else
                                    {{ $ronde->extern_auditor_naam ?? '—' }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $kleur = match ($ronde->status) {
                                        'afgerond' => 'green',
                                        'in_uitvoering' => 'blue',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$kleur">
                                    {{ ucfirst(str_replace('_', ' ', $ronde->status)) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="arrow-right"
                                    :href="route('audits.ronde', $ronde)" wire:navigate>Openen</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5"><flux:text>Nog geen rondes in dit plan.</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <flux:text>Er zijn nog geen auditplannen. Maak er een aan om te beginnen.</flux:text>
    @endforelse

    {{-- Plan-modal --}}
    <flux:modal wire:model.self="toontPlanFormulier" class="md:w-96">
        <form wire:submit="slaPlanOp" class="space-y-6">
            <flux:heading size="lg">Nieuw auditplan</flux:heading>
            <flux:input wire:model="jaar" type="number" label="Jaar" required
                description="Eén auditplan per jaar." />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontPlanFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Aanmaken</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Ronde-modal --}}
    <flux:modal wire:model.self="toontRondeFormulier" class="md:w-[30rem]">
        <form wire:submit="slaRondeOp" class="space-y-6">
            <div>
                <flux:heading size="lg">Nieuwe auditronde</flux:heading>
                <flux:subheading>Scope, uitvoerder en bevindingen leg je hierna in het rondedossier vast.</flux:subheading>
            </div>
            <flux:select wire:model="rondeType" label="Type" required>
                @foreach ($types as $type)
                    <flux:select.option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="rondeGeplandOp" type="date" label="Geplande datum" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontRondeFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Aanmaken &amp; openen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
