<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">Doelgroepen</flux:heading>
            <flux:subheading>
                Awareness-doelgroepen met expliciet lidmaatschap, los van de
                afdelingsindeling.
            </flux:subheading>
        </div>
        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweDoelgroep">Nieuwe doelgroep</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Omschrijving</flux:table.column>
            <flux:table.column align="end">Leden</flux:table.column>
            <flux:table.column align="end">Modules</flux:table.column>
            @if ($this->magMuteren())
                <flux:table.column align="end">Acties</flux:table.column>
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($doelgroepen as $doelgroep)
                <flux:table.row wire:key="doelgroep-{{ $doelgroep->id }}">
                    <flux:table.cell variant="strong">{{ $doelgroep->naam }}</flux:table.cell>
                    <flux:table.cell>{{ $doelgroep->omschrijving ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $doelgroep->gebruikers_count }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $doelgroep->modules_count }}</flux:table.cell>
                    @if ($this->magMuteren())
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                wire:click="bewerk({{ $doelgroep->id }})">Bewerken</flux:button>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>Nog geen doelgroepen.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="max-w-lg">
        <form wire:submit="opslaan" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $bewerktId ? 'Doelgroep bewerken' : 'Nieuwe doelgroep' }}</flux:heading>

            <flux:input wire:model="naam" label="Naam" />
            <flux:input wire:model="omschrijving" label="Omschrijving (optioneel)" />

            <div>
                <flux:label>Leden</flux:label>
                <div class="mt-2 flex max-h-64 flex-col gap-2 overflow-y-auto">
                    @forelse ($kiesbareGebruikers as $gebruiker)
                        <flux:checkbox wire:model="leden" value="{{ $gebruiker->id }}"
                            label="{{ $gebruiker->naam }}" />
                    @empty
                        <flux:text variant="subtle">Geen actieve gebruikers om te kiezen.</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
