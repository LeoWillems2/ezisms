<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.context-subnav')

    <div>
        <flux:heading size="xl">Scope-verklaring</flux:heading>
        <flux:subheading>De formele ISMS-scope met gemotiveerde uitsluitingen en interfaces ({{ $norm->naam_kort }} §4.3).</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif
    @if (session('fout'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    @php
        $actief = $this->actieveVersie;
        $werk = $this->werkVersie;
    @endphp

    {{-- Actieve versie --}}
    @if ($actief)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <flux:badge color="green">Actief — versie {{ $actief->versienummer }}</flux:badge>
                <flux:text>Geldig sinds {{ $actief->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:text>
                @if ($actief->herzieningVerstreken())
                    <flux:badge color="red" icon="exclamation-triangle">Herziening verstreken</flux:badge>
                @elseif ($actief->volgende_herziening_gepland)
                    <flux:text>· volgende herziening {{ $actief->volgende_herziening_gepland->format('d-m-Y') }}</flux:text>
                @endif
            </div>

            @if ($actief->herzieningVerstreken())
                <flux:callout class="mt-3" variant="warning" icon="exclamation-triangle"
                    heading="De geplande herziening van de scope is verstreken."
                    text="Een auditor verwacht periodieke herziening. Start een nieuwe conceptversie om de scope opnieuw vast te stellen." />
            @endif

            <div class="mt-4 whitespace-pre-line">
                <flux:text>{{ $actief->scopetekst }}</flux:text>
            </div>

            @if ($actief->organisatieEenheden->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach ($actief->organisatieEenheden as $eenheid)
                        <flux:badge size="sm" color="zinc">{{ $eenheid->naam }}</flux:badge>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex gap-2">
                {{-- Read-only inzage, ook voor lezers zonder muteerrecht. --}}
                <flux:button variant="ghost" icon="eye" wire:click="bekijkVersie({{ $actief->id }})">
                    Volledige versie bekijken
                </flux:button>

                @if ($this->magMuteren() && ! $werk)
                    <flux:button variant="primary" icon="plus" wire:click="nieuweConceptversieStarten">
                        Nieuwe conceptversie starten
                    </flux:button>
                @endif
            </div>
        </div>
    @elseif (! $werk)
        <flux:callout icon="information-circle" heading="Er is nog geen scope-verklaring."
            text="Stel de eerste versie op om de grens van het ISMS vast te leggen." />
        @if ($this->magMuteren())
            <div>
                <flux:button variant="primary" icon="plus" wire:click="eersteVersieOpstellen">
                    Eerste scope-versie opstellen
                </flux:button>
            </div>
        @endif
    @endif

    {{-- Werkversie: concept (bewerkbaar) of ter_goedkeuring (alleen-lezen + activeren) --}}
    @if ($werk)
        <div class="rounded-xl border-2 border-dashed border-zinc-300 p-5 dark:border-zinc-600">
            <div class="mb-4 flex items-center gap-3">
                <flux:badge :color="$werk->status === 'concept' ? 'amber' : 'blue'">
                    {{ $werk->status === 'concept' ? 'Concept' : 'Ter goedkeuring' }} — versie {{ $werk->versienummer }}
                </flux:badge>
            </div>

            @if ($werk->isBewerkbaar())
                {{-- Bewerkformulier --}}
                <form wire:submit="conceptOpslaan" class="flex flex-col gap-4">
                    <flux:textarea wire:model="scopetekst" label="Scopetekst" rows="5"
                        description="Beschrijf welke delen van de organisatie binnen het ISMS vallen." />

                    <flux:checkbox.group wire:model="geselecteerdeEenheden" label="Organisatie-eenheden binnen scope">
                        @forelse ($eenheden as $eenheid)
                            <flux:checkbox value="{{ $eenheid->id }}" label="{{ $eenheid->naam }} ({{ $eenheid->type }})" />
                        @empty
                            <flux:text>Nog geen organisatie-eenheden vastgelegd.</flux:text>
                        @endforelse
                    </flux:checkbox.group>

                    <flux:checkbox.group wire:model="geselecteerdeIssues" label="Relevante issues">
                        @forelse ($issues as $issue)
                            <flux:checkbox value="{{ $issue->id }}" label="{{ $issue->categorie }} — {{ \Illuminate\Support\Str::limit($issue->omschrijving, 40) }}" />
                        @empty
                            <flux:text>Nog geen issues vastgelegd.</flux:text>
                        @endforelse
                    </flux:checkbox.group>

                    <flux:checkbox.group wire:model="geselecteerdeBelanghebbenden" label="Relevante belanghebbenden">
                        @forelse ($belanghebbenden as $belanghebbende)
                            <flux:checkbox value="{{ $belanghebbende->id }}" label="{{ $belanghebbende->naam }}" />
                        @empty
                            <flux:text>Nog geen belanghebbenden vastgelegd.</flux:text>
                        @endforelse
                    </flux:checkbox.group>

                    <div>
                        <flux:button type="submit" variant="primary">Concept opslaan</flux:button>
                    </div>
                </form>

                {{-- Uitsluitingen --}}
                <flux:separator class="my-6" />
                <flux:heading size="sm">Uitsluitingen</flux:heading>
                <flux:text class="mb-3">Elke uitsluiting vereist een motivatie — dat is een harde eis van §4.3.</flux:text>

                @forelse ($werk->uitsluitingen as $uitsluiting)
                    <div wire:key="uitsluiting-{{ $uitsluiting->id }}" class="mb-2 flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div>
                            <flux:text class="font-medium">{{ $uitsluiting->omschrijving }}</flux:text>
                            <flux:text>Motivatie: {{ $uitsluiting->motivatie }}</flux:text>
                        </div>
                        <flux:button size="xs" variant="ghost" icon="trash"
                            wire:click="uitsluitingVerwijderen({{ $uitsluiting->id }})" />
                    </div>
                @empty
                    <flux:text class="mb-2">Geen uitsluitingen.</flux:text>
                @endforelse

                <form wire:submit="uitsluitingToevoegen" class="mt-3 flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:input wire:model="nieuweUitsluitingOmschrijving" label="Omschrijving uitsluiting" />
                    <flux:input wire:model="nieuweUitsluitingMotivatie" label="Motivatie" required />
                    <div><flux:button size="sm" type="submit" icon="plus">Uitsluiting toevoegen</flux:button></div>
                </form>

                {{-- Interfaces --}}
                <flux:separator class="my-6" />
                <flux:heading size="sm">Interfaces naar buiten-scope onderdelen</flux:heading>

                @forelse ($werk->interfaces as $interface)
                    <div wire:key="interface-{{ $interface->id }}" class="mb-2 flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div>
                            <flux:text class="font-medium">{{ $interface->omschrijving }}</flux:text>
                            @if ($interface->risico_implicatie)
                                <flux:text>Risico-implicatie: {{ $interface->risico_implicatie }}</flux:text>
                            @endif
                        </div>
                        <flux:button size="xs" variant="ghost" icon="trash"
                            wire:click="interfaceVerwijderen({{ $interface->id }})" />
                    </div>
                @empty
                    <flux:text class="mb-2">Geen interfaces.</flux:text>
                @endforelse

                <form wire:submit="interfaceToevoegen" class="mt-3 flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:input wire:model="nieuweInterfaceOmschrijving" label="Omschrijving interface"
                        description="Bijv. IT-beheer door een externe partij." />
                    <flux:input wire:model="nieuweInterfaceRisico" label="Risico-implicatie (optioneel)" />
                    <div><flux:button size="sm" type="submit" icon="plus">Interface toevoegen</flux:button></div>
                </form>

                {{-- Indienen --}}
                <flux:separator class="my-6" />
                <flux:button variant="primary" wire:click="indienenTerGoedkeuring">Indienen ter goedkeuring</flux:button>
            @else
                {{-- ter_goedkeuring: alleen-lezen weergave + activeren --}}
                <div class="whitespace-pre-line"><flux:text>{{ $werk->scopetekst }}</flux:text></div>

                @if ($werk->uitsluitingen->isNotEmpty())
                    <flux:heading size="sm" class="mt-4">Uitsluitingen</flux:heading>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($werk->uitsluitingen as $uitsluiting)
                            <li><flux:text>{{ $uitsluiting->omschrijving }} — {{ $uitsluiting->motivatie }}</flux:text></li>
                        @endforeach
                    </ul>
                @endif

                @if ($this->magGoedkeuren() || $this->magMuteren())
                    <flux:separator class="my-6" />
                    <div class="flex flex-col gap-3">
                        @if ($this->magGoedkeuren())
                            <flux:input wire:model="goedgekeurdDoor" label="Goedgekeurd door"
                                description="Namens wie is deze versie goedgekeurd (bijv. directie)?" />
                        @else
                            <flux:text variant="subtle">
                                Activeren doet de directie (rol Management); deze versie staat klaar ter goedkeuring.
                            </flux:text>
                        @endif
                        <div class="flex gap-2">
                            @if ($this->magGoedkeuren())
                                <flux:button variant="primary" wire:click="activeren"
                                    wire:confirm="Deze versie activeren? De huidige actieve versie wordt daarmee vervangen.">
                                    Activeren
                                </flux:button>
                            @endif
                            @if ($this->magMuteren())
                                <flux:button variant="ghost" wire:click="terugNaarConcept">Terug naar concept</flux:button>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Versiehistorie --}}
    @if ($historie->isNotEmpty())
        <div>
            <flux:heading size="lg">Versiehistorie</flux:heading>
            <flux:text class="mb-3">Vervangen versies blijven bewaard als auditbewijs.</flux:text>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Versie</flux:table.column>
                    <flux:table.column>Geldig vanaf</flux:table.column>
                    <flux:table.column>Goedgekeurd door</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($historie as $versie)
                        <flux:table.row wire:key="historie-{{ $versie->id }}">
                            <flux:table.cell variant="strong">{{ $versie->versienummer }}</flux:table.cell>
                            <flux:table.cell>{{ $versie->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $versie->goedgekeurd_door ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="bekijkVersie({{ $versie->id }})">
                                    Bekijken
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Read-only detailweergave van een willekeurige versie --}}
    <flux:modal wire:model.self="toontDetail" class="md:w-[40rem]">
        @php $bekeken = $this->bekekenVersie; @endphp
        @if ($bekeken)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">Scope-versie {{ $bekeken->versienummer }}</flux:heading>
                    @php
                        $statusKleur = match ($bekeken->status) {
                            'actief' => 'green',
                            'concept' => 'amber',
                            'ter_goedkeuring' => 'blue',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:badge size="sm" :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $bekeken->status)) }}</flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <flux:text>Geldig vanaf: {{ $bekeken->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:text>
                    <flux:text>Goedgekeurd door: {{ $bekeken->goedgekeurd_door ?? '—' }}</flux:text>
                    <flux:text>Volgende herziening: {{ $bekeken->volgende_herziening_gepland?->format('d-m-Y') ?? '—' }}</flux:text>
                </div>

                <div>
                    <flux:heading size="sm">Scopetekst</flux:heading>
                    <div class="mt-1 whitespace-pre-line"><flux:text>{{ $bekeken->scopetekst ?: '—' }}</flux:text></div>
                </div>

                <div>
                    <flux:heading size="sm">Organisatie-eenheden</flux:heading>
                    @if ($bekeken->organisatieEenheden->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($bekeken->organisatieEenheden as $eenheid)
                                <flux:badge size="sm" color="zinc">{{ $eenheid->naam }}</flux:badge>
                            @endforeach
                        </div>
                    @else
                        <flux:text>—</flux:text>
                    @endif
                </div>

                <div>
                    <flux:heading size="sm">Uitsluitingen</flux:heading>
                    @forelse ($bekeken->uitsluitingen as $uitsluiting)
                        <flux:text class="mt-1">• {{ $uitsluiting->omschrijving }} — <span class="italic">{{ $uitsluiting->motivatie }}</span></flux:text>
                    @empty
                        <flux:text>—</flux:text>
                    @endforelse
                </div>

                <div>
                    <flux:heading size="sm">Interfaces</flux:heading>
                    @forelse ($bekeken->interfaces as $interface)
                        <flux:text class="mt-1">• {{ $interface->omschrijving }}{{ $interface->risico_implicatie ? ' — '.$interface->risico_implicatie : '' }}</flux:text>
                    @empty
                        <flux:text>—</flux:text>
                    @endforelse
                </div>

                @if ($bekeken->issues->isNotEmpty() || $bekeken->belanghebbenden->isNotEmpty())
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:heading size="sm">Issues</flux:heading>
                            @foreach ($bekeken->issues as $issue)
                                <flux:text class="mt-1">• {{ $issue->categorie }}</flux:text>
                            @endforeach
                        </div>
                        <div>
                            <flux:heading size="sm">Belanghebbenden</flux:heading>
                            @foreach ($bekeken->belanghebbenden as $belanghebbende)
                                <flux:text class="mt-1">• {{ $belanghebbende->naam }}</flux:text>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="sluitBekekenVersie">Sluiten</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
