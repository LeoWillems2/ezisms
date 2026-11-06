{{-- Gedeelde sub-navigatie voor blok 14 (Notificatie & Integratielaag). Beide
     schermen zijn op 'lezen' (ook de Auditor); muteren checkt het component. --}}
<flux:navbar class="mb-2 max-w-full overflow-x-auto">
    <flux:navbar.item :href="route('notificaties.index')" :current="request()->routeIs('notificaties.index')" wire:navigate>
        Notificatieregels
    </flux:navbar.item>
    <flux:navbar.item :href="route('integraties.index')" :current="request()->routeIs('integraties.index')" wire:navigate>
        Integraties
    </flux:navbar.item>
    @if (\Illuminate\Support\Facades\Route::has('assistenttoegang.index'))
        <flux:navbar.item :href="route('assistenttoegang.index')" :current="request()->routeIs('assistenttoegang.index')" wire:navigate>
            Assistenttoegang
        </flux:navbar.item>
    @endif
</flux:navbar>
