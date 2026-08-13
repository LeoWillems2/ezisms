<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('incidenten.index')" wire:navigate>
            Terug naar incidenten
        </flux:button>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- 1. Kop. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="xl">{{ $incident->titel }}</flux:heading>
        <flux:subheading>
            Gemeld door {{ $incident->melder?->naam ?? 'onbekend' }} op
            {{ $incident->gemeld_op->lokaal()->format('d-m-Y H:i') }} &middot; ernst {{ $incident->ernst }}
        </flux:subheading>

        @if ($incident->omschrijving)
            <flux:text class="mt-3">{{ $incident->omschrijving }}</flux:text>
        @endif

        @if ($incident->gesloten_op)
            <flux:callout variant="success" icon="check-circle" class="mt-4"
                heading="Gesloten door {{ $incident->sluiter?->naam ?? 'onbekend' }} op {{ $incident->gesloten_op->lokaal()->format('d-m-Y H:i') }} ({{ $incident->doorlooptijdInDagen() }} dagen)">
                @if ($incident->geen_afwijking_reden)
                    <flux:callout.text>
                        Geen corrigerende maatregel: {{ $incident->geen_afwijking_reden }}
                    </flux:callout.text>
                @endif
            </flux:callout>
        @endif

        @if ($this->magMuteren())
            <flux:separator class="my-5" />

            <div class="grid gap-4 md:grid-cols-3">
                <x-keuzelijst wire:model="status" label="Status"
                    :opties="['gemeld' => 'Gemeld', 'in_onderzoek' => 'In onderzoek', 'opgelost' => 'Opgelost', 'gesloten' => 'Gesloten']" />

                <x-keuzelijst wire:model="assetId" label="Betreft asset" :opties="$assets"
                    leeg="— geen asset —" />

                <x-keuzelijst wire:model="risicoId" label="Gerealiseerd risico" :opties="$risicos"
                    leeg="— geen risico —" />
            </div>

            {{-- Het besluit dat §10.1 bedoelt, expliciet gemaakt. Alleen zichtbaar
                 wanneer het aan de orde is: er is geen afwijking geopend en de
                 gebruiker staat op het punt te sluiten (implementatie/08 §6). --}}
            @if ($afwijkingen->isEmpty() && in_array($status, ['opgelost', 'gesloten'], true))
                <div class="mt-4">
                    <flux:textarea wire:model="geenAfwijkingReden"
                        label="Waarom vergt dit incident geen corrigerende maatregel?"
                        description="Verplicht om te kunnen sluiten zonder afwijking. Dit is het besluit zelf, geen toelichting achteraf — een auditor leest hier of de vraag gesteld is." />
                </div>
            @endif

            @if ($belemmering !== null && $status === 'gesloten')
                <flux:callout icon="information-circle" class="mt-4" heading="Sluiten kan nog niet">
                    <flux:callout.text>{{ $belemmering }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:error name="status" />

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="opslaan">Opslaan</flux:button>
                <flux:button wire:click="openAfwijking"
                    wire:confirm="Er wordt een afwijking aangemaakt die de CAPA-cyclus in gaat. Doorgaan?">
                    Afwijking openen
                </flux:button>
            </div>
        @else
            <flux:badge class="mt-3" size="sm">{{ ucfirst(str_replace('_', ' ', $incident->status)) }}</flux:badge>
        @endif
    </div>

    {{-- 2. Externe meldplicht (implementatie/08b). --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">Externe meldplicht</flux:heading>
        <flux:text class="mb-4">
            Eerst de vraag of dit incident de meldplicht raakt. Raakt het geen persoonsgegevens
            @if ($this->cbwPlichtig()) en geen netwerk- of informatiesystemen @endif,
            dan is er geen documentatieplicht en hoeft er niets gemotiveerd te worden.
        </flux:text>

        @if ($this->magMuteren())
            <div class="flex flex-col gap-4">
                <div>
                    <x-keuzelijst wire:model.live="raaktPersoonsgegevens"
                        label="Raakt dit incident persoonsgegevens?"
                        leeg="— nog niet beoordeeld —" :opties="['1' => 'Ja', '0' => 'Nee']" />

                    @if ($assetsignaal)
                        <div class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-950/40">
                            <flux:text>{{ $assetsignaal }}</flux:text>
                        </div>
                    @endif
                </div>

                {{-- Alleen zichtbaar als de organisatie Cbw-plichtig is. Dat is
                     een instelling (config/meldplicht.php): een juridisch oordeel
                     dat je één keer maakt, niet per incident. --}}
                @if ($this->cbwPlichtig())
                    <x-keuzelijst wire:model.live="isNetwerkInformatieIncident"
                        label="Is dit een incident in netwerk- of informatiesystemen?"
                        leeg="— nog niet beoordeeld —" :opties="['1' => 'Ja', '0' => 'Nee']" />
                @endif

                @if ($this->heeftDocumentatieplicht())
                    <div class="flex flex-col gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:text>
                            <span class="font-medium">Onverwijld melden</span> staat in beide wetten
                            voorop; het aantal uren is de buitengrens, geen wachttijd. De termijnen
                            lopen vanaf kennisname — niet vanaf de registratie hier.
                        </flux:text>

                        <flux:input type="datetime-local" wire:model="kennisnameOp"
                            label="Kennisname door de organisatie"
                            description="Het wettelijke startpunt (AVG art. 33 lid 1, Cbw art. 26/27). Corrigeerbaar zodra het onderzoek dit scherper maakt." />

                        <x-keuzelijst wire:model.live="externMeldingsplichtig" label="Extern meldingsplichtig?"
                            leeg="— nog niet beoordeeld —" :opties="['1' => 'Ja', '0' => 'Nee']" />

                        <flux:textarea wire:model="meldplichtMotivatie"
                            label="Motivatie"
                            description="Ook verplicht bij 'nee'. Het oordeel dat een risico onwaarschijnlijk is (AVG art. 33 lid 1) of dat een incident niet significant is, heeft criteria en hoort navolgbaar te zijn." />

                        @if ($raaktPersoonsgegevens === '1' && $externMeldingsplichtig === '1')
                            <flux:checkbox wire:model="mededelingBetrokkenen"
                                label="Hoog risico: ook mededeling aan de betrokkenen (AVG art. 34)" />
                        @endif

                        @php
                            $grondslagLabels = array_filter([
                                $raaktPersoonsgegevens === '1' ? 'AVG' : null,
                                $isNetwerkInformatieIncident === '1' ? 'Cyberbeveiligingswet' : null,
                            ]);
                        @endphp
                        <flux:text class="text-sm">
                            Grondslag volgt uit de antwoorden hierboven:
                            <span class="font-medium">{{ implode(' + ', $grondslagLabels) }}</span>.
                        </flux:text>
                    </div>
                @endif

                <div>
                    <flux:button variant="primary" wire:click="beoordeelMeldplicht">Beoordeling vastleggen</flux:button>
                </div>
            </div>
        @else
            {{-- De melder ziet de stand en de termijnen, niet de motivatie: die
                 bevat een inschatting (kans op risico voor betrokkenen,
                 significantie) die bij de beoordelaar hoort. --}}
            <flux:text>
                @if ($incident->raakt_persoonsgegevens === null)
                    Nog niet beoordeeld.
                @elseif (! $incident->heeftDocumentatieplicht())
                    Beoordeeld: dit incident raakt de externe meldplicht niet.
                @elseif ($incident->extern_meldingsplichtig === null)
                    Raakt de meldplicht; de beoordeling loopt nog.
                @else
                    {{ $incident->extern_meldingsplichtig ? 'Extern meldingsplichtig.' : 'Beoordeeld: niet extern meldingsplichtig.' }}
                @endif
            </flux:text>
        @endif

        @if ($meldingen->isNotEmpty())
            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:heading size="sm" class="mb-3">Verplichtingen</flux:heading>

                @foreach ($meldingen as $melding)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 py-3 first:border-t-0 dark:border-zinc-800">
                        <div>
                            <flux:text class="font-medium">
                                {{ strtoupper($melding->grondslag) }} — {{ $melding->label() }}
                            </flux:text>
                            <flux:text class="text-sm">
                                {{ $melding->artikel() }}
                                @if ($melding->uiterlijk_op)
                                    · uiterlijk {{ $melding->uiterlijk_op->lokaal()->format('d-m-Y H:i') }}
                                @else
                                    {{-- Geen berekende datum tonen die stilzwijgend opschuift. --}}
                                    · verplicht, nog geen datum
                                @endif
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($melding->isGemeld())
                                <flux:badge size="sm" :color="$melding->isTeLaat() ? 'red' : 'green'">
                                    {{ $melding->isTeLaat() ? 'Te laat gemeld' : 'Gemeld' }}
                                    {{ $melding->gemeld_op->lokaal()->format('d-m-Y H:i') }}
                                </flux:badge>
                            @else
                                <flux:badge size="sm" :color="$melding->isTeLaat() ? 'red' : 'amber'">
                                    {{ $melding->isTeLaat() ? 'Termijn verstreken' : 'Open' }}
                                </flux:badge>
                                @if ($this->magMuteren())
                                    <flux:button size="sm" wire:click="meldingGedaan({{ $melding->id }})">
                                        Gemeld
                                    </flux:button>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 3. Afwijkingen die hieruit voortkwamen. --}}
    @if ($afwijkingen->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">Afwijkingen uit dit incident</flux:heading>

            @foreach ($afwijkingen as $afwijking)
                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800">
                    @if ($this->magMuteren())
                        <flux:link :href="route('afwijkingen.detail', $afwijking)" wire:navigate>
                            {{ $afwijking->auditOmschrijving() }}
                        </flux:link>
                    @else
                        <flux:text>{{ $afwijking->auditOmschrijving() }}</flux:text>
                    @endif
                    <flux:badge size="sm" :color="$afwijking->isGesloten() ? 'green' : 'amber'">
                        {{ ucfirst(str_replace('_', ' ', $afwijking->status)) }}
                    </flux:badge>
                </div>
            @endforeach
        </div>
    @endif

    {{-- 4. Bewijs (blok 6). --}}
    <livewire:bewijs-paneel blok-naam="incident-afwijkingenbeheer" entiteit-type="incident"
        :entiteit-id="$incident->id" />
</div>
