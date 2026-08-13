<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Risicoregister</flux:heading>
            <flux:subheading>
                Geïdentificeerde risico's met kans, impact en behandelplan. Score = kans x impact;
                de acceptatiedrempel staat op {{ $drempel }}.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-start justify-end gap-3">
            @include('partials.kopieknop')

            @if ($this->magMuteren())
                <flux:button variant="primary" icon="plus" wire:click="nieuwRisico">
                    Risico toevoegen
                </flux:button>
            @endif
        </div>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($metNietActieveEigenaar > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $metNietActieveEigenaar }} risico('s) met een niet-actieve eigenaar">
            <flux:callout.text>
                De risico-eigenaar is gedeactiveerd, geblokkeerd of nog niet actief. Wijs een
                actief account aan, zodat het risico belegd blijft.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-56">
            <flux:select.option value="">Alle statussen</flux:select.option>
            <flux:select.option value="geidentificeerd">Geïdentificeerd</flux:select.option>
            <flux:select.option value="beoordeeld">Beoordeeld</flux:select.option>
            <flux:select.option value="behandelplan_opgesteld">Behandelplan opgesteld</flux:select.option>
            <flux:select.option value="geaccepteerd">Geaccepteerd</flux:select.option>
            <flux:select.option value="in_uitvoering">In uitvoering</flux:select.option>
            <flux:select.option value="gemitigeerd">Gemitigeerd</flux:select.option>
        </flux:select>

        <flux:switch wire:model.live="alleenBovenDrempel" label="Alleen boven de drempel" />
    </div>

    {{-- Wie via het issue-register binnenkomt, ziet een voorgefilterde lijst.
         Zonder deze regel is niet te zien waaróm er risico's ontbreken. --}}
    @if ($gefilterdIssue)
        <flux:callout icon="funnel" heading="Alleen risico's met deze aanleiding">
            {{ ucfirst($gefilterdIssue->aard) }} — {{ $gefilterdIssue->categorie }}:
            {{ $gefilterdIssue->omschrijving }}
            <x-slot name="actions">
                <flux:button size="sm" wire:click="$set('filterIssue', '')">Filter opheffen</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Ref.</flux:table.column>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Score</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Eigenaar</flux:table.column>
            <flux:table.column>Volgende beoordeling</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($risicos as $risico)
                <flux:table.row wire:key="risico-{{ $risico->id }}">
                    <flux:table.cell variant="strong">{{ $risico->referentie() }}</flux:table.cell>
                    <flux:table.cell>{{ $risico->titel }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($risico->risicoscore === null)
                            <flux:badge size="sm" color="zinc">Niet beoordeeld</flux:badge>
                        @else
                            {{-- Semafoor uit één bron (Risico::scoreKleur); de matrix gebruikt dezelfde banden. --}}
                            <flux:badge size="sm" :color="\App\Models\Risico::scoreKleur($risico->risicoscore)">{{ $risico->risicoscore }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst(str_replace('_', ' ', $risico->status)) }}</flux:table.cell>
                    <flux:table.cell><x-gebruiker-naam :gebruiker="$risico->eigenaar" /></flux:table.cell>
                    <flux:table.cell>
                        @if ($risico->volgende_beoordeling_gepland)
                            {{ $risico->volgende_beoordeling_gepland->format('d-m-Y') }}
                            @if ($risico->herbeoordelingVerstreken())
                                <flux:badge size="sm" color="amber">Verstreken</flux:badge>
                            @endif
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            :href="route('risicos.detail', $risico)" wire:navigate>
                            Openen
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Geen risico's gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Risico toevoegen</flux:heading>
                <flux:subheading>Na toevoegen open je meteen het detailscherm om kans en impact te bepalen.</flux:subheading>
            </div>

            <flux:input wire:model="titel" label="Titel" required />
            <flux:textarea wire:model="dreiging" label="Dreiging" />
            <flux:textarea wire:model="kwetsbaarheid" label="Kwetsbaarheid" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Toevoegen &amp; openen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
