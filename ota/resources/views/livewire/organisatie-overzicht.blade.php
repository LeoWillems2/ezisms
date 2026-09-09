<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.context-subnav')

    <div>
        <flux:heading size="xl">Organisatie</flux:heading>
        <flux:subheading>Voor welke organisatie dit ISMS wordt gevoerd, en uit welke eenheden zij bestaat.</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <div class="blueprint p-5">
        <div class="flex items-start justify-between gap-4">
            <flux:heading size="lg">Gegevens</flux:heading>

            @if ($this->magMuteren())
                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="bewerkGegevens">
                    Bewerken
                </flux:button>
            @endif
        </div>

        @if ($profiel->gegevens)
            {{-- `whitespace-pre-line`: de invoer is platte tekst waarin de
                 invuller zelf de regels bepaalt — een adres over drie regels moet
                 er ook als drie regels uit komen. --}}
            <flux:text class="mt-2 whitespace-pre-line">{{ $profiel->gegevens }}</flux:text>
        @else
            <flux:text class="mt-2">Nog geen organisatiegegevens vastgelegd.</flux:text>
        @endif
    </div>

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Organisatie-eenheden</flux:heading>
            <flux:subheading>Afdelingen, locaties en processen — de bouwstenen van de ISMS-scope.</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweEenheid">
                Nieuwe eenheid
            </flux:button>
        @endif
    </div>

    <div class="blueprint p-5">
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

    <flux:modal wire:model.self="toontGegevensFormulier" class="md:w-[36rem]">
        <form wire:submit="gegevensOpslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Organisatiegegevens</flux:heading>
                <flux:subheading>
                    Vrije tekst — naam, adres, en wat er verder boven de Verklaring van
                    Toepasselijkheid en de auditrapporten hoort te staan. Regelovergangen
                    blijven behouden; opmaaktekens worden niet gelezen.
                </flux:subheading>
            </div>

            <flux:textarea
                wire:model="gegevens"
                label="Gegevens"
                rows="8"
                :maxlength="$this->maxGegevens()"
                :description="'Maximaal '.$this->maxGegevens().' tekens.'" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitGegevensFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>

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
