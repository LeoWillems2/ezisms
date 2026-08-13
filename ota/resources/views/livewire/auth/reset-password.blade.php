<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $wachtwoord = '';

    public string $wachtwoord_bevestiging = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'wachtwoord' => ['required', 'string', 'confirmed:wachtwoord_bevestiging', Rules\Password::defaults()],
        ], attributes: ['wachtwoord' => 'wachtwoord']);

        // De broker verwacht de sleutels 'password'/'password_confirmation';
        // het domeinmodel gebruikt 'wachtwoord' (conventies §3).
        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->wachtwoord,
                'password_confirmation' => $this->wachtwoord_bevestiging,
                'token' => $this->token,
            ],
            function ($gebruiker) {
                // Een geldig token van vóór een deactivering/blokkade mag geen
                // toegang teruggeven.
                if (! $gebruiker->magInloggen()) {
                    return;
                }

                $gebruiker->forceFill([
                    'wachtwoord' => $this->wachtwoord, // de 'hashed'-cast hasht dit
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($gebruiker));
            }
        );

        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', 'Uw wachtwoord is gewijzigd. U kunt nu inloggen.');

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Nieuw wachtwoord" description="Kies hieronder uw nieuwe wachtwoord" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="resetPassword" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="E-mailadres" type="email" name="email" required autocomplete="email" />
        </div>

        <div class="grid gap-2">
            <flux:input
                wire:model="wachtwoord"
                id="wachtwoord"
                label="Nieuw wachtwoord"
                description="Minimaal 12 tekens."
                type="password"
                required
                autocomplete="new-password"
            />
        </div>

        <div class="grid gap-2">
            <flux:input
                wire:model="wachtwoord_bevestiging"
                id="wachtwoord_bevestiging"
                label="Wachtwoord bevestigen"
                type="password"
                required
                autocomplete="new-password"
            />
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                Wachtwoord wijzigen
            </flux:button>
        </div>
    </form>
</div>
