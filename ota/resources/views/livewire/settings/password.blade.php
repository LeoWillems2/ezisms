<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Altijd beschikbaar voor de ingelogde gebruiker zelf, ongeacht rol
 * (implementatie/00b-navigatie-en-lay-out.md §4).
 */
new #[Layout('components.layouts.app')] class extends Component {
    public string $huidig_wachtwoord = '';
    public string $wachtwoord = '';
    public string $wachtwoord_bevestiging = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'huidig_wachtwoord' => ['required', 'string', 'current_password'],
                'wachtwoord' => ['required', 'string', Password::defaults(), 'confirmed:wachtwoord_bevestiging'],
            ], attributes: [
                'huidig_wachtwoord' => 'huidig wachtwoord',
                'wachtwoord' => 'wachtwoord',
            ]);
        } catch (ValidationException $e) {
            $this->reset('huidig_wachtwoord', 'wachtwoord', 'wachtwoord_bevestiging');

            throw $e;
        }

        // De 'hashed'-cast op het model verzorgt het hashen.
        Auth::user()->update(['wachtwoord' => $validated['wachtwoord']]);

        $this->reset('huidig_wachtwoord', 'wachtwoord', 'wachtwoord_bevestiging');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Wachtwoord wijzigen" subheading="Kies een wachtwoord van minimaal 12 tekens">
        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="huidig_wachtwoord"
                id="huidig_wachtwoord"
                label="Huidig wachtwoord"
                type="password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="wachtwoord"
                id="nieuw_wachtwoord"
                label="Nieuw wachtwoord"
                type="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="wachtwoord_bevestiging"
                id="wachtwoord_bevestiging"
                label="Nieuw wachtwoord bevestigen"
                type="password"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">Opslaan</flux:button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    Opgeslagen.
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
