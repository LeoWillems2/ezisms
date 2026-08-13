<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewijs-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Bewijsstukken</flux:heading>
            <flux:subheading>
                Centrale opslag van documenten die aantonen dat het ISMS-proces gevolgd is.
                Bewaartermijn: {{ \App\Support\Bewijsopslag::BEWAARJAREN }} jaar.
            </flux:subheading>
        </div>

        @if ($this->magUploaden())
            <flux:button variant="primary" icon="plus" wire:click="nieuwBewijsstuk">
                Bewijsstuk toevoegen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($ongekoppeld > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $ongekoppeld }} bewijsstuk(ken) zonder koppeling">
            <flux:callout.text>
                Een bewijsstuk dat nergens aan hangt is een bestand zonder bewijswaarde — een
                auditor kan er geen maatregel of beslissing mee onderbouwen. Gebruik "Koppelen"
                in de rij om het aan een asset, risico, SoA-maatregel of scope-verklaring te hangen.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-48">
            <flux:select.option value="">Alle</flux:select.option>
            <flux:select.option value="actief">Actief</flux:select.option>
            <flux:select.option value="gearchiveerd">Gearchiveerd</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterBlok" label="Blok" class="max-w-56">
            <flux:select.option value="">Alle blokken</flux:select.option>
            <flux:select.option value="identity-access">Identity &amp; Access</flux:select.option>
            <flux:select.option value="context-scope">Context &amp; Scope</flux:select.option>
            <flux:select.option value="asset-classificatie">Assets</flux:select.option>
            <flux:select.option value="risico-soa">Risico &amp; SoA</flux:select.option>
        </flux:select>

        <flux:switch wire:model.live="alleenOngekoppeld" label="Alleen ongekoppeld" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Bestand</flux:table.column>
            <flux:table.column>Geüpload door</flux:table.column>
            <flux:table.column>Gekoppeld aan</flux:table.column>
            <flux:table.column>Bewaren tot</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($bewijsstukken as $bewijsstuk)
                <flux:table.row wire:key="bewijsstuk-{{ $bewijsstuk->id }}">
                    <flux:table.cell variant="strong">{{ $bewijsstuk->naam }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $bewijsstuk->bestandsnaam }}
                        <flux:text class="text-xs">{{ $bewijsstuk->leesbareGrootte() }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $bewijsstuk->uploader->naam }}
                        <flux:text class="text-xs">{{ $bewijsstuk->geupload_op->format('d-m-Y') }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        @forelse ($bewijsstuk->koppelingen as $koppeling)
                            <div wire:key="koppeling-{{ $koppeling->id }}" class="flex items-center gap-1">
                                <flux:badge size="sm" color="zinc">{{ $koppeling->omschrijving() }}</flux:badge>
                                @if ($this->magOntkoppelen($koppeling))
                                    <flux:button size="xs" variant="ghost" icon="x-mark"
                                        wire:click="ontkoppel({{ $koppeling->id }})"
                                        wire:confirm="Koppeling verwijderen? Het bewijsstuk zelf blijft bestaan."
                                        aria-label="Koppeling verwijderen" />
                                @endif
                            </div>
                        @empty
                            <flux:badge size="sm" color="amber">Ongekoppeld</flux:badge>
                        @endforelse
                    </flux:table.cell>
                    <flux:table.cell>{{ $bewijsstuk->bewaren_tot->format('d-m-Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$bewijsstuk->status === 'actief' ? 'green' : 'zinc'">
                            {{ ucfirst($bewijsstuk->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @if ($koppelbareTypes !== [])
                                <flux:button size="sm" variant="ghost" icon="link"
                                    wire:click="koppel({{ $bewijsstuk->id }})">
                                    Koppelen
                                </flux:button>
                            @endif
                            @if ($bewijsstuk->isPreviewbaar())
                                <flux:button size="sm" variant="ghost" icon="eye" target="_blank"
                                    :href="route('bewijsstukken.preview', $bewijsstuk)">
                                    Preview
                                </flux:button>
                            @endif
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                                :href="route('bewijsstukken.download', $bewijsstuk)">
                                Downloaden
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Geen bewijsstukken gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Bewijsstuk toevoegen</flux:heading>
                <flux:subheading>Koppelen aan een risico, asset of maatregel doe je vanaf dat detailscherm.</flux:subheading>
            </div>

            <flux:input wire:model="naam" label="Naam" required />
            <flux:textarea wire:model="omschrijving" label="Omschrijving" />
            <flux:input type="file" wire:model="bestand" label="Bestand"
                description="Max. 20 MB. Toegestaan: pdf, png, jpg, docx, xlsx, txt, rtf, odt. Van rtf, docx en odt is een preview te openen." required />

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

    {{-- Bestaand bewijsstuk aan een entiteit koppelen --}}
    <flux:modal wire:model.self="toontKoppelen" class="md:w-[32rem]">
        <form wire:submit="koppelOpslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Bewijsstuk koppelen</flux:heading>
                <flux:subheading>
                    Eén bewijsstuk mag aan meerdere entiteiten hangen; koppelen aan een tweede
                    asset vervangt de eerste koppeling niet.
                </flux:subheading>
            </div>

            {{-- De keuzelijst toont alleen blokken die je mag muteren: anders
                 zouden hier titels van risico's staan die je niet mag inzien. --}}
            <x-keuzelijst wire:model.live="koppelType" label="Type" leeg="Kies een type" required
                :opties="$koppelbareTypes" />

            @if ($koppelType !== '')
                <x-keuzelijst wire:model="koppelEntiteitId" label="Entiteit" required
                    :leeg="$koppelOpties === [] ? 'Nog niets vastgelegd van dit type' : 'Kies'"
                    :opties="$koppelOpties" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitKoppelen">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Koppelen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
