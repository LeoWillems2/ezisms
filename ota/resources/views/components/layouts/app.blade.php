<x-layouts.app.sidebar>
    <flux:main>
        {{-- Respijtperiode voor tweefactor (implementatie/01d §9). Op elke
             pagina en niet alleen op het dashboard: een melding die één scherm
             diep zit, wordt door de helft van de mensen nooit gezien. --}}
        @if (auth()->check() && config('tweefactor.afdwingen') && ! auth()->user()->tweefactorActief() && auth()->user()->tweefactor_deadline !== null)
            <flux:callout variant="warning" icon="shield-exclamation" class="mb-6">
                <flux:callout.heading>Tweefactorauthenticatie is nog niet ingesteld</flux:callout.heading>
                <flux:callout.text>
                    @if (auth()->user()->tweefactorRespijtVerlopen())
                        De termijn is verstreken. Stel de tweede factor in om verder te kunnen werken.
                    @else
                        U heeft nog tot {{ auth()->user()->tweefactor_deadline->format('d-m-Y') }}
                        ({{ (int) ceil(now()->startOfDay()->diffInDays(auth()->user()->tweefactor_deadline, absolute: false)) }} dagen).
                    @endif
                    <a href="{{ route('settings.tweefactor') }}" class="underline" wire:navigate>Nu instellen</a>.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
