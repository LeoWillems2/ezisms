<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.context-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Organisatie-eenheden</flux:heading>
            <flux:subheading>Afdelingen, locaties en processen — de bouwstenen van de ISMS-scope.</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweEenheid">
                Nieuwe eenheid
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        @if ($wortels->isEmpty())
            <flux:text>Nog geen organisatie-eenheden vastgelegd.</flux:text>
        @else
            <ul class="space-y-1">
                @foreach ($wortels as $wortel)
                    @include('partials.organisatie-eenheid-node', ['eenheid' => $wortel, 'magMuteren' => $this->magMuteren()])
                @endforeach
            </ul>
        @endif
    </div>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Organisatie-eenheid</flux:heading>
                <flux:subheading>
                    @if ($bovenliggendeEenheidId)
                        Wordt toegevoegd als sub-eenheid.
                    @else
                        Wordt toegevoegd op het hoofdniveau.
                    @endif
                </flux:subheading>
            </div>

            <flux:input wire:model="naam" label="Naam" required />

            <flux:select wire:model="type" label="Type" required>
                <flux:select.option value="afdeling">Afdeling</flux:select.option>
                <flux:select.option value="locatie">Locatie</flux:select.option>
                <flux:select.option value="proces">Proces</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
