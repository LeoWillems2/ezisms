<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div>
        <flux:heading size="xl">Risicocriteria &amp; risk appetite</flux:heading>
        <flux:subheading>
            Het vastgestelde kader waarbinnen risico's beoordeeld worden ({{ $norm->naam_kort }} §6.1.2 a):
            de risicobereidheid, de grenswaarden waarop de semafoor en de acceptatieplicht sturen,
            en wat de niveaus 1 t/m 5 betekenen. De CISO stelt op, de directie stelt vast.
        </flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif
    @if (session('fout'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    @php
        $actief = $this->actieveVersie;
        $werk = $this->werkVersie;
        $verschuiving = $this->verschuiving;
    @endphp

    {{-- 1. De actieve versie: alleen-lezen, ook voor de CISO. Wijzigen loopt
         altijd via een nieuwe versie — dat is het hele punt van 04g. --}}
    @if ($actief)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex flex-wrap items-center gap-3">
                <flux:badge color="green">Actief — versie {{ $actief->versienummer }}</flux:badge>
                <flux:text>Geldig sinds {{ $actief->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:text>
                <flux:text>· Goedgekeurd door {{ $actief->goedgekeurd_door ?: '—' }}</flux:text>
                @if ($actief->herzieningVerstreken())
                    <flux:badge color="red" icon="exclamation-triangle">Herziening verstreken</flux:badge>
                @elseif ($actief->volgende_herziening_gepland)
                    <flux:text>· volgende herziening {{ $actief->volgende_herziening_gepland->format('d-m-Y') }}</flux:text>
                @endif
            </div>

            @if ($actief->herzieningVerstreken())
                <flux:callout class="mt-3" variant="warning" icon="exclamation-triangle"
                    heading="De geplande herziening van de risicocriteria is verstreken."
                    text="Een auditor verwacht dat de risicobereidheid periodiek opnieuw wordt vastgesteld. Start een nieuwe conceptversie." />
            @endif

            {{-- De koppeling naar beleid is een verwijzing en geen import: het
                 beleidsstuk is een PDF en het systeem kan niet zien of daar
                 hetzelfde getal in staat. Ontbreekt hij, dan is dat zichtbaar
                 onvolledig — niet geblokkeerd (04g §2.4). --}}
            @if ($actief->beleidsdocument)
                <flux:text class="mt-3">
                    Vastgesteld in: <span class="font-medium">{{ $actief->beleidsdocument->titel }}</span>
                    @if ($actief->beleidsdocument->actieveVersie)
                        (v{{ $actief->beleidsdocument->actieveVersie->versienummer }})
                    @endif
                    @if ($actief->besluit)
                        · besluit uit de directiebeoordeling van
                        {{ $actief->besluit->reviewsessie?->datum?->format('d-m-Y') ?? 'onbekende datum' }}
                    @endif
                </flux:text>
            @else
                <flux:callout class="mt-3" variant="warning" icon="link-slash"
                    heading="Deze criteria zijn niet herleidbaar naar vastgesteld beleid."
                    text="Koppel in een volgende versie het beleidsdocument waarin de risicobereidheid is vastgelegd; dat is wat een auditor bij §6.1.2 a) als eerste zoekt." />
            @endif

            <flux:separator class="my-4" />

            <flux:heading size="sm">Risk appetite (risicobereidheid)</flux:heading>
            <div class="mt-1 whitespace-pre-line"><flux:text>{{ $actief->omschrijving }}</flux:text></div>

            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500">
                <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-green-500"></span> Groen: &lt; {{ $actief->waarschuwingsdrempel_score }}</span>
                <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-amber-400"></span> Amber: {{ $actief->waarschuwingsdrempel_score }}–{{ $actief->drempelwaarde_score }}</span>
                <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-red-500"></span> Rood: &gt; {{ $actief->drempelwaarde_score }}</span>
            </div>

            {{-- De schaal hoort bij de criteria: de drempels zeggen wat een score
                 betekent, de schaal zegt wat het cijfer betekent waar die score
                 uit komt. Zonder dat tweede is de eerste niet te lezen. --}}
            @foreach (['kans' => 'Kans', 'impact' => 'Impact'] as $as => $label)
                <div class="mt-5">
                    <flux:heading size="sm">Beoordelingsschaal — {{ $label }}</flux:heading>
                    <flux:text class="mb-2">{{ $as === 'kans' ? $actief->leidraad_kans : $actief->leidraad_impact }}</flux:text>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Niveau</flux:table.column>
                            <flux:table.column>Betekenis</flux:table.column>
                            <flux:table.column>Kwantitatieve band</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($actief->niveausVan($as) as $niveau => $definitie)
                                <flux:table.row wire:key="actief-{{ $as }}-{{ $niveau }}">
                                    <flux:table.cell variant="strong">{{ $niveau }} — {{ $definitie->naam }}</flux:table.cell>
                                    <flux:table.cell>{{ $definitie->omschrijving }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($definitie->kwantitatieve_band)
                                            {{ $definitie->kwantitatieve_band }}
                                        @else
                                            <flux:text variant="subtle">nog niet gekwantificeerd</flux:text>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endforeach

            <div class="mt-5 flex gap-2">
                <flux:button variant="ghost" icon="eye" wire:click="bekijkVersie({{ $actief->id }})">
                    Volledige versie bekijken
                </flux:button>

                @if ($this->magMuteren() && ! $werk)
                    <flux:button variant="primary" icon="plus" wire:click="nieuweConceptversieStarten">
                        Nieuwe conceptversie starten
                    </flux:button>
                @endif
            </div>
        </div>
    @else
        <flux:callout variant="danger" icon="exclamation-triangle"
            heading="Deze installatie heeft geen actieve risicocriteria."
            text="Zonder vastgesteld kader is er geen acceptatiedrempel en geen beoordelingsschaal. Draai `php artisan db:seed --class=RisicocriteriaSeeder` om versie 1 aan te leggen." />
    @endif

    {{-- 2. De werkversie: bewerkbaar zolang zij concept is. --}}
    @if ($werk)
        <div class="rounded-xl border-2 border-dashed border-zinc-300 p-5 dark:border-zinc-600">
            <div class="mb-4 flex items-center gap-3">
                <flux:badge :color="$werk->status === 'concept' ? 'amber' : 'blue'">
                    {{ $werk->status === 'concept' ? 'Concept' : 'Ter goedkeuring' }} — versie {{ $werk->versienummer }}
                </flux:badge>
            </div>

            @if ($werk->isBewerkbaar() && $this->magMuteren())
                <form wire:submit="conceptOpslaan" class="flex flex-col gap-5">
                    <flux:textarea wire:model="omschrijving" label="Risk appetite (risicobereidheid)" rows="3"
                        description="Kwalitatieve verklaring van hoeveel risico de organisatie wil accepteren. De grenzen hieronder maken dit meetbaar." />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.live="drempelwaardeScore" type="number" min="1" max="25"
                            label="Acceptatiedrempel (rood)"
                            description="Score > deze waarde valt buiten de risk appetite en vereist een expliciete acceptatie." />
                        <flux:input wire:model.live="waarschuwingsdrempelScore" type="number" min="1" max="25"
                            label="Waarschuwingsgrens (amber)"
                            description="Score vanaf deze waarde krijgt aandacht. Ligt op of onder de acceptatiedrempel." />
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500">
                        <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-green-500"></span> Groen: &lt; {{ $waarschuwingsdrempelScore }}</span>
                        <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-amber-400"></span> Amber: {{ $waarschuwingsdrempelScore }}–{{ $drempelwaardeScore }}</span>
                        <span class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded bg-red-500"></span> Rood: &gt; {{ $drempelwaardeScore }}</span>
                    </div>

                    @include('partials.bandverschuiving', ['verschuiving' => $verschuiving])

                    {{-- De tien niveaudefinities, inline. De kwantitatieve band is
                         het veld waar een op cijfers sturende auditor naar kijkt;
                         het ISMS levert er niets in mee. --}}
                    @foreach (['kans' => 'Kans', 'impact' => 'Impact'] as $as => $label)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:heading size="sm" class="mb-2">Beoordelingsschaal — {{ $label }}</flux:heading>

                            <flux:textarea :wire:model="$as === 'kans' ? 'leidraadKans' : 'leidraadImpact'"
                                label="Leidraad" rows="3"
                                description="Hoe iemand deze as hoort te scoren." />

                            <div class="mt-4 flex flex-col gap-4">
                                @foreach ($niveaus[$as] ?? [] as $niveau => $inhoud)
                                    <div wire:key="werk-{{ $as }}-{{ $niveau }}" class="grid gap-3 sm:grid-cols-[6rem_1fr]">
                                        <flux:input wire:model="niveaus.{{ $as }}.{{ $niveau }}.naam"
                                            label="Niveau {{ $niveau }}" />
                                        <div class="flex flex-col gap-2">
                                            <flux:textarea wire:model="niveaus.{{ $as }}.{{ $niveau }}.omschrijving"
                                                label="Betekenis" rows="2" />
                                            <flux:input wire:model="niveaus.{{ $as }}.{{ $niveau }}.kwantitatieve_band"
                                                label="Kwantitatieve band (optioneel)"
                                                placeholder="{{ $as === 'impact' ? 'bijv. 1 tot 5% van de jaaromzet' : 'bijv. eens per kwartaal' }}" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <flux:textarea wire:model="wijzigingsreden" label="Wijzigingsreden" rows="2"
                        description="Waarom bestaat deze versie? Dit is wat een auditor bij de versiehistorie leest." />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="beleidsdocumentId" label="Vastgesteld in beleidsdocument"
                            description="Het beleidsstuk waarin dit kader is vastgelegd.">
                            <flux:select.option value="">— geen —</flux:select.option>
                            @foreach ($beleidsdocumenten as $document)
                                <flux:select.option value="{{ $document->id }}">{{ $document->titel }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="besluitId" label="Besluit uit de directiebeoordeling"
                            description="Het besluit waarin deze wijziging is genomen (§9.3).">
                            <flux:select.option value="">— geen —</flux:select.option>
                            @foreach ($besluiten as $besluit)
                                <flux:select.option value="{{ $besluit->id }}">
                                    {{ $besluit->reviewsessie?->datum?->format('d-m-Y') ?? '?' }} — {{ \Illuminate\Support\Str::limit($besluit->omschrijving, 60) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">Concept opslaan</flux:button>
                        <flux:button variant="ghost" wire:click="indienenTerGoedkeuring">Indienen ter goedkeuring</flux:button>
                    </div>
                </form>
            @else
                {{-- Ter goedkeuring, of een concept dat deze gebruiker niet mag
                     bewerken: alleen-lezen weergave van wat er voorligt. --}}
                <div class="whitespace-pre-line"><flux:text>{{ $werk->omschrijving }}</flux:text></div>

                <flux:text class="mt-3">
                    Acceptatiedrempel <span class="font-medium">{{ $werk->drempelwaarde_score }}</span>
                    (nu {{ $actief?->drempelwaarde_score ?? '—' }}),
                    waarschuwingsgrens <span class="font-medium">{{ $werk->waarschuwingsdrempel_score }}</span>
                    (nu {{ $actief?->waarschuwingsdrempel_score ?? '—' }})
                </flux:text>

                @if ($werk->wijzigingsreden)
                    <flux:text class="mt-2">Wijzigingsreden: {{ $werk->wijzigingsreden }}</flux:text>
                @endif

                <div class="mt-4">
                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="bekijkVersie({{ $werk->id }})">
                        Volledige versie bekijken
                    </flux:button>
                </div>
            @endif

            {{-- 3. Het goedkeuringsblok. De CISO ziet het ook — hij moet kunnen
                 zien wat hij heeft ingediend — maar bedient het niet. --}}
            @if ($werk->status === 'ter_goedkeuring')
                <flux:separator class="my-6" />

                @include('partials.bandverschuiving', ['verschuiving' => $verschuiving])

                <div class="mt-4 flex flex-col gap-3">
                    @if ($this->magGoedkeuren())
                        <flux:input wire:model="goedgekeurdDoor" label="Goedgekeurd door"
                            description="Namens wie is dit kader vastgesteld (bijv. directie)?" />
                        <div class="flex gap-2">
                            <flux:button variant="primary" wire:click="activeren"
                                wire:confirm="Deze criteria vaststellen? De huidige versie wordt daarmee vervangen en risico's die zwaarder gaan wegen krijgen een herbeoordelingstaak.">
                                Activeren
                            </flux:button>
                            <flux:button variant="ghost" wire:click="terugNaarConcept">Terug naar concept</flux:button>
                        </div>
                    @else
                        <flux:text variant="subtle">
                            Vaststellen doet de directie (rol Management); deze versie staat klaar ter goedkeuring.
                        </flux:text>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- 4. Versiehistorie --}}
    @if ($historie->isNotEmpty())
        <div>
            <flux:heading size="lg">Versiehistorie</flux:heading>
            <flux:text class="mb-3">
                Vervangen versies blijven bewaard als auditbewijs: elk beoordeeld risico verwijst naar
                de versie waaronder het beoordeeld is.
            </flux:text>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Versie</flux:table.column>
                    <flux:table.column>Geldig vanaf</flux:table.column>
                    <flux:table.column>Drempels</flux:table.column>
                    <flux:table.column>Goedgekeurd door</flux:table.column>
                    <flux:table.column>Wijzigingsreden</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($historie as $versie)
                        <flux:table.row wire:key="historie-{{ $versie->id }}">
                            <flux:table.cell variant="strong">{{ $versie->versienummer }}</flux:table.cell>
                            <flux:table.cell>{{ $versie->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $versie->waarschuwingsdrempel_score }} / {{ $versie->drempelwaarde_score }}</flux:table.cell>
                            <flux:table.cell>{{ $versie->goedgekeurd_door ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Str::limit($versie->wijzigingsreden ?? '—', 60) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="bekijkVersie({{ $versie->id }})">
                                    Bekijken
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Read-only detailweergave van een willekeurige versie --}}
    <flux:modal wire:model.self="toontDetail" class="md:w-[48rem]">
        @php $bekeken = $this->bekekenVersie; @endphp
        @if ($bekeken)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">Risicocriteria versie {{ $bekeken->versienummer }}</flux:heading>
                    @php
                        $statusKleur = match ($bekeken->status) {
                            'actief' => 'green',
                            'concept' => 'amber',
                            'ter_goedkeuring' => 'blue',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:badge size="sm" :color="$statusKleur">{{ ucfirst(str_replace('_', ' ', $bekeken->status)) }}</flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <flux:text>Geldig vanaf: {{ $bekeken->geldig_vanaf?->format('d-m-Y') ?? '—' }}</flux:text>
                    <flux:text>Goedgekeurd door: {{ $bekeken->goedgekeurd_door ?? '—' }}</flux:text>
                    <flux:text>Acceptatiedrempel: {{ $bekeken->drempelwaarde_score }}</flux:text>
                    <flux:text>Waarschuwingsgrens: {{ $bekeken->waarschuwingsdrempel_score }}</flux:text>
                    <flux:text>Vastgesteld in: {{ $bekeken->beleidsdocument?->titel ?? '—' }}</flux:text>
                    <flux:text>Volgende herziening: {{ $bekeken->volgende_herziening_gepland?->format('d-m-Y') ?? '—' }}</flux:text>
                </div>

                <div>
                    <flux:heading size="sm">Risk appetite</flux:heading>
                    <div class="mt-1 whitespace-pre-line"><flux:text>{{ $bekeken->omschrijving }}</flux:text></div>
                </div>

                @if ($bekeken->wijzigingsreden)
                    <div>
                        <flux:heading size="sm">Wijzigingsreden</flux:heading>
                        <flux:text class="mt-1">{{ $bekeken->wijzigingsreden }}</flux:text>
                    </div>
                @endif

                @foreach (['kans' => 'Kans', 'impact' => 'Impact'] as $as => $label)
                    <div>
                        <flux:heading size="sm">{{ $label }}</flux:heading>
                        @foreach ($bekeken->niveausVan($as) as $niveau => $definitie)
                            <flux:text class="mt-1">
                                <span class="font-medium">{{ $niveau }} — {{ $definitie->naam }}</span>
                                · {{ $definitie->omschrijving }}
                                @if ($definitie->kwantitatieve_band)
                                    <span class="italic">({{ $definitie->kwantitatieve_band }})</span>
                                @endif
                            </flux:text>
                        @endforeach
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="sluitBekekenVersie">Sluiten</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
