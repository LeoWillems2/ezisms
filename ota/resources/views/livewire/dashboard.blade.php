@php
    use App\Models\Risico;
    use App\Support\Kpitrend;
    use App\Support\Maatregelverdeling;
    use App\Support\Risicoverdeling;

    /** Semafoorkleur -> statustoken. `Risico::scoreKleur()` blijft de enige bron. */
    $bandToken = [
        'red' => 'var(--sig-kritiek)',
        'amber' => 'var(--sig-let-op)',
        'green' => 'var(--sig-goed)',
        'zinc' => 'var(--dia-stil)',
    ];

    $bandNaam = [
        'red' => 'boven de drempel',
        'amber' => 'aandacht',
        'green' => 'aanvaardbaar',
        'zinc' => 'niet beoordeeld',
    ];

    $vlagStijl = [
        'kritiek' => ['bg' => 'var(--sig-kritiek)', 'ink' => '#ffffff', 'glyph' => '!'],
        'let-op' => ['bg' => 'var(--sig-let-op)', 'ink' => '#3a2a00', 'glyph' => '!'],
        'goed' => ['bg' => 'var(--sig-goed)', 'ink' => '#ffffff', 'glyph' => '✓'],
        'neutraal' => ['bg' => 'var(--dia-stil)', 'ink' => '#ffffff', 'glyph' => '?'],
    ];
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Welkom, {{ auth()->user()->naam }}</flux:heading>
        <flux:subheading>
            {{ auth()->user()->rollen->pluck('naam')->implode(', ') ?: 'Geen rol toegewezen' }}
        </flux:subheading>
    </div>

    {{-- 1. Mijn openstaande taken (blok 7). Blijft bovenaan: dit is het enige
         paneel dat de Medewerker heeft, en het is waar hij het dashboard voor
         gebruikt. --}}
    @if ($mijnTaken !== null)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-3 flex items-center justify-between gap-4">
                <flux:heading size="lg">Mijn openstaande taken</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('taken.index')" wire:navigate>
                    Alle taken
                </flux:button>
            </div>

            @forelse ($mijnTaken as $taak)
                <div class="flex items-center justify-between gap-3 border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800">
                    <flux:text class="truncate">{{ $taak->titel }}</flux:text>
                    <flux:text class="shrink-0 text-sm {{ $taak->isFeitelijkVerlopen() ? 'text-red-600 dark:text-red-500' : '' }}">
                        {{ $taak->deadline->format('d-m-Y') }}
                    </flux:text>
                </div>
            @empty
                <flux:text>Geen openstaande taken.</flux:text>
            @endforelse
        </div>
    @endif

    {{-- 2. KPI-strip (12c §3.1). --}}
    @if ($strip !== null && $strip->isNotEmpty())
        <div>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">Kerncijfers</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('meetaanpak.index')" wire:navigate>
                    Meetaanpak
                </flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($strip as $trend)
                    @php
                        $laatste = $trend->laatste();
                        $uitkomst = $laatste ? $trend->uitkomst($laatste) : null;
                        $delta = $trend->delta();
                        $richting = $trend->richting();
                        $basis = $trend->basis();
                        $status = $trend->status();
                        // Dezelfde kleurtokens als de risicobanden: één semafoor
                        // op het hele dashboard, niet twee die net verschillen.
                        $statusKleur = Kpitrend::statusKleur($status);
                        $streef = $laatste?->streefwaarde;
                    @endphp

                    <div class="flex flex-col rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                        wire:key="kpi-{{ $trend->definitie->id }}">
                        <div class="font-mono text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ $faseLabels[$trend->definitie->fase] ?? $trend->definitie->fase }}
                        </div>
                        <div class="mt-0.5 min-h-[2.9em] text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $trend->definitie->naam }}
                        </div>

                        @if ($uitkomst === null)
                            <div class="mt-1 text-2xl font-semibold text-zinc-400 dark:text-zinc-500">—</div>
                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">nog niet gemeten</div>
                        @else
                            <div class="mt-1 text-3xl font-semibold leading-tight tracking-tight">
                                {{ $trend->waardeLabel($uitkomst) }}
                            </div>
                            {{-- Teller en noemer erbij (blok 12 §2a): "36 van 88" is
                                 uit te leggen, 41% niet, en de noemer beweegt mee.
                                 De streefwaarde staat ernaast, want een kleur zonder
                                 maatstaf is een oordeel zonder maatstaf (12d §6). --}}
                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                                @if ($trend->inAantal()){{ $trend->periodeLabel($laatste) }}@elseif ($trend->inDagen()){{ $laatste->teller }} dagen / {{ $laatste->noemer }} taken@else{{ $laatste->teller }} van {{ $laatste->noemer }}@endif@if ($streef !== null) · streef {{ $trend->waardeLabel($streef) }}@endif
                            </div>
                        @endif

                        {{-- Kleur draagt nooit alleen de betekenis: naast de stip
                             staat het oordeel in woorden. --}}
                        <div class="mt-2 flex items-center gap-1.5 text-[11px] text-zinc-600 dark:text-zinc-300">
                            <span class="size-2 shrink-0 rounded-full"
                                style="background: {{ $bandToken[$statusKleur] }}" aria-hidden="true"></span>
                            <span>{{ Kpitrend::statusLabel($status) }}</span>
                        </div>

                        @if ($delta !== null && $basis !== null && $laatste !== null)
                            @php
                                $maanden = $basis->gemeten_op->diffInMonths($laatste->gemeten_op);
                                $kleur = match ($richting) {
                                    'op' => 'text-green-700 dark:text-green-500',
                                    'neer' => 'text-red-600 dark:text-red-500',
                                    default => 'text-zinc-500 dark:text-zinc-400',
                                };
                                $pijl = $richting === 'vlak' ? '→' : ($delta > 0 ? '↑' : '↓');
                            @endphp
                            <div class="mt-2 flex items-center gap-1.5 text-xs {{ $kleur }}">
                                <span>{{ $pijl }} {{ $trend->deltaLabel(abs($delta)) }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">
                                    vs. {{ $maanden > 0 ? $maanden.' mnd' : 'vorige meting' }}
                                </span>
                            </div>
                        @endif

                        <x-diagram.sparkline :reeks="$trend->sparkline()"
                            :omschrijving="$trend->definitie->naam.': laatste '.Kpitrend::SPARKLINE_PUNTEN.' metingen'" />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 3. Signalen (12c §3.2). Boven de trends: wat aandacht vraagt eerst. --}}
    @if ($signalen !== null)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">Signalen</flux:heading>
                <flux:text class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                    wat aandacht vraagt
                </flux:text>
            </div>
            <flux:subheading class="mb-4">
                Niet de score maar de afwijking. Een terugval of een dip die zich herstelt is
                bewijs dat er gemeten wordt — geen smet op het ISMS.
            </flux:subheading>

            @forelse ($signalen as $signaal)
                @php $stijl = $vlagStijl[$signaal['vlag']] ?? $vlagStijl['neutraal']; @endphp
                <div class="grid grid-cols-[18px_1fr_auto] items-start gap-3 border-t border-zinc-100 py-2.5 first:border-t-0 dark:border-zinc-800">
                    {{-- Kleur draagt nooit alleen de betekenis: elke vlag heeft een
                         glyph, en de regel ernaast zegt het in woorden. --}}
                    <span class="mt-1 grid size-[18px] place-items-center rounded-full text-[11px] font-bold"
                        style="background: {{ $stijl['bg'] }}; color: {{ $stijl['ink'] }}"
                        aria-hidden="true">{{ $stijl['glyph'] }}</span>
                    <div>
                        <flux:text class="font-medium">{{ $signaal['tekst'] }}</flux:text>
                        <flux:text class="mt-0.5 block text-xs">{{ $signaal['uitleg'] }}</flux:text>
                    </div>
                    <flux:text class="whitespace-nowrap font-mono text-xs">{{ $signaal['getal'] }}</flux:text>
                </div>
            @empty
                <flux:text>Geen signalen. Dat kan betekenen dat alles op orde is — of dat er te weinig gemeten wordt.</flux:text>
            @endforelse
        </div>
    @endif

    {{-- 4. PDCA-trend (12c §3.3). Eén klein diagram per KPI: acht lijnen in één
         vlak is voorbij de grens waar kleur nog identiteit draagt, en de
         dagen-KPI zou een tweede y-as vragen. Nergens twee assen. --}}
    @if ($perFase !== null)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">PDCA-trend</flux:heading>
                <flux:text class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                    maandelijkse meetpunten
                </flux:text>
            </div>

            @foreach ($perFase as $fase => $trends)
                <div class="@if (! $loop->first) mt-6 @endif">
                    <div class="mb-3 flex items-center gap-3">
                        <flux:heading size="sm" class="font-mono uppercase tracking-wider">
                            {{ $faseLabels[$fase] }}
                        </flux:heading>
                        <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
                        <flux:text class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $trends->count() }} {{ $trends->count() === 1 ? 'KPI' : "KPI's" }}
                        </flux:text>
                    </div>

                    @if ($fase === 'act')
                        {{-- Deze toelichting stond er twee keer eerder anders: eerst als
                             "de Act-fase wordt nog niet gemeten", daarna als "de drie
                             metingen op de audit trail ontbreken nog". Beide zijn
                             ingehaald door 12d §4 en 12g. Een toelichting die blijft
                             staan nadat hij onwaar is geworden, is dezelfde fout als een
                             gebroken exportbelofte. --}}
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            Deze fase meet de bijsturing zelf: de corrigerende maatregelen uit §10.1,
                            en sinds 12g ook wat er in een periode <em>gebeurde</em> — scoredalingen
                            zonder onderbouwing, afgeronde behandelplannen en nieuw geïdentificeerde
                            risico's. Die laatste drie meten een venster en geen momentopname; hun
                            meetpunten dragen daarom een periode.
                        </flux:text>
                    @endif

                    @if ($trends->isEmpty())
                        {{-- Anders dan /meetaanpak blijft een lege fase hier staan: op
                             een trendpaneel is dat informatie. Het ISMS meet zijn eigen
                             bijsturing dan nog niet. --}}
                        <div class="rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
                            <flux:text class="font-medium">Nog geen {{ $faseLabels[$fase] }}-metingen</flux:text>
                            <flux:text class="mt-0.5 block text-sm">
                                Zolang deze fase leeg is, meet het ISMS deze stap van de cyclus niet.
                            </flux:text>
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($trends as $trend)
                                @php
                                    $reeks = $trend->reeks();
                                    $labels = $trend->metingen
                                        ->map(fn ($m) => $m->gemeten_op->translatedFormat('M y'))
                                        ->all();
                                    $laatste = $trend->laatste();
                                    // De streefwaarde van het laatste meetpunt, niet
                                    // die van de definitie: het diagram toont wat er
                                    // gemeten is, en de tegel hierboven oordeelt tegen
                                    // dezelfde waarde. Een pas vastgestelde streefwaarde
                                    // verschijnt dus bij de eerstvolgende meting — en
                                    // dat klopt, want daarvóór is er niets tegen
                                    // afgemeten.
                                    $streef = $laatste?->streefwaarde;
                                    // Eigen bovengrens per paneel; bij dagen afgerond
                                    // op een rond getal boven de piek. De streefwaarde
                                    // telt mee, anders valt de stippellijn buiten beeld
                                    // bij een KPI die ruim onder zijn norm zit.
                                    // Een ratio loopt tot 100; dagen en tellingen hebben
                                    // geen natuurlijk plafond, dus die krijgen een ronde
                                    // grens boven de piek.
                                    $bovengrens = $trend->inDagen() || $trend->inAantal()
                                        ? max(10, (int) (ceil((max([...$reeks, $streef ?? 0]) ?: 1) / 20) * 20))
                                        : 100;
                                @endphp

                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                                    wire:key="trend-{{ $trend->definitie->id }}">
                                    <flux:text class="block min-h-[2.7em] text-sm font-medium leading-snug">
                                        {{ $trend->definitie->naam }}
                                    </flux:text>
                                    <flux:text class="mb-1.5 block font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                                        @if ($laatste)
                                            @if ($trend->inAantal()){{ $trend->periodeLabel($laatste) }}@elseif ($trend->inDagen()){{ $laatste->teller }} / {{ $laatste->noemer }}@else{{ $laatste->teller }} van {{ $laatste->noemer }}@endif
                                        @else
                                            geen meetpunten
                                        @endif
                                    </flux:text>

                                    <x-diagram.trendlijn :reeks="$reeks" :labels="$labels"
                                        :maximum="$bovengrens" :eenheid="$trend->diagramEenheid()"
                                        :streefwaarde="$streef"
                                        :omschrijving="$trend->definitie->naam.', '.count($reeks).' maandmetingen'
                                            .($streef === null ? '' : ', streefwaarde '.$streef)" />

                                    {{-- Tabelweergave: geen waarde die alleen met een muis
                                         te lezen is. --}}
                                    @if (count($reeks) > 0)
                                        <details class="group mt-2">
                                            <summary class="flex cursor-pointer list-none items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300 [&::-webkit-details-marker]:hidden">
                                                <span class="transition-transform group-open:rotate-90">&#9656;</span>
                                                Tabel
                                            </summary>
                                            <div class="mt-2 max-h-40 overflow-auto">
                                                <table class="w-full text-xs tabular-nums">
                                                    <tbody>
                                                        @foreach ($trend->metingen as $i => $meting)
                                                            <tr class="border-b border-zinc-100 last:border-b-0 dark:border-zinc-800">
                                                                <td class="py-1 pr-2 font-mono text-zinc-500 dark:text-zinc-400">
                                                                    {{ $meting->gemeten_op->format('m-Y') }}
                                                                </td>
                                                                <td class="py-1 pr-2 text-right text-zinc-500 dark:text-zinc-400">
                                                                    {{ $meting->teller }}/{{ $meting->noemer }}
                                                                </td>
                                                                <td class="py-1 text-right font-medium">
                                                                    {{ $trend->waardeLabel($reeks[$i]) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- 5. Risico's en maatregelen (12c §3.4). --}}
    @if ($verdeling !== null && $maatregelen !== null)
        <div class="grid gap-6 lg:grid-cols-2">

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                    <flux:heading size="lg">Risico's</flux:heading>
                    <flux:button size="sm" variant="ghost" :href="route('risicos.matrix')" wire:navigate>
                        Tolerantiematrix
                    </flux:button>
                </div>
                <flux:subheading class="mb-4">
                    {{ $verdeling->beoordeeld }} beoordeeld
                    @if ($verdeling->nietBeoordeeld > 0)
                        · {{ $verdeling->nietBeoordeeld }} nog niet
                    @endif
                </flux:subheading>

                <div class="overflow-x-auto">
                    <table class="mx-auto border-separate border-spacing-[3px]">
                        <caption class="caption-bottom pt-2 font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                            kans 1–5 horizontaal · impact 1–5 verticaal
                        </caption>
                        <tbody>
                            @for ($impact = Risicoverdeling::SCHAAL; $impact >= 1; $impact--)
                                <tr>
                                    <th scope="row" class="px-1 font-mono text-[10px] font-normal text-zinc-600 dark:text-zinc-300">
                                        {{ $impact }}
                                    </th>
                                    @for ($kans = 1; $kans <= Risicoverdeling::SCHAAL; $kans++)
                                        @php
                                            $aantal = $verdeling->aantalIn($kans, $impact);
                                            $band = Risico::scoreKleur($kans * $impact);
                                        @endphp
                                        @if ($aantal > 0)
                                            {{-- Kleur als wash met de teller in gewone
                                                 tekstkleur: kleur ondersteunt, het getal
                                                 draagt. Wie kleur niet ziet, leest de
                                                 matrix nog steeds. --}}
                                            <td class="h-10 w-11 rounded-md text-center text-sm font-semibold tabular-nums"
                                                style="background: color-mix(in srgb, {{ $bandToken[$band] }} 26%, var(--dia-oppervlak))"
                                                title="kans {{ $kans }} × impact {{ $impact }} — score {{ $kans * $impact }}, {{ $bandNaam[$band] }}: {{ $aantal }} {{ $aantal === 1 ? 'risico' : "risico's" }}">
                                                {{ $aantal }}
                                            </td>
                                        @else
                                            <td class="h-10 w-11 rounded-md bg-zinc-50 text-center text-xs text-zinc-400 dark:bg-zinc-900 dark:text-zinc-600">
                                                ·
                                            </td>
                                        @endif
                                    @endfor
                                </tr>
                            @endfor
                            <tr>
                                <th></th>
                                @for ($kans = 1; $kans <= Risicoverdeling::SCHAAL; $kans++)
                                    <th class="font-mono text-[10px] font-normal text-zinc-600 dark:text-zinc-300">{{ $kans }}</th>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="inline-block size-2.5 rounded-sm" style="background: var(--sig-goed)"></i>
                        Aanvaardbaar &lt; {{ Risico::waarschuwingsdrempel() }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="inline-block size-2.5 rounded-sm" style="background: var(--sig-let-op)"></i>
                        Aandacht {{ Risico::waarschuwingsdrempel() }}–{{ Risico::drempelwaarde() }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="inline-block size-2.5 rounded-sm" style="background: var(--sig-kritiek)"></i>
                        Boven de drempel &gt; {{ Risico::drempelwaarde() }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                    <flux:heading size="lg">Maatregelen per thema</flux:heading>
                    <flux:button size="sm" variant="ghost" :href="route('soa.index')" wire:navigate>
                        SoA
                    </flux:button>
                </div>
                <flux:subheading class="mb-4">
                    {{ $maatregelen->totaal }} {{ $norm->bijlage }}-regels · {{ $maatregelen->toepasselijk() }} toepasselijk
                </flux:subheading>

                @php
                    $rampToken = [
                        'niet_gestart' => 'var(--ord-1)',
                        'in_uitvoering' => 'var(--ord-2)',
                        'geimplementeerd' => 'var(--ord-3)',
                        'nvt' => 'var(--ord-buiten)',
                    ];
                @endphp

                @foreach (Maatregelverdeling::THEMA_LABELS as $thema => $label)
                    @php
                        $totaalThema = $maatregelen->totaalVoorThema($thema);
                        $segmenten = collect(Maatregelverdeling::statussen())
                            ->map(fn (string $status) => [
                                'label' => Maatregelverdeling::STATUS_LABELS[$status],
                                'aantal' => $maatregelen->perThema[$thema][$status] ?? 0,
                                'kleur' => $rampToken[$status],
                            ])->all();
                    @endphp
                    <div class="grid grid-cols-[7rem_1fr_2rem] items-center gap-3 py-1.5">
                        <flux:text class="text-sm">{{ $label }}</flux:text>
                        <x-diagram.gestapelde-balk :segmenten="$segmenten"
                            :aandeel="$totaalThema / $maatregelen->grootsteThema() * 100"
                            :omschrijving="$label.': '.$totaalThema.' regels'" />
                        {{-- Alleen het totaal direct labelen, geen getal per segment. --}}
                        <flux:text class="text-right font-mono text-xs tabular-nums">{{ $totaalThema }}</flux:text>
                    </div>
                @endforeach

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                    @foreach (Maatregelverdeling::statussen() as $status)
                        <span class="inline-flex items-center gap-1.5">
                            <i class="inline-block size-2.5 rounded-sm" style="background: {{ $rampToken[$status] }}"></i>
                            {{ Maatregelverdeling::STATUS_LABELS[$status] }}
                        </span>
                    @endforeach
                </div>
            </div>

        </div>
    @endif

    {{-- 6. Aantallen (12c §3.5). Geen diagram: vier ongerelateerde getallen zijn
         geen verdeling en geen reeks. --}}
    @if ($aantallen !== null)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">Documenten en bewijzen</flux:heading>

            <div class="grid gap-px overflow-hidden rounded-lg bg-zinc-200 sm:grid-cols-2 lg:grid-cols-4 dark:bg-zinc-700">
                @foreach ($aantallen as $sleutel => $tel)
                    <div class="bg-white p-4 dark:bg-zinc-800" wire:key="tel-{{ $sleutel }}">
                        <div class="text-2xl font-semibold leading-tight tracking-tight">{{ $tel['getal'] }}</div>
                        <flux:text class="text-sm">{{ $tel['label'] }}</flux:text>
                        <flux:text class="mt-0.5 block font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                            {{ $tel['bij'] }}
                        </flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
