<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Toetsbestanden</flux:heading>
            <flux:subheading>
                De HTML-toetsen die in dit ISMS uitgezet kunnen worden.
            </flux:subheading>
        </div>
        @if ($this->magMuteren())
            <flux:button variant="primary" icon="arrow-up-tray" wire:click="openFormulier">
                Toets plaatsen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.text>{{ session('melding') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if (session('fout'))
        <flux:callout icon="exclamation-triangle" variant="warning">
            <flux:callout.text>{{ session('fout') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Dit scherm is het enige van blok `installatiebeheer`, en de rol die het
         ziet heeft verder niets. Eén regel uitleg hoort er dan bij: zonder
         context is een lege lijst niet te onderscheiden van een defect. --}}
    <flux:callout icon="information-circle">
        <flux:callout.text>
            Een toets is een op zichzelf staande HTML-pagina. Wat u hier plaatst, wordt
            uitgeserveerd in een afgeschermde omgeving: de pagina kan niet bij de gegevens
            of de sessie van het ISMS. Het <em>uitzetten</em> van een toets — bepalen wie hem
            moet maken — gebeurt niet hier maar door wie het bewustzijnsprogramma beheert.
            @if ($magBouwhulp)
                Hoe u een toets aan het ISMS koppelt, staat in de
                <flux:link :href="route('toetsen.bouwhulp')" wire:navigate>bouwhulp</flux:link>.
            @endif
        </flux:callout.text>
    </flux:callout>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Titel</flux:table.column>
            <flux:table.column>Bestand</flux:table.column>
            <flux:table.column>Grootte</flux:table.column>
            <flux:table.column>Gewijzigd</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rijen as $rij)
                <flux:table.row :key="$rij['bestand']">
                    <flux:table.cell variant="strong">{{ $rij['titel'] }}</flux:table.cell>
                    <flux:table.cell><code>{{ $rij['bestand'] }}</code></flux:table.cell>
                    <flux:table.cell>{{ number_format($rij['grootte'] / 1024, 0, ',', '.') }} kB</flux:table.cell>
                    <flux:table.cell>
                        {{ \Illuminate\Support\Carbon::createFromTimestamp($rij['gewijzigd'])->lokaal()->format('d-m-Y H:i') }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($rij['voorbeeld'])
                            <flux:button size="sm" variant="ghost" icon="eye"
                                :href="$rij['voorbeeld']" target="_blank">Bekijken</flux:button>
                        @endif
                        @if ($this->magMuteren())
                            @if ($bevestigVerwijderen === $rij['bestand'])
                                <flux:button size="sm" variant="danger"
                                    wire:click="verwijder('{{ $rij['bestand'] }}')">
                                    Definitief verwijderen
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="verwijder('{{ $rij['bestand'] }}')">Verwijderen</flux:button>
                            @endif
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>Er staan nog geen toetsbestanden op deze installatie.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="uploaden" class="space-y-6">
            <div>
                <flux:heading size="lg">Toets plaatsen</flux:heading>
                <flux:subheading>
                    Eén HTML-bestand, maximaal
                    {{ \App\Livewire\ToetsbestandenBeheer::MAX_KB / 1024 }} MB.
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="bestand" label="Toetsbestand" accept=".html" required />

            @if ($bevestigOverschrijven !== '')
                <flux:callout icon="exclamation-triangle" variant="warning">
                    <flux:callout.heading>Er staat al een toets met deze naam</flux:callout.heading>
                    <flux:callout.text>
                        <code>{{ $bevestigOverschrijven }}</code> bestaat al. Vervangen betekent dat
                        deelnemers die de toets nu open hebben staan, verder gaan met de nieuwe
                        versie. Klik nogmaals op Plaatsen om te vervangen.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex items-center justify-end gap-2">
                {{-- De upload loopt asynchroon; een Plaatsen vóór die klaar is valt
                     in "toetsbestand is verplicht", en dan lijkt het alsof je twee
                     keer moet klikken. Zelfde oplossing als bij bewijzen en de
                     beleidsversie (p16): de knop staat uit tijdens de upload. --}}
                <flux:text wire:loading wire:target="bestand" class="mr-auto text-sm text-zinc-500">
                    Bezig met uploaden…
                </flux:text>
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit"
                    wire:loading.attr="disabled" wire:target="bestand, uploaden">Plaatsen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
