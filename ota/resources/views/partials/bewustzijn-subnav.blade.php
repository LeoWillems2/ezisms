{{-- Gedeelde sub-navigatie voor blok 10 (Bewustzijn, Training & Toetsen).
     "Mijn trainingen" is voor iedereen met uitvoeren (ook de CISO); het
     programmabeheer, doelgroepen en het uitzetten zijn muteer-schermen; het
     trainingen- en resultatenoverzicht mag ook de Auditor lezen (magAllesZien
     sluit de Medewerker uit, want uitvoeren impliceert lezen). --}}
<flux:navbar class="mb-2">
    @can('heeft-niveau', ['bewustzijn-training', 'uitvoeren'])
        <flux:navbar.item :href="route('mijn-trainingen.index')" :current="request()->routeIs('mijn-trainingen.index')" wire:navigate>
            Mijn trainingen
        </flux:navbar.item>
    @endcan

    @if (\App\Support\Recordscope::magAllesZien('bewustzijn-training'))
        <flux:navbar.item :href="route('trainingen.index')" :current="request()->routeIs('trainingen.index')" wire:navigate>
            Trainingen
        </flux:navbar.item>
    @endif

    @can('heeft-niveau', ['bewustzijn-training', 'muteren'])
        <flux:navbar.item :href="route('doelgroepen.index')" :current="request()->routeIs('doelgroepen.index')" wire:navigate>
            Doelgroepen
        </flux:navbar.item>
        <flux:navbar.item :href="route('toetsen.uitzetten')" :current="request()->routeIs('toetsen.uitzetten')" wire:navigate>
            Toetsen uitzetten
        </flux:navbar.item>
        <flux:navbar.item :href="route('toetsen.bouwhulp')" :current="request()->routeIs('toetsen.bouwhulp')" wire:navigate>
            Bouwhulp
        </flux:navbar.item>
    @endcan

    @if (\App\Support\Recordscope::magAllesZien('bewustzijn-training'))
        <flux:navbar.item :href="route('toetsen.resultaten')" :current="request()->routeIs('toetsen.resultaten')" wire:navigate>
            Toetsresultaten
        </flux:navbar.item>
    @endif
</flux:navbar>
