@props([
    /** @var list<float> Oplopend in tijd; minstens twee punten. */
    'reeks' => [],
    'omschrijving' => '',
])

@php
    $punten = array_values(array_filter($reeks, fn ($w) => $w !== null));

    // Eén punt levert geen lijn op. Dan liever niets dan een misleidend streepje.
    $tekenbaar = count($punten) >= 2;

    if ($tekenbaar) {
        $breedte = 200;
        $hoogte = 34;
        $rand = 3;

        $min = min($punten);
        $max = max($punten);
        // Een vlakke reeks zou door nul gedeeld worden; die tekent als middenlijn.
        $bereik = $max - $min ?: 1;

        $x = fn (int $i) => $rand + $i * ($breedte - 2 * $rand) / (count($punten) - 1);
        $y = fn (float $w) => $hoogte - $rand - ($w - $min) / $bereik * ($hoogte - 2 * $rand);

        $pad = implode(' ', array_map(
            fn (float $w, int $i) => ($i === 0 ? 'M' : 'L').round($x($i), 2).' '.round($y($w), 2),
            $punten,
            array_keys($punten),
        ));

        $laatste = count($punten) - 1;
    }
@endphp

@if ($tekenbaar)
    {{-- `preserveAspectRatio="none"` mag hier: er staat geen tekst in, en
         `vector-effect` houdt de lijndikte op 2px ondanks het uitrekken. --}}
    <svg viewBox="0 0 {{ $breedte }} {{ $hoogte }}" preserveAspectRatio="none"
        class="mt-auto block h-[34px] w-full pt-2.5" role="img"
        aria-label="{{ $omschrijving }}">
        {{-- De reeks recessief, alleen de laatste stap in het accent: de tegel
             gaat over nu, de lijn is context. --}}
        <path d="{{ $pad }}" fill="none" stroke="var(--dia-stil)" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        <path d="M{{ round($x($laatste - 1), 2) }} {{ round($y($punten[$laatste - 1]), 2) }}L{{ round($x($laatste), 2) }} {{ round($y($punten[$laatste]), 2) }}"
            fill="none" stroke="var(--dia-lijn)" stroke-width="2"
            stroke-linecap="round" vector-effect="non-scaling-stroke" />
    </svg>
@else
    <div class="mt-auto pt-2.5 text-xs text-zinc-500 dark:text-zinc-400">
        Nog te weinig metingen voor een trend.
    </div>
@endif
