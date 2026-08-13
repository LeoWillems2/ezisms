<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.assets-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Systemen</flux:heading>
            <flux:subheading>Systemen en diensten waarin assets worden verwerkt.</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwSysteem">
                Systeem toevoegen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($redundantieGaps > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $redundantieGaps }} kritiek systeem/systemen zonder aangetoonde redundantie">
            <flux:callout.text>
                Bij een hoge of bedrijfskritieke beschikbaarheidseis vraagt {{ $norm->bijlage }} 8.14 om
                passende redundantie. Leg per systeem vast of die er is.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div>
        <flux:switch wire:model.live="toonAfgevoerde" label="Toon afgevoerde systemen" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Hostingtype</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Beschikbaarheid</flux:table.column>
            <flux:table.column>Leverancier</flux:table.column>
            <flux:table.column>Gekoppelde assets</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($systemen as $systeem)
                <flux:table.row wire:key="systeem-{{ $systeem->id }}">
                    <flux:table.cell variant="strong">{{ $systeem->naam }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$systeem->hostingtype === 'extern' ? 'amber' : 'sky'">
                            {{ ucfirst($systeem->hostingtype) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($systeem->isAfgevoerd())
                            <flux:badge size="sm" color="zinc">
                                Afgevoerd{{ $systeem->afgevoerd_op ? ' · '.$systeem->afgevoerd_op->format('d-m-Y') : '' }}
                            </flux:badge>
                        @else
                            <flux:badge size="sm" color="green">In gebruik</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($systeem->beschikbaarheidseis)
                            @php
                                $eisKleur = match ($systeem->beschikbaarheidseis) {
                                    'bedrijfskritiek' => 'red',
                                    'hoog' => 'amber',
                                    'normaal' => 'sky',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$eisKleur">
                                {{ ucfirst(str_replace('_', ' ', $systeem->beschikbaarheidseis)) }}
                            </flux:badge>
                            @if ($systeem->heeftRedundantieGap())
                                <flux:badge size="sm" color="red" class="ml-1">geen redundantie</flux:badge>
                            @elseif ($systeem->redundant)
                                <flux:badge size="sm" color="green" class="ml-1">redundant</flux:badge>
                            @endif
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                    {{-- Leverancier komt pas met blok 4.7 (Leveranciers). --}}
                    <flux:table.cell>—</flux:table.cell>
                    <flux:table.cell>{{ $systeem->assets_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($this->magMuteren())
                            @if ($systeem->isAfgevoerd())
                                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                                    wire:click="heractiveren({{ $systeem->id }})">Heractiveren</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" wire:click="bewerk({{ $systeem->id }})">Bewerken</flux:button>
                                <flux:button size="sm" variant="ghost" icon="archive-box"
                                    wire:click="afvoeren({{ $systeem->id }})"
                                    wire:confirm="Dit systeem afvoeren? Bestaande assetkoppelingen blijven als historie behouden." >
                                    Afvoeren
                                </flux:button>
                            @endif
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Nog geen systemen.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $bewerktId ? 'Systeem bewerken' : 'Systeem toevoegen' }}</flux:heading>
            </div>

            <flux:input wire:model="naam" label="Naam" required />

            <flux:select wire:model="hostingtype" label="Hostingtype" required>
                <flux:select.option value="intern">Intern</flux:select.option>
                <flux:select.option value="extern">Extern</flux:select.option>
            </flux:select>

            <flux:select wire:model="beschikbaarheidseis" label="Beschikbaarheidseis"
                :description="'De eis voor de beschikbaarheid van dit systeem ('.$norm->bijlage.' 8.14).'">
                <flux:select.option value="">— nog niet bepaald —</flux:select.option>
                <flux:select.option value="niet_kritiek">Niet kritiek</flux:select.option>
                <flux:select.option value="normaal">Normaal</flux:select.option>
                <flux:select.option value="hoog">Hoog</flux:select.option>
                <flux:select.option value="bedrijfskritiek">Bedrijfskritiek</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="redundant" label="Redundant uitgevoerd?">
                <flux:select.option value="">— onbekend —</flux:select.option>
                <flux:select.option value="1">Ja</flux:select.option>
                <flux:select.option value="0">Nee</flux:select.option>
            </flux:select>

            @if ($redundant === '1')
                <flux:input wire:model="redundantieToelichting" label="Toelichting redundantie"
                    placeholder="Bijv. twee geografisch gescheiden datacentra" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
