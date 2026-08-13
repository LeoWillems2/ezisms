{{-- Gedeelde sub-navigatie voor blok 7 (Taken & Workflow). Sjablonen zijn
     beheer en dus alleen zichtbaar met muteerrecht. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('taken.index')" :current="request()->routeIs('taken.index')" wire:navigate>
        Taken
    </flux:navbar.item>
    @can('heeft-niveau', ['taken-workflow-engine', 'muteren'])
        <flux:navbar.item :href="route('taaksjablonen.index')" :current="request()->routeIs('taaksjablonen.index')" wire:navigate>
            Sjablonen
        </flux:navbar.item>
    @endcan
</flux:navbar>
