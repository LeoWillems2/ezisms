<?php

use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * De tweede stap van het inloggen (implementatie/01d §7b).
 *
 * De gebruiker is hier nog **niet** ingelogd: het wachtwoord is goed bevonden en
 * zijn id staat in de sessie. Pas als de code klopt volgt `Auth::login()`, en
 * daarmee ook het `Login`-event dat de geslaagde poging wegschrijft.
 */
new #[Layout('components.layouts.auth')] class extends Component {
    public string $code = '';

    /** Eén veld tegelijk: twee velden naast elkaar leidt tot het verkeerde invullen. */
    public bool $herstelcode = false;

    public function mount(): void
    {
        if (Session::get('tweefactor.gebruiker_id') === null) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function verifieren(TwoFactorAuthenticationProvider $provider): void
    {
        $this->validate(['code' => ['required', 'string']], attributes: ['code' => 'code']);

        $gebruiker = Gebruiker::find(Session::get('tweefactor.gebruiker_id'));

        if ($gebruiker === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->vereisNietTeVaakGeprobeerd($gebruiker);

        $code = trim(str_replace(' ', '', $this->code));

        $geslaagd = $this->herstelcode
            ? $this->herstelcodeVerbruiken($gebruiker, $code)
            : $provider->verify(decrypt($gebruiker->two_factor_secret), $code);

        if (! $geslaagd) {
            $this->legMislukkingVast($gebruiker);

            throw ValidationException::withMessages([
                'code' => $this->herstelcode
                    ? 'Deze herstelcode is niet (meer) geldig.'
                    : 'Die code klopt niet. Controleer of de tijd op uw telefoon goed staat.',
            ]);
        }

        // Auth::login vuurt het Login-event; de bestaande listener schrijft de
        // geslaagde poging weg en werkt laatst_ingelogd_op bij.
        Auth::login($gebruiker, (bool) Session::get('tweefactor.remember'));

        RateLimiter::clear($this->limietSleutel($gebruiker));
        Session::forget('tweefactor');
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    private function herstelcodeVerbruiken(Gebruiker $gebruiker, string $code): bool
    {
        $treffer = collect($gebruiker->recoveryCodes())
            ->first(fn (string $bewaard) => hash_equals($bewaard, $code));

        if ($treffer === null) {
            return false;
        }

        // Vervangt de verbruikte code door een nieuwe, zodat het aantal
        // beschikbare codes gelijk blijft.
        $gebruiker->replaceRecoveryCode($treffer);

        return true;
    }

    /**
     * **Bewust géén `Failed`-event.** Dat event leidt via
     * `RegistreerMislukteLoginpoging` naar de accountblokkade, en beslissing 4
     * uit 01d §1 is precies dat we die koppeling hier niet willen: brute-force
     * op zes cijfers is met vijf pogingen per kwartier kansloos, terwijl
     * blokkeren op typefouten en klokdrift alleen zelf-DoS oplevert.
     *
     * Dit is de meest voor de hand liggende bouwfout in dit hele plan — het
     * event is er en het ligt voor de hand het te hergebruiken.
     */
    private function legMislukkingVast(Gebruiker $gebruiker): void
    {
        Loginpoging::create([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'tijdstip' => now(),
            'succesvol' => false,
            'reden' => $this->herstelcode ? 'herstelcode' : 'totp',
            'ip_adres' => request()->ip(),
        ]);

        RateLimiter::hit(
            $this->limietSleutel($gebruiker),
            config('tweefactor.venster_minuten') * 60,
        );
    }

    /**
     * Bij te veel pogingen: sessie wissen en terug naar het inlogscherm. De
     * aanvaller moet dan opnieuw het wachtwoord aanleveren; de gebruiker
     * verliest hooguit vijftien seconden.
     */
    private function vereisNietTeVaakGeprobeerd(Gebruiker $gebruiker): void
    {
        if (! RateLimiter::tooManyAttempts($this->limietSleutel($gebruiker), config('tweefactor.max_pogingen'))) {
            return;
        }

        Session::forget('tweefactor');
        Session::flash('status', 'Te veel pogingen met de verificatiecode. Log opnieuw in.');

        $this->redirect(route('login'), navigate: true);

        throw ValidationException::withMessages(['code' => 'Te veel pogingen.']);
    }

    private function limietSleutel(Gebruiker $gebruiker): string
    {
        return '2fa|'.$gebruiker->id.'|'.request()->ip();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Verificatiecode"
        :description="$herstelcode
            ? 'Voer een van uw herstelcodes in.'
            : 'Voer de code van zes cijfers uit uw authenticator-app in.'"
    />

    <form wire:submit="verifieren" class="flex flex-col gap-6">
        <flux:input
            wire:model="code"
            :label="$herstelcode ? 'Herstelcode' : 'Code'"
            :inputmode="$herstelcode ? 'text' : 'numeric'"
            autocomplete="one-time-code"
            required
            autofocus
        />

        <flux:button variant="primary" type="submit" class="w-full">Verifiëren</flux:button>

        <flux:button wire:click="$toggle('herstelcode')" type="button" variant="ghost" size="sm">
            {{ $herstelcode ? 'Ik gebruik toch mijn app' : 'Ik heb mijn telefoon niet — herstelcode gebruiken' }}
        </flux:button>
    </form>
</div>
