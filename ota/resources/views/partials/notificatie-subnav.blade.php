{{-- Gedeelde sub-navigatie voor blok 14 (Notificatie & Integratielaag). Beide
     schermen zijn op 'lezen' (ook de Auditor); muteren checkt het component. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('notificaties.index')" :current="request()->routeIs('notificaties.index')" wire:navigate>
        Notificatieregels
    </flux:navbar.item>
    <flux:navbar.item :href="route('integraties.index')" :current="request()->routeIs('integraties.index')" wire:navigate>
        Integraties
    </flux:navbar.item>
</flux:navbar>
