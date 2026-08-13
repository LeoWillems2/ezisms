{{--
    Keuzelijst met een lege waarde die écht bestaat.

    Een <select> kan niets tonen wat geen optie is. Bindt een component aan
    `?int … = null`, dan komt die null met geen enkele optie overeen en toont de
    browser na een Livewire-morph de eerste échte optie, terwijl de state leeg
    blijft: het formulier lijkt ingevuld en faalt bij opslaan. Bind daarom altijd
    aan een string ('' als leeg) en gebruik deze component.

    Verplicht:  Flux' placeholder — die optie is `disabled`, dus terug naar
                "niets" kan niet, en dat is hier juist gewenst.
    Optioneel:  een expliciete, sélecteerbare lege optie. Met Flux' placeholder
                zou een eenmaal gekozen waarde nooit meer te wissen zijn.

    Gebruik:
        <x-keuzelijst wire:model="eigenaarId" label="Eigenaar"
            :opties="$gebruikers->pluck('naam', 'id')" required />
--}}
@props([
    'opties' => [],
    'leeg' => null,
    'required' => false,
    // Expliciet als prop doorgeven, niet via de attribute-bag: anders consumeert
    // Flux ze wél voor het veldlabel maar zet ze óók als losse attributen op het
    // <select>-element zelf.
    'label' => null,
    'description' => null,
])

<flux:select
    :label="$label"
    :description="$description"
    :placeholder="$required ? ($leeg ?? 'Maak een keuze') : null"
    :required="$required"
    {{ $attributes }}
>
    @unless ($required)
        <flux:select.option value="">{{ $leeg ?? '— geen —' }}</flux:select.option>
    @endunless

    @foreach ($opties as $waarde => $tekst)
        <flux:select.option value="{{ $waarde }}">{{ $tekst }}</flux:select.option>
    @endforeach
</flux:select>
