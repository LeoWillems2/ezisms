<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.audits-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Auditprogramma</flux:heading>
            <flux:subheading>
                De meerjarige interne-auditcyclus (§9.2.2): welk deel van H4–H10 en Bijlage A wanneer aan bod komt.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuw">Nieuw programma</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Programma-formulier --}}
    @if ($toontFormulier)
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="naam" label="Naam" placeholder="Interne auditcyclus 2026–2028" />
                <flux:input type="date" wire:model="startDatum" label="Startdatum"
                    description="De certificaatdatum is het natuurlijke anker." />
                <flux:input type="number" wire:model="aantalJaren" label="Aantal jaren" />
            </div>
            <div class="mt-4">
                <flux:select wire:model="aard" label="Aard"
                    description="Voorbereiding = de aanloop naar certificering (nulmeting, eerste interne audits); die kent geen dekkingsverplichting over meerdere jaren. Certificeringscyclus = de driejarige cyclus die op de certificaatdatum begint.">
                    <flux:select.option value="certificeringscyclus">Certificeringscyclus</flux:select.option>
                    <flux:select.option value="voorbereiding">Voorbereiding</flux:select.option>
                </flux:select>
            </div>
            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="slaOp">Opslaan</flux:button>
                <flux:button variant="ghost" wire:click="$set('toontFormulier', false)">Annuleren</flux:button>
            </div>
        </div>
    @endif

    {{-- Programmalijst --}}
    <div class="flex flex-col gap-2">
        @forelse ($programmas as $p)
            <div @class([
                'rounded-xl border p-4',
                'border-indigo-400 dark:border-indigo-500' => $p->id === $geselecteerdId,
                'border-zinc-200 dark:border-zinc-700' => $p->id !== $geselecteerdId,
            ])>
                <div class="flex items-center justify-between gap-4">
                    <button type="button" wire:click="selecteer({{ $p->id }})" class="text-left">
                        <flux:heading size="lg">{{ $p->naam }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ $p->venster() }} ·
                            {{ $p->auditplannen_count }} jaarplan(nen) ·
                            {{ $p->dekkingen_count }} object(en) gepland
                        </flux:text>
                    </button>
                    <div class="flex items-center gap-2">
                        <flux:badge :color="match($p->status) { 'actief' => 'green', 'afgesloten' => 'zinc', default => 'amber' }">
                            {{ ucfirst($p->status) }}
                        </flux:badge>
                        @if ($this->magMuteren())
                            @if ($p->status === 'concept')
                                <flux:button size="sm" wire:click="activeer({{ $p->id }})">Activeren</flux:button>
                            @elseif ($p->status === 'actief')
                                <flux:button size="sm" variant="ghost"
                                    wire:click="sluitAf({{ $p->id }})"
                                    wire:confirm="Programma '{{ $p->naam }}' afsluiten? Dit markeert de cyclus als definitief afgerond en is niet terug te draaien.">Afsluiten</flux:button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <flux:callout icon="information-circle" heading="Nog geen auditprogramma. Maak er een aan om de meerjarige dekking te plannen." />
        @endforelse
    </div>

    {{-- Planning voor het geselecteerde programma --}}
    @if ($programma)
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">Planning · {{ $programma->naam }}</flux:heading>

            {{-- Jaarplan-koppeling --}}
            <div class="mt-3">
                <flux:text class="font-medium">Jaarplannen in de cyclus ({{ $programma->venster() }})</flux:text>
                @if ($plannenInVenster->isEmpty())
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        Geen jaarplannen in dit venster. Maak ze aan onder “Overzicht”.
                    </flux:text>
                @else
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($plannenInVenster as $plan)
                            @php $gekoppeld = $plan->auditprogramma_id === $programma->id; @endphp
                            <flux:badge :color="$gekoppeld ? 'green' : 'zinc'">
                                {{ $plan->jaar }}@if ($gekoppeld) · jaar {{ $plan->programmajaar }}@endif
                                @if ($this->magMuteren())
                                    @if ($gekoppeld)
                                        <button type="button" class="ml-1" wire:click="ontkoppelPlan({{ $plan->id }})" title="Ontkoppelen">×</button>
                                    @else
                                        <button type="button" class="ml-1" wire:click="koppelPlan({{ $plan->id }})" title="Koppelen">+</button>
                                    @endif
                                @endif
                            </flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Dekkingsplanning per object --}}
            <div class="mt-5 flex items-center justify-between">
                <flux:text class="font-medium">Dekkingsplanning (frequentie per object)</flux:text>
                @if ($this->magMuteren())
                    <flux:button size="sm" wire:click="vulStandaardplanning">Vul standaard (eenmaal per cyclus)</flux:button>
                @endif
            </div>

            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left dark:border-zinc-700">
                            <th class="py-2 pr-3">Object</th>
                            <th class="py-2 pr-3">Groep</th>
                            <th class="py-2 pr-3">Interval (jaren)</th>
                            @if ($this->magMuteren())<th class="py-2"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($objecten as $object)
                            @php $dekking = $dekkingen->get($object->id); @endphp
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="py-2 pr-3">
                                    <span class="font-medium">{{ $object->refCode() }}</span>
                                    <span class="text-zinc-500">{{ $object->omschrijving() }}</span>
                                </td>
                                <td class="py-2 pr-3 text-zinc-500">{{ $object->groep }}</td>
                                <td class="py-2 pr-3">
                                    @if ($dekking)
                                        @if ($this->magMuteren())
                                            <select
                                                wire:change="stelInterval({{ $object->id }}, $event.target.value)"
                                                class="rounded border border-zinc-300 bg-transparent px-2 py-1 dark:border-zinc-600"
                                            >
                                                @for ($i = 1; $i <= $programma->aantal_jaren; $i++)
                                                    <option value="{{ $i }}" @selected($dekking->interval_jaren === $i)>
                                                        {{ $i === 1 ? 'jaarlijks' : ($i === $programma->aantal_jaren ? $i.' (eenmaal per cyclus)' : 'elke '.$i.' jaar') }}
                                                    </option>
                                                @endfor
                                            </select>
                                        @else
                                            <flux:badge color="zinc">
                                                {{ $dekking->interval_jaren === 1 ? 'jaarlijks' : 'elke '.$dekking->interval_jaren.' jaar' }}
                                            </flux:badge>
                                        @endif
                                    @else
                                        <span class="text-zinc-400">niet gepland</span>
                                    @endif
                                </td>
                                @if ($this->magMuteren())
                                    <td class="py-2 text-right">
                                        @if ($dekking)
                                            <flux:button size="xs" variant="ghost" wire:click="verwijderDekking({{ $object->id }})">Verwijderen</flux:button>
                                        @else
                                            <flux:button size="xs" wire:click="stelInterval({{ $object->id }}, {{ $programma->aantal_jaren }})">Toevoegen</flux:button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
