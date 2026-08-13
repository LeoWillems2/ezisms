<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Statement of Applicability</flux:heading>
            {{-- De telling komt uit de database en niet uit deze zin: het aantal
                 maatregelen verschilt per norm (93 om 101) en een hardgecodeerd
                 getal in een bijschrift gaat dat niet bijhouden. --}}
            <flux:subheading>
                De {{ $totaal }} beheersmaatregelen uit {{ $norm->bijlage }} van {{ $norm->naam }}, met per
                maatregel de toepasselijkheid, de onderbouwing en de implementatiestatus.
            </flux:subheading>
        </div>

        @include('partials.kopieknop')
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($totaal === 0)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Geen maatregelen geladen">
            <flux:callout.text>
                De {{ $norm->bijlage }}-catalogus is nog niet ingelezen. Draai
                <code>php artisan db:seed --class=MaatregelSeeder</code>. Wil je de
                letterlijke normtekst in plaats van de eigen omschrijvingen, genereer die
                dan eerst met <code>python3 ../scripts/genereer_maatregelen_seed.py</code>.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Voortgang: het gap-signaal in één oogopslag. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Beoordeeld</flux:text>
            <flux:heading size="lg">{{ $totaal - $onbeslist }} / {{ $totaal }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Van toepassing</flux:text>
            <flux:heading size="lg">{{ $vanToepassingJa }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Geïmplementeerd</flux:text>
            <flux:heading size="lg">{{ $geimplementeerd }} / {{ $vanToepassingJa }}</flux:heading>
        </div>
    </div>

    @if ($onbeslist > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $onbeslist }} maatregelen zijn nog niet beoordeeld">
            <flux:callout.text>
                Een SoA is pas volledig als van élke maatregel is vastgelegd of hij van
                toepassing is — inclusief onderbouwing. "Onbeslist" is geen geldige
                eindstand voor een audit.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="filterThema" label="Thema" class="max-w-56">
            <flux:select.option value="">Alle thema's</flux:select.option>
            @foreach ($themas as $thema)
                <flux:select.option value="{{ $thema }}">{{ ucfirst($thema) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterStatus" label="Implementatiestatus" class="max-w-56">
            <flux:select.option value="">Alle statussen</flux:select.option>
            <flux:select.option value="nvt">Niet van toepassing</flux:select.option>
            <flux:select.option value="niet_gestart">Niet gestart</flux:select.option>
            <flux:select.option value="in_uitvoering">In uitvoering</flux:select.option>
            <flux:select.option value="geimplementeerd">Geïmplementeerd</flux:select.option>
        </flux:select>

        <flux:switch wire:model.live="alleenOnbeslist" label="Alleen onbeslist" />
    </div>

    @forelse ($perThema as $thema => $maatregelen)
        <div>
            <flux:heading size="lg" class="mb-2">
                {{ ucfirst($thema) }}
                <flux:badge size="sm" color="zinc">{{ $maatregelen->count() }}</flux:badge>
            </flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Ref.</flux:table.column>
                    <flux:table.column>Naam</flux:table.column>
                    <flux:table.column>Van toepassing</flux:table.column>
                    <flux:table.column>Implementatiestatus</flux:table.column>
                    <flux:table.column>Restrisico</flux:table.column>
                    <flux:table.column>Beleid</flux:table.column>
                    <flux:table.column>Laatst beoordeeld</flux:table.column>
                    <flux:table.column align="end">Acties</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($maatregelen as $maatregel)
                        @php $regel = $maatregel->soaRegel; @endphp
                        <flux:table.row wire:key="maatregel-{{ $maatregel->id }}">
                            <flux:table.cell variant="strong">A.{{ $maatregel->annex_a_referentie }}</flux:table.cell>
                            <flux:table.cell>{{ $maatregel->naam }}</flux:table.cell>
                            <flux:table.cell>
                                @if (! $regel || $regel->isOnbeslist())
                                    {{-- Opvallende kleur: dit is het gap-signaal, geen neutrale stand. --}}
                                    <flux:badge size="sm" color="amber">Onbeslist</flux:badge>
                                @elseif ($regel->van_toepassing)
                                    <flux:badge size="sm" color="green">Ja</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">Nee</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $status = $regel?->implementatiestatus ?? 'niet_gestart';
                                    $statusKleur = match ($status) {
                                        'geimplementeerd' => 'green',
                                        'in_uitvoering' => 'blue',
                                        'nvt' => 'zinc',
                                        default => 'zinc',
                                    };
                                    // Eén bron voor scherm en schermkopie (12h §5).
                                    $statusLabel = $this->statusLabel($status);
                                @endphp
                                <flux:badge size="sm" :color="$statusKleur">{{ $statusLabel }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{-- Plan 04c: max netto-restrisico van de gekoppelde
                                     risico's, met dezelfde semafoordrempels als de
                                     risico's zelf. Leeg = geen risico gekoppeld;
                                     "onbepaald" = wel gekoppeld maar restrisico niet
                                     ingevuld (≠ 0). --}}
                                @php
                                    $aantalRisicos = $regel?->aantalGekoppeldeRisicos() ?? 0;
                                    $piek = $regel?->piekRestrisico();
                                @endphp
                                @if ($aantalRisicos === 0)
                                    <flux:text>—</flux:text>
                                @elseif ($piek === null)
                                    <flux:badge size="sm" color="zinc">onbepaald</flux:badge>
                                @else
                                    <flux:badge size="sm" :color="\App\Models\Risico::scoreKleur($piek)">
                                        {{ $piek }}
                                        <span style="opacity:.65">({{ $aantalRisicos }})</span>
                                    </flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{-- Blok 5: zonder deze kolom is de koppeling wel
                                     te leggen maar nergens terug te zien. --}}
                                @if ($regel?->mistBeleid())
                                    <flux:badge size="sm" color="amber">Geen beleid</flux:badge>
                                @elseif ($regel && $regel->beleidsdocumenten->isNotEmpty())
                                    @foreach ($regel->beleidsdocumenten as $document)
                                        <flux:badge size="sm" color="zinc" class="mr-1">
                                            {{ $document->titel }}
                                        </flux:badge>
                                    @endforeach
                                @else
                                    <flux:text>—</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $regel?->laatst_beoordeeld_op?->format('d-m-Y') ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($regel && $this->magMuteren())
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        wire:click="bewerk({{ $regel->id }})">
                                        Beoordelen
                                    </flux:button>
                                @else
                                    <flux:text>—</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <flux:text>Geen maatregelen gevonden met deze filters.</flux:text>
    @endforelse

    <flux:modal wire:model.self="toontFormulier" class="md:w-[36rem]">
        @if ($this->bewerkteRegel)
            <form wire:submit="opslaan" class="space-y-6">
                <div>
                    <flux:heading size="lg">
                        A.{{ $this->bewerkteRegel->maatregel->annex_a_referentie }}
                        {{ $this->bewerkteRegel->maatregel->naam }}
                    </flux:heading>
                    {{-- Twee toestanden sinds 04f, en dat is het hele blok: óf de
                         organisatie heeft hier de normtekst uit haar eigen
                         exemplaar ingevoerd, óf er staat niets en dan zeggen we
                         dát. Het rode voorbehoud dat hier tot 06-08-2026 stond
                         is vervallen met de meegeleverde omschrijvingen: wat de
                         CISO zelf invoert ís de normtekst, en daar hoort geen
                         voorbehoud bij. --}}
                    @if ($this->bewerkteRegel->maatregel->toontOmschrijving())
                        <flux:subheading>
                            {{ $this->bewerkteRegel->maatregel->omschrijving }}
                        </flux:subheading>
                    @else
                        <flux:text class="mt-1 text-sm">
                            {{ \App\Models\Maatregel::GEEN_OMSCHRIJVING_AANHEF }}
                            <a href="{{ route('kennisbank', \App\Models\Maatregel::DISCLAIMER_SLUG) }}"
                               wire:navigate class="underline">{{ \App\Models\Maatregel::DISCLAIMER_LABEL }}</a>.
                        </flux:text>
                    @endif

                    {{-- De zorgspecifieke aanvulling (plan 04e). Een eigen blok
                         met een eigen kop en niet aangeplakt aan de omschrijving:
                         bron, licentiestatus en voorbehoud verschillen, en dat
                         moet zichtbaar blijven. Verschijnt alleen in zorgmodus,
                         en niet bij maatregelen waarvan is ingelezen dát ze geen
                         aanvulling hebben. --}}
                    @if ($this->bewerkteRegel->maatregel->toontZorgaanvulling())
                        <div class="mt-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <flux:heading size="sm">Zorgspecifieke aanvulling ({{ $norm->naam_kort }})</flux:heading>
                            <flux:text class="mt-1 text-sm">
                                {{ $this->bewerkteRegel->maatregel->zorgaanvullingTekst() }}
                            </flux:text>
                        </div>
                    @endif

                    {{-- De effectieve classificatie (plan 04d): een eigen
                         vaststelling als die er is, anders het meegeleverde
                         uitgangspunt. De dimensies komen uit het schema, zodat
                         een uitgeschakelde dimensie hier vanzelf verdwijnt. --}}
                    @php $kenmerken = $this->bewerkteRegel->kenmerken(); @endphp
                    @if ($kenmerken)
                        <div class="mt-3 space-y-1.5">
                            @foreach ($kenmerkDimensies as $sleutel => $dimensie)
                                @if (! empty($kenmerken[$sleutel]))
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <flux:text class="w-28 shrink-0 text-xs" title="{{ $dimensie['herkomst'] }}">{{ $dimensie['label'] }}</flux:text>
                                        @foreach ($kenmerken[$sleutel] as $waarde)
                                            <flux:badge size="sm" color="zinc">{{ $waarde }}</flux:badge>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Het onderscheid dat de auditor zoekt: heeft de
                         organisatie hier zelf naar gekeken, en week ze af? Geen
                         badge voor "nog niet vastgesteld" — dat is de begintoestand
                         van elke regel en zegt in de kop van elk formulier
                         hetzelfde. Waar het wél toe doet, in de export en de
                         kennisbank, staat de herkomst voluit. --}}
                    @if ($this->bewerkteRegel->heeftEigenClassificatie())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @if ($this->bewerkteRegel->wijktAfVanUitgangspunt())
                                <flux:badge size="sm" color="amber">Afgeweken van uitgangspunt</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">Eigen vaststelling</flux:badge>
                            @endif
                        </div>
                    @endif
                </div>

                <flux:select wire:model.live="vanToepassing" label="Van toepassing">
                    <flux:select.option value="">Onbeslist</flux:select.option>
                    <flux:select.option value="1">Ja</flux:select.option>
                    <flux:select.option value="0">Nee</flux:select.option>
                </flux:select>

                <flux:textarea wire:model="motivatie" label="Motivatie"
                    description="Verplicht zodra er een beslissing ligt — zowel een 'ja' als een 'nee' moet onderbouwd zijn." />

                <flux:input wire:model="beleidreferentie" label="Beleidreferentie"
                    description="Korte verwijzing, bijv. een hoofdstuknummer in het beleid." />

                <flux:input wire:model="procesreferentie" label="Procesreferentie (werkwijze of checklist)" />

                <flux:select wire:model="implementatiestatus" label="Implementatiestatus">
                    <flux:select.option value="nvt">Niet van toepassing</flux:select.option>
                    <flux:select.option value="niet_gestart">Niet gestart</flux:select.option>
                    <flux:select.option value="in_uitvoering">In uitvoering</flux:select.option>
                    <flux:select.option value="geimplementeerd">Geïmplementeerd</flux:select.option>
                </flux:select>

                {{-- Eigen maatregelclassificatie (plan 04d fase 3). Voorgevuld
                     met de effectieve classificatie: bij een eerste bewerking is
                     dat het meegeleverde uitgangspunt. Voorvullen is geen
                     vaststellen — pas na opslaan staat hier iets van deze
                     organisatie. --}}
                <details class="group border-t border-zinc-200 pt-4 dark:border-zinc-700"
                    wire:ignore.self
                    @if ($errors->has('kenmerken') || $errors->has('kenmerken.*')) open @endif>
                    {{-- Eigen pijl in plaats van de standaardmarkering van
                         <summary>: die verdwijnt in Chrome zodra de regel een
                         flexbox is, en ziet er per browser anders uit. --}}
                    <summary class="flex cursor-pointer list-none items-center gap-2 [&::-webkit-details-marker]:hidden">
                        <flux:icon.chevron-right
                            class="size-4 shrink-0 text-zinc-500 transition-transform group-open:rotate-90 dark:text-zinc-400" />
                        <flux:heading size="sm">Classificatie</flux:heading>
                        @if ($this->bewerkteRegel->heeftEigenClassificatie())
                            <flux:badge size="sm" color="zinc">vastgesteld</flux:badge>
                        @endif
                    </summary>

                    <div class="mt-4 space-y-4">
                        <flux:subheading>
                            Hoe deze maatregel bij ons werkt. Opslaan legt de keuze vast als eigen
                            vaststelling, ook als je niets wijzigt. Zie de
                            <flux:link :href="route('kennisbank', 'maatregelclassificatie')" wire:navigate>kennisbank</flux:link>
                            voor het verschil met het uitgangspunt — en waarom er vier dimensies zijn.
                        </flux:subheading>

                        @foreach ($kenmerkDimensies as $sleutel => $dimensie)
                            <div>
                                <flux:checkbox.group :label="$dimensie['label']" :description="$dimensie['toelichting']"
                                    class="grid gap-1 md:grid-cols-2" wire:model="kenmerken.{{ $sleutel }}">
                                    @foreach ($kenmerkWaarden[$sleutel] as $waarde)
                                        <flux:checkbox :value="$waarde" :label="$waarde" />
                                    @endforeach
                                </flux:checkbox.group>

                                @error('kenmerken.'.$sleutel)
                                    <flux:text class="mt-1 text-sm text-red-600 dark:text-red-500">{{ $message }}</flux:text>
                                @enderror
                            </div>
                        @endforeach

                        @error('kenmerken')
                            <flux:text class="text-sm text-red-600 dark:text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>
                </details>

                <div class="flex justify-between gap-2">
                    <div>
                        @if ($this->bewerkteRegel->heeftEigenClassificatie())
                            <flux:button variant="subtle" type="button" icon="arrow-uturn-left"
                                wire:click="terugNaarUitgangspunt">
                                Terug naar uitgangspunt
                            </flux:button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                        <flux:button variant="primary" type="submit">Opslaan</flux:button>
                    </div>
                </div>
            </form>

            {{-- Bewijsstukken (blok 6). Buiten het formulier: het paneel heeft
                 zijn eigen upload-form en die mag niet genest raken. --}}
            <div class="mt-6">
                <livewire:bewijs-paneel blok-naam="risico-soa" entiteit-type="soa_regel"
                    :entiteit-id="$this->bewerkteRegel->id" :wire:key="'bewijs-soa-'.$this->bewerkteRegel->id" />
            </div>
        @endif
    </flux:modal>
</div>
