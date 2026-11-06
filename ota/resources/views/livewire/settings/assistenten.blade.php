<?php

use App\Support\Assistent\Koppelingen;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * De eigen gekoppelde assistenten (implementatie/14b §8.2). Bij de
 * instellingen en niet bij een blok: het zijn de eigen koppelingen, net als
 * het wachtwoord en de tweede factor. De route bestaat alleen als
 * ISMS_ASSISTENT aan staat.
 */
new #[Layout('components.layouts.app')] class extends Component {
    public function trekIn(string $clientId): void
    {
        $aantal = Koppelingen::trekIn(auth()->user(), $clientId, reden: 'door de gebruiker');

        session()->flash('melding', $aantal > 0 ? 'Koppeling ingetrokken.' : 'Er was niets meer in te trekken.');
    }

    public function with(): array
    {
        return [
            'koppelingen' => Koppelingen::van(auth()->user()),
            'magKoppelen' => Koppelingen::magKoppelen(auth()->user()),
            'mcpUrl' => url('/mcp'),
        ];
    }
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Assistenten" subheading="AI-assistenten die namens u in het ISMS mogen lezen">
        @if (session('melding'))
            <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" class="mb-4" />
        @endif

        @if ($magKoppelen)
            <flux:text class="text-sm">
                Een assistent zoals Claude Code of claude.ai koppelt u met dit adres:
            </flux:text>
            <div class="mt-1 mb-4"><code>{{ $mcpUrl }}</code></div>
            <flux:text class="mb-4 text-sm">
                Bij het koppelen logt u in en geeft u toestemming. De assistent kan daarna alles lezen wat u in het ISMS
                kunt zien, en niets wijzigen. Hoe het werkt en waar u op moet letten, staat in het kennisbankartikel
                <a href="{{ route('kennisbank', 'assistenttoegang') }}" class="underline" wire:navigate>Assistenttoegang</a>.
            </flux:text>
        @else
            <flux:callout icon="information-circle" class="mb-4">
                <flux:callout.text>
                    Uw rol mag geen assistent koppelen. Welke rollen dat mogen, bepaalt de CISO.
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:heading size="sm" class="mb-2">Gekoppeld</flux:heading>
        @forelse ($koppelingen as $client)
            <div class="flex items-center justify-between gap-4 py-1 text-sm" wire:key="client-{{ $client['client_id'] }}">
                <div>
                    <div class="font-medium">{{ $client['naam'] }}</div>
                    <div class="text-zinc-500">
                        {{ implode(', ', $client['hosts']) ?: 'onbekende host' }}
                        · gekoppeld {{ $client['gekoppeld_op']?->lokaal()->format('d-m-Y') }}
                        · laatst gebruikt {{ $client['laatst_gebruikt']?->lokaal()->format('d-m-Y H:i') ?? 'nog niet' }}
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="trekIn('{{ $client['client_id'] }}')"
                    wire:confirm="De koppeling met {{ $client['naam'] }} intrekken? De assistent moet daarna opnieuw toestemming vragen.">
                    Intrekken
                </flux:button>
            </div>
        @empty
            <flux:text class="text-sm">U hebt geen assistent gekoppeld.</flux:text>
        @endforelse
    </x-settings.layout>
</div>
