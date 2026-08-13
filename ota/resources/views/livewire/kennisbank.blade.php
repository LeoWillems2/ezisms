<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Kennisbank</flux:heading>
        <flux:subheading>Uitleg en achtergrond bij de opzet van het ISMS.</flux:subheading>
    </div>

    <div class="flex flex-col gap-8 md:flex-row">
        {{-- Navigatie: de artikellijst, of de zoekresultaten die haar vervangen.
             Zoeken vervangt de navigatie en niet de inhoud — het artikel rechts
             blijft staan waar het stond. --}}
        <aside @class(['w-full shrink-0', 'md:w-96' => $zoekt, 'md:w-64' => ! $zoekt])>
            <flux:input
                wire:model.live.debounce.400ms="zoekterm"
                type="search"
                icon="magnifying-glass"
                placeholder="Zoek in de kennisbank"
                class="mb-4"
            />

            @if ($zoekt)
                @if ($resultaten === [])
                    <flux:text class="text-sm">
                        Geen artikel gevonden voor <span class="font-medium">{{ trim($zoekterm) }}</span>.
                    </flux:text>
                    <flux:button variant="ghost" size="sm" class="mt-2" wire:click="$set('zoekterm', '')">
                        Terug naar het overzicht
                    </flux:button>
                @else
                    <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        {{ count($resultaten) }} {{ count($resultaten) === 1 ? 'artikel' : 'artikelen' }}
                    </flux:text>
                    <ul class="flex flex-col gap-1">
                        @foreach ($resultaten as $resultaat)
                            <li wire:key="tref-{{ $resultaat->slug }}">
                                <a href="{{ $resultaat->url() }}" wire:navigate
                                   class="block rounded-md px-3 py-2 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $resultaat->titel }}
                                    </span>
                                    <span class="block text-xs text-zinc-500">{{ $resultaat->categorie }}</span>
                                    <span class="kennis-passage mt-1 block text-xs text-zinc-600 dark:text-zinc-400">
                                        {!! $resultaat->passage !!}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                @foreach ($perCategorie as $categorie => $artikelen)
                    <div class="mb-4">
                        <flux:text class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ $categorie }}
                        </flux:text>
                        <ul class="flex flex-col gap-1">
                            @foreach ($artikelen as $slug => $artikel)
                                <li wire:key="art-{{ $slug }}">
                                    <a href="{{ route('kennisbank', $slug) }}" wire:navigate
                                       @class([
                                           'block rounded-md px-3 py-2 text-sm transition',
                                           'bg-zinc-100 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' => $slug === $huidigeSlug,
                                           'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/50' => $slug !== $huidigeSlug,
                                       ])>
                                        {{ $artikel['titel'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </aside>

        {{-- Artikelinhoud. --}}
        <article class="min-w-0 flex-1">
            @if ($inhoudHtml === null)
                <flux:text>Geen artikel geselecteerd.</flux:text>
            @else
                {{-- De downloadknop naast de titel, zoals de schermkopieknop
                     elders. Twee voorwaarden, en ze zeggen iets anders: de
                     autorisatiecheck gaat over wie mag meenemen (alleen de
                     CISO), `$downloadbaar` over of er een bruikbaar document
                     van te maken is (zie Kennisartikelen). De route toetst ze
                     allebei nog eens — een knop die er niet staat is geen
                     beveiliging. Geen wire:navigate: dit is een
                     bestandsdownload, geen paginanavigatie. --}}
                <div class="mb-4 flex items-start justify-between gap-4">
                    <flux:heading size="lg">{{ $titel }}</flux:heading>

                    @if ($downloadbaar)
                        @can('kennisartikel-downloaden')
                            <flux:button
                                :href="route('kennisbank.download', $huidigeSlug)"
                                icon="arrow-down-tray"
                                variant="ghost"
                                size="sm"
                                class="shrink-0"
                            >
                                Download
                            </flux:button>
                        @endcan
                    @endif
                </div>

                <div class="kennis-inhoud max-w-3xl">{!! $inhoudHtml !!}</div>
            @endif
        </article>
    </div>

    {{-- Scoped opmaak voor de gerenderde markdown; thema-neutraal (rgba), want
         dit project heeft geen typography-plugin. --}}
    <style>
        .kennis-inhoud { line-height: 1.65; }
        /* Kopankers (implementatie/00g §5): een diepe link mag de kop niet
           bovenaan het scherm laten plakken. */
        .kennis-inhoud :is(h2, h3, h4, h5, h6)[id] { scroll-margin-top: 5rem; }
        .kennis-inhoud h2 { font-size: 1.15rem; font-weight: 600; margin: 1.6rem 0 .6rem; }
        .kennis-inhoud h2:first-child { margin-top: 0; }
        .kennis-inhoud p { margin: .6rem 0; }
        .kennis-inhoud ul, .kennis-inhoud ol { margin: .6rem 0; padding-left: 1.4rem; }
        .kennis-inhoud ul { list-style: disc; }
        .kennis-inhoud ol { list-style: decimal; }
        .kennis-inhoud li { margin: .25rem 0; }
        .kennis-inhoud code {
            font-size: .85em; padding: .1em .35em; border-radius: .25rem;
            background: rgba(128, 128, 128, .16);
        }
        .kennis-inhoud pre { overflow-x: auto; padding: .8rem 1rem; border-radius: .5rem;
            background: rgba(128, 128, 128, .12); margin: .8rem 0; }
        .kennis-inhoud pre code { background: none; padding: 0; }
        .kennis-inhoud blockquote {
            border-left: 3px solid rgba(128, 128, 128, .4);
            padding: .2rem 0 .2rem 1rem; margin: .9rem 0; opacity: .9;
        }
        .kennis-inhoud table { border-collapse: collapse; width: 100%; margin: .9rem 0; font-size: .9rem; display: block; overflow-x: auto; }
        .kennis-inhoud th, .kennis-inhoud td {
            border: 1px solid rgba(128, 128, 128, .3); padding: .4rem .6rem; text-align: left; vertical-align: top;
        }
        .kennis-inhoud th { background: rgba(128, 128, 128, .12); font-weight: 600; }
        .kennis-inhoud a { text-decoration: underline; text-underline-offset: 2px; }
        /* De gemarkeerde treffer in een zoekpassage; thema-neutraal, net als
           de opmaak hierboven. */
        .kennis-passage { line-height: 1.5; }
        .kennis-passage mark {
            background: rgba(250, 204, 21, .35); color: inherit;
            padding: 0 .1em; border-radius: .15rem;
        }
    </style>
</div>
