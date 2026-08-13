<?php

use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Statuscontrole vindt bewust ná de credential-check plaats: zo verraadt
     * de melding niets over accounts waarvan de aanvrager het wachtwoord niet
     * kent (implementatie/00b-navigatie-en-lay-out.md §3).
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $gebruiker = Gebruiker::where('email', $this->email)->first();

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            // Zelfde event dat de guard normaal zelf afvuurt; de listener
            // registreert de poging en blokkeert zo nodig het account.
            event(new Failed('web', $gebruiker, ['email' => $this->email, 'password' => $this->password]));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $gebruiker->magInloggen()) {
            Loginpoging::create([
                'gebruiker_id' => $gebruiker->id,
                'email_ingevoerd' => $this->email,
                'tijdstip' => now(),
                'succesvol' => false,
                'reden' => 'status',
                'ip_adres' => request()->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => match ($gebruiker->status) {
                    // De reden van een handmatige blokkade komt hier bewust niet
                    // te staan: die is geschreven voor de CISO, over iemand die
                    // op dat moment verdacht wordt — en dit is de ene plek waar
                    // niet vaststaat dat de rechthebbende meeleest (01f §5).
                    'geblokkeerd' => $gebruiker->blokkadeIsHandmatig()
                        ? 'Dit account is geblokkeerd. Neem contact op met de CISO.'
                        : 'Dit account is geblokkeerd wegens te veel mislukte inlogpogingen. Neem contact op met de CISO.',
                    'gedeactiveerd' => 'Dit account is niet meer actief.',
                    default => 'Stel eerst een wachtwoord in via de uitnodigingslink die u heeft ontvangen.',
                },
            ]);
        }

        // Tweede factor: nog niet inloggen, alleen onthouden wie er aan de beurt
        // is (implementatie/01d §7a). Bewust géén `Session::regenerate()` hier —
        // die hoort bij het moment van authenticatie, en dat is het slagen van
        // de challenge; er is nu ook nog geen sessie om te beschermen.
        if ($gebruiker->tweefactorActief()) {
            RateLimiter::clear($this->throttleKey());

            Session::put('tweefactor.gebruiker_id', $gebruiker->id);
            Session::put('tweefactor.remember', $this->remember);

            $this->redirect(route('tweefactor.challenge'), navigate: true);

            return;
        }

        // Auth::login vuurt het Login-event af; die listener schrijft de
        // geslaagde poging weg en werkt laatst_ingelogd_op bij.
        Auth::login($gebruiker, $this->remember);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Inloggen" description="Voer uw e-mailadres en wachtwoord in." />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input wire:model="email" label="E-mailadres" type="email" name="email" required autofocus autocomplete="email" />

        <div class="relative">
            <flux:input
                wire:model="password"
                label="Wachtwoord"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />

            @if (Route::has('password.request'))
                <x-text-link class="absolute right-0 top-0" href="{{ route('password.request') }}">
                    Wachtwoord vergeten?
                </x-text-link>
            @endif
        </div>

        <flux:checkbox wire:model="remember" label="Aangemeld blijven" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">Inloggen</flux:button>
        </div>
    </form>
</div>
