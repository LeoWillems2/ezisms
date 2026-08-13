<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Beleid &amp; procedures</flux:heading>
            <flux:subheading>
                Vastgesteld beleid met versiehistorie, goedkeuring en leesbevestiging (A.5.1).
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwDocument">Nieuw document</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- De taak in blok 7 herinnert eraan; dit maakt het afhandelbaar zonder
         zoeken (implementatie/05 §10). --}}
    @php
        $openstaand = $this->openstaandeBevestigingen();
    @endphp
    @if ($openstaand > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $openstaand }} document(en) wachten op uw leesbevestiging" />
    @endif

    @if ($metNietActieveEigenaar > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $metNietActieveEigenaar }} document(en) met een niet-actieve eigenaar">
            <flux:callout.text>
                De documenteigenaar is gedeactiveerd, geblokkeerd of nog niet actief. Wijs een
                actief account aan, zodat het beheer van het document belegd blijft.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <x-keuzelijst wire:model.live="filterStatus" label="Status" class="max-w-56"
            leeg="Alle statussen"
            :opties="['actief' => 'Actief', 'concept' => 'Concept', 'ter_goedkeuring' => 'Ter goedkeuring', 'ingetrokken' => 'Ingetrokken']" />

        <x-keuzelijst wire:model.live="filterType" label="Type" class="max-w-56"
            leeg="Alle typen"
            :opties="['beleid' => 'Beleid', 'procedure' => 'Procedure']" />
    </div>

    @if ($toontFormulier)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">
                {{ $bewerktId ? 'Document bewerken' : 'Nieuw beleidsdocument' }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="titel" label="Titel" required />

                <x-keuzelijst wire:model.live="type" label="Type" required
                    :opties="['beleid' => 'Beleid', 'procedure' => 'Procedure']" />

                <flux:textarea wire:model="omschrijving" label="Omschrijving" class="md:col-span-2" />

                <x-keuzelijst wire:model="eigenaarId" label="Eigenaar" :opties="$gebruikers"
                    leeg="— geen eigenaar —" />

                <div class="flex items-end">
                    <flux:checkbox wire:model.live="leesbevestigingVereist"
                        label="Leesbevestiging vereist"
                        description="Aanzetten legt bij de eerstvolgende nachtelijke run een taak neer bij de gekozen afdelingen. Standaard aan bij beleid, uit bij een procedure." />
                </div>

                @if ($leesbevestigingVereist)
                    <div class="md:col-span-2">
                        <flux:text class="mb-2 font-medium">Afdelingen die moeten bevestigen</flux:text>

                        @if (count($afdelingen) === 0)
                            <flux:text class="text-sm">
                                Er zijn nog geen afdelingen. Maak eerst een organisatie-eenheid van
                                type &ldquo;afdeling&rdquo; aan onder Organisatie-eenheden.
                            </flux:text>
                        @else
                            <flux:checkbox.group wire:model="afdelingIds" class="grid gap-1 md:grid-cols-2">
                                @foreach ($afdelingen as $id => $naam)
                                    <flux:checkbox :value="$id" :label="$naam" />
                                @endforeach
                            </flux:checkbox.group>
                        @endif

                        @error('afdelingIds')
                            <flux:text class="mt-1 text-sm text-red-600 dark:text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="opslaan">Opslaan</flux:button>
                <flux:button variant="ghost" wire:click="sluitFormulier">Annuleren</flux:button>
            </div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Actieve versie</flux:table.column>
            <flux:table.column>Herziening</flux:table.column>
            <flux:table.column>Bevestigd</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($documenten as $document)
                @php
                    $versie = $document->actieveVersie;
                    $graad = $document->bevestigingsgraad();
                @endphp
                <flux:table.row wire:key="doc-{{ $document->id }}">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('beleid.detail', $document)" wire:navigate>
                            {{ $document->titel }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst($document->type) }}</flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($document->status) {
                                'actief' => 'green',
                                'ter_goedkeuring' => 'blue',
                                'ingetrokken' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">
                            {{ ucfirst(str_replace('_', ' ', $document->status)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $versie ? 'v'.$versie->versienummer : '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($versie?->volgende_herziening_gepland)
                            @php
                                $verstreken = $versie->herzieningVerstreken();
                                $bijna = ! $verstreken
                                    && $versie->volgende_herziening_gepland->isBefore(now()->addDays(30));
                                $kleur = $verstreken ? 'text-red-600 dark:text-red-500'
                                    : ($bijna ? 'text-amber-600 dark:text-amber-500' : '');
                            @endphp
                            <span class="{{ $kleur }}">
                                {{ $versie->volgende_herziening_gepland->format('d-m-Y') }}
                            </span>
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if (! $document->leesbevestiging_vereist)
                            {{-- Bewust "n.v.t." en niet 0%: een nul leest als een
                                 falende beheersmaatregel (§6). --}}
                            <flux:text>n.v.t.</flux:text>
                        @elseif ($this->magAllesZien())
                            <flux:text>{{ $graad === null ? '—' : $graad.'%' }}</flux:text>
                        @elseif ($versie === null)
                            <flux:text>—</flux:text>
                        @elseif ($versie->isBevestigdDoor(auth()->id()))
                            <flux:badge size="sm" color="green" icon="check">Bevestigd</flux:badge>
                        @elseif (! $document->isInDoelgroep(auth()->user()))
                            {{-- Buiten de doelgroep: dit document richt zich niet
                                 op de afdeling van deze gebruiker (§6). --}}
                            <flux:text>n.v.t.</flux:text>
                        @else
                            {{-- Dezelfde bestemming als de titelkolom, en bewust
                                 geen wire:click: bevestigen kan alleen op het
                                 detailscherm, waar het bestand te openen is
                                 (§10). Vanuit de lijst is het een klik zonder
                                 het document gezien te hebben. --}}
                            {{-- Zelfde geel als de waarschuwing bovenaan, zodat
                                 de callout en de regels die hem veroorzaken bij
                                 elkaar horen. Wel op de sterkte van rand en
                                 icoon (yellow-400) en niet die van de vulling
                                 (yellow-50): dat laatste is als knop vrijwel
                                 wit. --}}
                            <flux:button size="sm" variant="primary" color="yellow"
                                :href="route('beleid.detail', $document)" wire:navigate>
                                Bevestigen
                            </flux:button>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($this->magMuteren())
                            <flux:button size="sm" variant="ghost" wire:click="bewerk({{ $document->id }})">
                                Bewerken
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text>Geen beleidsdocumenten gevonden.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
