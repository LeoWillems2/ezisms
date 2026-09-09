<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('audits.index')" wire:navigate>
            Terug naar audits
        </flux:button>
    </div>

    <div class="flex items-start justify-between gap-4">
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

        @include('partials.kopieknop')
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
                    wire:confirm="Afronden bevriest de bevindingen en de behandelingen. Doorgaan?">Ronde afronden</flux:button>
            @endif
            <flux:text variant="subtle">Na afronden zijn de bevindingen definitief en niet meer te wijzigen.</flux:text>
        @else
            <flux:text variant="subtle">Afgerond op {{ $auditronde->uitgevoerd_op?->format('d-m-Y') ?? '—' }} — bevindingen bevroren.</flux:text>
        @endif
    </div>

    {{-- Dekkingsvlag (plan 11c). Bewust altijd zichtbaar, ook voor wie niet mag
         muteren: dat een ronde niet meetelt is informatie, geen instelling die
         je verstopt. Alleen omzetten is voorbehouden aan de CISO. --}}
    <div class="blueprint p-5">
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
    <div class="blueprint p-5">
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
                            <details class="rounded-lg border border-zinc-200">
                                <summary class="cursor-pointer select-none px-3 py-2 text-sm font-medium text-zinc-600">
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
            </dl>
        @endif
    </div>


    {{-- Normatieve scope: niet wat de bedoeling was, maar wat er feitelijk is
         gebeurd (plan 11d). Dit is het antwoord op de vraag van de externe
         auditor: hoe weet ik dat u alles hebt bekeken? --}}
    <div class="blueprint p-5">
        @php
            $telling = collect($objectstatussen)->countBy();
        @endphp

        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
            <flux:heading size="lg">Normatieve scope</flux:heading>
            @if ($auditronde->auditobjecten->isNotEmpty())
                <flux:text variant="subtle">
                    {{ $telling->get('geen_opmerkingen', 0) + $telling->get('bevinding', 0) }}
                    van de {{ $auditronde->auditobjecten->count() }} behandeld
                    @if ($telling->get('bevinding', 0) > 0), waarvan {{ $telling->get('bevinding') }} met bevinding @endif
                    @if ($telling->get('niet_toegekomen', 0) > 0) · {{ $telling->get('niet_toegekomen') }} niet aan toegekomen @endif
                </flux:text>
            @endif
        </div>

        @if ($auditronde->auditobjecten->isEmpty())
            <flux:text>Nog geen clausules of controls in de scope; die legt de CISO vast bij de planning.</flux:text>
        @else
            <div class="flex flex-wrap gap-1">
                @foreach ($auditronde->auditobjecten->sortBy([['groep', 'asc'], ['volgorde', 'asc']]) as $object)
                    @php
                        $status = $objectstatussen[$object->id] ?? 'niet_behandeld';
                        $kleur = match ($status) {
                            'geen_opmerkingen' => 'green',
                            'bevinding' => 'amber',
                            'niet_toegekomen' => 'red',
                            default => 'zinc',
                        };
                        $bron = match (true) {
                            (bool) $object->pivot->eigen_waarneming => ' — eigen waarneming',
                            $object->pivot->gesproken_met_id !== null => ' — gesproken met '
                                .($gesprekspartners[$object->pivot->gesproken_met_id] ?? 'onbekend'),
                            default => '',
                        };
                        $titel = $object->omschrijving().' — '.$afhandelingLabels[$status]
                            .($status === 'niet_toegekomen' ? ' — '.$object->pivot->toelichting : $bron)
                            .($object->pivot->buiten_planning ? ' (tijdens de uitvoering toegevoegd)' : '');
                    @endphp

                    @if ($this->magBevindingBewerken() && $status !== 'bevinding')
                        <button type="button" wire:click="behandelObject({{ $object->id }})" title="{{ $titel }}">
                            <flux:badge size="sm" :color="$kleur">
                                {{ $object->pivot->buiten_planning ? '+' : '' }}{{ $object->refCode() }}
                            </flux:badge>
                        </button>
                    @else
                        <flux:badge size="sm" :color="$kleur" title="{{ $titel }}">
                            {{ $object->pivot->buiten_planning ? '+' : '' }}{{ $object->refCode() }}
                        </flux:badge>
                    @endif
                @endforeach
            </div>

            <flux:text variant="subtle" class="mt-3 text-xs">
                Groen: behandeld, geen opmerkingen. Oranje: er is een bevinding. Rood: niet aan toegekomen.
                Grijs: nog niet behandeld. Een "+" betekent: tijdens de uitvoering aan de scope toegevoegd.
                @if ($this->magBevindingBewerken()) Klik een knop aan om de behandeling vast te leggen. @endif
            </flux:text>
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
                <flux:table.column>Betreft</flux:table.column>
                <flux:table.column>Bron</flux:table.column>
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
                            {{ $bevinding->auditobject?->auditOmschrijving() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($bevinding->eigen_waarneming)
                                <flux:text variant="subtle">eigen waarneming</flux:text>
                            @else
                                {{ $gesprekspartners[$bevinding->gesprekspartnerId()] ?? '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $bKleur = match ($bevinding->status) {
                                    'gesloten' => 'green',
                                    'non_conformiteit_gestart' => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$bKleur"
                                title="{{ $bevinding->isGesloten() ? 'Afgehandeld: '.$bevinding->afhandelingsnotitie : '' }}">
                                {{ ucfirst(str_replace('_', ' ', $bevinding->status)) }}
                            </flux:badge>
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
                                        wire:click="sluitBevinding({{ $bevinding->id }})">Sluiten</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6"><flux:text>Nog geen bevindingen vastgelegd.</flux:text></flux:table.cell>
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

    {{-- Sluiten: wat er met de bevinding is gebeurd (plan 11d §13). --}}
    <flux:modal wire:model.self="toontSluitFormulier" class="md:w-[32rem]">
        <form wire:submit="bevestigSluiten" class="space-y-6">
            <div>
                <flux:heading size="lg">Bevinding sluiten</flux:heading>
                <flux:subheading>
                    Een gesloten bevinding is definitief en kan niet heropend worden.
                </flux:subheading>
            </div>

            <flux:textarea wire:model="sluitNotitie" label="Afhandeling" required
                description="Wat is er met deze bevinding gebeurd? Bij de volgende audit is dit het antwoord op de vraag wat u ermee hebt gedaan." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontSluitFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Sluiten</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Behandeling van één object in de normatieve scope (plan 11d). --}}
    <flux:modal wire:model.self="toontBehandelFormulier" class="md:w-[32rem]">
        <form wire:submit="slaBehandelingOp" class="space-y-6">
            @php
                $behandeld = $auditronde->auditobjecten->firstWhere('id', $behandeldObjectId);
            @endphp

            <div>
                <flux:heading size="lg">{{ $behandeld?->refCode() }} behandelen</flux:heading>
                <flux:subheading>{{ $behandeld?->omschrijving() }}</flux:subheading>
            </div>

            <flux:radio.group wire:model.live="behandelAfhandeling" label="Afhandeling">
                @foreach ($handmatigeAfhandelingen as $waarde)
                    <flux:radio value="{{ $waarde }}" label="{{ ucfirst($afhandelingLabels[$waarde]) }}" />
                @endforeach
            </flux:radio.group>

            @if ($behandelAfhandeling === 'geen_opmerkingen')
                <x-keuzelijst wire:model="behandelBron" label="Bron" required
                    leeg="— kies een bron —" :opties="$bronnen"
                    description="Zonder bron is 'geen opmerkingen' een bewering; hiermee is het auditbewijs. Nagelezen in de documentatie? Kies eigen waarneming." />
            @else
                <flux:textarea wire:model="behandelToelichting" label="Reden"
                    description="Waarom is dit object niet behandeld? Dit staat straks in het dossier bij het gat in de dekking." />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitBehandelFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Vastleggen</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Afronden met objecten die nog grijs staan: geen blokkade, wel een reden
         per object (plan 11d §5). --}}
    <flux:modal wire:model.self="toontAfrondFormulier" class="md:w-[36rem]">
        <form wire:submit="rondAfMetRedenen" class="space-y-6">
            <div>
                <flux:heading size="lg">Ronde afronden</flux:heading>
                <flux:subheading>
                    Deze objecten staan nog in de scope zonder behandeling. Noteer per object waarom;
                    ze tellen daarna niet mee voor de dekking, en in het dossier staat waaróm er een gat zit.
                </flux:subheading>
            </div>

            <div class="max-h-64 space-y-3 overflow-y-auto">
                @foreach ($onbehandeld as $object)
                    <flux:input wire:key="reden-{{ $object->id }}"
                        wire:model="afrondRedenen.{{ $object->id }}"
                        label="{{ $object->refCode() }} {{ $object->omschrijving() }}" />
                @endforeach
            </div>

            <flux:text variant="subtle" class="text-xs">
                Na afronden zijn de bevindingen en de behandelingen definitief.
            </flux:text>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontAfrondFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Redenen vastleggen en afronden</flux:button>
            </div>
        </form>
    </flux:modal>

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

            <x-keuzelijst wire:model.live="bevindingAuditobjectId" label="Betreft" required
                leeg="— kies een clausule of control —" :opties="$onderwerpen"
                description="Ook de clausules uit H4-H10. Valt de keuze buiten de normatieve scope van deze ronde, dan groeit de scope mee." />

            <x-keuzelijst wire:model="bevindingBron" label="Bron" required
                leeg="— kies een bron —" :opties="$bronnen"
                description="Waar de bevinding vandaan komt: met wie je erover sprak, of je eigen waarneming. Staat er al een bron bij dit object in de scope, dan wordt die voorgesteld." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitBevindingFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
