<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.context-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Belanghebbenden</flux:heading>
            <flux:subheading>Belanghebbenden en hun eisen/verwachtingen ({{ $norm->naam_kort }} §4.2).</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweBelanghebbende">
                Belanghebbende toevoegen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <div class="flex flex-col gap-3">
        @forelse ($belanghebbenden as $belanghebbende)
            <details wire:key="belanghebbende-{{ $belanghebbende->id }}"
                class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <summary class="flex cursor-pointer items-center gap-2">
                    <span class="font-medium">{{ $belanghebbende->naam }}</span>
                    <flux:badge size="sm" :color="$belanghebbende->aard === 'intern' ? 'sky' : 'amber'">
                        {{ ucfirst($belanghebbende->aard) }}
                    </flux:badge>
                    <flux:badge size="sm" color="zinc">{{ $belanghebbende->eisen->count() }} eis(en)</flux:badge>
                </summary>

                <div class="mt-4">
                    <div class="flex flex-col gap-4">
                        @if ($belanghebbende->relevantie_voor_isms)
                            <flux:text>{{ $belanghebbende->relevantie_voor_isms }}</flux:text>
                        @endif

                        @if ($this->magMuteren())
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="bewerk({{ $belanghebbende->id }})">Bewerken</flux:button>
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="verwijderen({{ $belanghebbende->id }})"
                                    wire:confirm="'{{ $belanghebbende->naam }}' en alle bijbehorende eisen verwijderen?" />
                            </div>
                        @endif

                        <div>
                            <flux:heading size="sm">Eisen</flux:heading>
                            @if ($belanghebbende->eisen->isEmpty())
                                <flux:text class="mt-1">Nog geen eisen vastgelegd.</flux:text>
                            @else
                                <ul class="mt-2 space-y-1">
                                    @foreach ($belanghebbende->eisen as $eis)
                                        <li wire:key="eis-{{ $eis->id }}" class="flex items-center gap-2">
                                            <flux:badge size="sm" color="zinc">{{ ucfirst($eis->bron) }}</flux:badge>
                                            <flux:text>{{ $eis->omschrijving }}</flux:text>
                                            @if ($this->magMuteren())
                                                <flux:button size="xs" variant="ghost" icon="trash"
                                                    wire:click="eisVerwijderen({{ $eis->id }})" />
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @if ($this->magMuteren())
                            @if ($eisVoorBelanghebbendeId === $belanghebbende->id)
                                <form wire:submit="eisOpslaan" class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <flux:textarea wire:model="eisOmschrijving" label="Omschrijving" required />
                                    <flux:select wire:model="eisBron" label="Bron" required>
                                        <flux:select.option value="contractueel">Contractueel</flux:select.option>
                                        <flux:select.option value="wettelijk">Wettelijk</flux:select.option>
                                        <flux:select.option value="verwachting">Verwachting</flux:select.option>
                                    </flux:select>
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" type="button" wire:click="annuleerEis">Annuleren</flux:button>
                                        <flux:button size="sm" variant="primary" type="submit">Eis opslaan</flux:button>
                                    </div>
                                </form>
                            @else
                                <div>
                                    <flux:button size="sm" variant="ghost" icon="plus"
                                        wire:click="eisToevoegenAan({{ $belanghebbende->id }})">
                                        Eis toevoegen
                                    </flux:button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </details>
        @empty
            <flux:text>Nog geen belanghebbenden.</flux:text>
        @endforelse
    </div>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $bewerktId ? 'Belanghebbende bewerken' : 'Belanghebbende toevoegen' }}</flux:heading>
            </div>

            <flux:input wire:model="naam" label="Naam"
                description="Bijv. klant, toezichthouder, aandeelhouder, medewerker, leverancier." required />

            <flux:select wire:model="aard" label="Aard" required>
                <flux:select.option value="intern">Intern</flux:select.option>
                <flux:select.option value="extern">Extern</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="relevantieVoorIsms" label="Relevantie voor het ISMS" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
