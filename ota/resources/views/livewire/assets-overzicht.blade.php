<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.assets-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Assets</flux:heading>
            <flux:subheading>Inventaris van informatie, systemen en hardware met eigenaarschap en classificatie.</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwAsset">
                Asset toevoegen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($metNietActieveEigenaar > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $metNietActieveEigenaar }} asset(s) met een niet-actieve accountable of responsible">
            <flux:callout.text>
                De aangewezen persoon is gedeactiveerd, geblokkeerd of nog niet actief. Wijs op het
                assetdetail een actief account aan, zodat de RACI-verantwoordelijkheid belegd blijft.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-48">
            <flux:select.option value="">Alle statussen</flux:select.option>
            <flux:select.option value="geregistreerd">Geregistreerd</flux:select.option>
            <flux:select.option value="actief">Actief</flux:select.option>
            <flux:select.option value="buiten_gebruik">Buiten gebruik</flux:select.option>
            <flux:select.option value="afgestoten">Afgestoten</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterScope" label="Scope" class="max-w-48">
            <flux:select.option value="">Alle</flux:select.option>
            <flux:select.option value="binnen">Binnen scope</flux:select.option>
            <flux:select.option value="buiten">Buiten scope</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterPersoonsgegevens" label="Persoonsgegevens" class="max-w-56">
            <flux:select.option value="">Alle</flux:select.option>
            @foreach ($persoonsgegevensFilterOpties as $waarde => $label)
                <flux:select.option value="{{ $waarde }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:switch wire:model.live="alleenNietGeclassificeerd" label="Alleen niet-geclassificeerd" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Organisatie-eenheid</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>C / I / B</flux:table.column>
            <flux:table.column>Persoonsgegevens</flux:table.column>
            <flux:table.column>Scope</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($assets as $asset)
                <flux:table.row wire:key="asset-{{ $asset->id }}">
                    <flux:table.cell variant="strong">{{ $asset->naam }}</flux:table.cell>
                    <flux:table.cell>{{ ucfirst(str_replace('_', ' ', $asset->type)) }}</flux:table.cell>
                    <flux:table.cell>{{ $asset->organisatieEenheid->naam }}</flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($asset->status) {
                                'actief' => 'green',
                                'geregistreerd' => 'amber',
                                'buiten_gebruik' => 'zinc',
                                'afgestoten' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            @foreach (['vertrouwelijkheidsniveau' => 'C', 'integriteitsniveau' => 'I', 'beschikbaarheidsniveau' => 'B'] as $veld => $letter)
                                @if ($asset->$veld)
                                    @php $niveauTekst = ucfirst(str_replace('_', ' ', $asset->$veld)); @endphp
                                    <flux:badge size="sm" color="zinc" title="{{ $niveauTekst }}">{{ $letter }}: {{ $niveauTekst }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red" variant="subtle">{{ $letter }}: —</flux:badge>
                                @endif
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            // Onbeoordeeld is amber en niet rood: het is een gat
                            // in de administratie, geen fout in de inhoud.
                            [$pgKleur, $pgTekst] = match ($asset->persoonsgegevens) {
                                'geen' => ['zinc', 'Geen'],
                                'gewoon' => ['blue', 'Gewoon'],
                                'bijzonder' => ['red', 'Bijzonder'],
                                'strafrechtelijk' => ['red', 'Strafrechtelijk'],
                                default => ['amber', 'Nog niet beoordeeld'],
                            };
                        @endphp
                        <flux:badge size="sm" :color="$pgKleur"
                            :variant="$asset->persoonsgegevens === null ? 'subtle' : null">{{ $pgTekst }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($asset->binnen_scope)
                            <flux:badge size="sm" color="green">Binnen</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Buiten</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            :href="route('assets.detail', $asset)" wire:navigate>
                            Openen
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8"><flux:text>Geen assets gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Asset toevoegen</flux:heading>
                <flux:subheading>Na toevoegen open je meteen het detailscherm om te classificeren.</flux:subheading>
            </div>

            <flux:input wire:model="naam" label="Naam" required />

            <flux:select wire:model="type" label="Type" required>
                <flux:select.option value="informatie">Informatie</flux:select.option>
                <flux:select.option value="systeem_of_dienst">Systeem of dienst</flux:select.option>
                <flux:select.option value="hardware">Hardware</flux:select.option>
            </flux:select>

            <x-keuzelijst wire:model="organisatieEenheidId" label="Organisatie-eenheid"
                leeg="Kies een eenheid" required
                :opties="$eenheden->mapWithKeys(fn ($e) => [$e->id => $e->naam.' ('.$e->type.')'])" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Toevoegen &amp; openen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
