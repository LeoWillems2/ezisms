<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.wijzigingen-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Wijzigingssjablonen</flux:heading>
            <flux:subheading>
                De stappenreeks per soort en zwaarte. Een nieuwe route maakt u hier zelf;
                aanpassingen gelden voor nieuwe dossiers, want lopende dossiers houden de reeks
                waarmee ze zijn gestart.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwSjabloon">Nieuw sjabloon</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if (session('belemmering'))
        <flux:callout variant="warning" icon="hand-raised" heading="Dat kan nu niet">
            <flux:callout.text>{{ session('belemmering') }}</flux:callout.text>
        </flux:callout>
    @endif

    @foreach ($sjablonen as $sjabloon)
        <flux:card wire:key="sjabloon-{{ $sjabloon->id }}">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">
                        {{ $sjabloon->naam }}
                        @unless ($sjabloon->actief)
                            <flux:badge size="sm" color="zinc">Inactief</flux:badge>
                        @endunless
                        @if ($sjabloon->geleverd)
                            <flux:badge size="sm" color="blue">Meegeleverd</flux:badge>
                            @if ($sjabloon->isAangepast())
                                <flux:badge size="sm" color="amber">Aangepast</flux:badge>
                            @endif
                        @endif
                    </flux:heading>
                    <flux:subheading>{{ $sjabloon->omschrijving }}</flux:subheading>

                    {{-- De normcontrole: een route raakt niet alleen kapot door een stap
                         te verwijderen, maar net zo goed door een staptype te veranderen.
                         Beide komen hier uit (implementatie/15 §19). --}}
                    @php $ontbreekt = $sjabloon->ontbrekendeStaptypen(); @endphp
                    @if ($ontbreekt !== [])
                        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2"
                            heading="Deze route dekt A.8.32 niet volledig">
                            <flux:callout.text>
                                Er ontbreekt een stap voor: {{ implode('; ', $ontbreekt) }}.
                                Afwijken mag, maar een dossier langs deze route laat die punten niet zien.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                </div>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="open({{ $sjabloon->id }})">
                        {{ $geopendSjabloonId === $sjabloon->id ? 'Inklappen' : 'Stappen tonen' }}
                    </flux:button>
                    @if ($this->magMuteren())
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            wire:click="bewerkSjabloon({{ $sjabloon->id }})">
                            Bewerken
                        </flux:button>
                        <flux:button size="sm" variant="ghost"
                            wire:click="zetActief({{ $sjabloon->id }}, {{ $sjabloon->actief ? 'false' : 'true' }})">
                            {{ $sjabloon->actief ? 'Op inactief' : 'Activeren' }}
                        </flux:button>
                        @if ($sjabloon->isAangepast())
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                                wire:click="zetTerug({{ $sjabloon->id }})"
                                wire:confirm="Deze route terugzetten naar de geleverde stappen? Uw aanpassingen aan de stappen vervallen.">
                                Terugzetten
                            </flux:button>
                        @endif
                        @unless ($sjabloon->geleverd)
                            <flux:button size="sm" variant="ghost" icon="trash"
                                wire:click="verwijderSjabloon({{ $sjabloon->id }})"
                                wire:confirm="Dit sjabloon verwijderen? Dat kan alleen als er nooit een dossier op heeft gedraaid.">
                                Verwijderen
                            </flux:button>
                        @endunless
                    @endif
                </div>
            </div>

            @if ($geopendSjabloonId === $sjabloon->id)
                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>Titel</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Deadline</flux:table.column>
                        <flux:table.column>Bijzonderheden</flux:table.column>
                        <flux:table.column align="end">Acties</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($sjabloon->stappen as $stap)
                            <flux:table.row wire:key="sjabloonstap-{{ $stap->id }}">
                                <flux:table.cell>{{ $stap->volgorde }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    {{ $stap->titel }}
                                    <flux:text class="text-xs">{{ $stap->omschrijving }}</flux:text>
                                </flux:table.cell>
                                <flux:table.cell>{{ ucfirst($stap->staptype) }}</flux:table.cell>
                                <flux:table.cell>
                                    {{-- Het anker is de geplande datum van de wijziging. --}}
                                    {{ $stap->deadline_offset_dagen > 0 ? '+' : '' }}{{ $stap->deadline_offset_dagen }} dagen
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($stap->bewijs_verplicht)
                                        <flux:badge size="sm" color="blue">Bewijs verplicht</flux:badge>
                                    @endif
                                    @if ($stap->bij_afkeuren_terug_naar)
                                        <flux:badge size="sm" color="zinc">
                                            Afkeuren → stap {{ $stap->bij_afkeuren_terug_naar }}
                                        </flux:badge>
                                    @endif
                                    @if ($stap->doelgroep)
                                        <flux:badge size="sm" color="zinc">{{ $stap->doelgroep->naam }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($this->magMuteren())
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                wire:click="bewerkStap({{ $stap->id }})">
                                                Bewerken
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" icon="trash"
                                                wire:click="verwijderStap({{ $stap->id }})"
                                                wire:confirm="Deze stap verwijderen?">
                                                Verwijderen
                                            </flux:button>
                                        </div>
                                    @else
                                        <flux:text>—</flux:text>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if ($this->magMuteren())
                    <flux:button size="sm" variant="ghost" icon="plus" class="mt-2"
                        wire:click="nieuweStap({{ $sjabloon->id }})">
                        Stap toevoegen
                    </flux:button>
                @endif
            @endif
        </flux:card>
    @endforeach

    <flux:modal wire:model.self="toontSjabloon" class="md:w-[32rem]">
        <form wire:submit="sjabloonOpslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $bewerktSjabloonId ? 'Sjabloon bewerken' : 'Nieuw sjabloon' }}</flux:heading>
                <flux:subheading>
                    Soort en zwaarte bepalen wanneer deze route gekozen wordt; de stappen voegt u
                    daarna toe.
                </flux:subheading>
            </div>

            <flux:input wire:model="sjabloonNaam" label="Naam" required />
            <flux:textarea wire:model="sjabloonOmschrijving" label="Omschrijving"
                description="Waarvoor is deze route bedoeld, en wat onderscheidt hem van de andere?" />

            <flux:select wire:model="sjabloonSoort" label="Soort" required>
                @foreach (\App\Livewire\WijzigingenOverzicht::SOORTEN as $soortOptie)
                    <flux:select.option value="{{ $soortOptie }}">{{ ucfirst($soortOptie) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="sjabloonZwaarte" label="Zwaarte" required>
                <flux:select.option value="standaard">Standaard</flux:select.option>
                <flux:select.option value="ingrijpend">Ingrijpend</flux:select.option>
                {{-- Spoed is geen vlag in de code maar gewoon een sjabloon waarin
                     'uitvoeren' vóór 'goedkeuring' staat (implementatie/15 §2c). --}}
                <flux:select.option value="spoed">Spoed</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontSjabloon', false)">
                    Annuleren
                </flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="toontStap" class="md:w-[34rem]">
        <form wire:submit="stapOpslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $stapId ? 'Stap bewerken' : 'Nieuwe stap' }}</flux:heading>
                <flux:subheading>
                    Stappen met dezelfde volgorde lopen parallel en worden samen actueel.
                </flux:subheading>
            </div>

            <flux:input wire:model="titel" label="Titel" required />
            <flux:textarea wire:model="omschrijving" label="Omschrijving" />

            <flux:select wire:model="staptype" label="Type" required>
                @foreach (\App\Models\Sjabloonstap::STAPTYPEN as $type)
                    <flux:select.option value="{{ $type }}">{{ ucfirst($type) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="volgorde" type="number" min="1" label="Volgorde" required />
                <flux:input wire:model="deadlineOffsetDagen" type="number" label="Dagen t.o.v. de planning"
                    description="Negatief = ervóór." required />
            </div>

            {{-- Toewijzen gebeurt normaal op het dossier: wie een stap doet, blijkt
                 pas als de wijziging er is (implementatie/15 §18). Dit veld is er
                 alleen voor de uitzondering die altijd bij dezelfde persoon ligt. --}}
            <x-keuzelijst wire:model="standaardEigenaarId" label="Standaard-eigenaar, meestal leeg laten"
                leeg="Geen standaard" :opties="$gebruikers->pluck('naam', 'id')"
                description="Wie een stap doet, wijst u per wijziging toe op het dossier. Vul dit alleen
                    in voor een stap die altijd bij dezelfde persoon ligt, zoals een vaste autorisatie." />

            <x-keuzelijst wire:model="doelgroepId" label="Doelgroep (bij informeren)" leeg="Geen doelgroep"
                :opties="$doelgroepen" />

            <flux:input wire:model="bijAfkeurenTerugNaar" type="number" min="1"
                label="Bij afkeuren terug naar volgorde"
                description="Leeg = een afkeuring wijst de wijziging af." />

            <flux:checkbox wire:model="bewijsVerplicht" label="Bewijs verplicht vóór afronden" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontStap', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
