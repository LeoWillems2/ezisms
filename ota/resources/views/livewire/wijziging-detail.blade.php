<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.wijzigingen-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $wijziging->titel }}</flux:heading>
            <flux:subheading>
                {{ ucfirst($wijziging->soort) }} · {{ ucfirst($wijziging->zwaarte) }}
                @if ($wijziging->leverancier) · {{ $wijziging->leverancier->naam }} @endif
                @if ($wijziging->externe_referentie) · ticket {{ $wijziging->externe_referentie }} @endif
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @php
                $statusKleur = match ($wijziging->status) {
                    'gesloten' => 'green',
                    'afgewezen', 'geannuleerd' => 'red',
                    'uitgevoerd' => 'blue',
                    default => 'zinc',
                };
            @endphp
            <flux:badge :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $wijziging->status)) }}</flux:badge>
        </div>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- De belemmering uit het dossier (implementatie/15 §6): dezelfde melding
         die een gebruiker op /taken krijgt wanneer hij daar de stap afvinkt. --}}
    @if (session('belemmering'))
        <flux:callout variant="danger" icon="hand-raised" heading="Deze stap kan nu niet worden afgerond">
            <flux:callout.text>{{ session('belemmering') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($wijziging->isAfgerond())
        <div class="flex flex-col gap-2">
            <flux:callout variant="secondary" icon="lock-closed"
                heading="Dit dossier is afgerond en niet meer te wijzigen">
                @if ($this->magMuteren())
                    <flux:callout.text>
                        Klopt er iets niet aan de afsluiting? Heropenen zet het dossier terug; de
                        evaluatie vervalt en de oude stand blijft in de audit trail staan.
                    </flux:callout.text>
                @endif
            </flux:callout>

            @if ($this->magMuteren())
                {{-- De enige uitweg uit een eindstand. Zonder deze knop is een dossier
                     dat ten onrechte is gesloten onherstelbaar, en blijft een gap-signaal
                     dat eruit volgt voorgoed op het register staan. --}}
                <div>
                    <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="heropenen"
                        wire:confirm="Dit dossier heropenen? De vastgelegde evaluatie vervalt.">
                        Dossier heropenen
                    </flux:button>
                </div>
            @endif
        </div>
    @endif

    {{-- In behandeling nemen: sjabloon kiezen en de voorgenomen datum zetten.
         De stapdeadlines volgen daaruit (implementatie/15 §3). --}}
    @if ($wijziging->status === 'aangemeld' && $this->magMuteren())
        <flux:card>
            <flux:heading size="lg">In behandeling nemen</flux:heading>
            <flux:subheading>
                Kies de route en de voorgenomen datum. De stappen en hun deadlines volgen uit het sjabloon.
            </flux:subheading>

            <form wire:submit="neemInBehandeling" class="mt-4 flex flex-wrap items-end gap-4">
                <x-keuzelijst wire:model="sjabloonId" label="Sjabloon" leeg="Kies een sjabloon" required
                    :opties="$sjablonen" class="max-w-96" />
                <flux:input wire:model="geplandOp" type="date" label="Voorgenomen datum" required class="max-w-56" />
                <flux:button variant="primary" type="submit">Reeks starten</flux:button>
            </form>
        </flux:card>
    @endif

    {{-- Een stap zonder eigenaar staat bij niemand onder "mijn taken" en levert
         geen bericht op: de reeks loopt dan stil zonder dat iemand iets merkt. --}}
    @if ($zonderEigenaar > 0 && ! $wijziging->isAfgerond())
        <flux:callout variant="warning" icon="user-plus"
            heading="{{ $zonderEigenaar }} stap(pen) zonder eigenaar">
            <flux:callout.text>
                Wijs ze hieronder toe. Zolang een stap bij niemand staat, krijgt niemand er bericht
                over en verschijnt hij bij niemand in de takenlijst.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- De stappenreeks. Dit is het enige scherm dat hem in zijn geheel toont;
         op /taken ziet een Medewerker alleen zijn eigen stappen (07b §11). --}}
    @if ($stappen->isNotEmpty())
        <flux:card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">Stappen</flux:heading>
                    <flux:subheading>
                        Gepland op {{ $wijziging->gepland_op?->format('d-m-Y') ?? '—' }}
                        @if ($wijziging->uitgevoerd_op)
                            · uitgevoerd op {{ $wijziging->uitgevoerd_op->format('d-m-Y') }}
                        @endif
                    </flux:subheading>
                </div>

                @if ($this->magMuteren() && ! $wijziging->isAfgerond())
                    <form wire:submit="verzetPlanning" class="flex items-end gap-2">
                        <flux:input wire:model="geplandOp" type="date" label="Planning verzetten" class="max-w-44" />
                        <flux:button size="sm" type="submit">Verzetten</flux:button>
                    </form>
                @endif
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>#</flux:table.column>
                    <flux:table.column>Stap</flux:table.column>
                    <flux:table.column>Eigenaar</flux:table.column>
                    <flux:table.column>Deadline</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($stappen as $stap)
                        <flux:table.row wire:key="stap-{{ $stap->id }}">
                            <flux:table.cell>{{ $stap->volgorde }}</flux:table.cell>
                            <flux:table.cell variant="strong">
                                {{ $stap->titel }}
                                @if ($stap->staptype)
                                    <flux:text class="text-xs">{{ ucfirst($stap->staptype) }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{-- Toewijzen hoort bij het dossier: wie een stap doet,
                                     blijkt pas als de wijziging er is. --}}
                                @if ($this->magMuteren() && ! $wijziging->isAfgerond() && $stap->status !== 'voltooid')
                                    <flux:select size="sm" class="max-w-52"
                                        wire:change="wijsToe({{ $stap->id }}, $event.target.value)">
                                        <flux:select.option value="" :selected="$stap->eigenaar_id === null">
                                            Niemand toegewezen
                                        </flux:select.option>
                                        @foreach ($gebruikers as $gebruiker)
                                            <flux:select.option value="{{ $gebruiker->id }}"
                                                :selected="$stap->eigenaar_id === $gebruiker->id">
                                                {{ $gebruiker->naam }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($stap->eigenaar)
                                    <x-gebruiker-naam :gebruiker="$stap->eigenaar" />
                                @else
                                    <flux:badge size="sm" color="amber">Geen eigenaar</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="{{ $stap->isFeitelijkVerlopen() ? 'text-red-600 dark:text-red-500' : '' }}">
                                    {{ $stap->deadline->format('d-m-Y') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $stapKleur = match ($stap->status) {
                                        'voltooid' => 'green',
                                        'in_uitvoering' => 'blue',
                                        'verlopen' => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$stapKleur">
                                    {{ ucfirst(str_replace('_', ' ', $stap->status)) }}
                                </flux:badge>
                                @if ($stap->uitkomst === 'goedgekeurd')
                                    <flux:badge size="sm" color="green">Goedgekeurd</flux:badge>
                                @elseif ($stap->uitkomst === 'afgekeurd')
                                    <flux:badge size="sm" color="red">Afgekeurd</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if (! $wijziging->isAfgerond() && $stap->status !== 'voltooid'
                                    && $stap->status !== 'wachtend' && ($stap->isVanMij() || $this->magMuteren()))
                                    <div class="flex justify-end gap-1">
                                        @if ($stap->vraagt_uitkomst)
                                            <flux:button size="sm" variant="ghost" icon="check"
                                                wire:click="stapBeslissen({{ $stap->id }}, 'goedgekeurd')">
                                                Goedkeuren
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" icon="x-mark"
                                                wire:click="stapBeslissen({{ $stap->id }}, 'afgekeurd')">
                                                Afkeuren
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" icon="check"
                                                wire:click="stapVoltooien({{ $stap->id }})">
                                                Afronden
                                            </flux:button>
                                        @endif
                                    </div>
                                @else
                                    <flux:text>—</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    {{-- Dossiervelden. Het terugvalplan staat hier omdat een uitvoerstap er
         hard op controleert (A.8.32 f). --}}
    <flux:card>
        <flux:heading size="lg">Dossier</flux:heading>

        @if ($this->magMuteren() && ! $wijziging->isAfgerond())
            <form wire:submit="dossierOpslaan" class="mt-4 space-y-6">
                <flux:textarea wire:model="impactToelichting" label="Impactanalyse"
                    description="Downtime, afhankelijkheden, gewijzigde werking, licenties (A.8.32 a)." />

                <flux:textarea wire:model="terugvalplan" label="Terugvalplan"
                    description="Verplicht vóórdat een uitvoerstap mag worden afgerond (A.8.32 f)." />

                <flux:input wire:model="externeReferentie" label="Ticketnummer" />

                <flux:field>
                    <flux:label>Geraakte systemen</flux:label>
                    <flux:description>Eén release raakt vaak meerdere systemen.</flux:description>
                    <div class="mt-2 grid gap-1 md:grid-cols-2">
                        @foreach ($alleSystemen as $systeemId => $systeemNaam)
                            <flux:checkbox wire:model="systeemIds" value="{{ $systeemId }}"
                                label="{{ $systeemNaam }}" />
                        @endforeach
                    </div>
                </flux:field>

                <flux:button variant="primary" type="submit">Dossier opslaan</flux:button>
            </form>
        @else
            <div class="mt-4 space-y-4">
                <div>
                    <flux:heading size="sm">Impactanalyse</flux:heading>
                    <flux:text>{{ $wijziging->impact_toelichting ?: '—' }}</flux:text>
                </div>
                <div>
                    <flux:heading size="sm">Terugvalplan</flux:heading>
                    <flux:text>{{ $wijziging->terugvalplan ?: '—' }}</flux:text>
                </div>
                <div>
                    <flux:heading size="sm">Geraakte systemen</flux:heading>
                    <flux:text>{{ $wijziging->systemen->pluck('naam')->join(', ') ?: '—' }}</flux:text>
                </div>
            </div>
        @endif
    </flux:card>

    <livewire:bewijs-paneel blok-naam="wijzigingsbeheer" entiteit-type="wijziging"
        :entiteit-id="$wijziging->id" />

    {{-- Evaluatie: A.8.32 g). Pas mogelijk als alle stappen klaar zijn. --}}
    <flux:card>
        <flux:heading size="lg">Evaluatie</flux:heading>

        @if ($wijziging->status === 'gesloten')
            <div class="mt-4 space-y-2">
                <flux:badge :color="$wijziging->geslaagd ? 'green' : 'red'">
                    {{ $wijziging->geslaagd ? 'Geslaagd' : 'Niet geslaagd' }}
                </flux:badge>
                @if ($wijziging->teruggedraaid)
                    <flux:badge color="amber">Teruggedraaid</flux:badge>
                @endif
                <flux:text>{{ $wijziging->evaluatie }}</flux:text>
            </div>
        @elseif ($this->magMuteren() && ! $wijziging->isAfgerond())
            @if ($belemmeringVoorSluiten)
                <flux:callout variant="secondary" icon="clock" class="mt-4"
                    heading="{{ $belemmeringVoorSluiten }}" />
            @else
                <form wire:submit="afsluiten" class="mt-4 space-y-6">
                    <flux:checkbox wire:model="geslaagd" label="De wijziging is geslaagd" />
                    <flux:checkbox wire:model="teruggedraaid" label="De wijziging is teruggedraaid" />
                    <flux:textarea wire:model="evaluatie" label="Wat ging goed, wat kan beter?" required />
                    <flux:button variant="primary" type="submit">Evalueren en sluiten</flux:button>
                </form>
            @endif
        @else
            <flux:text class="mt-4">Nog niet geëvalueerd.</flux:text>
        @endif
    </flux:card>

    @if ($this->magMuteren() && ! $wijziging->isAfgerond())
        <div>
            <flux:button variant="ghost" icon="x-circle" wire:click="annuleren"
                wire:confirm="Deze wijziging annuleren? Het dossier wordt daarmee afgerond.">
                Wijziging annuleren
            </flux:button>
        </div>
    @endif
</div>
