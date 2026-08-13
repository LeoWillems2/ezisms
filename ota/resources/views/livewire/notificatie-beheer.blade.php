<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.notificatie-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Notificatieregels</flux:heading>
            <flux:subheading>
                Welke gebeurtenis mailt wie. Een regel uitzetten is een bewuste keuze en wordt vastgelegd in de audit trail.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuw">Nieuwe regel</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Helptekst: de types die de code daadwerkelijk uitzendt (§5). --}}
    <flux:callout icon="information-circle">
        <flux:callout.heading>Herkende gebeurtenis-types</flux:callout.heading>
        <flux:callout.text>
            <ul class="mt-1 space-y-1">
                @foreach ($bekendeTypes as $type => $uitleg)
                    <li><code>{{ $type }}</code> — {{ $uitleg }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm">
                Een regel met een ander type is geen fout; hij vuurt alleen nooit.
            </p>
        </flux:callout.text>
    </flux:callout>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Gebeurtenis</flux:table.column>
            <flux:table.column>Ontvanger</flux:table.column>
            <flux:table.column>Actief</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($regels as $regel)
                <flux:table.row wire:key="regel-{{ $regel->id }}">
                    <flux:table.cell variant="strong"><code>{{ $regel->gebeurtenis_type }}</code></flux:table.cell>
                    <flux:table.cell>
                        {{ $regel->ontvanger_rol ?? 'Betrokkene uit de gebeurtenis' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$regel->actief ? 'green' : 'zinc'">
                            {{ $regel->actief ? 'Aan' : 'Uit' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($this->magMuteren())
                            <flux:button size="sm" variant="ghost"
                                icon="{{ $regel->actief ? 'pause' : 'play' }}"
                                wire:click="schakel({{ $regel->id }})">
                                {{ $regel->actief ? 'Uitzetten' : 'Aanzetten' }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                wire:click="bewerk({{ $regel->id }})">Bewerken</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4"><flux:text>Nog geen notificatieregels.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Gezondheidsbeeld: de recente verzendlog (§9/§11). --}}
    <div>
        <div class="mb-2 flex items-center gap-2">
            <flux:heading size="lg">Recente verzendingen</flux:heading>
            @if ($aantalMislukt > 0)
                <flux:badge size="sm" color="red">{{ $aantalMislukt }} mislukt (totaal)</flux:badge>
            @endif
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Gegenereerd</flux:table.column>
                <flux:table.column>Gebeurtenis</flux:table.column>
                <flux:table.column>Ontvanger</flux:table.column>
                <flux:table.column>Resultaat</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($log as $regel)
                    <flux:table.row wire:key="log-{{ $regel->id }}">
                        <flux:table.cell>{{ $regel->gegenereerd_op?->lokaal()->format('d-m-Y H:i') }}</flux:table.cell>
                        <flux:table.cell><code>{{ $regel->gebeurtenis_type }}</code></flux:table.cell>
                        <flux:table.cell>{{ $regel->gebruiker?->naam ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$regel->resultaat === 'succes' ? 'green' : 'red'">
                                {{ ucfirst($regel->resultaat) }}
                            </flux:badge>
                            @if ($regel->resultaat === 'fout' && $regel->fout)
                                <flux:text class="text-xs">{{ $regel->fout }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4"><flux:text>Nog niets verzonden.</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Regel-modal --}}
    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="slaOp" class="space-y-6">
            <flux:heading size="lg">{{ $regelId ? 'Regel bewerken' : 'Nieuwe regel' }}</flux:heading>
            <flux:input wire:model="gebeurtenisType" label="Gebeurtenis-type" required
                description="Vrije tekst; kies bij voorkeur een van de herkende types hierboven." />
            <flux:input wire:model="ontvangerRol" label="Ontvanger-rol"
                description="Een rolnaam (bijv. CISO). Leeg = de betrokkene uit de gebeurtenis." />
            <flux:switch wire:model="actief" label="Actief" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
