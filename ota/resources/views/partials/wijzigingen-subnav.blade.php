{{-- Gedeelde sub-navigatie voor blok 15 (Wijzigingsbeheer). Het register is
     voor iedereen met 'lezen' (melden staat op 'uitvoeren'); het sjabloonbeheer
     is een muteer-scherm.

     De tab heet voluit "Wijzigingssjablonen" en niet "Sjablonen": blok 7 heeft
     óók een tab die zo heet, en een CISO ziet beide schermen. Twee identieke
     labels voor twee verschillende soorten sjabloon is precies de verwarring
     die een menu hoort weg te nemen. --}}
<flux:navbar class="mb-2">
    <flux:navbar.item :href="route('wijzigingen.index')" :current="request()->routeIs('wijzigingen.*')" wire:navigate>
        Register
    </flux:navbar.item>

    @can('heeft-niveau', ['wijzigingsbeheer', 'muteren'])
        <flux:navbar.item :href="route('wijzigingssjablonen.index')" :current="request()->routeIs('wijzigingssjablonen.index')" wire:navigate>
            Wijzigingssjablonen
        </flux:navbar.item>
    @endcan
</flux:navbar>
