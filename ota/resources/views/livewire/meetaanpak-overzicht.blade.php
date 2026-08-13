@php
    // Één plek voor "hoe schrijf je zo'n waarde op": de eenheid bepaalt het achtervoegsel.
    $metEenheid = fn ($waarde, $eenheid) => $waarde === null ? null : match ($eenheid) {
        'dagen' => $waarde.' dagen',
        'aantal' => (string) (int) $waarde,
        default => $waarde.'%',
    };
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">KPI's</flux:heading>
            <flux:subheading>
                De meetaanpak ({{ $norm->naam_kort }} §9.1): wát het ISMS meet om PDCA-voortgang aan te tonen,
                waartegen het beoordeeld wordt, en de tot nu toe vastgelegde meetpunten.
            </flux:subheading>
        </div>
        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweKpi">Nieuwe KPI</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <flux:callout icon="information-circle">
        <flux:callout.text>
            Dit is de meetaanpak zelf: per KPI de fase, de berekeningswijze, de streefwaarde en de
            definitieversie. Metingen zijn <strong>onveranderlijk</strong> (teller en noemer, nooit
            het percentage) en worden maandelijks vastgelegd. De catalogus is wél te beheren: een
            gewijzigde meetbron of richting hoogt de definitieversie op, zodat een breuk in de reeks
            zichtbaar blijft. Achtergrond en de twee valkuilen staan in de
            <flux:link :href="route('kennisbank', 'kpis-en-meetwaarden')" wire:navigate>kennisbank</flux:link>.
        </flux:callout.text>
    </flux:callout>

    @if ($aantalMetingen === 0)
        <flux:callout variant="warning" icon="clock">
            <flux:callout.heading>Nog geen meetpunten</flux:callout.heading>
            <flux:callout.text>
                Er is nog niets gemeten — logisch aan het begin van de cyclus. De catalogus hieronder
                laat wel zien wat er gemeten wórdt en hoe. Het eerste meetpunt ontstaat bij de eerstvolgende
                maandelijkse meting.
            </flux:callout.text>
        </flux:callout>
    @endif

    @foreach ($perFase as $fase => $definities)
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ $faseLabels[$fase] }}</flux:heading>
                <flux:badge size="sm" color="zinc">{{ $definities->count() }} KPI('s)</flux:badge>
            </div>

            <div class="grid gap-3">
                @foreach ($definities as $definitie)
                    <div wire:key="kpi-{{ $definitie->id }}"
                        class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 {{ $definitie->actief ? '' : 'opacity-60' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <flux:heading size="sm">{{ $definitie->naam }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500">{{ $definitie->berekeningswijze }}</flux:text>
                                <flux:text class="mt-1 text-xs text-zinc-400">Sleutel: <code>{{ $definitie->sleutel }}</code></flux:text>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-1">
                                @unless ($definitie->actief)
                                    <flux:badge size="sm" color="zinc">Inactief</flux:badge>
                                @endunless
                                @if ($definitie->isHandmatig())
                                    <flux:badge size="sm" color="amber">Handmatig</flux:badge>
                                @endif
                                <flux:badge size="sm" color="zinc">{{ $definitie->eenheid }}</flux:badge>
                                <flux:badge size="sm" color="zinc">
                                    {{ $definitie->richting === 'omlaag' ? 'lager is beter' : 'hoger is beter' }}
                                </flux:badge>
                                <flux:badge size="sm" color="zinc">Definitie v{{ $definitie->definitie_versie }}</flux:badge>
                            </div>
                        </div>

                        <flux:text class="mt-2 text-sm text-zinc-500">
                            @if ($definitie->streefwaarde === null)
                                Geen streefwaarde vastgesteld.
                            @else
                                Streefwaarde {{ $metEenheid($definitie->streefwaarde, $definitie->eenheid) }}@if ($definitie->signaalwaarde !== null), signaalwaarde {{ $metEenheid($definitie->signaalwaarde, $definitie->eenheid) }}@endif.
                                @if ($definitie->streefwaardeIsVastgesteld())
                                    <span class="text-zinc-400">Vastgesteld op {{ $definitie->streefwaarde_vastgesteld_op->format('d-m-Y') }}.</span>
                                @else
                                    <flux:badge size="sm" color="amber">Voorstel — niet vastgesteld</flux:badge>
                                @endif
                            @endif
                        </flux:text>

                        @unless ($definitie->streefwaarde === null || $definitie->streefwaardeIsVastgesteld())
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                Deze streefwaarde is met het product meegeleverd als suggestie. Zolang uw
                                organisatie haar niet heeft vastgesteld telt ze nergens mee: de KPI
                                blijft op <em>geen streefwaarde vastgesteld</em> staan en kleurt niet.
                            </flux:text>
                        @endunless

                        @if ($this->magMuteren())
                            <div class="mt-3 flex flex-wrap gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    wire:click="bewerk({{ $definitie->id }})">Bewerken</flux:button>
                                @if ($definitie->isHandmatig())
                                    <flux:button size="sm" variant="ghost" icon="plus"
                                        wire:click="nieuwMeetpunt({{ $definitie->id }})">Meetpunt invoeren</flux:button>
                                @endif
                                @unless ($definitie->streefwaarde === null || $definitie->streefwaardeIsVastgesteld())
                                    <flux:button size="sm" variant="filled" icon="check"
                                        wire:click="bevestigStreefwaarde({{ $definitie->id }})">Streefwaarde vaststellen</flux:button>
                                @endunless
                                <flux:button size="sm" variant="ghost"
                                    wire:click="zetActief({{ $definitie->id }}, {{ $definitie->actief ? 'false' : 'true' }})">
                                    {{ $definitie->actief ? 'Op inactief zetten' : 'Weer activeren' }}
                                </flux:button>
                                @if ($definitie->magVerwijderdWorden())
                                    <flux:button size="sm" variant="ghost" icon="trash"
                                        wire:click="verwijder({{ $definitie->id }})"
                                        wire:confirm="Deze KPI verwijderen? Er is nog niets mee gemeten.">Verwijderen</flux:button>
                                @endif
                            </div>
                        @endif

                        @if ($definitie->metingen->isEmpty())
                            <flux:text class="mt-3 text-sm text-zinc-400">
                                Nog geen meetpunten vastgelegd.
                                @if ($definitie->isHandmatig())
                                    Deze KPI wordt niet automatisch gemeten — de meetpunten komen met de hand.
                                @endif
                            </flux:text>
                        @else
                            {{-- Alleen het jongste meetpunt staat open; de historie
                                 zit eronder. Een KPI met twee jaar maandmetingen duwde
                                 anders elke volgende KPI van het scherm af.

                                 Alpine en niet het <details>-patroon van de audit trail:
                                 <details> mag geen <tr> omvatten, en twee tabellen naast
                                 elkaar krijgen ongelijke kolombreedtes. --}}
                            <div class="mt-3" x-data="{ open: false }">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>Gemeten op</flux:table.column>
                                    <flux:table.column>Meetpunt</flux:table.column>
                                    <flux:table.column>Uitkomst</flux:table.column>
                                    <flux:table.column>Streefwaarde toen</flux:table.column>
                                    <flux:table.column>Vastgelegd door</flux:table.column>
                                    <flux:table.column>Definitieversie</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($definitie->metingen as $meting)
                                        {{-- `style` staat inline op verborgen, zodat de historie
                                             niet even oplicht voordat Alpine start. --}}
                                        <flux:table.row wire:key="meting-{{ $meting->id }}"
                                            x-show="{{ $loop->first ? 'true' : 'open' }}"
                                            style="{{ $loop->first ? '' : 'display: none' }}">
                                            <flux:table.cell>{{ $meting->gemeten_op->format('d-m-Y') }}</flux:table.cell>
                                            <flux:table.cell>{{ $meting->teller }} van {{ $meting->noemer }}</flux:table.cell>
                                            <flux:table.cell variant="strong">
                                                @if ($definitie->eenheid === 'aantal')
                                                    {{ $meting->teller }}
                                                @elseif ($definitie->eenheid === 'dagen')
                                                    {{ $meting->gemiddelde() !== null ? $meting->gemiddelde().' dagen (gem.)' : '—' }}
                                                @else
                                                    {{ $meting->percentage() !== null ? $meting->percentage().'%' : '—' }}
                                                @endif
                                            </flux:table.cell>
                                            {{-- De streefwaarde die bij dít meetpunt hoorde, niet de huidige (12d §2b). --}}
                                            <flux:table.cell>{{ $metEenheid($meting->streefwaarde, $definitie->eenheid) ?? '—' }}</flux:table.cell>
                                            <flux:table.cell>
                                                {{ $meting->herkomst() }}
                                                @if (filled($meting->toelichting))
                                                    <flux:text class="text-xs text-zinc-400">{{ $meting->toelichting }}</flux:text>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>v{{ $meting->definitie_versie }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>

                            @if ($definitie->metingen->count() > 1)
                                @php $ouder = $definitie->metingen->count() - 1; @endphp
                                <button type="button" x-on:click="open = ! open"
                                    :aria-expanded="open ? 'true' : 'false'"
                                    class="mt-2 flex cursor-pointer items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                                    <span class="transition-transform" :class="open && 'rotate-90'"
                                        aria-hidden="true">&#9656;</span>
                                    <span x-show="! open">
                                        Toon {{ $ouder }} eerder{{ $ouder === 1 ? ' meetpunt' : 'e meetpunten' }}
                                    </span>
                                    <span x-show="open" style="display: none">Verberg de historie</span>
                                </button>
                            @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Het formulier wordt niet meegestuurd aan wie het niet mag gebruiken; de
         acties zelf checken serverside nog eens (12e §6). --}}
    @if ($this->magMuteren())
    <flux:modal wire:model.self="toontFormulier" class="max-w-xl">
        <form wire:submit="opslaan" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $bewerktId ? 'KPI bewerken' : 'Nieuwe KPI' }}</flux:heading>

            <flux:input wire:model="naam" label="Naam" />

            <flux:select wire:model.live="meetbron" label="Meetbron">
                <flux:select.option value="">Handmatig — ik voer teller en noemer zelf in</flux:select.option>
                @foreach ($meetbronnen as $sleutel => $label)
                    <flux:select.option value="{{ $sleutel }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:text class="-mt-2 text-sm text-zinc-500">
                Dit zijn de metingen die de applicatie zelf kan uitrekenen. Meet u iets buiten het
                ISMS — een phishingsimulatie, een servicedesk — kies dan <em>handmatig</em>. Een
                nieuwe berekening toevoegen vraagt een aanpassing in de applicatie.
            </flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="fase" label="PDCA-fase">
                    @foreach ($faseLabels as $waarde => $label)
                        <flux:select.option value="{{ $waarde }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="eenheid" label="Eenheid" :disabled="$eenheidVast">
                    <flux:select.option value="ratio">Ratio (percentage)</flux:select.option>
                    <flux:select.option value="dagen">Dagen (gemiddelde)</flux:select.option>
                    <flux:select.option value="aantal">Aantal (telling)</flux:select.option>
                </flux:select>
            </div>

            @if ($eenheidVast)
                <flux:text class="-mt-2 text-sm text-zinc-500">
                    De eenheid ligt vast zodra er een meetpunt bestaat: hij bepaalt wat teller en
                    noemer betekenen. Wilt u iets anders meten, maak dan een nieuwe KPI aan.
                </flux:text>
            @endif

            <flux:select wire:model="richting" label="Welke kant op is goed?">
                <flux:select.option value="omhoog">Omhoog — hoger is beter</flux:select.option>
                <flux:select.option value="omlaag">Omlaag — lager is beter</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="berekeningswijze" label="Berekeningswijze" rows="3" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="streefwaarde" label="Streefwaarde (optioneel)" />
                <flux:input wire:model="signaalwaarde" label="Signaalwaarde (optioneel)" />
            </div>
            <flux:text class="-mt-2 text-sm text-zinc-500">
                Zonder streefwaarde krijgt de KPI de status <em>geen streefwaarde vastgesteld</em> — nooit
                groen. Laat het veld leeg als de waarde nog niet bestuurlijk is vastgesteld; een
                verzonnen streefwaarde wordt bij de eerste audit als beleid gelezen.
            </flux:text>

            <flux:checkbox wire:model="actief" label="Actief — deze KPI wordt maandelijks gemeten" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- De vraag die de applicatie niet zelf kan beantwoorden (12f §3). --}}
    <flux:modal wire:model.self="toontMethodevraag" class="max-w-lg">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Is de meetmethode veranderd?</flux:heading>
                <flux:subheading>{{ $naam }}</flux:subheading>
            </div>

            <flux:text>
                U wijzigt de berekeningswijze van een KPI die u zelf meet. Bij zo'n KPI staat de
                methode nergens anders dan in die tekst — de applicatie kan dus niet zien of u iets
                verduidelijkt of dat u voortaan iets ánders meet.
            </flux:text>

            <ul class="list-disc space-y-1 pl-5 text-sm text-zinc-500">
                <li><strong>Meet u het anders</strong> — andere bron, andere populatie, andere
                    telling — dan zijn de punten hiervóór en hierná niet vergelijkbaar. De
                    definitieversie gaat omhoog en de reeks toont die breuk.</li>
                <li><strong>Verduidelijkt u alleen de formulering</strong>, dan blijft de reeks
                    ononderbroken. Die keuze wordt wel vastgelegd in de audit trail.</li>
            </ul>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button variant="ghost" wire:click="sluitMethodevraag">Annuleren</flux:button>
                <flux:button variant="ghost" wire:click="beantwoordMethodevraag(false)">
                    Alleen de formulering
                </flux:button>
                {{-- Voorop en primair: wie doorklikt zonder te lezen krijgt de
                     conservatieve uitkomst. Een breuk ten onrechte melden kost een
                     saai antwoord; hem ten onrechte verzwijgen laat twee
                     onvergelijkbare perioden als één trend lezen. --}}
                <flux:button variant="primary" wire:click="beantwoordMethodevraag(true)">
                    Ja, de methode is veranderd
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="toontStreefwaardeBevestiging" class="max-w-lg">
        @if ($streefwaardeKpi)
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">Streefwaarde vaststellen?</flux:heading>
                    <flux:subheading>{{ $streefwaardeKpi->naam }}</flux:subheading>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text>
                        Streefwaarde <strong>{{ $metEenheid($streefwaardeKpi->streefwaarde, $streefwaardeKpi->eenheid) }}</strong>@if ($streefwaardeKpi->signaalwaarde !== null),
                        signaalwaarde <strong>{{ $metEenheid($streefwaardeKpi->signaalwaarde, $streefwaardeKpi->eenheid) }}</strong>@endif.
                        {{ $streefwaardeKpi->richting === 'omlaag' ? 'Lager is beter.' : 'Hoger is beter.' }}
                    </flux:text>
                </div>

                <flux:text>
                    Hiermee maakt u van een meegeleverd voorstel de streefwaarde van uw eigen
                    organisatie. Een auditor leest haar als vastgesteld beleid en kan vragen wie
                    haar heeft vastgesteld; die vastlegging gebeurt automatisch in de audit trail.
                </flux:text>

                <ul class="list-disc space-y-1 pl-5 text-sm text-zinc-500">
                    <li>Vanaf het <strong>eerstvolgende</strong> meetpunt krijgt deze KPI een
                        oordeel: streefwaarde gehaald, niet gehaald, of voorbij de signaalwaarde.</li>
                    <li>Bestaande meetpunten blijven <em>onbepaald</em> — die zijn gemeten toen er
                        nog geen streefwaarde was, en dat verandert niet met terugwerkende kracht.</li>
                    <li>Bijstellen kan later; het veld leegmaken trekt de vaststelling weer in.</li>
                </ul>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="sluitStreefwaardeBevestiging">Annuleren</flux:button>
                    <flux:button variant="primary" icon="check"
                        wire:click="stelStreefwaardeVast({{ $streefwaardeKpi->id }})">Streefwaarde vaststellen</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.self="toontMeting" class="max-w-lg">
        <form wire:submit="slaMeetpuntOp" class="flex flex-col gap-4">
            <flux:heading size="lg">Meetpunt invoeren</flux:heading>

            <flux:callout icon="lock-closed">
                <flux:callout.text>
                    Een meetpunt is <strong>onveranderlijk</strong>: het is niet te wijzigen en niet
                    te verwijderen. Klopt er later iets niet, dan legt u een nieuw meetpunt vast met
                    een toelichting die zegt wat er mis was. Eén meetpunt per maand.
                </flux:callout.text>
            </flux:callout>

            <flux:input type="date" wire:model="gemetenOp" label="Gemeten op" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" wire:model="teller" label="Teller" min="0" />
                <flux:input type="number" wire:model="noemer" label="Noemer" min="1" />
            </div>
            <flux:text class="-mt-2 text-sm text-zinc-500">
                Teller en noemer, nooit het percentage: "61 van 90" is te reconstrueren en te
                verantwoorden, "68%" niet — en de noemer beweegt mee.
            </flux:text>

            <flux:textarea wire:model="meettoelichting" label="Toelichting (optioneel)" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitMeting">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Vastleggen</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
