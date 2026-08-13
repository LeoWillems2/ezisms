<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.assets-subnav')

    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('assets.index')" wire:navigate>
            Terug naar assets
        </flux:button>
    </div>

    <div class="flex items-center gap-3">
        <flux:heading size="xl">{{ $asset->naam }}</flux:heading>
        @php
            $statusKleur = match ($asset->status) {
                'actief' => 'green',
                'geregistreerd' => 'amber',
                'buiten_gebruik' => 'zinc',
                'afgestoten' => 'red',
                default => 'zinc',
            };
        @endphp
        <flux:badge :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</flux:badge>
        @unless ($asset->isGeclassificeerd())
            <flux:badge color="red" icon="exclamation-triangle">Nog niet volledig geclassificeerd</flux:badge>
        @endunless
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif
    @if (session('fout'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    @php $readonly = ! $this->magMuteren(); @endphp

    {{-- Basisgegevens --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Basisgegevens</flux:heading>
        <form wire:submit="opslaanBasis" class="flex flex-col gap-4">
            <flux:input wire:model="naam" label="Naam" :readonly="$readonly" required />

            <flux:select wire:model="type" label="Type" :disabled="$readonly" required>
                <flux:select.option value="informatie">Informatie</flux:select.option>
                <flux:select.option value="systeem_of_dienst">Systeem of dienst</flux:select.option>
                <flux:select.option value="hardware">Hardware</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="omschrijving" label="Omschrijving" :readonly="$readonly" />

            <x-keuzelijst wire:model="organisatieEenheidId" label="Organisatie-eenheid"
                leeg="Kies een eenheid" required :disabled="$readonly"
                :opties="$eenheden->mapWithKeys(fn ($e) => [$e->id => $e->naam.' ('.$e->type.')'])" />

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-keuzelijst wire:model="accountableId" label="Accountable" leeg="— geen —" :disabled="$readonly"
                        :opties="$accountableGebruikers"
                        description="Eindverantwoordelijk (RACI). Puur informatief; geeft geen rechten in het platform." />
                    @if ($accountableGebruiker && ! $accountableGebruiker->isActief())
                        <flux:text class="mt-1 text-sm text-amber-600 dark:text-amber-500">
                            {{ ucfirst($accountableGebruiker->status) }} — wijs een actief account aan.
                        </flux:text>
                    @endif
                </div>

                <div>
                    <x-keuzelijst wire:model="responsibleId" label="Responsible" leeg="— geen —" :disabled="$readonly"
                        :opties="$responsibleGebruikers"
                        description="Dagelijkse/uitvoerende verantwoordelijkheid (RACI)." />
                    @if ($responsibleGebruiker && ! $responsibleGebruiker->isActief())
                        <flux:text class="mt-1 text-sm text-amber-600 dark:text-amber-500">
                            {{ ucfirst($responsibleGebruiker->status) }} — wijs een actief account aan.
                        </flux:text>
                    @endif
                </div>
            </div>

            <flux:switch wire:model="binnenScope" label="Binnen scope" :disabled="$readonly" />

            @unless ($readonly)
                <div><flux:button type="submit" variant="primary">Basisgegevens opslaan</flux:button></div>
            @endunless
        </form>
    </div>

    {{-- Classificatie --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">Classificatie</flux:heading>
        <flux:text class="mb-4">De status wordt automatisch "Actief" zodra alle drie de dimensies zijn ingevuld.</flux:text>

        <form wire:submit="opslaanClassificatie" class="flex flex-col gap-5">
            @foreach (['vertrouwelijkheid' => 'vertrouwelijkheidsniveau', 'integriteit' => 'integriteitsniveau', 'beschikbaarheid' => 'beschikbaarheidsniveau'] as $dimensie => $veld)
                <div>
                    <x-keuzelijst wire:model.live="{{ $veld }}" label="{{ ucfirst($dimensie) }}"
                        leeg="Niet geclassificeerd" :disabled="$readonly"
                        :opties="$niveauOpties[$dimensie]" />

                    @if ($this->$veld)
                        @php
                            $schema = $schemas->get($dimensie.':'.$this->$veld);
                            $niveauLabel = $niveauOpties[$dimensie][$this->$veld] ?? ucfirst($this->$veld);
                        @endphp
                        <div class="mt-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/50">
                            @if ($schema && $schema->omgangsregels)
                                <flux:text><span class="font-medium">Omgangsregels:</span> {{ $schema->omgangsregels }}</flux:text>
                            @else
                                <flux:text>Nog geen omgangsregels vastgelegd voor niveau "{{ $niveauLabel }}". Leg deze vast als organisatiebeleid.</flux:text>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Persoonsgegevens: geen classificatiedimensie maar een eigen
                 beoordeling met een eigen, wettelijk vocabulaire (03b §3). --}}
            <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">
                <x-keuzelijst wire:model.live="persoonsgegevens" label="Persoonsgegevens"
                    leeg="Nog niet beoordeeld" :disabled="$readonly"
                    :opties="$persoonsgegevensOpties" />

                {{-- Via de sleutel opzoeken en niet op waarheid van het veld:
                     na een validatiefout staat de afgekeurde waarde nog in de
                     binding, en die heeft geen uitlegregel. --}}
                @if ($uitleg = $persoonsgegevensUitleg[$this->persoonsgegevens] ?? null)
                    <div class="mt-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/50">
                        <flux:text>{{ $uitleg }}</flux:text>
                    </div>
                @endif

                @if ($asset->privacy_beoordeeld_op)
                    <flux:text class="mt-2 text-sm">
                        Beoordeeld op {{ $asset->privacy_beoordeeld_op->format('d-m-Y') }}.
                    </flux:text>
                @endif
            </div>

            @unless ($readonly)
                <div><flux:button type="submit" variant="primary">Classificatie opslaan</flux:button></div>
            @endunless
        </form>

        @if ($waarschuwing = $asset->privacywaarschuwing())
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950/40">
                <flux:text>{{ $waarschuwing }}</flux:text>
            </div>
        @endif
    </div>

    {{-- Gekoppelde systemen --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Gekoppelde systemen</flux:heading>
        <form wire:submit="systemenOpslaan" class="flex flex-col gap-4">
            <flux:checkbox.group wire:model="geselecteerdeSystemen" label="Systemen waarin dit asset wordt verwerkt">
                @forelse ($systemen as $systeem)
                    <flux:checkbox value="{{ $systeem->id }}" label="{{ $systeem->naam }} ({{ $systeem->hostingtype }})" :disabled="$readonly" />
                @empty
                    <flux:text>Nog geen systemen vastgelegd.</flux:text>
                @endforelse
            </flux:checkbox.group>

            @unless ($readonly)
                @if ($systemen->isNotEmpty())
                    <div><flux:button type="submit" variant="primary">Koppelingen opslaan</flux:button></div>
                @endif
            @endunless
        </form>
    </div>

    {{-- Toewijzingen --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">Toewijzingen</flux:heading>
        <flux:text class="mb-4">Aan wie is dit bedrijfsmiddel uitgereikt? Een openstaande toewijzing blokkeert het afstoten ({{ $norm->bijlage }} 5.11).</flux:text>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Gebruiker</flux:table.column>
                <flux:table.column>Toegewezen op</flux:table.column>
                <flux:table.column>Geretourneerd op</flux:table.column>
                <flux:table.column align="end">Acties</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($toewijzingen as $toewijzing)
                    <flux:table.row wire:key="toewijzing-{{ $toewijzing->id }}">
                        <flux:table.cell variant="strong">{{ $toewijzing->gebruiker->naam }}</flux:table.cell>
                        <flux:table.cell>{{ $toewijzing->toegewezen_op->format('d-m-Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($toewijzing->geretourneerd_op)
                                {{ $toewijzing->geretourneerd_op->format('d-m-Y') }}
                            @else
                                <flux:badge size="sm" color="amber">Open</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if (! $toewijzing->geretourneerd_op && $this->magMuteren())
                                <flux:button size="sm" variant="ghost" wire:click="retourRegistreren({{ $toewijzing->id }})">
                                    Retour registreren
                                </flux:button>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4"><flux:text>Nog geen toewijzingen.</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @unless ($readonly)
            <form wire:submit="toewijzen" class="mt-4 flex flex-wrap items-end gap-3">
                <x-keuzelijst wire:model="toewijzingGebruikerId" label="Toewijzen aan" leeg="Kies gebruiker"
                    required class="min-w-56" :opties="$gebruikers" />
                <flux:input wire:model="toewijzingDatum" type="date" label="Toegewezen op" class="max-w-48" />
                <flux:button type="submit" icon="plus">Toewijzen</flux:button>
            </form>
        @endunless
    </div>

    {{-- Bewijsstukken (blok 6, herbruikbaar paneel) --}}
    <livewire:bewijs-paneel blok-naam="asset-classificatie" entiteit-type="asset" :entiteit-id="$asset->id"
        :wire:key="'bewijs-asset-'.$asset->id" />

    {{-- Status --}}
    @unless ($readonly)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">Status</flux:heading>
            <div class="flex flex-wrap gap-2">
                @if (in_array($asset->status, ['geregistreerd', 'actief'], true))
                    <flux:button variant="ghost" wire:click="buitenGebruikStellen">Buiten gebruik stellen</flux:button>
                @endif

                @if ($asset->status !== 'afgestoten')
                    <flux:button variant="danger" wire:click="afstoten"
                        wire:confirm="Dit asset afstoten? Dit kan niet ongedaan gemaakt worden.">
                        Afstoten
                    </flux:button>
                @endif
            </div>
            @if ($asset->heeftOpenToewijzingen())
                <flux:text class="mt-3">Let op: afstoten is geblokkeerd zolang er openstaande toewijzingen zijn.</flux:text>
            @endif
        </div>
    @endunless
</div>
