<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewijs-subnav')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Audit trail</flux:heading>
            <flux:subheading>
                Append-only logboek van wie-wat-wanneer over alle blokken heen. Regels kunnen niet
                worden gewijzigd of verwijderd.
            </flux:subheading>
        </div>

        @include('partials.kopieknop')
    </div>

    {{-- De ketenstatus (implementatie/06c §6). Staat bovenaan omdat dit de eerste
         vraag is die een auditor bij een audit trail stelt: hoe weet u dat er
         niets uit is gehaald? --}}
    <div class="flex flex-wrap items-center gap-3">
        @if ($ketencontrole === null)
            <flux:badge size="sm" color="zinc">Keten nog niet gecontroleerd</flux:badge>
        @else
            <flux:badge size="sm" :color="$ketencontrole->intact ? 'green' : 'red'">
                {{ $ketencontrole->samenvatting() }}
            </flux:badge>
            <flux:text class="text-xs">
                {{ $ketencontrole->isVerzegeling() ? 'Verzegeld' : 'Gecontroleerd' }}
                op {{ $ketencontrole->tijdstip->lokaal()->format('d-m-Y H:i') }}
                @if ($kophash !== null)
                    — kophash <span class="font-mono">{{ \Illuminate\Support\Str::limit($kophash, 12, '…') }}</span>
                @endif
            </flux:text>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterBlok" label="Blok" class="max-w-56">
            <flux:select.option value="">Alle blokken</flux:select.option>
            @foreach ($blokken as $blok)
                <flux:select.option value="{{ $blok->code }}">{{ $blok->naam }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterEntiteitType" label="Entiteit" class="max-w-48">
            <flux:select.option value="">Alle</flux:select.option>
            @foreach ($entiteitTypes as $type)
                <flux:select.option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterGebruikerId" label="Gebruiker" class="max-w-48">
            <flux:select.option value="">Iedereen</flux:select.option>
            @foreach ($gebruikers as $gebruiker)
                <flux:select.option value="{{ $gebruiker->id }}">{{ $gebruiker->naam }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterActie" label="Actie" class="max-w-48">
            <flux:select.option value="">Alle acties</flux:select.option>
            <flux:select.option value="aangemaakt">Aangemaakt</flux:select.option>
            <flux:select.option value="gewijzigd">Gewijzigd</flux:select.option>
            <flux:select.option value="status_gewijzigd">Status gewijzigd</flux:select.option>
            <flux:select.option value="verwijderd">Verwijderd</flux:select.option>
        </flux:select>

        <flux:input type="date" wire:model.live="vanaf" label="Vanaf" class="max-w-44" />
        <flux:input type="date" wire:model.live="tot" label="Tot en met" class="max-w-44" />
    </div>

    <flux:table>
        <flux:table.columns>
            {{-- De zone erbij: dit is het scherm waarop een regel naast extern
                 bewijs wordt gelegd (een mail, een ticket). Een tijd zonder zone
                 nodigt uit tot een vergelijking die er een of twee uur naast zit
                 zonder dat iemand dat merkt (00o §3). --}}
            <flux:table.column>Tijdstip ({{ now()->lokaal()->format('T') }})</flux:table.column>
            <flux:table.column>Gebruiker</flux:table.column>
            <flux:table.column>Blok</flux:table.column>
            <flux:table.column>Entiteit</flux:table.column>
            <flux:table.column>Actie</flux:table.column>
            <flux:table.column>Wijziging</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($regels as $regel)
                <flux:table.row wire:key="logregel-{{ $regel->id }}">
                    <flux:table.cell>{{ $regel->tijdstip->lokaal()->format('d-m-Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell>
                        {{-- Momentopname: blijft leesbaar als het account weg is. --}}
                        {{ $regel->gebruiker_naam }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $regel->blok_naam }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $regel->entiteit_omschrijving }}
                        <flux:text class="text-xs">
                            {{ str_replace('_', ' ', $regel->entiteit_type) }}
                            {{-- Zonder id: een gebeurtenis die de hele verzameling
                                 raakt, zoals het handhaven van een bewaartermijn.
                                 "#" zonder nummer zou als een fout lezen. --}}
                            @if ($regel->entiteit_id !== null)
                                #{{ $regel->entiteit_id }}
                            @else
                                (verzameling)
                            @endif
                        </flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $actieKleur = match ($regel->actie) {
                                'aangemaakt' => 'green',
                                'verwijderd' => 'red',
                                'status_gewijzigd' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$actieKleur">
                            {{ ucfirst(str_replace('_', ' ', $regel->actie)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($regel->gewijzigdeVelden())
                            {{-- Native <details>: flux:accordion is een Pro-component. --}}
                            <details class="text-sm">
                                <summary class="cursor-pointer select-none">
                                    {{ implode(', ', $regel->gewijzigdeVelden()) }}
                                </summary>
                                <table class="mt-2 text-xs">
                                    <tbody>
                                        @foreach ($regel->gewijzigdeVelden() as $veld)
                                            <tr>
                                                <td class="pr-3 align-top font-medium">{{ $veld }}</td>
                                                <td class="pr-3 align-top text-zinc-500">
                                                    {{ \Illuminate\Support\Str::limit((string) ($regel->oude_waarde[$veld] ?? '—'), 60) }}
                                                </td>
                                                <td class="align-top">
                                                    &rarr; {{ \Illuminate\Support\Str::limit((string) ($regel->nieuwe_waarde[$veld] ?? '—'), 60) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6"><flux:text>Geen logregels gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Als enige overzicht gepagineerd: dit is de enige tabel die onbeperkt groeit. --}}
    {{ $regels->links() }}
</div>
