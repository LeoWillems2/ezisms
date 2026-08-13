{{-- Gedeelde sub-navigatie voor blok 3 (Asset & Classificatie). Eén top-level
     menu-item in de zijbalk ("Assets") verwijst naar de assetlijst; systemen
     zijn van hieruit bereikbaar. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('assets.index')" :current="request()->routeIs('assets.*')" wire:navigate>
        Assets
    </flux:navbar.item>
    <flux:navbar.item :href="route('systemen.index')" :current="request()->routeIs('systemen.index')" wire:navigate>
        Systemen
    </flux:navbar.item>
</flux:navbar>
