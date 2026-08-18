{{--
    De BIO-verplichtingen onder één beheersmaatregel, als leesweergave in de
    SoA-tabel (deelproducten/04c §3).

    Verwacht `$regel` (een SoaRegel) met `overheidsmaatregelBeoordelingen`,
    daarbinnen `overheidsmaatregel` en `bewijsKoppelingen`, alle drie eager
    geladen — anders is dit een query per verplichting.

    Is de bovenliggende beheersmaatregel uitgesloten, dan wordt de hele lijst
    gedimd getoond met de reden erbij. Niet verbergen: verplichtingen die zonder
    uitleg ontbreken zijn erger dan verplichtingen die niet meetellen.
--}}
@php $uitgesloten = $regel->van_toepassing === false; @endphp

<div @class(['space-y-3', 'opacity-60' => $uitgesloten])>
    @if ($uitgesloten)
        <flux:badge size="sm" color="zinc">
            Beheersmaatregel uitgesloten — deze verplichtingen tellen niet mee
        </flux:badge>
    @endif

    @foreach ($regel->overheidsmaatregelBeoordelingen as $beoordeling)
        @php $om = $beoordeling->overheidsmaatregel; @endphp
        <div class="border-t border-zinc-200 pt-3 first:border-0 first:pt-0 dark:border-zinc-700">
            <div class="flex flex-wrap items-center gap-1.5">
                <flux:badge size="sm" color="zinc">{{ $om->nummer }}</flux:badge>
                <flux:badge size="sm"
                    :color="$beoordeling->status === 'belegd' ? 'green' : ($beoordeling->status === 'niet_beoordeeld' ? 'amber' : 'zinc')">
                    {{ $beoordeling->statusLabel() }}
                </flux:badge>
                @unless ($om->cbw_reikwijdte)
                    <flux:badge size="sm" color="amber"
                        title="Verplichtende zelfregulering in plaats van de Cyberbeveiligingswet">
                        Buiten Cbw-reikwijdte
                    </flux:badge>
                @endunless
                @if ($beoordeling->mistRisicoanalyse())
                    <flux:badge size="sm" color="amber">Risicoanalyse ontbreekt</flux:badge>
                @endif
                @if ($beoordeling->mistBewijs())
                    {{-- Belegd verklaard zonder bewijsstuk: deel 1 §4 vraagt om
                         opzet, bestaan én werking met verwijzingen. --}}
                    <flux:badge size="sm" color="amber">Geen bewijs</flux:badge>
                @elseif ($beoordeling->bewijsKoppelingen->isNotEmpty())
                    <flux:badge size="sm" color="zinc">
                        {{ $beoordeling->bewijsKoppelingen->count() }} × bewijs
                    </flux:badge>
                @endif
                @if ($beoordeling->laatst_beoordeeld_op)
                    <flux:text class="text-xs">
                        beoordeeld {{ $beoordeling->laatst_beoordeeld_op->format('d-m-Y') }}
                    </flux:text>
                @endif
            </div>

            {{-- `whitespace-pre-line`: 40 van de 118 teksten zijn meerregelig met
                 opsommingstekens, en HTML vouwt die regelovergangen anders weg. --}}
            @if ($om->toontTekst())
                <flux:text class="mt-1 whitespace-pre-line text-sm">{{ $om->tekst }}</flux:text>
            @else
                <flux:text class="mt-1 text-sm">
                    {{ \App\Models\Overheidsmaatregel::GEEN_TEKST_AANHEF }}
                    <a href="{{ route('kennisbank', \App\Models\Maatregel::DISCLAIMER_SLUG) }}"
                       wire:navigate class="underline">{{ \App\Models\Maatregel::DISCLAIMER_LABEL }}</a>.
                </flux:text>
            @endif

            @if ($beoordeling->motivatie)
                <flux:text class="mt-1 whitespace-pre-line text-xs">
                    Onderbouwing: {{ $beoordeling->motivatie }}
                </flux:text>
            @endif

            @if ($beoordeling->beleidreferentie || $beoordeling->procesreferentie)
                <flux:text class="text-xs">
                    {{ collect([$beoordeling->beleidreferentie, $beoordeling->procesreferentie])
                        ->filter()->implode(' · ') }}
                </flux:text>
            @endif
        </div>
    @endforeach
</div>
