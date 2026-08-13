<div class="flex flex-col gap-6">
    @if ($bevestigd)
        <x-auth-header
            title="Adres bevestigd"
            description="Uw account gebruikt vanaf nu dit e-mailadres." />

        <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <div class="font-medium">{{ $gebruiker->naam }}</div>
            <div class="text-zinc-500 dark:text-zinc-400">{{ $nieuwEmail }}</div>
        </div>

        {{-- Hier staat expliciet wat er níet is veranderd: de vraag "moet ik nu
             een nieuw wachtwoord?" komt anders bij de CISO terecht. --}}
        <flux:text class="text-sm">
            Log voortaan in met dit adres. Uw wachtwoord en uw tweede factor zijn
            niet veranderd.
        </flux:text>

        <flux:button :href="route('login')" variant="primary" class="w-full" wire:navigate>
            Naar inloggen
        </flux:button>
    @elseif ($mislukt)
        <x-auth-header
            title="Bevestigen lukt niet meer"
            description="Er is iets veranderd sinds deze link is verstuurd." />

        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ $mislukt }}" />
    @else
        <x-auth-header
            title="Bevestig uw nieuwe e-mailadres"
            description="Er verandert pas iets als u hieronder bevestigt." />

        <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <div class="font-medium">{{ $gebruiker->naam }}</div>
            <div class="text-zinc-500 line-through dark:text-zinc-400">{{ $gebruiker->email }}</div>
            <div class="text-zinc-900 dark:text-zinc-100">{{ $nieuwEmail }}</div>
        </div>

        <flux:text class="text-sm">
            Na bevestiging logt u in met het nieuwe adres. Uw wachtwoord, uw tweede
            factor en uw lopende sessies veranderen niet.
        </flux:text>

        {{-- Een knop en geen automatische afhandeling bij het openen: de
             linkscanner van een mailfilter zou de wijziging anders zelf
             bevestigen (01h §0). --}}
        <flux:button wire:click="bevestigen" variant="primary" class="w-full">
            Nieuw adres bevestigen
        </flux:button>

        <flux:text class="text-xs">
            Heeft u hier niet om gevraagd? Sluit deze pagina en neem contact op met
            de CISO.
        </flux:text>
    @endif
</div>
