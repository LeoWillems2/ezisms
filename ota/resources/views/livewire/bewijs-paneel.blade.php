<div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Bewijsstukken</flux:heading>
            <flux:text>Documenten die aantonen dat dit daadwerkelijk zo is uitgevoerd.</flux:text>
        </div>

        @if ($this->magKoppelen())
            {{-- Twee losse handelingen: een nieuw bestand opvoeren, of een stuk
                 koppelen dat al in de repository staat (bijv. één pentestrapport
                 aan meerdere assets). --}}
            <div class="flex gap-2">
                <flux:button size="sm" icon="plus" wire:click="nieuwBewijsstuk">Nieuw bewijsstuk</flux:button>
                <flux:button size="sm" variant="ghost" icon="link" wire:click="koppelBestaand">
                    Bestaand koppelen
                </flux:button>
            </div>
        @endif
    </div>

    @forelse ($bewijsstukken as $bewijsstuk)
        <div wire:key="bewijs-{{ $bewijsstuk->id }}"
            class="flex items-center justify-between gap-3 border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800">
            <div class="min-w-0">
                <flux:link :href="route('bewijsstukken.download', $bewijsstuk)" class="truncate">
                    {{ $bewijsstuk->naam }}
                </flux:link>
                <flux:text class="text-xs">
                    {{ $bewijsstuk->bestandsnaam }} · {{ $bewijsstuk->leesbareGrootte() }} ·
                    {{ $bewijsstuk->uploader->naam }}, {{ $bewijsstuk->geupload_op->format('d-m-Y') }}
                </flux:text>
            </div>

            @if ($this->magKoppelen())
                <flux:button size="sm" variant="ghost" icon="x-mark"
                    wire:click="ontkoppel({{ $bewijsstuk->koppelingen->first()->id }})"
                    wire:confirm="Koppeling verwijderen? Het bewijsstuk zelf blijft bestaan en blijft aan eventuele andere entiteiten gekoppeld.">
                    Ontkoppelen
                </flux:button>
            @endif
        </div>
    @empty
        <flux:text>Nog geen bewijsstuk gekoppeld.</flux:text>
    @endforelse

    {{-- Nieuw bestand uploaden --}}
    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <flux:heading size="lg">Nieuw bewijsstuk</flux:heading>

            <flux:input wire:model="naam" label="Naam" required />
            <flux:input type="file" wire:model="bestand" label="Bestand"
                description="Max. 20 MB. Toegestaan: pdf, png, jpg, docx, xlsx, txt." required />

            <div class="flex items-center justify-end gap-2">
                {{-- De upload loopt asynchroon; zonder deze poort valt een Opslaan
                     vóór de upload klaar is in "bestand is verplicht" (p16). --}}
                <flux:text wire:loading wire:target="bestand" class="mr-auto text-sm text-zinc-500">
                    Bezig met uploaden…
                </flux:text>
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit"
                    wire:loading.attr="disabled" wire:target="bestand, opslaan">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Bestaand bewijsstuk koppelen --}}
    <flux:modal wire:model.self="toontKoppelen" class="md:w-[36rem]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Bestaand bewijsstuk koppelen</flux:heading>
                <flux:subheading>
                    Eén bewijsstuk mag aan meerdere entiteiten hangen — een pentestrapport
                    onderbouwt vaak meerdere assets tegelijk.
                </flux:subheading>
            </div>

            <flux:input wire:model.live.debounce.300ms="zoekterm" icon="magnifying-glass"
                placeholder="Zoek op naam of bestandsnaam" />

            <div class="max-h-80 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                @forelse ($kandidaten as $kandidaat)
                    <div wire:key="kandidaat-{{ $kandidaat->id }}" class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <flux:text class="truncate font-medium">{{ $kandidaat->naam }}</flux:text>
                            <flux:text class="text-xs">
                                {{ $kandidaat->bestandsnaam }} · {{ $kandidaat->leesbareGrootte() }}
                                @if ($kandidaat->koppelingen->isEmpty())
                                    · <span class="text-amber-600 dark:text-amber-500">nog ongekoppeld</span>
                                @else
                                    · {{ $kandidaat->koppelingen->count() }}x gekoppeld
                                @endif
                            </flux:text>
                        </div>
                        <flux:button size="sm" wire:click="koppelBestaandBewijsstuk({{ $kandidaat->id }})">
                            Koppelen
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="py-2">
                        @if ($zoekterm !== '')
                            Geen bewijsstuk gevonden voor "{{ $zoekterm }}".
                        @else
                            Er zijn geen bewijsstukken die nog niet aan deze entiteit hangen.
                        @endif
                    </flux:text>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="sluitKoppelen">Sluiten</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
