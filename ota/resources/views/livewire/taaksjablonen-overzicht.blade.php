<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.taken-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Taaksjablonen</flux:heading>
            <flux:subheading>
                Terugkerende taken. <code>isms:genereer-taken</code> maakt de eerstvolgende taak aan
                zodra de deadline binnen het ingestelde aantal dagen valt.
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="nieuwSjabloon">Nieuw sjabloon</flux:button>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @php $zonderEigenaar = $sjablonen->whereNull('standaard_eigenaar_id')->count(); @endphp
    @if ($zonderEigenaar > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $zonderEigenaar }} sjabloon/sjablonen zonder standaard-eigenaar">
            <flux:callout.text>
                Die leveren taken op die bij niemand op het dashboard verschijnen en alleen door een
                CISO afgevinkt kunnen worden. Wijs een standaard-eigenaar aan, of pas de eigenaar per
                taak aan via het takenoverzicht.
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Herhaling</flux:table.column>
            <flux:table.column>Bron-blok</flux:table.column>
            <flux:table.column>Standaard-eigenaar</flux:table.column>
            <flux:table.column>Vooraf</flux:table.column>
            <flux:table.column>Taken</flux:table.column>
            <flux:table.column>Actief</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($sjablonen as $sjabloon)
                <flux:table.row wire:key="sjabloon-{{ $sjabloon->id }}">
                    <flux:table.cell variant="strong">{{ $sjabloon->naam }}</flux:table.cell>
                    <flux:table.cell>
                        {{ ucfirst(str_replace('_', ' ', $sjabloon->herhaling)) }}
                        @if ($sjabloon->herhaling === 'aangepast')
                            ({{ $sjabloon->interval_dagen }} dagen)
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $sjabloon->bron_blok }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($sjabloon->standaardEigenaar)
                            {{ $sjabloon->standaardEigenaar->naam }}
                        @else
                            <flux:badge size="sm" color="amber">Geen</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $sjabloon->aanmaken_dagen_vooraf }} dgn</flux:table.cell>
                    <flux:table.cell>{{ $sjabloon->taken_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$sjabloon->actief ? 'green' : 'zinc'">
                            {{ $sjabloon->actief ? 'Ja' : 'Nee' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            wire:click="bewerk({{ $sjabloon->id }})">
                            Bewerken
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8"><flux:text>Nog geen sjablonen.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[34rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <flux:heading size="lg">{{ $bewerktId ? 'Sjabloon bewerken' : 'Nieuw sjabloon' }}</flux:heading>

            <flux:input wire:model="naam" label="Naam" required />
            <flux:textarea wire:model="omschrijving" label="Omschrijving" />

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="herhaling" label="Herhaling" required>
                    <flux:select.option value="eenmalig">Eenmalig</flux:select.option>
                    <flux:select.option value="maandelijks">Maandelijks</flux:select.option>
                    <flux:select.option value="per_kwartaal">Per kwartaal</flux:select.option>
                    <flux:select.option value="jaarlijks">Jaarlijks</flux:select.option>
                    <flux:select.option value="aangepast">Aangepast</flux:select.option>
                </flux:select>

                @if ($herhaling === 'aangepast')
                    <flux:input wire:model="intervalDagen" type="number" min="1" label="Interval (dagen)" required />
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="bronBlok" label="Bron-blok" required>
                    <flux:select.option value="identity-access">Identity &amp; Access</flux:select.option>
                    <flux:select.option value="context-scope">Context &amp; Scope</flux:select.option>
                    <flux:select.option value="asset-classificatie">Assets</flux:select.option>
                    <flux:select.option value="risico-soa">Risico &amp; SoA</flux:select.option>
                    <flux:select.option value="bewijsrepository-audit-trail">Bewijs &amp; audit trail</flux:select.option>
                </flux:select>

                <flux:input wire:model="aanmakenDagenVooraf" type="number" min="0" label="Dagen vooraf aanmaken"
                    description="Een jaarlijkse taak die pas op de deadline verschijnt is nutteloos." required />
            </div>

            <x-keuzelijst wire:model="standaardEigenaarId" label="Standaard-eigenaar" leeg="— geen —"
                :opties="$gebruikers->pluck('naam', 'id')"
                description="Zonder eigenaar verschijnt de gegenereerde taak bij niemand op het dashboard." />

            <flux:switch wire:model="actief" label="Actief" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
