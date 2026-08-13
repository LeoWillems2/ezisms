<?php

use App\Models\Gebruiker;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Functionele eis uit deelproducten/01-identity-access.md §7: een gebruiker
 * moet een vergeten wachtwoord zelf kunnen herstellen zonder tussenkomst van
 * de CISO.
 */
new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Alleen actieve accounts: een gedeactiveerd of geblokkeerd account mag
        // niet via een reset weer toegang krijgen, en een uitgenodigd account
        // hoort de uitnodigingslink te gebruiken.
        $gebruiker = Gebruiker::where('email', $this->email)->first();

        if ($gebruiker?->magInloggen()) {
            Password::sendResetLink($this->only('email'));
        }

        // Altijd dezelfde melding, ongeacht of het account bestaat of actief is
        // — anders is dit formulier een account-enumeratie-lek.
        session()->flash('status', 'Als er een actief account bij dit e-mailadres hoort, is er een herstellink verstuurd.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Wachtwoord vergeten" description="Voer uw e-mailadres in om een herstellink te ontvangen" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <flux:input wire:model="email" label="E-mailadres" type="email" name="email" required autofocus />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">Herstellink versturen</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Terug naar
        <x-text-link href="{{ route('login') }}">inloggen</x-text-link>
    </div>
</div>
