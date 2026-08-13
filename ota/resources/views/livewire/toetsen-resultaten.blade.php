<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div>
        <flux:heading size="xl">Toetsresultaten</flux:heading>
        <flux:subheading>Deelname en uitslagen per uitgezette toets ({{ $norm->bijlage }} 6.3).</flux:subheading>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterToets" label="Toets" class="max-w-72">
            <flux:select.option value="">Alle toetsen</flux:select.option>
            @foreach ($toetsen as $bestand => $titel)
                <flux:select.option value="{{ $bestand }}">{{ $titel }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterStatus" label="Status" class="max-w-56">
            <flux:select.option value="openstaand">Openstaand</flux:select.option>
            <flux:select.option value="uitgezet">Uitgezet</flux:select.option>
            <flux:select.option value="gezakt">Gezakt</flux:select.option>
            <flux:select.option value="geslaagd">Geslaagd</flux:select.option>
            <flux:select.option value="">Alle</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Toets</flux:table.column>
            <flux:table.column>Deelnemer</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Score</flux:table.column>
            <flux:table.column align="end">Pogingen</flux:table.column>
            <flux:table.column>Deadline</flux:table.column>
            <flux:table.column>Afgerond op</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($opdrachten as $opdracht)
                <flux:table.row wire:key="opdracht-{{ $opdracht->id }}">
                    <flux:table.cell variant="strong">{{ $opdracht->toets_titel }}</flux:table.cell>
                    <flux:table.cell>{{ $opdracht->taak?->eigenaar?->naam ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($opdracht->status === 'geslaagd')
                            <flux:badge size="sm" color="green">geslaagd</flux:badge>
                        @elseif ($opdracht->status === 'gezakt')
                            <flux:badge size="sm" color="red">gezakt</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">uitgezet</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        {{ $opdracht->laatste_score !== null ? $opdracht->laatste_score.' / '.$opdracht->laatste_totaal : '—' }}
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $opdracht->pogingen }}</flux:table.cell>
                    <flux:table.cell>{{ $opdracht->taak?->deadline?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $opdracht->geslaagd_op?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7"><flux:text>Geen toetsen gevonden.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
