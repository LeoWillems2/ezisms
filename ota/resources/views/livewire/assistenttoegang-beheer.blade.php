<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.notificatie-subnav')

    <div>
        <flux:heading size="xl">Assistenttoegang</flux:heading>
        <flux:subheading>
            Welke rollen een AI-assistent aan het ISMS mogen koppelen, wie dat gedaan heeft en wat er is opgevraagd.
        </flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <flux:callout icon="information-circle">
        <flux:callout.heading>Alleen lezen, namens de gebruiker</flux:callout.heading>
        <flux:callout.text>
            Een gekoppelde assistent ziet precies wat de gebruiker zelf in het ISMS ziet, inclusief bijlagen en
            bewijsstukken, en kan niets wijzigen. Wat de assistent opvraagt, gaat naar de AI-dienst die de gebruiker
            met die assistent gebruikt. Het adres om te koppelen is <code>{{ $mcpUrl }}</code>.
            Zie het kennisbankartikel <a href="{{ route('kennisbank', 'assistenttoegang') }}" class="underline" wire:navigate>Assistenttoegang</a>.
        </flux:callout.text>
    </flux:callout>

    <section class="blueprint p-4">
        <flux:heading size="lg">Rollen</flux:heading>
        <flux:text class="mb-3 text-sm">
            Standaard mag geen enkele rol koppelen. Uitzetten trekt de bestaande koppelingen in van wie daardoor geen
            recht meer heeft. Elke wijziging staat in de audit trail.
        </flux:text>

        <div class="flex flex-col gap-2">
            @foreach ($rollen as $rol)
                @php $aan = in_array($rol->id, $toegestaan, true); @endphp
                <div class="flex items-center justify-between gap-4" wire:key="rol-{{ $rol->id }}">
                    <div>
                        <span class="font-medium">{{ $rol->naam }}</span>
                        <flux:badge size="sm" :color="$aan ? 'green' : 'zinc'">{{ $aan ? 'mag koppelen' : 'mag niet koppelen' }}</flux:badge>
                    </div>
                    @if ($this->magMuteren())
                        @if ($aan)
                            <flux:button size="sm" variant="ghost" wire:click="zetRol({{ $rol->id }}, false)"
                                wire:confirm="Koppelen uitzetten voor {{ $rol->naam }}? Bestaande koppelingen van wie daardoor geen recht meer heeft, worden ingetrokken.">
                                Uitzetten
                            </flux:button>
                        @else
                            <flux:button size="sm" variant="ghost" wire:click="zetRol({{ $rol->id }}, true)">Aanzetten</flux:button>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="blueprint p-4">
        <flux:heading size="lg">Gekoppelde assistenten</flux:heading>

        @forelse ($koppelingen as $rij)
            <div class="mt-3" wire:key="koppeling-{{ $rij['gebruiker']->id }}">
                <div class="font-medium">{{ $rij['gebruiker']->naam }}</div>
                @foreach ($rij['clients'] as $client)
                    <div class="flex items-center justify-between gap-4 text-sm" wire:key="client-{{ $rij['gebruiker']->id }}-{{ $client['client_id'] }}">
                        <div>
                            {{ $client['naam'] }}
                            <span class="text-zinc-500">· {{ implode(', ', $client['hosts']) ?: 'onbekende host' }}</span>
                            <span class="text-zinc-500">· gekoppeld {{ $client['gekoppeld_op']?->lokaal()->format('d-m-Y') }}</span>
                            <span class="text-zinc-500">· laatst gebruikt {{ $client['laatst_gebruikt']?->lokaal()->format('d-m-Y H:i') ?? 'nog niet' }}</span>
                        </div>
                        @if ($this->magMuteren())
                            <flux:button size="sm" variant="ghost" icon="x-mark"
                                wire:click="trekIn({{ $rij['gebruiker']->id }}, '{{ $client['client_id'] }}')"
                                wire:confirm="De koppeling van {{ $rij['gebruiker']->naam }} met {{ $client['naam'] }} intrekken?">
                                Intrekken
                            </flux:button>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <flux:text class="mt-2 text-sm">Er zijn geen gekoppelde assistenten.</flux:text>
        @endforelse
    </section>

    <section class="blueprint p-4">
        <flux:heading size="lg">Aanroepen</flux:heading>
        <flux:text class="mb-3 text-sm">
            De laatste {{ $logRegels }} opvragingen door gekoppelde assistenten. Ze worden {{ config('assistent.log_bewaren_dagen') }} dagen bewaard.
        </flux:text>

        <div class="mb-3 flex flex-wrap gap-3">
            <flux:select wire:model.live="filterGebruiker" size="sm" class="max-w-xs">
                <flux:select.option value="">Alle gebruikers</flux:select.option>
                @foreach ($logGebruikers as $g)
                    <flux:select.option value="{{ $g->id }}">{{ $g->naam }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.400ms="filterNaam" size="sm" class="max-w-xs" placeholder="Tool of bestand, bijv. zoek" />
        </div>

        @if ($log->isEmpty())
            <flux:text class="text-sm">Nog geen aanroepen.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-zinc-500">
                            <th class="py-1 pr-3">Tijdstip</th>
                            <th class="py-1 pr-3">Gebruiker</th>
                            <th class="py-1 pr-3">Assistent</th>
                            <th class="py-1 pr-3">Opgevraagd</th>
                            <th class="py-1 pr-3">Argumenten</th>
                            <th class="py-1 pr-3">Aantal</th>
                            <th class="py-1">Uitkomst</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($log as $regel)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="aanroep-{{ $regel->id }}">
                                <td class="py-1 pr-3 whitespace-nowrap">{{ $regel->aangeroepen_op->lokaal()->format('d-m-Y H:i:s') }}</td>
                                <td class="py-1 pr-3">{{ $regel->gebruiker?->naam }}</td>
                                <td class="py-1 pr-3">{{ $regel->client_naam }}</td>
                                <td class="py-1 pr-3"><code>{{ $regel->naam }}</code></td>
                                <td class="py-1 pr-3 break-all">{{ $regel->argumenten ? \Illuminate\Support\Str::limit(json_encode($regel->argumenten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 120) : '' }}</td>
                                <td class="py-1 pr-3">{{ $regel->aantal_resultaten }}</td>
                                <td class="py-1">
                                    <flux:badge size="sm" :color="match ($regel->uitkomst) { 'ok' => 'green', 'geweigerd' => 'amber', default => 'red' }">
                                        {{ $regel->uitkomst }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
