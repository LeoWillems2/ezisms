<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    //
}; ?>

<div class="flex flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout heading="Weergave" subheading="Kies hoe de applicatie eruitziet">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">Licht</flux:radio>
            <flux:radio value="dark" icon="moon">Donker</flux:radio>
            <flux:radio value="system" icon="computer-desktop">Systeem</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</div>
