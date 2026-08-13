<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">Trainingen</flux:heading>
            <flux:subheading>
                Awareness- en trainingsmodules, hun doelgroepen en de trainingsgraad
                (&sect;7.2, &sect;7.3, {{ $norm->bijlage }} 6.3).
            </flux:subheading>
        </div>
        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweModule">Nieuwe module</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($modulesZonderDoelgroep > 0)
        <flux:callout variant="warning" icon="user-group"
            heading="{{ $modulesZonderDoelgroep }} actieve module(s) zonder doelgroep">
            <flux:callout.text>Een verplichting zonder publiek: koppel een doelgroep zodat de training iemand bereikt.</flux:callout.text>
        </flux:callout>
    @endif

    @if ($toetsModulesNietUitgezet > 0)
        <flux:callout variant="warning" icon="academic-cap"
            heading="{{ $toetsModulesNietUitgezet }} toets-module(s) nog niet uitgezet">
            <flux:callout.text>
                Deze modules worden via een toets afgerond, maar er is nog geen toets uitgezet — dan is
                de module niet af te ronden. Zet de toets uit via "Toetsen uitzetten".
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterActief" label="Status" class="max-w-56">
            <flux:select.option value="actief">Actief</flux:select.option>
            <flux:select.option value="ingetrokken">Ingetrokken</flux:select.option>
            <flux:select.option value="">Alle</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterDoelgroep" label="Doelgroep" class="max-w-56">
            <flux:select.option value="">Alle doelgroepen</flux:select.option>
            @foreach ($doelgroepen as $doelgroep)
                <flux:select.option value="{{ $doelgroep->id }}">{{ $doelgroep->naam }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Geldigheid</flux:table.column>
            <flux:table.column>Toets</flux:table.column>
            <flux:table.column>Doelgroep</flux:table.column>
            <flux:table.column>Trainingsgraad</flux:table.column>
            <flux:table.column>Verlopen</flux:table.column>
            @if ($this->magMuteren())
                <flux:table.column align="end">Acties</flux:table.column>
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rijen as $rij)
                @php($module = $rij['module'])
                <flux:table.row wire:key="module-{{ $module->id }}">
                    <flux:table.cell variant="strong">
                        {{ $module->titel }}
                        @unless ($module->actief)
                            <flux:badge size="sm" color="zinc">ingetrokken</flux:badge>
                        @endunless
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $module->geldigheidsduur_maanden ? $module->geldigheidsduur_maanden.' mnd' : 'eenmalig' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($module->heeftToets())
                            <flux:badge size="sm" color="blue">{{ $module->toets_bestand }}</flux:badge>
                        @else
                            <flux:text variant="subtle">zelfregistratie</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $rij['doelgroepGrootte'] }} gebr.</flux:table.cell>
                    <flux:table.cell>
                        @if ($rij['graad'] === null)
                            <flux:text variant="subtle">n.v.t.</flux:text>
                        @else
                            <flux:badge size="sm" :color="$rij['graad'] >= 80 ? 'green' : ($rij['graad'] >= 50 ? 'amber' : 'red')">
                                {{ $rij['graad'] }}%
                            </flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($rij['verlopen'] > 0)
                            <flux:text class="text-red-600 dark:text-red-400">{{ $rij['verlopen'] }}</flux:text>
                        @else
                            &mdash;
                        @endif
                    </flux:table.cell>
                    @if ($this->magMuteren())
                        <flux:table.cell align="end">
                            {{-- Toets inzien: de voorbeeldroute serveert hem
                                 zonder token uit, in een nieuw tabblad. Alleen
                                 als het bestand bestaat (§8, 01e §1.3). --}}
                            @if ($rij['toetsPreviewUrl'])
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    :href="$rij['toetsPreviewUrl']" target="_blank">Preview</flux:button>
                            @endif
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                wire:click="bewerk({{ $module->id }})">Bewerken</flux:button>
                            <flux:button size="sm" variant="ghost"
                                wire:click="wisselActief({{ $module->id }})">
                                {{ $module->actief ? 'Intrekken' : 'Heractiveren' }}
                            </flux:button>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Geen modules gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Formulier --}}
    <flux:modal wire:model.self="toontFormulier" class="max-w-lg">
        <form wire:submit="opslaan" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $bewerktId ? 'Module bewerken' : 'Nieuwe module' }}</flux:heading>

            <flux:input wire:model="titel" label="Titel" />
            <flux:input type="number" wire:model="geldigheidsduurMaanden" label="Geldigheidsduur (maanden)"
                description="Leeg = eenmalig, geen verloop." min="1" max="120" />

            <x-keuzelijst wire:model="toetsBestand" label="Toets (optioneel)"
                description="Kies een toetsbestand; de module wordt dan via de toets afgerond in plaats van via zelfregistratie."
                :opties="collect($toetsen)->mapWithKeys(fn ($titel, $bestand) => [$bestand => $titel.' ('.$bestand.')'])" />

            <div>
                <flux:label>Doelgroepen</flux:label>
                <div class="mt-2 flex flex-col gap-2">
                    @forelse ($doelgroepen as $doelgroep)
                        <flux:checkbox wire:model="geselecteerdeDoelgroepen" value="{{ $doelgroep->id }}"
                            label="{{ $doelgroep->naam }}" />
                    @empty
                        <flux:text variant="subtle">Nog geen doelgroepen — maak er eerst een aan.</flux:text>
                    @endforelse
                </div>
            </div>

            @if ($beleidsdocumenten->isNotEmpty())
                <div>
                    <flux:label>Gekoppeld beleid (informatief)</flux:label>
                    <div class="mt-2 flex flex-col gap-2">
                        @foreach ($beleidsdocumenten as $doc)
                            <flux:checkbox wire:model="geselecteerdeBeleidsdocumenten" value="{{ $doc->id }}"
                                label="{{ $doc->titel }}" />
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
