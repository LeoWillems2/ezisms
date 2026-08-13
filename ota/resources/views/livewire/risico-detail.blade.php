<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('risicos.index')" wire:navigate>
            Terug naar risicoregister
        </flux:button>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:heading size="xl">{{ $risico->referentie() }} · {{ $risico->titel }}</flux:heading>
        <flux:badge>{{ ucfirst(str_replace('_', ' ', $risico->status)) }}</flux:badge>
        @if ($risico->risicoscore !== null)
            <flux:badge :color="\App\Models\Risico::scoreKleur($risico->risicoscore)">Score {{ $risico->risicoscore }}</flux:badge>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($risico->herbeoordelingVerstreken())
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Herbeoordeling verstreken">
            <flux:callout.text>
                De geplande herbeoordeling was
                {{ $risico->volgende_beoordeling_gepland->format('d-m-Y') }}. Beoordeel kans en
                impact opnieuw en zet een nieuwe datum.
            </flux:callout.text>
        </flux:callout>
    @endif

    @php $readonly = ! $this->magMuteren(); @endphp

    {{-- Basisgegevens --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Basisgegevens</flux:heading>
        <form wire:submit="opslaanBasis" class="flex flex-col gap-4">
            <flux:input wire:model="titel" label="Titel" :readonly="$readonly" required />
            <flux:textarea wire:model="dreiging" label="Dreiging" :readonly="$readonly" />
            <flux:textarea wire:model="kwetsbaarheid" label="Kwetsbaarheid" :readonly="$readonly" />

            <div class="grid gap-4 md:grid-cols-2">
                <x-keuzelijst wire:model="gekoppeldAssetId" label="Gekoppeld asset" leeg="— geen —"
                    :disabled="$readonly" :opties="$assets->pluck('naam', 'id')" />

                <div>
                    <x-keuzelijst wire:model="risicoEigenaarId" label="Risico-eigenaar" leeg="— geen —"
                        :disabled="$readonly" :opties="$gebruikers->pluck('naam', 'id')" />
                    @if ($eigenaarGebruiker && ! $eigenaarGebruiker->isActief())
                        <flux:text class="mt-1 text-sm text-amber-600 dark:text-amber-500">
                            {{ ucfirst($eigenaarGebruiker->status) }} — wijs een actief account aan.
                        </flux:text>
                    @endif
                </div>
            </div>

            {{-- Aanleiding: de §4.1-kwesties waaruit dit risico voortkomt (plan 02b).
                 Staat hier en niet bij het behandelplan, omdat het bij de
                 identificatie hoort en niet bij het antwoord. --}}
            <div>
                <flux:label>Aanleiding (context-issues)</flux:label>
                <flux:text class="mb-2 text-sm">
                    Uit welke §4.1-kwestie(s) is dit risico voortgekomen? Leeg laten mag —
                    risico's komen ook uit assets, incidenten of audits.
                </flux:text>

                @forelse ($issues as $aard => $groep)
                    <flux:heading size="sm" class="mt-3 mb-1">{{ ucfirst($aard) }}</flux:heading>
                    <flux:checkbox.group wire:model="geselecteerdeAanleidingen">
                        @foreach ($groep as $issue)
                            <flux:checkbox value="{{ $issue->id }}" :disabled="$readonly"
                                label="{{ $issue->categorie }} — {{ Str::limit($issue->omschrijving, 90) }}" />
                        @endforeach
                    </flux:checkbox.group>
                @empty
                    <flux:text class="text-sm">
                        Er zijn nog geen issues vastgelegd.
                        <flux:link :href="route('issues.index')" wire:navigate>Naar het issue-register</flux:link>.
                    </flux:text>
                @endforelse
            </div>

            @unless ($readonly)
                <div><flux:button type="submit" variant="primary">Basisgegevens opslaan</flux:button></div>
            @endunless
        </form>
    </div>

    {{-- Beoordeling --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">Beoordeling</flux:heading>
        <flux:text class="mb-4">
            De risicoscore wordt berekend als kans x impact en is niet handmatig te zetten.
            Wat de vijf niveaus betekenen staat bij de
            <flux:link :href="route('risicos.criteria')" wire:navigate>risicocriteria</flux:link>.
        </flux:text>

        <form wire:submit="opslaanBeoordeling" class="flex flex-col gap-4">
            <div class="grid gap-4 md:grid-cols-3">
                {{-- Naam erbij, cijfer voorop (00j §1.2): het cijfer is wat in de
                     matrix, de score en de export staat, de naam is wat de
                     beoordelaar nodig heeft om te kiezen. --}}
                <x-keuzelijst wire:model="kansNiveau" label="Kans (1-5)" leeg="Niet beoordeeld"
                    :disabled="$readonly" :opties="\App\Support\Beoordelingsschaal::opties('kans')" />

                <x-keuzelijst wire:model="impactNiveau" label="Impact (1-5)" leeg="Niet beoordeeld"
                    :disabled="$readonly" :opties="\App\Support\Beoordelingsschaal::opties('impact')" />

                <flux:input :value="$risico->risicoscore ?? '—'" readonly
                    label="Risicoscore (Berekend, drempel = {{ $drempel }})" />
            </div>

            <flux:input wire:model="volgendeBeoordelingGepland" type="date" label="Volgende beoordeling gepland"
                class="max-w-56" :readonly="$readonly" />

            {{-- Onder welk kader deze score tot stand kwam (04g §2.6a). Een
                 vervangen versie krijgt een amber badge: de score staat er nog,
                 maar de betekenis ervan is sindsdien opnieuw vastgesteld. --}}
            @if ($risico->criteriaVersie)
                <div class="flex items-center gap-2">
                    <flux:text variant="subtle">
                        Beoordeeld onder risicocriteria v{{ $risico->criteriaVersie->versienummer }}
                    </flux:text>
                    @if ($risico->beoordeeldOnderOudCriterium())
                        <flux:badge size="sm" color="amber" icon="exclamation-triangle">
                            Inmiddels vervangen kader
                        </flux:badge>
                    @endif
                </div>
            @endif

            @unless ($readonly)
                <div><flux:button type="submit" variant="primary">Beoordeling opslaan</flux:button></div>
            @endunless
        </form>
    </div>

    {{-- Behandelplan --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">Behandelplan</flux:heading>
        <flux:text class="mb-4">
            Koppel de maatregelen waarmee dit risico wordt behandeld. Alleen maatregelen die in de
            SoA op "van toepassing = ja" staan zijn hier te kiezen.
        </flux:text>

        @if ($behandelingen->isNotEmpty())
            <flux:table class="mb-5">
                <flux:table.columns>
                    <flux:table.column>Optie</flux:table.column>
                    <flux:table.column>Maatregelen</flux:table.column>
                    <flux:table.column>Restrisico</flux:table.column>
                    <flux:table.column>Geaccepteerd door</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($behandelingen as $behandeling)
                        <flux:table.row wire:key="behandeling-{{ $behandeling->id }}">
                            <flux:table.cell variant="strong">{{ ucfirst($behandeling->behandeloptie) }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $behandeling->soaRegels->map(fn ($regel) => 'A.'.$regel->maatregel->annex_a_referentie)->implode(', ') ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $behandeling->restrisico_score ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $behandeling->geaccepteerd_door ?? '—' }}
                                @if ($behandeling->geaccepteerd_op)
                                    ({{ $behandeling->geaccepteerd_op->format('d-m-Y') }})
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @unless ($readonly)
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="bewerkBehandeling({{ $behandeling->id }})">
                                        Bewerken
                                    </flux:button>
                                @endunless
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        @unless ($readonly)
            <form wire:submit="opslaanBehandeling" class="flex flex-col gap-4">
                <flux:heading size="sm">
                    {{ $behandelingId ? 'Behandeling bewerken' : 'Nieuwe behandeling' }}
                </flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model.live="behandeloptie" label="Behandeloptie">
                        <flux:select.option value="mitigeren">Mitigeren</flux:select.option>
                        <flux:select.option value="accepteren">Accepteren</flux:select.option>
                        <flux:select.option value="overdragen">Overdragen</flux:select.option>
                        <flux:select.option value="vermijden">Vermijden</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="restrisicoScore" type="number" min="0" max="25" label="Restrisicoscore" />
                </div>

                @if ($behandeloptie === 'accepteren' && $risico->boventDrempel())
                    <flux:callout icon="information-circle"
                        heading="Dit restrisico ligt boven de acceptatiedrempel ({{ $drempel }})."
                        text="Het plan is hiermee vastgelegd, maar het risico geldt pas als geaccepteerd zodra de directie tekent." />
                @endif

                <flux:checkbox.group wire:model="geselecteerdeSoaRegels" label="Gekoppelde maatregelen (SoA)">
                    @forelse ($soaRegels as $regel)
                        <flux:checkbox value="{{ $regel->id }}"
                            label="A.{{ $regel->maatregel->annex_a_referentie }} {{ $regel->maatregel->naam }}" />
                    @empty
                        <flux:text>
                            Nog geen maatregelen op "van toepassing = ja" gezet in de
                            <a href="{{ route('soa.index') }}" wire:navigate class="underline">SoA</a>.
                        </flux:text>
                    @endforelse
                </flux:checkbox.group>

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Behandelplan opslaan</flux:button>
                    @if ($behandelingId)
                        <flux:button type="button" variant="ghost" wire:click="nieuwBehandelplan">
                            Nieuwe behandeling
                        </flux:button>
                    @endif
                </div>
            </form>
        @endunless

        {{-- Acceptatie van het restrisico: eigen actie achter `goedkeuren`
             (implementatie/01c §4), zodat wie het plan schrijft niet zijn eigen
             restrisico tekent. --}}
        @if ($this->magGoedkeuren() && $teAccepteren->isNotEmpty())
            <flux:separator class="my-6" />
            <flux:heading size="sm">Restrisico accepteren</flux:heading>
            <flux:text class="mt-1 mb-4">
                Leg vast wie namens de organisatie tekent voor het restrisico dat na behandeling overblijft.
            </flux:text>

            <div class="flex flex-col gap-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="geaccepteerdDoor" label="Geaccepteerd door" />
                    <flux:input wire:model="geaccepteerdOp" type="date" label="Geaccepteerd op"
                        description="Leeg = vandaag." />
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($teAccepteren as $behandeling)
                        <flux:button variant="primary" wire:key="accepteer-{{ $behandeling->id }}"
                            wire:click="accepteerRestrisico({{ $behandeling->id }})"
                            wire:confirm="Het restrisico accepteren? Dit wordt vastgelegd in de audit trail.">
                            Accepteren (restrisico {{ $behandeling->restrisico_score ?? '—' }})
                        </flux:button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Bewijsstukken (blok 6, herbruikbaar paneel) --}}
    <livewire:bewijs-paneel blok-naam="risico-soa" entiteit-type="risico" :entiteit-id="$risico->id"
        :wire:key="'bewijs-risico-'.$risico->id" />

    {{-- Status --}}
    @unless ($readonly)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-1">Status</flux:heading>
            <flux:text class="mb-4">
                De status volgt normaal automatisch uit beoordeling en behandelplan; hier zet je de
                uitvoering handmatig verder.
            </flux:text>
            <div class="flex flex-wrap gap-2">
                @if ($risico->status !== 'in_uitvoering')
                    <flux:button variant="ghost" wire:click="statusWijzigen('in_uitvoering')">
                        Op "in uitvoering" zetten
                    </flux:button>
                @endif
                @if ($risico->status !== 'gemitigeerd')
                    <flux:button variant="ghost" wire:click="statusWijzigen('gemitigeerd')">
                        Op "gemitigeerd" zetten
                    </flux:button>
                @endif
            </div>
        </div>
    @endunless
</div>
