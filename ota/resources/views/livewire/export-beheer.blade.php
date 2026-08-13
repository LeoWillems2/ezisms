<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Export</flux:heading>
        <flux:subheading>Het ISMS wegschrijven als leesbare Markdown-mapstructuur.</flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.text>{{ session('melding') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if (session('fout'))
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>De export is niet gemaakt</flux:callout.heading>
            <flux:callout.text>{{ session('fout') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:callout icon="information-circle">
        <flux:callout.text>
            De export komt op een vast pad: <code>{{ $doelmap }}</code>. Elke export krijgt
            daar een eigen map met datumstempel; er wordt nooit iets overschreven.
            <strong>U kunt de inhoud hier niet inzien</strong> — daarvoor is toegang tot de
            server nodig. Personen staan er met initialen en rol in, niet met hun volledige
            naam, en bewijsstukken worden alleen bij naam genoemd en niet meegekopieerd.
        </flux:callout.text>
    </flux:callout>

    @if ($this->magMuteren())
        <div>
            @if ($bevestigt)
                <flux:callout icon="exclamation-triangle" variant="warning">
                    <flux:callout.heading>Weet u het zeker?</flux:callout.heading>
                    <flux:callout.text>
                        Hiermee zet u de <strong>volledige inhoud van het ISMS</strong> als
                        leesbare tekst buiten de applicatie: registers, risico's, incidenten,
                        audits en beoordelingen. Vanaf dat moment gelden de rechten uit dit
                        systeem niet meer voor die bestanden — wie bij de server kan, kan alles
                        lezen. Zorg dat u weet waarom u dit doet en wie erbij kan.
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button variant="primary" wire:click="exporteer"
                            wire:loading.attr="disabled" wire:target="exporteer">
                            Ja, exporteren
                        </flux:button>
                        <flux:button variant="ghost" wire:click="annuleer">Annuleren</flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="vraagBevestiging">
                    Export maken
                </flux:button>
            @endif

            <flux:text wire:loading wire:target="exporteer" class="mt-2 text-sm text-zinc-500">
                Bezig met exporteren… dit kan even duren.
            </flux:text>
        </div>
    @endif

    <div>
        <flux:heading size="lg" class="mb-2">Wat er in de uitgang staat</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Map</flux:table.column>
                <flux:table.column>Gemaakt</flux:table.column>
                <flux:table.column>Bestanden</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rijen as $rij)
                    <flux:table.row :key="$rij['naam']">
                        <flux:table.cell variant="strong"><code>{{ $rij['naam'] }}</code></flux:table.cell>
                        <flux:table.cell>
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($rij['gewijzigd'])->lokaal()->format('d-m-Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $rij['bestanden'] }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <flux:text>Er is nog geen export gemaakt.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:text class="mt-3 text-sm text-zinc-500">
            Opruimen doet u op de server; het systeem gooit een export niet zelf weg.
        </flux:text>
    </div>
</div>
