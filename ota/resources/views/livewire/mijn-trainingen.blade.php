<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div>
        <flux:heading size="xl">Mijn trainingen</flux:heading>
        <flux:subheading>De trainingen die voor jouw doelgroep(en) verplicht zijn, en de toetsen die los aan jou zijn toegewezen.</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif
    @if (session('fout'))
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Training</flux:table.column>
            <flux:table.column>Geldigheid</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actie</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rijen as $rij)
                @php($module = $rij['module'])
                <flux:table.row wire:key="mijn-module-{{ $module->id }}">
                    <flux:table.cell variant="strong">{{ $module->titel }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $module->geldigheidsduur_maanden ? $module->geldigheidsduur_maanden.' mnd' : 'eenmalig' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($rij['status'] === 'voltooid')
                            <flux:badge size="sm" color="green">voltooid</flux:badge>
                        @elseif ($rij['status'] === 'verlopen')
                            <flux:badge size="sm" color="red">verlopen</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">te doen</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($module->heeftToets())
                            @if ($rij['toets'])
                                <flux:button size="sm" variant="primary" icon="academic-cap" icon:variant="micro"
                                    href="{{ $rij['toets']->deelnemerUrl() }}" target="_blank">Start toets</flux:button>
                            @else
                                <flux:text variant="subtle">Toets nog niet uitgezet</flux:text>
                            @endif
                        @elseif ($rij['status'] === 'voltooid')
                            <flux:text variant="subtle">—</flux:text>
                        @else
                            <flux:button size="sm" variant="primary"
                                wire:click="meldVoltooid({{ $module->id }})">Als voltooid melden</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    {{-- Bewust over de doelgroep en niet over "trainingen" in het
                         algemeen: eronder kan nog een losse toets staan. --}}
                    <flux:table.cell colspan="4">
                        <flux:text>Er zijn geen trainingen verplicht voor jouw doelgroep(en).</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Losse toetsen: uitgezet zonder module, dus zonder doelgroep en zonder
         geldigheidsduur. Een eigen tabel in plaats van extra rijen hierboven —
         die kolommen (geldigheid, "als voltooid melden") betekenen hier niets,
         en de kop zou dan iets beloven wat de rij niet waarmaakt. --}}
    @if ($losseToetsen->isNotEmpty())
        <div>
            <flux:heading size="lg">Losse toetsen</flux:heading>
            <flux:subheading>Aan jou toegewezen buiten een trainingsmodule om.</flux:subheading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Toets</flux:table.column>
                <flux:table.column>Deadline</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actie</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($losseToetsen as $toets)
                    <flux:table.row wire:key="losse-toets-{{ $toets->id }}">
                        <flux:table.cell variant="strong">{{ $toets->toets_titel }}</flux:table.cell>
                        <flux:table.cell>{{ $toets->taak?->deadline?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($toets->status === 'geslaagd')
                                <flux:badge size="sm" color="green">geslaagd</flux:badge>
                            @elseif ($toets->status === 'gezakt')
                                <flux:badge size="sm" color="red">gezakt</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">te doen</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            {{-- Ook na 'gezakt' opnieuw te starten: de toets bepaalt
                                 zelf of een nieuwe poging mag, niet dit scherm. --}}
                            @if ($toets->status === 'geslaagd')
                                <flux:text variant="subtle">—</flux:text>
                            @else
                                <flux:button size="sm" variant="primary" icon="academic-cap" icon:variant="micro"
                                    href="{{ $toets->deelnemerUrl() }}" target="_blank">Start toets</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
