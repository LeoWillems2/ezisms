<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Tolerantiematrix</flux:heading>
            <flux:subheading>
                Het risicoregister als kans × impact. Klik op een cel om de risico's erin te zien.
            </flux:subheading>
        </div>

        @include('partials.kopieknop')
    </div>

    @php
        // Achtergrondklasse per semafoorband; populatie bepaalt alleen de nadruk
        // (lege cellen dimmen), de kleur volgt altijd de score.
        $celKlasse = fn (string $kleur) => match ($kleur) {
            'red' => 'bg-red-500 text-white',
            'amber' => 'bg-amber-400 text-zinc-900',
            'green' => 'bg-green-500 text-white',
            default => 'bg-zinc-300 text-zinc-900 dark:bg-zinc-600 dark:text-white',
        };
    @endphp

    <div class="overflow-x-auto">
        {{-- 6 kolommen: 1 as-label + 5 kans-waarden. Display, sjabloon én gap
             staan inline: de `inline-grid`-utility zit niet in de gecompileerde
             Tailwind-bundle (nergens anders gebruikt), dus we mogen er niet op
             leunen. Zo hangt de layout nergens van JIT-detectie af. --}}
        <div style="display: grid; grid-template-columns: auto repeat(5, 4rem); gap: 0.25rem; width: max-content;">
            {{-- Rijen van hoogste impact (boven) naar laagste (onder). --}}
            @for ($impact = $schaal; $impact >= 1; $impact--)
                {{-- De naam als tooltip en niet als label: de as moet smal
                     blijven. De volledige definitie staat bij de
                     risicocriteria, waar de legenda hieronder naar wijst. --}}
                <div class="flex items-center justify-end pr-2 text-sm font-medium text-zinc-500"
                    title="Impact {{ $impact }} — {{ \App\Support\Beoordelingsschaal::naam('impact', $impact) }}">
                    {{ $impact }}
                </div>
                @for ($kans = 1; $kans <= $schaal; $kans++)
                    @php
                        $aantal = $tellers[$impact][$kans] ?? 0;
                        $kleur = \App\Models\Risico::scoreKleur($kans * $impact);
                        $geselecteerd = $kans === $this->kans && $impact === $this->impact;
                    @endphp
                    <button type="button"
                        wire:click="selecteerCel({{ $kans }}, {{ $impact }})"
                        wire:key="cel-{{ $kans }}-{{ $impact }}"
                        title="Kans {{ $kans }} × impact {{ $impact }} = {{ $kans * $impact }} — {{ $aantal }} risico('s)"
                        @class([
                            'flex h-16 flex-col items-center justify-center rounded-md transition',
                            'focus:outline-none focus:ring-2 focus:ring-offset-1',
                            $celKlasse($kleur),
                            'opacity-40' => $aantal === 0 && ! $geselecteerd,
                            'ring-2 ring-offset-2 ring-blue-600 dark:ring-offset-zinc-900' => $geselecteerd,
                        ])>
                        <span class="text-lg font-semibold">{{ $aantal }}</span>
                        <span class="text-xs opacity-80">{{ $kans * $impact }}</span>
                    </button>
                @endfor
            @endfor

            {{-- X-as: lege hoek + kans-labels 1..5. --}}
            <div></div>
            @for ($kans = 1; $kans <= $schaal; $kans++)
                <div class="pt-1 text-center text-sm font-medium text-zinc-500"
                    title="Kans {{ $kans }} — {{ \App\Support\Beoordelingsschaal::naam('kans', $kans) }}">{{ $kans }}</div>
            @endfor
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-6 text-sm text-zinc-500">
        <span>
            Horizontaal: <strong>kans</strong> (1–5) · Verticaal: <strong>impact</strong> (1–5) · score = kans × impact ·
            <flux:link :href="route('risicos.criteria')" wire:navigate>wat de niveaus betekenen</flux:link>
        </span>
    </div>

    {{-- Legenda (§6), met de actuele drempel ingevuld. --}}
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading size="sm" class="mb-2">Legenda</flux:heading>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-red-500"></span> Score &gt; {{ $drempel }} — boven de acceptatiedrempel</span>
            <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-amber-400"></span> Score {{ $waarschuwing }}–{{ $drempel }} — aandacht</span>
            <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-green-500"></span> Score &lt; {{ $waarschuwing }} — aanvaardbaar</span>
            <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-zinc-300 dark:bg-zinc-600"></span> Niet beoordeeld (kans of impact ontbreekt)</span>
        </div>
    </div>

    {{-- Niet-beoordeelde risico's: buiten de matrix, maar wel telbaar/klikbaar. --}}
    @if ($nietBeoordeeldAantal > 0)
        <div>
            <flux:button size="sm" variant="{{ $this->nietBeoordeeld ? 'primary' : 'ghost' }}"
                icon="question-mark-circle" wire:click="selecteerNietBeoordeeld">
                {{ $nietBeoordeeldAantal }} niet beoordeeld
            </flux:button>
        </div>
    @endif

    {{-- Gefilterde lijst onder de matrix. --}}
    @if ($geselecteerdeRisicos !== null)
        <div>
            <flux:heading size="lg" class="mb-2">
                @if ($this->nietBeoordeeld)
                    Niet beoordeelde risico's
                @else
                    Risico's — kans {{ $this->kans }} × impact {{ $this->impact }}
                @endif
                <flux:badge size="sm" color="zinc">{{ $geselecteerdeRisicos->count() }}</flux:badge>
            </flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Titel</flux:table.column>
                    <flux:table.column>Score</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Eigenaar</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($geselecteerdeRisicos as $risico)
                        <flux:table.row wire:key="risico-{{ $risico->id }}">
                            <flux:table.cell variant="strong">{{ $risico->titel }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($risico->risicoscore === null)
                                    <flux:badge size="sm" color="zinc">Niet beoordeeld</flux:badge>
                                @else
                                    <flux:badge size="sm" :color="\App\Models\Risico::scoreKleur($risico->risicoscore)">{{ $risico->risicoscore }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ ucfirst(str_replace('_', ' ', $risico->status)) }}</flux:table.cell>
                            <flux:table.cell>{{ $risico->eigenaar?->naam ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="arrow-right"
                                    :href="route('risicos.detail', $risico)" wire:navigate>Openen</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5"><flux:text>Geen risico's in deze selectie.</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
