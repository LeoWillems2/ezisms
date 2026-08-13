<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div>
        <flux:heading size="xl">Toetsen uitzetten</flux:heading>
        <flux:subheading>
            Zet een toets als taak uit bij geselecteerde gebruikers, met een deadline.
            Een geslaagde toets registreert de voltooiing machinaal.
        </flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <form wire:submit="uitzetten" class="flex flex-col gap-6">
        {{-- 1. Wat zet je uit? Module-toets (bestand + koppeling uit de module)
             of een losse toets zonder module. --}}
        <div>
            <flux:heading size="lg">1. Welke toets</flux:heading>
            <flux:radio.group wire:model.live="bron" class="mt-2">
                <flux:radio value="module" label="Module-toets — koppelt de uitslag aan een trainingsmodule" />
                <flux:radio value="los" label="Losse toets — eenmalig, niet aan een module gekoppeld" />
            </flux:radio.group>

            <div class="mt-3">
                @if ($bron === 'module')
                    @if ($modules->isEmpty())
                        <flux:callout variant="warning" icon="academic-cap"
                            heading="Geen modules met een toets.">
                            <flux:callout.text>
                                Geef eerst een module een toetsbestand (via Trainingen), of kies "Losse toets".
                            </flux:callout.text>
                        </flux:callout>
                    @else
                        <x-keuzelijst wire:model="moduleId" label="Module" required class="max-w-96"
                            :opties="$modules->mapWithKeys(fn ($m) => [$m->id => $m->titel.' — '.$m->toets_bestand])" />
                    @endif
                @else
                    <x-keuzelijst wire:model="losseToets" label="Toetsbestand" required class="max-w-96"
                        :opties="collect($losseToetsen)->mapWithKeys(fn ($titel, $bestand) => [$bestand => $titel.' ('.$bestand.')'])" />
                @endif
            </div>
        </div>

        <flux:input type="number" wire:model="weken" label="Af te ronden binnen (weken)"
            min="1" max="52" class="max-w-48" />

        {{-- 2. Gebruikers --}}
        <div>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">2. Gebruikers</flux:heading>
                <flux:button size="sm" variant="ghost" type="button" wire:click="selecteerAlleZichtbare">
                    Selecteer alle zichtbare
                </flux:button>
            </div>

            <div class="mt-2 flex flex-wrap items-end gap-4">
                <flux:select wire:model.live="filterEenheid" label="Organisatie-eenheid" class="max-w-56">
                    <flux:select.option value="">Alle eenheden</flux:select.option>
                    @foreach ($eenheden as $eenheid)
                        <flux:select.option value="{{ $eenheid->id }}">{{ $eenheid->naam }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterRol" label="Rol" class="max-w-56">
                    <flux:select.option value="">Alle rollen</flux:select.option>
                    @foreach ($rollen as $rol)
                        <flux:select.option value="{{ $rol->id }}">{{ $rol->naam }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="mt-3 flex max-h-72 flex-col gap-2 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                @forelse ($gebruikers as $gebruiker)
                    <flux:checkbox wire:model="geselecteerdeGebruikers" value="{{ $gebruiker->id }}"
                        label="{{ $gebruiker->naam }}" />
                @empty
                    <flux:text variant="subtle">Geen gebruikers voor deze filter.</flux:text>
                @endforelse
            </div>
            <flux:error name="geselecteerdeGebruikers" />
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit" icon="paper-airplane">Uitzetten</flux:button>
        </div>
    </form>
</div>
