<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewijs-subnav')

    <div>
        <flux:heading size="xl">Meegegeven schermkopieën</flux:heading>
        <flux:subheading>
            Wat er als Word-document het systeem uit is gegaan: welk scherm, met welke filters, hoeveel
            regels en door wie. De kopieën zelf worden niet bewaard — dit is de lijst, niet het archief.
        </flux:subheading>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Moment</flux:table.column>
            <flux:table.column>Scherm</flux:table.column>
            <flux:table.column>Omvang</flux:table.column>
            <flux:table.column>Filters</flux:table.column>
            <flux:table.column>Door</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($kopieen as $kopie)
                <flux:table.row wire:key="schermkopie-{{ $kopie->id }}">
                    <flux:table.cell>{{ $kopie->gemaakt_op->lokaal()->format('d-m-Y H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $kopie->scherm }}
                        @if ($kopie->met_persoonsgegevens)
                            {{-- Niet wélke gegevens, alleen dát ze erin zaten (12h §8). --}}
                            <flux:badge size="sm" color="amber">persoonsgegevens</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($kopie->isVolledig())
                            Alle {{ $kopie->totaal_rijen }} regels
                        @else
                            {{ $kopie->aantal_rijen }} van {{ $kopie->totaal_rijen }} regels
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @forelse ($kopie->filters ?? [] as $label => $waarde)
                            <flux:text class="text-xs">{{ $label }}: {{ $waarde }}</flux:text>
                        @empty
                            <flux:text>—</flux:text>
                        @endforelse
                    </flux:table.cell>
                    <flux:table.cell>{{ $kopie->gebruiker?->naam ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>Er zijn nog geen schermkopieën meegegeven.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $kopieen->links() }}
</div>
