{{-- Gedeelde sub-navigatie voor blok 4 (Risico & SoA). Eén top-level menu-item
     in de zijbalk ("SoA & Risico's") verwijst naar de SoA; het risicoregister
     is van hieruit bereikbaar. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('soa.index')" :current="request()->routeIs('soa.index')" wire:navigate>
        Statement of Applicability
    </flux:navbar.item>
    <flux:navbar.item :href="route('soa.restrisico-trend')" :current="request()->routeIs('soa.restrisico-trend')" wire:navigate>
        Restrisico-trend
    </flux:navbar.item>
    <flux:navbar.item :href="route('risicos.index')" :current="request()->routeIs('risicos.index', 'risicos.detail')" wire:navigate>
        Risicoregister
    </flux:navbar.item>
    <flux:navbar.item :href="route('risicos.matrix')" :current="request()->routeIs('risicos.matrix')" wire:navigate>
        Tolerantiematrix
    </flux:navbar.item>
    <flux:navbar.item :href="route('risicos.criteria')" :current="request()->routeIs('risicos.criteria')" wire:navigate>
        Risicocriteria
    </flux:navbar.item>
</flux:navbar>
