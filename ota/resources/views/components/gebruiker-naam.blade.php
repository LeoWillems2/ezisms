{{--
    Toont de naam van een gebruiker, met een amber statusbadge zodra het account
    niet actief is (gedeactiveerd/geblokkeerd/uitgenodigd). Zo blijft een
    eigenaar/toegewezene die inmiddels niet meer kan werken zichtbaar als een op
    te ruimen gap, in plaats van er stil te blijven hangen.

    Gebruik:  <x-gebruiker-naam :gebruiker="$asset->responsible" />
              <x-gebruiker-naam :gebruiker="$taak->eigenaar" leeg="Geen eigenaar" />
--}}
@props(['gebruiker' => null, 'leeg' => '—'])

@if ($gebruiker)
    <span class="inline-flex items-center gap-1">
        {{ $gebruiker->naam }}
        @unless ($gebruiker->isActief())
            <flux:badge size="sm" color="amber">{{ ucfirst($gebruiker->status) }}</flux:badge>
        @endunless
    </span>
@else
    {{ $leeg }}
@endif
