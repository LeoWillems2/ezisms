<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Tweefactorauthenticatie instellen (implementatie/01d §6).
 *
 * Altijd beschikbaar voor de ingelogde gebruiker zelf, ongeacht rol — net als
 * profiel en wachtwoord (00b §4). Drie toestanden: nog niets, wel gegenereerd
 * maar niet bevestigd, en actief.
 *
 * **Er is bewust geen uitschakelknop.** 2FA is verplicht (§1 beslissing 1), dus
 * uitzetten zou betekenen dat de middleware de gebruiker meteen terugstuurt naar
 * dit scherm — een knop die een keuze suggereert die er niet is. Wat mensen
 * werkelijk willen bij een nieuwe telefoon is "ander apparaat koppelen", en dat
 * staat er wel.
 */
new #[Layout('components.layouts.app')] class extends Component {
    public string $wachtwoord = '';

    public string $code = '';

    /** Getoond ná het bevestigen of op verzoek — nooit ongevraagd. */
    public ?array $herstelcodes = null;

    public bool $toontQrCode = false;

    public function mount(): void
    {
        // Wie hier binnenkomt met een half afgeronde koppeling ziet meteen het
        // bevestigingsscherm; anders is het secret onvindbaar geworden.
        $this->toontQrCode = $this->gebruiker()->two_factor_secret !== null
            && ! $this->gebruiker()->tweefactorActief();
    }

    public function gebruiker(): App\Models\Gebruiker
    {
        return Auth::user();
    }

    /**
     * Het wachtwoord wordt hier zelf gecontroleerd en niet via Fortify's
     * `confirmPassword`: die loopt door de guard en gaat uit van een
     * `password`-kolom, terwijl dit domeinmodel `wachtwoord` heet (§2).
     */
    private function vereisWachtwoord(): void
    {
        $this->validate(['wachtwoord' => ['required', 'string']], attributes: ['wachtwoord' => 'wachtwoord']);

        if (! Hash::check($this->wachtwoord, $this->gebruiker()->wachtwoord)) {
            throw ValidationException::withMessages([
                'wachtwoord' => 'Het opgegeven wachtwoord klopt niet.',
            ]);
        }

        $this->reset('wachtwoord');
    }

    public function inschakelen(EnableTwoFactorAuthentication $inschakelen): void
    {
        $this->vereisWachtwoord();

        // `force: true` zodat "ander apparaat koppelen" een nieuw secret
        // oplevert. Het oude blijft geldig tot de bevestiging binnen is: een
        // mislukte overzetting mag niemand buitensluiten.
        $inschakelen($this->gebruiker(), force: true);

        $this->toontQrCode = true;
        $this->herstelcodes = null;
    }

    public function bevestigen(ConfirmTwoFactorAuthentication $bevestigen): void
    {
        $this->validate(['code' => ['required', 'string']], attributes: ['code' => 'code']);

        try {
            $bevestigen($this->gebruiker(), $this->code);
        } catch (ValidationException) {
            // Fortify meldt in een eigen errorBag; hier hoort de melding bij het
            // veld waar de gebruiker naar kijkt.
            throw ValidationException::withMessages([
                'code' => 'Die code klopt niet. Controleer of de tijd op uw telefoon goed staat.',
            ]);
        }

        $this->reset('code');
        $this->toontQrCode = false;

        // Eén keer tonen, meteen na het bevestigen: dit is het moment waarop
        // iemand ze opschrijft.
        $this->herstelcodes = $this->gebruiker()->fresh()->recoveryCodes();
    }

    public function herstelcodesTonen(): void
    {
        $this->vereisWachtwoord();

        $this->herstelcodes = $this->gebruiker()->recoveryCodes();
    }

    public function herstelcodesVernieuwen(GenerateNewRecoveryCodes $genereer): void
    {
        $this->vereisWachtwoord();

        $genereer($this->gebruiker());

        $this->herstelcodes = $this->gebruiker()->fresh()->recoveryCodes();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        heading="Tweefactorauthenticatie"
        subheading="Een code uit uw authenticator-app, naast uw wachtwoord"
    >
        @php($gebruiker = $this->gebruiker())

        <div class="mt-6 space-y-6">
            @if ($gebruiker->tweefactorActief())
                <flux:badge color="green">Actief</flux:badge>

                <flux:text>
                    Bij het inloggen vraagt het systeem na uw wachtwoord om een code van zes cijfers.
                </flux:text>
            @elseif ($toontQrCode)
                <flux:badge color="amber">Nog te bevestigen</flux:badge>

                <flux:text>
                    Scan de code hieronder met uw authenticator-app en voer daarna de zes cijfers in
                    die de app toont. Pas dan is de tweede factor actief.
                </flux:text>

                <div class="rounded-lg bg-white p-4 dark:bg-zinc-100" style="width: max-content">
                    {!! $gebruiker->twoFactorQrCodeSvg() !!}
                </div>

                {{-- Geen luxe: op een desktop zonder camera is overtypen de enige route. --}}
                <flux:text class="text-xs">
                    Werkt scannen niet? Voer deze sleutel met de hand in:
                    <span class="font-mono">{{ decrypt($gebruiker->two_factor_secret) }}</span>
                </flux:text>

                <form wire:submit="bevestigen" class="space-y-4">
                    <flux:input
                        wire:model="code"
                        label="Code uit de app"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                    />

                    <flux:button variant="primary" type="submit">Bevestigen</flux:button>
                </form>
            @else
                <flux:badge color="zinc">Nog niet ingesteld</flux:badge>

                <flux:text>
                    Voer uw wachtwoord in om te beginnen. U krijgt daarna een QR-code voor uw
                    authenticator-app.
                </flux:text>
            @endif

            @if ($herstelcodes !== null)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="Bewaar deze herstelcodes">
                    <flux:callout.text>
                        Elke code werkt één keer, in plaats van een code uit de app. Ze zijn hierna
                        alleen nog op te vragen met uw wachtwoord — en als u ze kwijt bent, kan
                        alleen de CISO uw tweede factor opnieuw instellen.
                    </flux:callout.text>
                </flux:callout>

                <ul class="font-mono text-sm">
                    @foreach ($herstelcodes as $herstelcode)
                        <li>{{ $herstelcode }}</li>
                    @endforeach
                </ul>
            @endif

            @if (! $toontQrCode)
                <form wire:submit="inschakelen" class="space-y-4">
                    <flux:input
                        wire:model="wachtwoord"
                        label="Uw wachtwoord"
                        type="password"
                        autocomplete="current-password"
                        required
                    />

                    <div class="flex flex-wrap gap-3">
                        <flux:button variant="primary" type="submit">
                            {{ $gebruiker->tweefactorActief() ? 'Ander apparaat koppelen' : 'Instellen' }}
                        </flux:button>

                        @if ($gebruiker->tweefactorActief())
                            <flux:button wire:click="herstelcodesTonen" type="button" variant="ghost">
                                Herstelcodes tonen
                            </flux:button>

                            <flux:button wire:click="herstelcodesVernieuwen" type="button" variant="ghost">
                                Nieuwe herstelcodes
                            </flux:button>
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </x-settings.layout>
</section>
