<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.risico-soa-subnav')

    <div>
        <flux:heading size="xl">Restrisico-trend</flux:heading>
        <flux:subheading>
            De ontwikkeling van het restrisico per control over de jaren (&sect;9.1-meting,
            &sect;10-verbetering). Read-only &mdash; de jaarsnapshots worden onveranderlijk
            vastgelegd en nooit herrekend.
        </flux:subheading>
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    <flux:callout icon="information-circle">
        <flux:callout.text>
            Per control het hoogste netto-restrisico van de gekoppelde risico's, per peiljaar.
            De actuele stand staat in de kolom <strong>Restrisico</strong> op de
            <flux:link :href="route('soa.index')" wire:navigate>SoA</flux:link>; hier zie je hoe
            die zich ontwikkelt. Achtergrond staat in de
            <flux:link :href="route('kennisbank', 'soa-onderbouwen-en-restrisico')" wire:navigate>kennisbank</flux:link>.
        </flux:callout.text>
    </flux:callout>

    {{-- Een restrisico van 12 betekent iets anders zodra de betekenis van
         impact 4 opnieuw is vastgesteld. De grafiek tekent daar vrolijk
         doorheen; deze melding zegt dat de vergelijking niet zuiver is. --}}
    @if ($criteriaversiesInBeeld->count() > 1)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>De peiljaren vallen onder verschillende risicocriteria</flux:callout.heading>
            <flux:callout.text>
                Deze reeks omvat de versies
                {{ $criteriaversiesInBeeld->map(fn ($v) => 'v'.$v->versienummer)->implode(', ') }}
                van de <flux:link :href="route('risicos.criteria')" wire:navigate>risicocriteria</flux:link>.
                De scores zijn daardoor niet zonder meer vergelijkbaar: is de betekenis van een
                impactniveau tussentijds opnieuw vastgesteld, dan meet een gelijk gebleven getal
                iets anders. Vermeld dat bij de duiding van de trend.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($aantalSnapshots === 0)
        <flux:callout variant="warning" icon="clock">
            <flux:callout.heading>Nog geen snapshots</flux:callout.heading>
            <flux:callout.text>
                Er is nog geen enkele jaarsnapshot vastgelegd &mdash; logisch aan het begin van de
                cyclus. De eerste ontstaat bij de jaarlijkse vastlegging (of handmatig via
                <code>php artisan isms:leg-restrisico-vast</code>). Een trend heeft minstens twee
                peiljaren nodig.
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-3">
            @foreach ($perControl as $rijen)
                @php($maatregel = $rijen->first()->soaRegel->maatregel)
                <div wire:key="control-{{ $rijen->first()->soa_regel_id }}"
                    class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">A.{{ $maatregel->annex_a_referentie }} {{ $maatregel->naam }}</flux:heading>

                    <flux:table class="mt-3">
                        <flux:table.columns>
                            <flux:table.column>Peiljaar</flux:table.column>
                            <flux:table.column>Restrisico</flux:table.column>
                            <flux:table.column>Risico's</flux:table.column>
                            <flux:table.column>Toelichting</flux:table.column>
                            @if ($this->magMuteren())
                                <flux:table.column align="end">Acties</flux:table.column>
                            @endif
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($rijen as $snapshot)
                                <flux:table.row wire:key="snapshot-{{ $snapshot->id }}">
                                    <flux:table.cell variant="strong">{{ $snapshot->peiljaar }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($snapshot->max_restrisico === null)
                                            <flux:badge size="sm" color="zinc">onbepaald</flux:badge>
                                        @else
                                            <flux:badge size="sm" :color="\App\Models\Risico::scoreKleur($snapshot->max_restrisico)">
                                                {{ $snapshot->max_restrisico }}
                                            </flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $snapshot->aantal_risicos }}</flux:table.cell>
                                    <flux:table.cell>
                                        {{ $snapshot->toelichting ?: '—' }}
                                    </flux:table.cell>
                                    @if ($this->magMuteren())
                                        <flux:table.cell align="end">
                                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                wire:click="bewerk({{ $snapshot->id }})">
                                                Toelichting
                                            </flux:button>
                                        </flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Alleen de toelichting is bewerkbaar; de cijfers blijven bevroren. --}}
    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">Toelichting bij de snapshot</flux:heading>
                <flux:subheading>
                    Leg de reden van de beweging vast (gemitigeerd, herscoord, risico afgevoerd).
                    Het restrisico en het aantal risico's zijn bevroren en niet te wijzigen.
                </flux:subheading>
            </div>

            <flux:textarea wire:model="toelichting" label="Toelichting" rows="4"
                placeholder="Bijv. R-7 gemitigeerd na invoering MFA." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
