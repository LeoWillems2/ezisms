<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('management-review.index')" wire:navigate>
            Terug naar reviews
        </flux:button>
    </div>

    <div>
        <flux:heading size="xl">
            Managementreview {{ $reviewsessie->datum->format('d-m-Y') }}
            <flux:badge size="sm" :color="$reviewsessie->status === 'gehouden' ? 'green' : 'zinc'">
                {{ ucfirst($reviewsessie->status) }}
            </flux:badge>
        </flux:heading>
        <flux:subheading>Agenda (§9.3), besluiten en verbeteracties van deze directiebeoordeling.</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Basisgegevens --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Sessiegegevens</flux:heading>

        @if ($this->magMuteren())
            <form wire:submit="slaBasisgegevensOp" class="space-y-4">
                <flux:input wire:model="datum" type="date" label="Datum" required />
                <flux:textarea wire:model="deelnemers" label="Deelnemers"
                    description="Vrije tekst — wie namens de directie en het ISMS aanwezig was." />
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">Opslaan</flux:button>
                </div>
            </form>
        @else
            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs">Datum</flux:text>
                    <flux:text>{{ $reviewsessie->datum->format('d-m-Y') }}</flux:text>
                </div>
                <div class="sm:col-span-2">
                    <flux:text class="text-xs">Deelnemers</flux:text>
                    <flux:text>{{ $reviewsessie->deelnemers ?: '—' }}</flux:text>
                </div>
            </dl>
        @endif
    </div>

    {{-- Status / gehouden markeren --}}
    <div class="flex flex-wrap items-center gap-3">
        @if ($reviewsessie->status === 'gepland')
            @if ($this->magGoedkeuren())
                <flux:button variant="primary" icon="check" wire:click="markeerGehouden">Markeer als gehouden</flux:button>
            @elseif ($this->magMuteren())
                <flux:text variant="subtle">Vastleggen dat de review gehouden is, doet de directie (rol Management).</flux:text>
            @endif
            @if (($this->magGoedkeuren() || $this->magMuteren()) && $belemmering)
                <flux:text variant="subtle">Nog niet compleet: alle 9 §9.3-onderwerpen moeten een samenvatting hebben.</flux:text>
            @endif
        @else
            <flux:text variant="subtle">Deze review is vastgelegd als gehouden.</flux:text>
        @endif
    </div>

    @error('status')
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ $message }}" />
    @enderror

    {{-- Agenda (§9.3) --}}
    <div>
        <flux:heading size="lg" class="mb-1">Agenda — verplichte §9.3-onderwerpen</flux:heading>
        <flux:subheading class="mb-3">
            Elk onderwerp hoort een samenvatting te krijgen. "Niets te melden" mag, maar leg dat expliciet vast.
        </flux:subheading>

        @if ($this->magMuteren())
            <form wire:submit="slaAgendaOp" class="space-y-4">
                @foreach ($categorieen as $categorie)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm" class="mb-2">{{ ucfirst(str_replace('_', ' ', $categorie)) }}</flux:heading>
                        <flux:textarea wire:model="samenvattingen.{{ $categorie }}" rows="2"
                            placeholder="Samenvatting van dit onderwerp…" />
                        <div class="mt-2">
                            <x-keuzelijst wire:model="agendaBlokken.{{ $categorie }}" label="Bronblok (optioneel)"
                                leeg="— geen —" :opties="$blokopties" />
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">Agenda opslaan</flux:button>
                </div>
            </form>
        @else
            <div class="space-y-3">
                @foreach ($reviewsessie->agendapunten as $punt)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ ucfirst(str_replace('_', ' ', $punt->categorie)) }}</flux:heading>
                        <flux:text>{{ $punt->samenvatting }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Besluiten & verbeteracties --}}
    <div>
        <flux:heading size="lg" class="mb-3">Besluiten &amp; verbeteracties</flux:heading>

        @forelse ($besluiten as $besluit)
            <div class="mb-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="besluit-{{ $besluit->id }}">
                <div class="mb-3 flex items-start justify-between gap-4">
                    <flux:text variant="strong">{{ $besluit->omschrijving }}</flux:text>
                    @if ($this->magMuteren())
                        <flux:button size="sm" variant="ghost" icon="plus"
                            wire:click="nieuweVerbeteractie({{ $besluit->id }})">Verbeteractie</flux:button>
                    @endif
                </div>

                @if ($besluit->verbeteracties->isNotEmpty())
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Actie</flux:table.column>
                            <flux:table.column>Eigenaar</flux:table.column>
                            <flux:table.column>Deadline</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column align="end">Acties</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($besluit->verbeteracties as $actie)
                                <flux:table.row wire:key="verbeteractie-{{ $actie->id }}">
                                    <flux:table.cell>{{ \Illuminate\Support\Str::limit($actie->omschrijving, 60) }}</flux:table.cell>
                                    <flux:table.cell>{{ $actie->eigenaar?->naam ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        @php
                                            $verlopen = $actie->status === 'open' && $actie->deadline && $actie->deadline->isPast();
                                        @endphp
                                        <span class="{{ $verlopen ? 'text-red-600 dark:text-red-500' : '' }}">
                                            {{ $actie->deadline?->format('d-m-Y') ?? '—' }}
                                        </span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$actie->status === 'voltooid' ? 'green' : 'amber'">
                                            {{ ucfirst($actie->status) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        @if ($this->magMuteren())
                                            <div class="flex justify-end gap-1">
                                                <flux:button size="sm" variant="ghost"
                                                    icon="{{ $actie->status === 'voltooid' ? 'arrow-uturn-left' : 'check' }}"
                                                    wire:click="toggleVerbeteractie({{ $actie->id }})">
                                                    {{ $actie->status === 'voltooid' ? 'Heropenen' : 'Voltooid' }}
                                                </flux:button>
                                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                    wire:click="bewerkVerbeteractie({{ $actie->id }})">Bewerken</flux:button>
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text variant="subtle">Nog geen verbeteracties bij dit besluit.</flux:text>
                @endif
            </div>
        @empty
            <flux:text variant="subtle">Nog geen besluiten vastgelegd.</flux:text>
        @endforelse

        @if ($this->magMuteren())
            <form wire:submit="voegBesluitToe" class="mt-3 flex items-end gap-2">
                <flux:input wire:model="besluitOmschrijving" label="Nieuw besluit" class="flex-1" />
                <flux:button variant="primary" type="submit">Toevoegen</flux:button>
            </form>
        @endif
    </div>

    {{-- Verbeteractie-formulier --}}
    <flux:modal wire:model.self="toontVerbeteractieFormulier" class="md:w-[32rem]">
        <form wire:submit="slaVerbeteractieOp" class="space-y-6">
            <flux:heading size="lg">{{ $bewerktVerbeteractieId ? 'Verbeteractie bewerken' : 'Nieuwe verbeteractie' }}</flux:heading>
            <flux:textarea wire:model="vaOmschrijving" label="Omschrijving" required />
            <x-keuzelijst wire:model="vaEigenaarId" label="Eigenaar" leeg="— geen —" :opties="$gebruikers"
                description="Krijgt via /taken een herinnering op de deadline." />
            <flux:input wire:model="vaDeadline" type="date" label="Deadline" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontVerbeteractieFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
