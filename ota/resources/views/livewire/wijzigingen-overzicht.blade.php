<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.wijzigingen-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Wijzigingsbeheer</flux:heading>
            <flux:subheading>
                Het register achter A.8.32: welke wijzigingen zijn er geweest, en met welke goedkeuring.
            </flux:subheading>
        </div>

        @if ($this->magMelden())
            <flux:button variant="primary" icon="plus" wire:click="nieuweWijziging">Wijziging aanmelden</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($zonderTerugvalplan > 0)
        <flux:callout variant="danger" icon="exclamation-triangle"
            heading="{{ $zonderTerugvalplan }} uitgevoerde wijziging(en) zonder terugvalplan">
            <flux:callout.text>
                A.8.32 f) vraagt om nood- en voorzorgsmaatregelen, met inbegrip van vangnetprocedures.
                Dit getal hoort nul te zijn.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($spoedZonderGoedkeuring > 0)
        <flux:callout variant="warning" icon="bolt"
            heading="{{ $spoedZonderGoedkeuring }} spoedwijziging(en) wachten nog op goedkeuring achteraf">
            <flux:callout.text>
                De spoedroute is toegestaan; het overslaan van de goedkeuring niet. Rond de
                goedkeuringsstap alsnog af.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($afgevoerdZonderDossier->isNotEmpty())
        <flux:callout variant="warning" icon="archive-box-x-mark"
            heading="{{ $afgevoerdZonderDossier->count() }} systeem/systemen afgevoerd zonder afvoerdossier">
            <flux:callout.text>
                Buiten dit register om uitgefaseerd: {{ $afgevoerdZonderDossier->pluck('naam')->join(', ') }}.
                Bij een afvoer moeten toegang, gegevens en contract aantoonbaar zijn afgehandeld; zonder dossier
                is dat niet te laten zien. Voer de afvoer alsnog op met het sjabloon
                &ldquo;Afvoer van een systeem of dienst&rdquo;.
                <br>
                Het signaal kijkt {{ \App\Models\Systeem::SIGNAALPERIODE_MAANDEN }} maanden terug.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-56">
            <flux:select.option value="lopend">Lopend</flux:select.option>
            <flux:select.option value="">Alle</flux:select.option>
            <flux:select.option value="aangemeld">Aangemeld</flux:select.option>
            <flux:select.option value="in_behandeling">In behandeling</flux:select.option>
            <flux:select.option value="uitgevoerd">Uitgevoerd</flux:select.option>
            <flux:select.option value="gesloten">Gesloten</flux:select.option>
            <flux:select.option value="afgewezen">Afgewezen</flux:select.option>
            <flux:select.option value="geannuleerd">Geannuleerd</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterSoort" label="Soort" class="max-w-56">
            <flux:select.option value="">Alle soorten</flux:select.option>
            @foreach (\App\Livewire\WijzigingenOverzicht::SOORTEN as $soortOptie)
                <flux:select.option value="{{ $soortOptie }}">{{ ucfirst($soortOptie) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterZwaarte" label="Zwaarte" class="max-w-56">
            <flux:select.option value="">Alle</flux:select.option>
            <flux:select.option value="standaard">Standaard</flux:select.option>
            <flux:select.option value="ingrijpend">Ingrijpend</flux:select.option>
            <flux:select.option value="spoed">Spoed</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Soort</flux:table.column>
            <flux:table.column>Zwaarte</flux:table.column>
            <flux:table.column>Systemen</flux:table.column>
            <flux:table.column>Gepland op</flux:table.column>
            <flux:table.column>Voortgang</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($wijzigingen as $wijziging)
                <flux:table.row wire:key="wijziging-{{ $wijziging->id }}">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('wijzigingen.detail', $wijziging)" wire:navigate>
                            {{ $wijziging->titel }}
                        </flux:link>
                        @if ($wijziging->leverancier)
                            <flux:text class="text-xs">{{ $wijziging->leverancier->naam }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst($wijziging->soort) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$wijziging->zwaarte === 'spoed' ? 'amber' : 'zinc'">
                            {{ ucfirst($wijziging->zwaarte) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $wijziging->systemen->pluck('naam')->join(', ') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $wijziging->gepland_op?->format('d-m-Y') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @php $vg = $voortgang[$wijziging->id] ?? null; @endphp
                        {{ $vg ? "{$vg['klaar']} van {$vg['totaal']}" : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($wijziging->status) {
                                'gesloten' => 'green',
                                'afgewezen', 'geannuleerd' => 'red',
                                'uitgevoerd' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">
                            {{ ucfirst(str_replace('_', ' ', $wijziging->status)) }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Geen wijzigingen gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Wijziging aanmelden</flux:heading>
                <flux:subheading>
                    Meld de aankondiging; de CISO kiest daarna het sjabloon en de datum.
                </flux:subheading>
            </div>

            <flux:input wire:model="titel" label="Titel" required />

            <flux:select wire:model="soort" label="Soort" required>
                @foreach (\App\Livewire\WijzigingenOverzicht::SOORTEN as $soortOptie)
                    <flux:select.option value="{{ $soortOptie }}">{{ ucfirst($soortOptie) }}</flux:select.option>
                @endforeach
            </flux:select>

            <x-keuzelijst wire:model="leverancierId" label="Leverancier" leeg="Geen leverancier"
                :opties="$leveranciers" />

            <flux:input wire:model="aangekondigdOp" type="date" label="Aangekondigd op" />

            <flux:input wire:model="externeReferentie" label="Ticketnummer"
                description="Optioneel, het nummer uit uw eigen ITSM-systeem." />

            <flux:textarea wire:model="impactToelichting" label="Eerste indruk van de impact" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontFormulier', false)">
                    Annuleren
                </flux:button>
                <flux:button variant="primary" type="submit">Aanmelden</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
