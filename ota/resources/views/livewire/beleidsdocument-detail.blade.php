<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('beleid.index')" wire:navigate>
            Terug naar beleid
        </flux:button>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @error('publicatie')
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" />
    @enderror

    {{-- 1. Kop met de geldende versie prominent. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $beleidsdocument->titel }}</flux:heading>
                <flux:subheading>
                    {{ ucfirst($beleidsdocument->type) }}
                    @if ($beleidsdocument->eigenaar)
                        — eigenaar: <x-gebruiker-naam :gebruiker="$beleidsdocument->eigenaar" />
                    @endif
                </flux:subheading>
            </div>

            @if ($actieveVersie?->bewijsstuk)
                <div class="flex flex-wrap justify-end gap-2">
                    @if ($actieveVersie->bewijsstuk->isPreviewbaar())
                        <flux:button variant="ghost" icon="eye" target="_blank"
                            :href="route('bewijsstukken.preview', $actieveVersie->bewijsstuk)">
                            Preview
                        </flux:button>
                    @endif
                    <flux:button icon="arrow-down-tray"
                        :href="route('bewijsstukken.download', $actieveVersie->bewijsstuk)">
                        Geldende versie (v{{ $actieveVersie->versienummer }})
                    </flux:button>
                </div>
            @endif
        </div>

        @if ($beleidsdocument->omschrijving)
            <flux:text class="mt-3">{{ $beleidsdocument->omschrijving }}</flux:text>
        @endif

        @if ($beleidsdocument->isIngetrokken())
            <flux:callout variant="danger" icon="x-circle" class="mt-4"
                heading="Ingetrokken op {{ $beleidsdocument->ingetrokken_op->format('d-m-Y') }}" />
        @endif

        @if ($this->magMuteren())
            <flux:separator class="my-5" />

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="titel" label="Titel" required />

                <x-keuzelijst wire:model="eigenaarId" label="Eigenaar" :opties="$gebruikers"
                    leeg="— geen eigenaar —" />

                <flux:textarea wire:model="omschrijving" label="Omschrijving" class="md:col-span-2" />

                <flux:checkbox wire:model.live="leesbevestigingVereist"
                    label="Leesbevestiging vereist"
                    description="Aanzetten legt bij de eerstvolgende nachtelijke run een taak neer bij de gekozen afdelingen. Dat is de bedoeling, maar niet iets wat u per ongeluk aanvinkt." />

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
                <flux:button variant="primary" wire:click="opslaanKop">Opslaan</flux:button>
                @unless ($beleidsdocument->isIngetrokken())
                    <flux:button variant="ghost"
                        wire:click="intrekken"
                        wire:confirm="Het document intrekken? De geldende versie vervalt en er blijft geen actief beleid over.">
                        Intrekken
                    </flux:button>
                @endunless
            </div>
        @endif
    </div>

    {{-- 2. Versies. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="mb-4 flex items-center justify-between gap-4">
            <flux:heading size="lg">Versies</flux:heading>
            @if ($this->magMuteren())
                <flux:button size="sm" icon="plus" wire:click="nieuweVersie">Nieuwe versie</flux:button>
            @endif
        </div>

        @if ($toontVersieFormulier)
            <div class="mb-5 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:textarea wire:model="wijzigingsreden" label="Wijzigingsreden" class="md:col-span-2" />
                    <flux:input wire:model="volgendeHerzieningGepland" type="date"
                        label="Volgende herziening gepland"
                        description="Levert automatisch een herzieningstaak op zodra de versie actief is." />
                    <flux:input wire:model="bestand" type="file" label="Documentbestand"
                        description="PDF, Office- of RTF-bestand, max. 20 MB. Een PDF is in het scherm te bekijken; van een RTF-, DOCX- of ODT-bestand komt er een HTML-preview. Publiceren kan pas met een bestand." />
                </div>

                <div class="mt-4 flex items-center gap-2">
                    {{-- De upload loopt asynchroon; klikken vóór de upload klaar is
                         zou het bestand anders stil laten vallen (p16). --}}
                    <flux:button variant="primary" wire:click="opslaanVersie"
                        wire:loading.attr="disabled" wire:target="bestand, opslaanVersie">Versie aanmaken</flux:button>
                    <flux:button variant="ghost" wire:click="sluitVersieFormulier">Annuleren</flux:button>
                    <flux:text wire:loading wire:target="bestand" class="text-sm text-zinc-500">
                        Bezig met uploaden…
                    </flux:text>
                </div>
            </div>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Versie</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Gepubliceerd</flux:table.column>
                <flux:table.column>Goedgekeurd door</flux:table.column>
                <flux:table.column>Wijzigingsreden</flux:table.column>
                <flux:table.column align="end">Acties</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($versies as $versie)
                    <flux:table.row wire:key="versie-{{ $versie->id }}">
                        <flux:table.cell variant="strong">
                            v{{ $versie->versienummer }}
                            @if ($versie->bewijsstuk)
                                <flux:link :href="route('bewijsstukken.download', $versie->bewijsstuk)"
                                    class="ml-2 text-xs">bestand</flux:link>
                                @if ($versie->bewijsstuk->isPreviewbaar())
                                    <flux:link :href="route('bewijsstukken.preview', $versie->bewijsstuk)"
                                        target="_blank" class="ml-2 text-xs">preview</flux:link>
                                @endif
                            @else
                                <flux:badge size="sm" color="amber">geen bestand</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $kleur = match ($versie->status) {
                                    'actief' => 'green',
                                    'ter_goedkeuring' => 'blue',
                                    'vervangen' => 'zinc',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$kleur">
                                {{ ucfirst(str_replace('_', ' ', $versie->status)) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $versie->gepubliceerd_op?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($versie->goedkeurder)
                                {{ $versie->goedkeurder->naam }}
                                <flux:text class="text-xs">{{ $versie->goedgekeurd_op?->lokaal()->format('d-m-Y H:i') }}</flux:text>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $versie->wijzigingsreden ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @if ($this->magMuteren() && $versie->status === 'concept')
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="terGoedkeuring({{ $versie->id }})">
                                        Ter goedkeuring
                                    </flux:button>
                                @endif
                                @if ($this->magGoedkeuren() && $versie->status === 'ter_goedkeuring')
                                    <flux:button size="sm" variant="primary"
                                        wire:click="publiceren({{ $versie->id }})"
                                        wire:confirm="Deze versie publiceren? De huidige actieve versie wordt vervangen.">
                                        Publiceren
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>Nog geen versies.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- 3. SoA-koppelingen: het koppelvlak dat blok 4 openliet. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-2">Onderbouwt SoA-maatregelen</flux:heading>
        <flux:subheading class="mb-4">
            Een SoA-regel die van toepassing is zonder onderbouwend actief beleid is een gap.
        </flux:subheading>

        @if ($this->magSoaKoppelen())
            <flux:checkbox.group wire:model="geselecteerdeSoaRegels" class="grid gap-1 md:grid-cols-2">
                @foreach ($soaRegels as $regel)
                    <flux:checkbox :value="$regel->id"
                        :label="'A.'.$regel->maatregel->annex_a_referentie.' '.$regel->maatregel->naam" />
                @endforeach
            </flux:checkbox.group>

            <flux:button class="mt-4" variant="primary" wire:click="opslaanSoaKoppeling">
                Koppelingen opslaan
            </flux:button>
        @else
            @forelse ($beleidsdocument->soaRegels as $regel)
                <flux:badge size="sm" color="zinc" class="mr-1">
                    A.{{ $regel->maatregel->annex_a_referentie }}
                </flux:badge>
            @empty
                <flux:text>Geen gekoppelde maatregelen.</flux:text>
            @endforelse
        @endif
    </div>

    {{-- 4. Leesbevestigingen. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Leesbevestiging</flux:heading>

        @if (! $beleidsdocument->leesbevestiging_vereist)
            <flux:text>
                Voor dit document is geen leesbevestiging vereist. Het gaat om een
                onderwerpspecifieke beleidsregel die niet iedereen aangaat (A.5.1:
                &ldquo;relevant personeel&rdquo;).
            </flux:text>
        @elseif ($actieveVersie === null)
            <flux:text>Nog geen actieve versie om te bevestigen.</flux:text>
        @else
            @if ($zelfBevestigd)
                <flux:badge color="green" icon="check">U heeft v{{ $actieveVersie->versienummer }} bevestigd</flux:badge>
            @elseif (! $inDoelgroep)
                {{-- Buiten de doelgroep: dit document richt zich niet op de
                     afdeling van deze gebruiker (§6). Geen knop. --}}
                <flux:text>
                    Dit document is niet op uw afdeling gericht; u hoeft het niet te bevestigen.
                </flux:text>
            @else
                <div class="mb-4">
                    {{-- Wie het bestand niet heeft opgehaald, krijgt een extra
                         zin in plaats van een blokkade: op papier of in een
                         teamsessie gelezen is een geldige route (§14). --}}
                    <flux:button variant="primary" wire:click="bevestig"
                        wire:confirm="{{ $zelfGeraadpleegd
                            ? 'U bevestigt dat u dit document heeft gelezen en begrepen. Een bevestiging kan niet ongedaan worden gemaakt.'
                            : 'U heeft dit document niet gedownload. Bevestig alleen als u het langs een andere weg heeft gelezen. Een bevestiging kan niet ongedaan worden gemaakt.' }}">
                        Ik heb dit gelezen
                    </flux:button>

                    @unless ($zelfGeraadpleegd)
                        <flux:text class="mt-2 text-sm">
                            U heeft het documentbestand nog niet opgehaald.
                        </flux:text>
                    @endunless
                </div>
            @endif

            @if ($this->magAllesZien())
                <flux:separator class="my-5" />

                {{-- Het signaal uit §14: geen poort vóór de knop, maar een
                     cijfer achteraf. Een bevestiging zonder download is geen
                     overtreding — het is de vraag of de bevestiging ergens op
                     rust, en dat is stuurinformatie. --}}
                @php
                    $blind = collect($zonderRaadpleging)->filter()->count();
                @endphp
                @if ($blind > 0)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mb-5"
                        heading="{{ $blind }} van de {{ $bevestigingen->count() }} bevestiging(en) zonder download">
                        <flux:callout.text>
                            Deze personen hebben bevestigd zonder het documentbestand te hebben
                            opgehaald. Dat kan kloppen &mdash; op papier of in een teamsessie gelezen
                            telt ook &mdash; maar het is geen bevestiging die op zichzelf staat.
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <flux:heading size="sm" class="mb-2">
                            Bevestigd ({{ $bevestigingen->count() }})
                        </flux:heading>
                        @forelse ($bevestigingen as $bevestiging)
                            <div class="flex items-center justify-between gap-3 border-t border-zinc-100 py-1 first:border-t-0 dark:border-zinc-800">
                                <flux:text>
                                    {{ $bevestiging->gebruiker?->naam ?? '—' }}
                                    @if ($zonderRaadpleging[$bevestiging->gebruiker_id] ?? false)
                                        <flux:badge size="sm" color="yellow" class="ms-1">geen download</flux:badge>
                                    @endif
                                </flux:text>
                                <flux:text class="text-sm">{{ $bevestiging->bevestigd_op->lokaal()->format('d-m-Y H:i') }}</flux:text>
                            </div>
                        @empty
                            <flux:text>Nog niemand.</flux:text>
                        @endforelse
                    </div>

                    <div>
                        <flux:heading size="sm" class="mb-2">
                            Nog niet bevestigd ({{ $nietBevestigd->count() }})
                        </flux:heading>
                        @forelse ($nietBevestigd as $gebruiker)
                            <div class="border-t border-zinc-100 py-1 first:border-t-0 dark:border-zinc-800">
                                <flux:text>{{ $gebruiker->naam }}</flux:text>
                            </div>
                        @empty
                            <flux:text>Iedereen heeft bevestigd.</flux:text>
                        @endforelse
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
