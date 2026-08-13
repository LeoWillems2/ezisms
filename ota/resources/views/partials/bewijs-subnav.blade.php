{{-- Gedeelde sub-navigatie voor blok 6 (Bewijsrepository & Audit Trail). --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('bewijsstukken.index')" :current="request()->routeIs('bewijsstukken.*')" wire:navigate>
        Bewijsstukken
    </flux:navbar.item>
    <flux:navbar.item :href="route('audit-log.index')" :current="request()->routeIs('audit-log.index')" wire:navigate>
        Audit trail
    </flux:navbar.item>
    <flux:navbar.item :href="route('schermkopieen.index')" :current="request()->routeIs('schermkopieen.index')" wire:navigate>
        Schermkopieën
    </flux:navbar.item>
</flux:navbar>
