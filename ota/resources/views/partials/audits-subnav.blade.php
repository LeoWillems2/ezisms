{{-- Gedeelde sub-navigatie voor blok 11 (Auditmanagement). Eén menu-item
     ("Audits") in de zijbalk; het auditprogramma en de dekkingsmatrix (plan 11b)
     zijn van hieruit bereikbaar. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('audits.index')" :current="request()->routeIs('audits.index', 'audits.ronde')" wire:navigate>
        Overzicht
    </flux:navbar.item>
    <flux:navbar.item :href="route('audits.programma')" :current="request()->routeIs('audits.programma')" wire:navigate>
        Auditprogramma
    </flux:navbar.item>
    <flux:navbar.item :href="route('audits.dekking')" :current="request()->routeIs('audits.dekking')" wire:navigate>
        Dekkingsmatrix
    </flux:navbar.item>
</flux:navbar>
