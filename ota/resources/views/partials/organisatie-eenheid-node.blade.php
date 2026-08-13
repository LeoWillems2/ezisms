{{-- Recursieve boom-node. Verwacht $eenheid en $magMuteren.
     Geen hiërarchie-package: bij de verwachte omvang (tientallen eenheden) is
     een simpele recursieve partial voldoende (implementatie/02 §6). --}}
@php
    $typeKleur = match ($eenheid->type) {
        'afdeling' => 'blue',
        'locatie' => 'green',
        'proces' => 'purple',
        default => 'zinc',
    };
@endphp
<li wire:key="eenheid-{{ $eenheid->id }}">
    <div class="flex items-center gap-2 py-1">
        <flux:text class="font-medium">{{ $eenheid->naam }}</flux:text>
        <flux:badge size="sm" :color="$typeKleur">{{ ucfirst($eenheid->type) }}</flux:badge>

        @if ($magMuteren)
            <flux:button size="xs" variant="ghost" icon="plus"
                wire:click="nieuweEenheid({{ $eenheid->id }})">
                Sub-eenheid
            </flux:button>
            <flux:button size="xs" variant="ghost" icon="trash"
                wire:click="verwijderen({{ $eenheid->id }})"
                wire:confirm="'{{ $eenheid->naam }}' verwijderen? Eventuele sub-eenheden komen op het hoofdniveau te staan." />
        @endif
    </div>

    @if ($eenheid->subEenheden->isNotEmpty())
        <ul class="ml-6 border-l border-zinc-200 pl-4 dark:border-zinc-700">
            @foreach ($eenheid->subEenheden->sortBy('naam') as $subEenheid)
                @include('partials.organisatie-eenheid-node', ['eenheid' => $subEenheid, 'magMuteren' => $magMuteren])
            @endforeach
        </ul>
    @endif
</li>
