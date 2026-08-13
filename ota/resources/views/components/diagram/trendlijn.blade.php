@props([
    /** @var list<float> Oplopend in tijd. */
    'reeks' => [],
    /** @var list<string> Labels per punt, even lang als $reeks. */
    'labels' => [],
    /** Bovengrens van de y-as. Elk paneel heeft zijn eigen as — nooit twee assen in één vlak. */
    'maximum' => 100,
    /** Achtervoegsel bij een waarde: '%', ' d', of leeg bij een telling. */
    'eenheid' => '%',
    /** De vastgestelde streefwaarde, of null. Tekent als stippellijn. */
    'streefwaarde' => null,
    'omschrijving' => '',
])

@php
    $punten = array_values($reeks);
    $tekenbaar = count($punten) >= 2;

    if ($tekenbaar) {
        $breedte = 260;
        $hoogte = 86;
        // Ruimte rechts voor het eindlabel, onder voor de x-as: de container mag
        // de aslabels niet afsnijden.
        $marge = ['boven' => 10, 'rechts' => 38, 'onder' => 18, 'links' => 4];

        $bovengrens = max(1, $maximum);

        $x = fn (int $i) => $marge['links'] + $i * ($breedte - $marge['links'] - $marge['rechts']) / (count($punten) - 1);
        $y = fn (float $w) => $marge['boven'] + (1 - min($w, $bovengrens) / $bovengrens) * ($hoogte - $marge['boven'] - $marge['onder']);

        $lijn = implode(' ', array_map(
            fn (float $w, int $i) => ($i === 0 ? 'M' : 'L').round($x($i), 2).' '.round($y($w), 2),
            $punten,
            array_keys($punten),
        ));

        $vlak = $lijn
            .'L'.round($x(count($punten) - 1), 2).' '.round($y(0), 2)
            .'L'.round($x(0), 2).' '.round($y(0), 2).'Z';

        $laatste = count($punten) - 1;
        // Een telling heeft geen achtervoegsel en geen decimalen; een percentage
        // rondt af, dagen krijgen er één.
        $schrijf = fn (float $w) => match ($eenheid) {
            '%' => round($w).'%',
            '' => (string) (int) round($w),
            default => number_format($w, 1, ',', '.').$eenheid,
        };
        $eindwaarde = $schrijf($punten[$laatste]);
    }
@endphp

@if ($tekenbaar)
    {{-- Geen `preserveAspectRatio="none"`: er staat tekst in de SVG en die zou
         meerekken met de kaartbreedte. Uniform schalen via width/height. --}}
    <svg viewBox="0 0 {{ $breedte }} {{ $hoogte }}" class="block h-auto w-full"
        role="img" aria-label="{{ $omschrijving }}">
        {{-- Raster: solide hairlines, één stap van het vlak af. Nooit gestreept —
             dat leest als drempel of prognose terwijl het een raster is. --}}
        @foreach ([0, $bovengrens / 2, $bovengrens] as $waarde)
            <line x1="{{ $marge['links'] }}" x2="{{ $breedte - $marge['rechts'] }}"
                y1="{{ round($y($waarde), 2) }}" y2="{{ round($y($waarde), 2) }}"
                stroke="var(--dia-raster)" stroke-width="1" />
        @endforeach

        <text x="{{ $breedte - $marge['rechts'] + 5 }}" y="{{ round($y($bovengrens) + 3.5, 2) }}"
            fill="currentColor" font-size="8.5"
            class="fill-zinc-500 font-mono dark:fill-zinc-400">{{ $schrijf($bovengrens) }}</text>

        <path d="{{ $vlak }}" fill="var(--dia-lijn)" fill-opacity="0.10" stroke="none" />
        <path d="{{ $lijn }}" fill="none" stroke="var(--dia-lijn)" stroke-width="2"
            stroke-linejoin="round" stroke-linecap="round" />

        {{-- De streefwaarde, gestreept. Het raster hierboven is bewust solide,
             juist omdat gestreept in dit diagram "drempel" betekent — dit is de
             enige lijn die dat mag zijn. Alleen een vastgestelde streefwaarde
             komt hier terecht (12e §9); een voorstel tekent niets. --}}
        @if ($streefwaarde !== null)
            <line x1="{{ $marge['links'] }}" x2="{{ $breedte - $marge['rechts'] }}"
                y1="{{ round($y($streefwaarde), 2) }}" y2="{{ round($y($streefwaarde), 2) }}"
                stroke="var(--dia-lijn)" stroke-width="1.25" stroke-dasharray="4 3"
                stroke-opacity="0.75" />
            {{-- Niet labelen als hij tegen het aslabel aan ligt: twee getallen
                 over elkaar is slechter dan één getal minder. --}}
            @if (abs($y($streefwaarde) - $y($bovengrens)) >= 9)
                <text x="{{ $breedte - $marge['rechts'] + 5 }}" y="{{ round($y($streefwaarde) + 3.5, 2) }}"
                    font-size="8.5"
                    class="fill-zinc-500 font-mono dark:fill-zinc-400">{{ $schrijf($streefwaarde) }}</text>
            @endif
        @endif

        {{-- Eindpunt met een ring in de vlakkleur, zodat hij leesbaar blijft waar
             hij de lijn of het raster kruist. --}}
        <circle cx="{{ round($x($laatste), 2) }}" cy="{{ round($y($punten[$laatste]), 2) }}" r="4"
            fill="var(--dia-lijn)" stroke="var(--dia-oppervlak)" stroke-width="2" />

        {{-- Alleen het eindpunt krijgt een waarde. Een getal bij elk punt is
             chaos en wordt niet gelezen; de tabelweergave heeft de rest. --}}
        <text x="{{ round($x($laatste) + 8, 2) }}" y="{{ round($y($punten[$laatste]) + 3.5, 2) }}"
            font-size="9.5" font-weight="600"
            class="fill-zinc-900 dark:fill-white">{{ $eindwaarde }}</text>

        @if (count($labels) === count($punten))
            <text x="{{ $marge['links'] }}" y="{{ $hoogte - 4 }}" font-size="8.5" text-anchor="start"
                class="fill-zinc-500 font-mono dark:fill-zinc-400">{{ $labels[0] }}</text>
            <text x="{{ round($x($laatste), 2) }}" y="{{ $hoogte - 4 }}" font-size="8.5" text-anchor="end"
                class="fill-zinc-500 font-mono dark:fill-zinc-400">{{ $labels[$laatste] }}</text>
        @endif
    </svg>
@else
    <div class="py-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
        {{ count($punten) === 1 ? 'Eén meetpunt — een trend ontstaat vanaf het tweede.' : 'Nog niet gemeten.' }}
    </div>
@endif
