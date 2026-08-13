<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.taken-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Taken</flux:heading>
            <flux:subheading>
                Terugkerende en eenmalige compliance-taken met eigenaar, deadline en escalatie.
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweTaak">Nieuwe taak</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($metNietActieveEigenaar > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $metNietActieveEigenaar }} openstaande taak/taken bij een niet-actieve eigenaar">
            <flux:callout.text>
                De eigenaar is gedeactiveerd, geblokkeerd of nog niet actief en krijgt de taak dus
                niet meer onder ogen. Wijs de taak toe aan een actief account.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-56">
            <flux:select.option value="openstaand">Openstaand</flux:select.option>
            <flux:select.option value="">Alle</flux:select.option>
            <flux:select.option value="wachtend">Wachtend</flux:select.option>
            <flux:select.option value="open">Open</flux:select.option>
            <flux:select.option value="in_uitvoering">In uitvoering</flux:select.option>
            <flux:select.option value="verlopen">Verlopen</flux:select.option>
            <flux:select.option value="voltooid">Voltooid</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterBlok" label="Blok" class="max-w-56">
            <flux:select.option value="">Alle blokken</flux:select.option>
            <flux:select.option value="identity-access">Identity &amp; Access</flux:select.option>
            <flux:select.option value="context-scope">Context &amp; Scope</flux:select.option>
            <flux:select.option value="asset-classificatie">Assets</flux:select.option>
            <flux:select.option value="risico-soa">Risico &amp; SoA</flux:select.option>
        </flux:select>

        @if ($this->magAllesZien())
            <flux:switch wire:model.live="alleenVanMij" label="Alleen mijn taken" />
        @endif
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Gekoppeld aan</flux:table.column>
            <flux:table.column>Eigenaar</flux:table.column>
            <flux:table.column>Deadline</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($taken as $taak)
                <flux:table.row wire:key="taak-{{ $taak->id }}">
                    <flux:table.cell variant="strong">
                        {{ $taak->titel }}
                        @if ($taak->sjabloon)
                            <flux:text class="text-xs">uit sjabloon: {{ $taak->sjabloon->naam }}</flux:text>
                        @endif
                        @php
                            // Positie in de reeks, in groepen geteld (zie
                            // TakenOverzicht::reeksen): parallelle stappen delen
                            // hun volgorde en de nummering kan gaten hebben.
                            $volgordes = $taak->isStap()
                                ? ($reeksen[$taak->gekoppeld_entiteit_type . ':' . $taak->gekoppeld_entiteit_id] ?? [])
                                : [];
                            $positie = $volgordes ? array_search($taak->volgorde, $volgordes, true) : false;
                        @endphp
                        @if ($positie !== false)
                            <flux:text class="text-xs">stap {{ $positie + 1 }} van {{ count($volgordes) }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($taak->gekoppeldOmschrijving())
                            <flux:badge size="sm" color="zinc">{{ $taak->gekoppeldOmschrijving() }}</flux:badge>
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($taak->eigenaar)
                            <x-gebruiker-naam :gebruiker="$taak->eigenaar" />
                        @else
                            {{-- Zonder eigenaar herinnert de taak niemand ergens aan. --}}
                            <flux:badge size="sm" color="amber">Geen eigenaar</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            // isFeitelijkVerlopen(), niet de opgeslagen status:
                            // een stilstaande scheduler mag geen taak als "op tijd"
                            // tonen waarvan de deadline gisteren lag.
                            $verlopen = $taak->isFeitelijkVerlopen();
                            $bijna = ! $verlopen && $taak->status !== 'voltooid'
                                && $taak->deadline->isBefore(now()->addDays(7));
                            $kleur = $verlopen ? 'text-red-600 dark:text-red-500'
                                : ($bijna ? 'text-amber-600 dark:text-amber-500' : '');
                        @endphp
                        <span class="{{ $kleur }}">{{ $taak->deadline->format('d-m-Y') }}</span>
                        @if ($taak->escalatie_niveau === 2)
                            <flux:badge size="sm" color="red">Escalatie</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $statusKleur = match ($taak->status) {
                                'voltooid' => 'green',
                                'in_uitvoering' => 'blue',
                                'verlopen' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$statusKleur">
                            {{ ucfirst(str_replace('_', ' ', $taak->status)) }}
                        </flux:badge>
                        @if ($taak->uitkomst === 'goedgekeurd')
                            <flux:badge size="sm" color="green">Goedgekeurd</flux:badge>
                        @elseif ($taak->uitkomst === 'afgekeurd')
                            <flux:badge size="sm" color="red">Afgekeurd</flux:badge>
                        @endif
                        @if ($taak->status === 'voltooid' && $taak->vertragingInDagen() > 0)
                            <flux:text class="text-xs">{{ $taak->vertragingInDagen() }} dag(en) te laat</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($taak->isVanMij() || $this->magMuteren())
                            <div class="flex justify-end gap-1">
                                {{-- Toetstaak (blok 10): de deelnemer start de toets via de
                                     token-URL uit de relatie; de token staat zo nergens in
                                     vrije tekst. --}}
                                @if ($taak->toetsopdracht && $taak->status !== 'voltooid' && $taak->isVanMij())
                                    <flux:button size="sm" variant="primary" icon="academic-cap"
                                        href="{{ $taak->toetsopdracht->deelnemerUrl() }}" target="_blank">
                                        Start toets
                                    </flux:button>
                                @endif
                                @if (in_array($taak->status, ['open', 'verlopen'], true))
                                    <flux:button size="sm" variant="ghost" wire:click="oppakken({{ $taak->id }})">
                                        Oppakken
                                    </flux:button>
                                @endif
                                {{-- Een wachtende stap is nog niet aan de beurt; een goedkeuringsstap
                                     rondt af mét resultaat, want "voltooid" zegt daar niets over de
                                     uitkomst (implementatie/07b §10). --}}
                                @if ($taak->status !== 'voltooid' && $taak->status !== 'wachtend')
                                    @if ($taak->vraagt_uitkomst)
                                        <flux:button size="sm" variant="ghost" icon="check"
                                            wire:click="legUitkomstVast({{ $taak->id }}, 'goedgekeurd')">
                                            Goedkeuren
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" icon="x-mark"
                                            wire:click="legUitkomstVast({{ $taak->id }}, 'afgekeurd')">
                                            Afkeuren
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="check"
                                            wire:click="voltooien({{ $taak->id }})">
                                            Voltooien
                                        </flux:button>
                                    @endif
                                @endif
                                {{-- Heropenen: toezichtsactie voor de CISO om een afmelding af te
                                     keuren. Alleen bij taken waarvan de status zelf de waarheid is;
                                     bij beheerde/toetstaken komt voltooid uit de bron. --}}
                                @if ($this->magMuteren() && $taak->status === 'voltooid' && $taak->isHeropenbaar())
                                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                                        wire:click="heropenen({{ $taak->id }})">
                                        Heropenen
                                    </flux:button>
                                @endif
                                @if ($this->magMuteren())
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        wire:click="bewerk({{ $taak->id }})">
                                        Bewerken
                                    </flux:button>
                                @endif
                            </div>
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6"><flux:text>Geen taken gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            @php $beheerd = $this->bewerkteTaak?->deadlineWordtBeheerd() ?? false; @endphp

            <div>
                <flux:heading size="lg">{{ $bewerktId ? 'Taak bewerken' : 'Nieuwe taak' }}</flux:heading>
                <flux:subheading>
                    @if ($bewerktId)
                        Terugkerende taken beheer je bij Sjablonen; hier pas je deze ene taak aan.
                    @else
                        Een losse taak zonder sjabloon; terugkerende taken beheer je bij Sjablonen.
                    @endif
                </flux:subheading>
            </div>

            <flux:input wire:model="titel" label="Titel" required />
            <flux:textarea wire:model="omschrijving" label="Omschrijving" />

            <x-keuzelijst wire:model="eigenaarId" label="Eigenaar" leeg="Kies een eigenaar" required
                :opties="$gebruikers->pluck('naam', 'id')"
                description="Verplicht: een taak zonder eigenaar verschijnt bij niemand op het dashboard." />

            <flux:input wire:model="deadline" type="date" label="Deadline" required
                :readonly="$beheerd"
                :description="$beheerd
                    ? 'Deze deadline komt uit '.$this->bewerkteTaak->gekoppeldOmschrijving().' en wordt daar bijgehouden.'
                    : null" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">{{ $bewerktId ? 'Opslaan' : 'Aanmaken' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
