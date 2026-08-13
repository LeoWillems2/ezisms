<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Management review</flux:heading>
            <flux:subheading>
                De directiebeoordeling (§9.3) met de verplichte agenda, besluiten en verbeteracties (§10.2).
            </flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuweReview">Nieuwe review plannen</flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if ($reviewAchterstallig)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="Er is niet recent een review gehouden">
            <flux:callout.text>
                @if ($laatsteGehouden)
                    De laatste gehouden review was op {{ $laatsteGehouden->datum->format('d-m-Y') }}, langer dan een jaar geleden.
                @else
                    Er is nog geen enkele review als "gehouden" vastgelegd.
                @endif
                §9.3 verlangt een review op geplande tijdstippen.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Open verbeteracties</flux:text>
            <flux:heading size="lg">{{ $openVerbeteracties }}</flux:heading>
            @if ($verstrekenVerbeteracties > 0)
                <flux:badge size="sm" color="red">{{ $verstrekenVerbeteracties }} over de deadline</flux:badge>
            @endif
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Laatste gehouden review</flux:text>
            <flux:heading size="lg">{{ $laatsteGehouden?->datum->format('d-m-Y') ?? 'nog geen' }}</flux:heading>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Datum</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Agenda (§9.3)</flux:table.column>
            <flux:table.column>Besluiten</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($sessies as $sessie)
                <flux:table.row wire:key="sessie-{{ $sessie->id }}">
                    <flux:table.cell variant="strong">{{ $sessie->datum->format('d-m-Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$sessie->status === 'gehouden' ? 'green' : 'zinc'">
                            {{ ucfirst($sessie->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$sessie->agendapunten_count === 9 ? 'green' : 'amber'">
                            {{ $sessie->agendapunten_count }} / 9
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $sessie->besluiten_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="arrow-right"
                            :href="route('management-review.detail', $sessie)" wire:navigate>Openen</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5"><flux:text>Nog geen reviews gepland.</flux:text></flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[30rem]">
        <form wire:submit="slaOp" class="space-y-6">
            <flux:heading size="lg">Nieuwe review plannen</flux:heading>
            <flux:input wire:model="datum" type="date" label="Datum" required />
            <flux:textarea wire:model="deelnemers" label="Deelnemers"
                description="Vrije tekst — wie namens de directie en het ISMS aanwezig is." />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontFormulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Plannen &amp; openen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
