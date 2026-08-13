{{-- Gedeelde sub-navigatie voor blok 2 (Context & Scope). Eén top-level
     menu-item in de zijbalk verwijst naar 'scope.show'; van daaruit zijn de
     losse registers via deze balk bereikbaar. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('scope.show')" :current="request()->routeIs('scope.show')" wire:navigate>
        Scope-verklaring
    </flux:navbar.item>
    <flux:navbar.item :href="route('organisatie-eenheden.index')" :current="request()->routeIs('organisatie-eenheden.index')" wire:navigate>
        Organisatie-eenheden
    </flux:navbar.item>
    <flux:navbar.item :href="route('issues.index')" :current="request()->routeIs('issues.index')" wire:navigate>
        Issues
    </flux:navbar.item>
    <flux:navbar.item :href="route('belanghebbenden.index')" :current="request()->routeIs('belanghebbenden.index')" wire:navigate>
        Belanghebbenden
    </flux:navbar.item>
</flux:navbar>
