<div class="flex flex-col gap-6">
    @if ($stap === 'wachtwoord')
        <x-auth-header
            title="Wachtwoord instellen"
            description="Kies een wachtwoord om uw ISMS-account te activeren." />

        <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <div class="font-medium">{{ $gebruiker->naam }}</div>
            <div class="text-zinc-500 dark:text-zinc-400">{{ $gebruiker->email }}</div>
        </div>

        <form wire:submit="opslaan" class="flex flex-col gap-6">
            <flux:input
                wire:model="wachtwoord"
                type="password"
                label="Wachtwoord"
                description="Minimaal 12 tekens."
                required
                autofocus
                autocomplete="new-password" />

            <flux:input
                wire:model="wachtwoord_bevestiging"
                type="password"
                label="Wachtwoord bevestigen"
                required
                autocomplete="new-password" />

            <flux:button variant="primary" type="submit" class="w-full">Wachtwoord instellen</flux:button>
        </form>
    @elseif ($stap === 'tweefactor')
        <x-auth-header
            title="Tweede factor koppelen"
            description="Scan de code met een authenticator-app en voer de zes cijfers in die de app toont." />

        <div class="rounded-lg bg-white p-4 dark:bg-zinc-100" style="width: max-content">
            {!! $gebruiker->twoFactorQrCodeSvg() !!}
        </div>

        {{-- Op een desktop zonder camera is overtypen de enige route. --}}
        <flux:text class="text-xs">
            Werkt scannen niet? Voer deze sleutel met de hand in:
            <span class="font-mono">{{ decrypt($gebruiker->two_factor_secret) }}</span>
        </flux:text>

        <form wire:submit="bevestigen" class="flex flex-col gap-6">
            <flux:input
                wire:model="code"
                label="Code uit de app"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                autofocus />

            <flux:button variant="primary" type="submit" class="w-full">Bevestigen</flux:button>
        </form>

        <flux:button wire:click="laterInstellen" type="button" variant="ghost" size="sm">
            Ik heb mijn telefoon nu niet bij me
        </flux:button>
    @else
        <x-auth-header
            title="Klaar"
            description="Uw account is actief en de tweede factor staat aan." />

        <flux:callout variant="warning" icon="exclamation-triangle" heading="Bewaar deze herstelcodes">
            <flux:callout.text>
                Elke code werkt één keer, in plaats van een code uit de app. Bewaar ze ergens
                anders dan op uw telefoon — ze zijn hierna alleen nog op te vragen met uw
                wachtwoord.
            </flux:callout.text>
        </flux:callout>

        <ul class="font-mono text-sm">
            @foreach ($herstelcodes ?? [] as $herstelcode)
                <li>{{ $herstelcode }}</li>
            @endforeach
        </ul>

        <flux:button wire:click="naarLogin" variant="primary" class="w-full">Naar het inlogscherm</flux:button>
    @endif
</div>
