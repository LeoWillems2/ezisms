<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('afwijkingen.index')" wire:navigate>
            Terug naar afwijkingen
        </flux:button>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- 1. Kop. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $afwijking->auditOmschrijving() }}</flux:heading>
                <flux:subheading>
                    Bron: {{ str_replace('_', ' ', $afwijking->bron) }}
                    @if ($afwijking->incident)
                        &middot; uit incident &ldquo;{{ $afwijking->incident->titel }}&rdquo;
                    @endif
                </flux:subheading>
            </div>
            <flux:badge :color="$afwijking->isGesloten() ? 'green' : 'amber'">
                {{ ucfirst(str_replace('_', ' ', $afwijking->status)) }}
            </flux:badge>
        </div>

        <flux:text class="mt-3">{{ $afwijking->omschrijving }}</flux:text>

        @if ($afwijking->isGesloten())
            <flux:callout variant="success" icon="check-circle" class="mt-4"
                heading="Gesloten door {{ $afwijking->sluiter?->naam ?? 'onbekend' }} op {{ $afwijking->gesloten_op->lokaal()->format('d-m-Y H:i') }}" />
        @endif

        @if ($this->magMuteren())
            <flux:separator class="my-5" />

            <div class="flex flex-wrap items-end gap-4">
                <x-keuzelijst wire:model="eigenaarId" label="Eigenaar" class="max-w-72"
                    :opties="$gebruikers" leeg="— geen eigenaar —" />
                <flux:button wire:click="opslaanKop">Opslaan</flux:button>
            </div>

            <flux:separator class="my-5" />

            {{-- De reden waarom sluiten niet kan staat er bij, in plaats van
                 een grijze knop zonder uitleg (implementatie/08 §10). --}}
            @if ($belemmering === null)
                <flux:button variant="primary" wire:click="sluiten"
                    wire:confirm="U verklaart dat deze afwijking is weggenomen. Dat besluit wordt met uw naam vastgelegd.">
                    Afwijking sluiten
                </flux:button>
            @else
                <flux:callout icon="information-circle" heading="Sluiten kan nog niet">
                    <flux:callout.text>{{ $belemmering }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:error name="afsluiting" />
        @endif
    </div>

    {{-- 2. Grondoorzaken. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Grondoorzaken ({{ $grondoorzaken->count() }})</flux:heading>

        @forelse ($grondoorzaken as $oorzaak)
            <div class="border-t border-zinc-100 py-2 first:border-t-0 dark:border-zinc-800">
                <flux:text>{{ $oorzaak->omschrijving }}</flux:text>
                @if ($oorzaak->methodiek)
                    <flux:text class="text-sm">Methodiek: {{ $oorzaak->methodiek }}</flux:text>
                @endif
            </div>
        @empty
            <flux:text>Nog geen grondoorzaak vastgelegd. Zolang die er niet is, blijft een
                maatregel een gok.</flux:text>
        @endforelse

        @if ($this->magMuteren() && ! $afwijking->isGesloten())
            <flux:separator class="my-4" />
            <div class="grid gap-4 md:grid-cols-3">
                <flux:textarea wire:model="oorzaakOmschrijving" label="Grondoorzaak" class="md:col-span-2" />
                <flux:input wire:model="oorzaakMethodiek" label="Methodiek"
                    placeholder="bijv. 5x waarom" />
            </div>
            <flux:button class="mt-3" wire:click="voegOorzaakToe">Grondoorzaak toevoegen</flux:button>
        @endif
    </div>

    {{-- 3. Corrigerende maatregelen met hun toetsen. --}}
    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-4">Corrigerende maatregelen ({{ $maatregelen->count() }})</flux:heading>

        @forelse ($maatregelen as $maatregel)
            <div class="border-t border-zinc-100 py-3 first:border-t-0 dark:border-zinc-800"
                wire:key="maatregel-{{ $maatregel->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:text>{{ $maatregel->omschrijving }}</flux:text>
                        <flux:text class="text-sm">
                            {{ $maatregel->eigenaar?->naam ?? 'geen eigenaar' }}
                            @if ($maatregel->deadline)
                                &middot; deadline {{ $maatregel->deadline->format('d-m-Y') }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:badge size="sm" :color="$maatregel->status === 'voltooid' ? 'green' : 'zinc'">
                        {{ ucfirst(str_replace('_', ' ', $maatregel->status)) }}
                    </flux:badge>
                </div>

                @foreach ($maatregel->toetsen as $toets)
                    <div class="ms-4 mt-2 border-s-2 border-zinc-200 ps-3 dark:border-zinc-700">
                        <flux:badge size="sm" :color="$toets->resultaat === 'effectief' ? 'green' : 'red'">
                            {{ str_replace('_', ' ', $toets->resultaat) }}
                        </flux:badge>
                        <flux:text class="text-sm">
                            {{ $toets->uitgevoerd_op->format('d-m-Y') }} &middot;
                            {{ $toets->uitvoerder?->naam ?? 'onbekend' }}
                            @if ($toets->toelichting) &mdash; {{ $toets->toelichting }} @endif
                        </flux:text>
                    </div>
                @endforeach

                @if (! $afwijking->isGesloten() && ($this->magMuteren() || $maatregel->eigenaar_id === auth()->id()))
                    <div class="mt-2 flex gap-2">
                        @if ($maatregel->status !== 'in_uitvoering')
                            <flux:button size="sm" variant="ghost"
                                wire:click="werkMaatregelBij({{ $maatregel->id }}, 'in_uitvoering')">
                                In uitvoering
                            </flux:button>
                        @endif
                        @if ($maatregel->status !== 'voltooid')
                            <flux:button size="sm" variant="ghost"
                                wire:click="werkMaatregelBij({{ $maatregel->id }}, 'voltooid')">
                                Voltooid melden
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <flux:text>Nog geen maatregel. Zonder maatregel kan de afwijking niet gesloten worden.</flux:text>
        @endforelse

        @if ($this->magMuteren() && ! $afwijking->isGesloten())
            <flux:separator class="my-4" />
            <div class="grid gap-4 md:grid-cols-3">
                <flux:textarea wire:model="maatregelOmschrijving" label="Maatregel" class="md:col-span-3" />
                <x-keuzelijst wire:model="maatregelEigenaarId" label="Eigenaar" :opties="$maatregelGebruikers"
                    leeg="— geen eigenaar —" />
                <flux:input type="date" wire:model="maatregelDeadline" label="Deadline" />
            </div>
            <flux:button class="mt-3" wire:click="voegMaatregelToe">Maatregel toevoegen</flux:button>
        @endif
    </div>

    {{-- 4. Effectiviteitstoets vastleggen. --}}
    @if ($this->magMuteren() && $maatregelen->where('status', 'voltooid')->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-1">Effectiviteitstoets vastleggen</flux:heading>
            <flux:subheading class="mb-4">
                &sect;10.2 vraagt niet alleen om een maatregel, maar om de vaststelling dát hij werkt.
                Een toets met &ldquo;niet effectief&rdquo; zet de maatregel terug in uitvoering.
            </flux:subheading>

            <div class="grid gap-4 md:grid-cols-3">
                <x-keuzelijst wire:model="toetsMaatregelId" label="Maatregel"
                    leeg="— kies een maatregel —"
                    :opties="$maatregelen->where('status', 'voltooid')->mapWithKeys(fn ($m) => [$m->id => $m->auditOmschrijving()])->all()" />

                <x-keuzelijst wire:model="toetsResultaat" label="Resultaat"
                    :opties="['effectief' => 'Effectief', 'niet_effectief' => 'Niet effectief']" />

                <flux:input wire:model="toetsToelichting" label="Toelichting" />
            </div>

            <flux:error name="toetsMaatregelId" />

            <flux:button class="mt-3" variant="primary" wire:click="legToetsVast">Toets vastleggen</flux:button>
        </div>
    @endif

    {{-- 5. Bewijs (blok 6). --}}
    <livewire:bewijs-paneel blok-naam="incident-afwijkingenbeheer" entiteit-type="afwijking"
        :entiteit-id="$afwijking->id" />
</div>
