<?php

use App\Support\Oidc\Configuratie;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Eigen profiel. Naam en e-mailadres zijn hier bewust alleen-lezen: die worden
 * door de CISO beheerd via /gebruikers, niet door de gebruiker zelf
 * (implementatie/00b-navigatie-en-lay-out.md §4). Wachtwoord wijzigen staat op
 * een eigen tabblad en is voor iedere rol beschikbaar.
 */
new #[Layout('components.layouts.app')] class extends Component {
    public string $naam = '';
    public string $email = '';
    public string $rollen = '';
    public string $status = '';

    public ?string $aangemeldVia = null;

    public function mount(): void
    {
        $gebruiker = Auth::user();

        $this->naam = $gebruiker->naam;
        $this->email = $gebruiker->email;
        $this->rollen = $gebruiker->rollen->pluck('naam')->implode(', ');
        $this->status = ucfirst($gebruiker->status);

        // Een extern account: waarmee het is gekoppeld (01j §8).
        if ($gebruiker->isExtern()) {
            $identiteit = $gebruiker->externeIdentiteit;
            $this->aangemeldVia = Configuratie::weergavenaam()
                .($identiteit?->idp_gebruikersnaam ? ' als '.$identiteit->idp_gebruikersnaam : ($identiteit ? '' : ' (nog niet gekoppeld)'));
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Profiel" subheading="Uw accountgegevens binnen het ISMS">
        <div class="my-6 w-full space-y-6">
            <flux:input :value="$naam" label="Naam" type="text" readonly />
            <flux:input :value="$email" label="E-mailadres" type="email" readonly />
            <flux:input :value="$rollen" label="Rol(len)" type="text" readonly />
            <flux:input :value="$status" label="Status" type="text" readonly />
            @if ($aangemeldVia)
                <flux:input :value="$aangemeldVia" label="Aangemeld via" type="text" readonly />
            @endif

            <flux:text>
                Kloppen uw naam of e-mailadres niet? Neem contact op met de CISO —
                accountgegevens worden centraal beheerd.
            </flux:text>
        </div>
    </x-settings.layout>
</section>
