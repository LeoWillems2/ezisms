<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('audits.index')" wire:navigate>
            Terug naar audits
        </flux:button>
    </div>

    <div>
        <flux:heading size="xl">
            {{ $auditronde->typeLabel() }} — plan {{ $auditronde->auditplan->jaar }}
            @php
                $statusKleur = match ($auditronde->status) {
                    'afgerond' => 'green',
                    'in_uitvoering' => 'blue',
                    default => 'zinc',
                };
            @endphp
            <flux:badge size="sm" :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $auditronde->status)) }}</flux:badge>
        </flux:heading>
        <flux:subheading>Scope, uitvoerder, status en bevindingen van deze auditronde.</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif
    @if (session('fout'))
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    {{-- Statusovergangen: bij een interne ronde door de toegewezen auditor, bij
         een externe door de CISO (magUitvoerenDoor). --}}
    <div class="flex flex-wrap items-center gap-3">
        @if ($auditronde->status === 'gepland')
            @if ($this->magUitvoeren())
                <flux:button variant="primary" icon="play" wire:click="startUitvoering">Uitvoering starten</flux:button>
            @elseif ($auditronde->isIntern())
                <flux:text variant="subtle">De toegewezen auditor start de uitvoering.</flux:text>
            @endif
        @elseif ($auditronde->status === 'in_uitvoering')
            @if ($this->magUitvoeren())
                <flux:button variant="primary" icon="check" wire:click="rondAf"
                    wire:confirm="Afronden bevriest de bevindingen. Doorgaan?">Ronde afronden</flux:button>
            @endif
            <flux:text variant="subtle">Na afronden zijn de bevindingen definitief en niet meer te wijzigen.</flux:text>
        @else
            <flux:text variant="subtle">Afgerond op {{ $auditronde->uitgevoerd_op?->format('d-m-Y') ?? '—' }} — bevindingen bevroren.</flux:text>
        @endif
    </div>

    {{-- Dekkingsvlag (plan 11c). Bewust altijd zichtbaar, ook voor wie niet mag
         muteren: dat een ronde niet meetelt is informatie, geen instelling die
         je verstopt. Alleen omzetten is voorbehouden aan de CISO. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">Dekking</flux:heading>
                @if ($auditronde->telt_mee_voor_dekking)
                    <flux:text variant="subtle">Telt mee voor de dekkingsmatrix van het auditprogramma.</flux:text>
                @else
                    <flux:badge size="sm" color="amber">Telt niet mee</flux:badge>
                    <flux:text variant="subtle" class="mt-1">
                        Telt niet mee voor de dekkingsmatrix; blijft input voor §9.2.2 en de
                        directiebeoordeling, en blijft bruikbaar als bron voor afwijkingen.
                    </flux:text>
                @endif
            </div>

            @if ($this->magMuteren())
                <flux:button size="sm" variant="ghost" wire:click="wisselDekkingsvlag">
                    {{ $auditronde->telt_mee_voor_dekking ? 'Buiten de dekking houden' : 'Weer laten meetellen' }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Planning (administratief, alleen zolang 'gepland'). --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Planning</flux:heading>

        @if ($this->magPlannen())
            <form wire:submit="slaPlanningOp" class="space-y-5">
                <flux:select wire:model.live="type" label="Type" required>
                    @foreach ($types as $t)
                        <flux:select.option value="{{ $t }}">{{ \App\Models\Auditronde::labelVoorType($t) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="geplandOp" type="date" label="Geplande datum" />

                @if (in_array($type, \App\Models\Auditronde::INTERNE_TYPEN, true))
                    <x-keuzelijst wire:model="auditorGebruikerId" label="Auditor (intern)"
                        leeg="— nog niet toegewezen —" :opties="$auditors"
                        description="Het (vaak tijdelijke) Auditor-account dat de bevindingen op deze ronde mag vastleggen." />
                @else
                    <flux:input wire:model="externAuditorNaam" label="Externe auditor (naam)"
                        description="De certificerende instelling heeft geen account; het rapport hangt hieronder als bewijs." />
                @endif

                <flux:checkbox.group wire:model="scopeEenheden" label="Scope (organisatie-eenheden)"
                    class="grid gap-1 md:grid-cols-2">
                    @forelse ($eenheden as $id => $naam)
                        <flux:checkbox value="{{ $id }}" label="{{ $naam }}" />
                    @empty
                        <flux:text>Nog geen organisatie-eenheden vastgelegd bij Context &amp; Scope.</flux:text>
                    @endforelse
                </flux:checkbox.group>

                {{-- Normatieve scope (plan 11b): welke clausules/controls deze ronde
                     dekt. De aangevinkte staan verzameld bovenaan; de overige
                     (90+) zitten onder een uitklap zodat het geen muur wordt. --}}
                <div class="space-y-2">
                    <flux:label>Normatieve scope (clausules / controls)</flux:label>

                    @if (! $heeftObjecten)
                        <flux:text>Nog geen auditobjecten. Seed de clausules en draai isms:sync-auditobjecten.</flux:text>
                    @else
                        @if (empty($gekozenObjecten))
                            <flux:text variant="subtle">Nog niets geselecteerd — klap de lijst uit om controls te kiezen.</flux:text>
                        @else
                            <flux:checkbox.group wire:model="scopeObjecten" class="grid gap-1 md:grid-cols-2">
                                @foreach ($gekozenObjecten as $id => $label)
                                    <flux:checkbox value="{{ $id }}" label="{{ $label }}" />
                                @endforeach
                            </flux:checkbox.group>
                        @endif

                        @if (! empty($overigeObjecten))
                            <details class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <summary class="cursor-pointer select-none px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                                    {{ count($overigeObjecten) }} overige controls
                                </summary>
                                <flux:checkbox.group wire:model="scopeObjecten"
                                    class="grid max-h-64 gap-1 overflow-y-auto px-3 pb-3 md:grid-cols-2">
                                    @foreach ($overigeObjecten as $id => $label)
                                        <flux:checkbox value="{{ $id }}" label="{{ $label }}" />
                                    @endforeach
                                </flux:checkbox.group>
                            </details>
                        @endif

                        <flux:text variant="subtle" class="text-xs">
                            Aangevinkte controls verschijnen bovenaan; de herschikking volgt na "Planning opslaan".
                        </flux:text>
                    @endif
                </div>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">Planning opslaan</flux:button>
                </div>
            </form>
        @else
            {{-- Read-only zodra de ronde loopt of is afgerond, of voor wie niet mag muteren. --}}
            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs">Geplande datum</flux:text>
                    <flux:text>{{ $auditronde->gepland_op?->format('d-m-Y') ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs">Uitvoerder</flux:text>
                    <flux:text>
                        @if ($auditronde->isIntern())
                            {{ $auditronde->auditor?->naam ?? '— nog niet toegewezen —' }}
                        @else
                            {{ $auditronde->extern_auditor_naam ?? '—' }}
                        @endif
                    </flux:text>
                </div>
                <div class="sm:col-span-2">
                    <flux:text class="text-xs">Scope (organisatie-eenheden)</flux:text>
                    @if ($auditronde->organisatieEenheden->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($auditronde->organisatieEenheden as $eenheid)
                                <flux:badge size="sm" color="zinc">{{ $eenheid->naam }}</flux:badge>
                            @endforeach
                        </div>
                    @else
                        <flux:text>—</flux:text>
                    @endif
                </div>
                <div class="sm:col-span-2">
                    <flux:text class="text-xs">Normatieve scope (clausules / controls)</flux:text>
                    @if ($auditronde->auditobjecten->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($auditronde->auditobjecten as $object)
                                <flux:badge size="sm" color="zinc">{{ $object->refCode() }}</flux:badge>
                            @endforeach
                        </div>
                    @else
                        <flux:text>—</flux:text>
                    @endif
                </div>
            </dl>
        @endif
    </div>

    {{-- Bevindingen. --}}
    <div>
        <div class="mb-2 flex items-center justify-between gap-4">
            <flux:heading size="lg">Bevindingen</flux:heading>
            @if ($this->magBevindingBewerken())
                <flux:button size="sm" variant="primary" icon="plus" wire:click="nieuweBevinding">Nieuwe bevinding</flux:button>
            @endif
        </div>

        @unless ($this->magBevindingBewerken())
            <flux:text variant="subtle" class="mb-2">
                @if ($auditronde->status === 'afgerond')
                    De ronde is afgerond; de bevindingen zijn bevroren.
                @else
                    Alleen de toegewezen uitvoerder legt tijdens de uitvoering bevindingen vast.
                @endif
            </flux:text>
        @endunless

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Omschrijving</flux:table.column>
                <flux:table.column>Maatregel</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Acties</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($bevindingen as $bevinding)
                    <flux:table.row wire:key="bevinding-{{ $bevinding->id }}">
                        <flux:table.cell variant="strong">
                            <flux:badge size="sm" color="{{ str_contains($bevinding->type, 'major') ? 'red' : (str_contains($bevinding->type, 'minor') ? 'amber' : 'zinc') }}">
                                {{ ucfirst(str_replace('_', ' ', $bevinding->type)) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ Str::limit($bevinding->omschrijving, 80) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $bevinding->maatregel ? 'A.'.$bevinding->maatregel->annex_a_referentie : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $bKleur = match ($bevinding->status) {
                                    'gesloten' => 'green',
                                    'non_conformiteit_gestart' => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$bKleur">{{ ucfirst(str_replace('_', ' ', $bevinding->status)) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @if ($this->magBevindingBewerken())
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        wire:click="bewerkBevinding({{ $bevinding->id }})">Bewerken</flux:button>
                                @endif

                                @if ($this->magMuteren() && $bevinding->status !== 'gesloten')
                                    @if ($bevinding->afwijking)
                                        <flux:button size="sm" variant="ghost" icon="wrench-screwdriver"
                                            :href="route('afwijkingen.detail', $bevinding->afwijking)" wire:navigate>Afwijking</flux:button>
                                    @elseif ($bevinding->isNonConformiteit())
                                        <flux:button size="sm" variant="ghost" icon="wrench-screwdriver"
                                            wire:click="opvolgenAlsNonConformiteit({{ $bevinding->id }})">Non-conformiteit starten</flux:button>
                                    @endif

                                    <flux:button size="sm" variant="ghost" icon="check"
                                        wire:click="sluitBevinding({{ $bevinding->id }})"
                                        wire:confirm="Bevinding sluiten? Een gesloten bevinding is definitief en kan niet heropend worden.">Sluiten</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5"><flux:text>Nog geen bevindingen vastgelegd.</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Extern auditrapport als bewijsstuk (blok 6). --}}
    <div>
        <flux:heading size="lg" class="mb-2">Auditrapport &amp; bewijs</flux:heading>
        <livewire:bewijs-paneel blok-naam="auditmanagement" entiteit-type="auditronde"
            :entiteit-id="$auditronde->id" :wire:key="'bewijs-auditronde-'.$auditronde->id" />
    </div>

    {{-- Bevinding-formulier --}}
    <flux:modal wire:model.self="toontBevindingFormulier" class="md:w-[32rem]">
        <form wire:submit="slaBevindingOp" class="space-y-6">
            <flux:heading size="lg">{{ $bewerktBevindingId ? 'Bevinding bewerken' : 'Nieuwe bevinding' }}</flux:heading>

            <flux:select wire:model="bevindingType" label="Type" required>
                @foreach ($bevindingTypes as $bt)
                    <flux:select.option value="{{ $bt }}">{{ ucfirst(str_replace('_', ' ', $bt)) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="bevindingOmschrijving" label="Omschrijving" required />

            <x-keuzelijst wire:model="bevindingMaatregelId" label="Betreft maatregel (optioneel)"
                leeg="— geen —" :opties="$maatregelen" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitBevindingFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
