<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:link :href="route('leveranciers.index')" wire:navigate class="text-sm">&larr; Terug naar leveranciers</flux:link>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($magHeractiveren)
        <flux:callout variant="secondary" icon="lock-closed"
            heading="Deze leverancier is beëindigd en daarom alleen-lezen.">
            <flux:callout.text>Gebruik &ldquo;Heractiveren&rdquo; onder Beëindiging om weer te kunnen wijzigen.</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Kop --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading size="xl">{{ $leverancier->naam }}</flux:heading>
        @php
            $statusKleur = match ($leverancier->status) {
                'actief' => 'green', 'beeindigd' => 'zinc', default => 'amber',
            };
        @endphp
        <flux:badge :color="$statusKleur">{{ ucfirst($leverancier->status) }}</flux:badge>
        @if ($leverancier->risiconiveau)
            @php
                $niveauKleur = match ($leverancier->risiconiveau) {
                    'hoog' => 'red', 'midden' => 'amber', default => 'zinc',
                };
            @endphp
            <flux:badge :color="$niveauKleur">Risico: {{ ucfirst($leverancier->risiconiveau) }}</flux:badge>
        @endif
        @unless ($leverancier->heeftRechtOpAudit())
            @if ($leverancier->risiconiveau === 'hoog')
                <flux:badge color="red">Geen recht op audit</flux:badge>
            @endif
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Basisgegevens --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Basisgegevens</flux:heading>
            @if ($magMuteren)
                <form wire:submit="slaBasisgegevensOp" class="space-y-4">
                    <flux:input wire:model="naam" label="Naam" required />
                    <flux:select wire:model="risiconiveau" label="Risiconiveau">
                        <flux:select.option value="">— nog niet bepaald —</flux:select.option>
                        <flux:select.option value="laag">Laag</flux:select.option>
                        <flux:select.option value="midden">Midden</flux:select.option>
                        <flux:select.option value="hoog">Hoog</flux:select.option>
                    </flux:select>
                    <flux:input type="date" wire:model="eigenCertificeringGeldigTot"
                        :label="'Eigen '.$norm->leverancierscertificaat.'-certificaat geldig tot'"
                        description="Een geldig certificaat telt — naast een contractclausule — als recht op audit." />
                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">Opslaan</flux:button>
                    </div>
                </form>
            @else
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-zinc-500">Naam</dt><dd>{{ $leverancier->naam }}</dd>
                    <dt class="text-zinc-500">Risiconiveau</dt><dd>{{ $leverancier->risiconiveau ? ucfirst($leverancier->risiconiveau) : '—' }}</dd>
                    <dt class="text-zinc-500">Certificaat geldig tot</dt>
                    <dd>{{ $leverancier->eigen_certificering_geldig_tot?->format('d-m-Y') ?? '—' }}</dd>
                </dl>
            @endif
        </flux:card>

        {{-- Beëindigen / teruggavecontrole --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Beëindiging</flux:heading>
            @if ($leverancier->status === 'beeindigd')
                <flux:callout variant="secondary" icon="check-circle" heading="Beëindigd">
                    <flux:callout.text>
                        Op {{ $leverancier->beeindigd_op?->format('d-m-Y') }};
                        data-teruggave bevestigd
                        @if ($leverancier->teruggaveDoor) door {{ $leverancier->teruggaveDoor->naam }} @endif
                        op {{ $leverancier->data_teruggave_bevestigd_op?->format('d-m-Y') }}.
                    </flux:callout.text>
                </flux:callout>
                @if ($magHeractiveren)
                    <div>
                        <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="heractiveren"
                            wire:confirm="Deze leverancier opnieuw activeren? De teruggavebevestiging vervalt.">
                            Heractiveren
                        </flux:button>
                    </div>
                @endif
            @elseif ($magMuteren)
                <flux:text>
                    Een leverancier mag pas beëindigd worden nadat is bevestigd dat data en
                    toegang zijn teruggegeven of vernietigd ({{ $norm->bijlage }} 5.22).
                </flux:text>
                <flux:checkbox wire:model.live="dataTeruggaveBevestigd"
                    label="Data en toegang zijn teruggegeven of vernietigd" />
                @error('dataTeruggaveBevestigd')
                    <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
                <div>
                    <flux:button variant="danger" wire:click="beeindig" :disabled="$belemmering !== null">
                        Leverancier beëindigen
                    </flux:button>
                </div>
            @else
                <flux:text>Actief.</flux:text>
            @endif
        </flux:card>
    </div>

    {{-- Contractclausules --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Contractclausules</flux:heading>
        <flux:subheading>De securityrelevante clausules ({{ $norm->bijlage }} 5.19–5.23) — geen volledig contractbeheer.</flux:subheading>
        <div class="flex flex-wrap gap-3">
            @foreach ($clausuletypes as $type => $label)
                @php $aanwezig = (bool) ($clausuleAanwezig[$type] ?? false); @endphp
                @if ($magMuteren)
                    <flux:button size="sm" wire:click="wisselClausule('{{ $type }}')"
                        :variant="$aanwezig ? 'primary' : 'ghost'"
                        :icon="$aanwezig ? 'check' : 'x-mark'">
                        {{ $label }}
                    </flux:button>
                @else
                    <flux:badge :color="$aanwezig ? 'green' : 'zinc'">
                        {{ $label }}: {{ $aanwezig ? 'aanwezig' : 'afwezig' }}
                    </flux:badge>
                @endif
            @endforeach
        </div>
    </flux:card>

    {{-- Diensten --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Diensten</flux:heading>

        @forelse ($diensten as $dienst)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 pb-3 dark:border-zinc-700"
                wire:key="dienst-{{ $dienst->id }}">
                <div class="space-y-1">
                    <flux:text class="font-medium">{{ $dienst->omschrijving }}</flux:text>
                    <div class="flex flex-wrap items-center gap-2">
                        @forelse ($dienst->systemen as $systeem)
                            <flux:badge size="sm" color="blue" wire:key="dienst-{{ $dienst->id }}-sys-{{ $systeem->id }}">
                                {{ $systeem->naam }}
                                @if ($magMuteren)
                                    <button type="button" class="ml-1"
                                        wire:click="ontkoppelSysteem({{ $dienst->id }}, {{ $systeem->id }})">&times;</button>
                                @endif
                            </flux:badge>
                        @empty
                            <flux:text class="text-sm text-zinc-500">Geen systeem gekoppeld</flux:text>
                        @endforelse
                    </div>
                </div>
                @if ($magMuteren)
                    <div class="flex items-center gap-2">
                        <select class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-700"
                            wire:change="koppelSysteem({{ $dienst->id }}, $event.target.value)">
                            <option value="">Koppel systeem…</option>
                            @foreach ($systemen as $systeem)
                                <option value="{{ $systeem->id }}">{{ $systeem->naam }}</option>
                            @endforeach
                        </select>
                        <flux:button size="sm" variant="ghost" icon="trash"
                            wire:click="verwijderDienst({{ $dienst->id }})"
                            wire:confirm="Deze dienst verwijderen?" />
                    </div>
                @endif
            </div>
        @empty
            <flux:text>Nog geen diensten vastgelegd.</flux:text>
        @endforelse

        @if ($magMuteren)
            <form wire:submit="voegDienstToe" class="space-y-3 pt-2">
                <flux:input wire:model="dienstOmschrijving" label="Nieuwe dienst" placeholder="Omschrijving" />
                @if ($systemen->isNotEmpty())
                    <div>
                        <flux:label>Koppel systemen (optioneel)</flux:label>
                        <div class="mt-1 flex flex-wrap gap-3">
                            @foreach ($systemen as $systeem)
                                <flux:checkbox wire:model="dienstSystemen" value="{{ $systeem->id }}"
                                    label="{{ $systeem->naam }}" />
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit" icon="plus">Dienst toevoegen</flux:button>
                </div>
            </form>
        @endif
    </flux:card>

    {{-- Beoordelingen --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Beoordelingen</flux:heading>

        @if ($magMuteren)
            <form wire:submit="voegBeoordelingToe" class="grid gap-3 sm:grid-cols-2">
                <flux:input type="date" wire:model="beoordelingUitgevoerdOp" label="Uitgevoerd op" required />
                <flux:input type="date" wire:model="beoordelingVolgende" label="Volgende beoordeling gepland" />
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="beoordelingBevindingen" label="Bevindingen" rows="2" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <flux:button variant="primary" type="submit" icon="plus">Beoordeling toevoegen</flux:button>
                </div>
            </form>
        @endif

        @forelse ($beoordelingen as $beoordeling)
            <div class="border-b border-zinc-100 pb-3 dark:border-zinc-700" wire:key="beoordeling-{{ $beoordeling->id }}">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <flux:text class="font-medium">{{ $beoordeling->uitgevoerd_op?->format('d-m-Y') }}</flux:text>
                    @if ($beoordeling->uitvoerder)
                        <flux:text class="text-zinc-500">door {{ $beoordeling->uitvoerder->naam }}</flux:text>
                    @endif
                    @if ($beoordeling->volgende_beoordeling_gepland)
                        <flux:badge size="sm" :color="$beoordeling->volgende_beoordeling_gepland->isPast() ? 'red' : 'zinc'">
                            volgende: {{ $beoordeling->volgende_beoordeling_gepland->format('d-m-Y') }}
                        </flux:badge>
                    @endif
                </div>
                @if ($beoordeling->bevindingen)
                    <flux:text class="mt-1 text-sm">{{ $beoordeling->bevindingen }}</flux:text>
                @endif
            </div>
        @empty
            <flux:text>Nog geen beoordelingen. De eerste beoordeling activeert de leverancier.</flux:text>
        @endforelse
    </flux:card>

    {{-- Gekoppelde risico's (read-only) --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Gekoppelde risico's</flux:heading>
        <flux:subheading>Leveranciersrisico loopt via het gewone risicoregister (blok 4).</flux:subheading>
        @forelse ($risicos as $risico)
            <div class="flex items-center justify-between text-sm" wire:key="risico-{{ $risico->id }}">
                <flux:text>{{ $risico->titel }}</flux:text>
                <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', $risico->status) }}</flux:badge>
            </div>
        @empty
            <flux:text>Geen gekoppelde risico's.</flux:text>
        @endforelse
    </flux:card>

    {{-- Bewijsstukken (blok 6) --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">Bewijsstukken</flux:heading>
        <livewire:bewijs-paneel blok-naam="leveranciers-derdenrisico" entiteit-type="leverancier"
            :entiteit-id="$leverancier->id" :wire:key="'bewijs-leverancier-'.$leverancier->id" />
    </flux:card>
</div>
