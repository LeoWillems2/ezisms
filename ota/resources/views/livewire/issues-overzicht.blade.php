<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.context-subnav')

    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Issues</flux:heading>
            <flux:subheading>Interne en externe issues die de context van de organisatie bepalen ({{ $norm->naam_kort }} §4.1).</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="nieuwIssue">
                Issue toevoegen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    {{-- Dekkingssignaal (plan 02b §6): een §4.1-kwestie die nergens in de
         risicobeoordeling landt is óf niet relevant genoeg om te registreren, óf
         een gat. Het signaal stelt die vraag; het dwingt geen koppeling af. --}}
    @if ($zonderRisico)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $zonderRisico }} van de {{ $issues->count() }} issues zijn niet doorvertaald naar een risico.">
            Loop ze langs bij de contextherziening: niet relevant genoeg om te registreren,
            of een gat in de risicobeoordeling? Koppelen doe je op het risico zelf.
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Aard</flux:table.column>
            <flux:table.column>Categorie</flux:table.column>
            <flux:table.column>Omschrijving</flux:table.column>
            <flux:table.column>Laatst beoordeeld</flux:table.column>
            @if ($this->magRisicosZien())
                <flux:table.column>Risico's</flux:table.column>
            @endif
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($issues as $issue)
                <flux:table.row wire:key="issue-{{ $issue->id }}">
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$issue->aard === 'intern' ? 'sky' : 'amber'">
                            {{ ucfirst($issue->aard) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell variant="strong">{{ $issue->categorie }}</flux:table.cell>
                    <flux:table.cell>{{ $issue->omschrijving }}</flux:table.cell>
                    <flux:table.cell>{{ $issue->laatst_beoordeeld_op?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                    @if ($this->magRisicosZien())
                        <flux:table.cell>
                            @if ($issue->risicos_count > 0)
                                <flux:link :href="route('risicos.index', ['filterIssue' => $issue->id])" wire:navigate>
                                    {{ $issue->risicos_count }}
                                </flux:link>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </flux:table.cell>
                    @endif
                    <flux:table.cell align="end">
                        @if ($this->magMuteren())
                            <flux:button size="sm" variant="ghost" wire:click="bewerk({{ $issue->id }})">Bewerken</flux:button>
                            <flux:button size="sm" variant="ghost" icon="trash"
                                wire:click="verwijderen({{ $issue->id }})"
                                wire:confirm="Dit issue verwijderen?" />
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="{{ $this->magRisicosZien() ? 6 : 5 }}">
                        <flux:text>Nog geen issues.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontFormulier" class="md:w-[32rem]">
        <form wire:submit="opslaan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $bewerktId ? 'Issue bewerken' : 'Issue toevoegen' }}</flux:heading>
            </div>

            <flux:select wire:model="aard" label="Aard" required>
                <flux:select.option value="intern">Intern</flux:select.option>
                <flux:select.option value="extern">Extern</flux:select.option>
            </flux:select>

            <flux:input wire:model="categorie" label="Categorie"
                description="Bijv. juridisch, technologisch, markt, organisatorisch." required />

            <flux:textarea wire:model="omschrijving" label="Omschrijving" required />

            <flux:input wire:model="laatstBeoordeeldOp" type="date" label="Laatst beoordeeld op" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitFormulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
