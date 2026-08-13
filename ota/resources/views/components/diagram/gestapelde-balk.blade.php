@props([
    /** @var list<array{label: string, aantal: int, kleur: string}> In ordeningsvolgorde. */
    'segmenten' => [],
    /** Aandeel van de volle breedte, 0–100: hiermee zijn de rijen onderling te vergelijken. */
    'aandeel' => 100,
    'omschrijving' => '',
])

@php
    $zichtbaar = array_values(array_filter($segmenten, fn (array $s) => $s['aantal'] > 0));
    $totaal = array_sum(array_column($segmenten, 'aantal'));
@endphp

{{-- In HTML en niet in SVG: geen schaling, dus geen vervormde tekst, en `gap`
     levert de scheiding van 2px tussen de vlakken zonder er een rand om te
     tekenen. Een rand zou data-gewicht toevoegen dat geen data is. --}}
<div class="flex h-3.5 gap-0.5" style="width: {{ max(2, round($aandeel, 2)) }}%"
    role="img" aria-label="{{ $omschrijving }}">
    @forelse ($zichtbaar as $segment)
        <div class="min-w-0.5 first:rounded-l-sm last:rounded-r-sm"
            style="background: {{ $segment['kleur'] }}; flex: {{ $segment['aantal'] }} 0 0"
            title="{{ $segment['label'] }}: {{ $segment['aantal'] }} van {{ $totaal }}"></div>
    @empty
        <div class="w-full rounded-sm border border-dashed border-zinc-300 dark:border-zinc-600"></div>
    @endforelse
</div>
