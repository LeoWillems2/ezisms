{{--
    De knop "Kopie voor de auditor" (implementatie/12h §10).

    Eén regel per scherm, naast de schermtitel:

        @include('partials.kopieknop')

    Bewust een partial en geen los component: een partial erft de scope van het
    scherm, zodat er niets doorgegeven hoeft te worden en de aanroep overal
    hetzelfde is. Dezelfde keuze als bij de subnavigaties hiernaast.

    Het scherm moet `App\Livewire\Concerns\LevertSchermkopie` gebruiken — daar
    zit de autorisatiecheck, de vastlegging en de melding als pandoc ontbreekt.
--}}
@if ($this->magKopieren())
    <div class="flex flex-col items-end gap-2">
        <flux:button
            wire:click="kopieerVoorAuditor"
            wire:loading.attr="disabled"
            icon="arrow-down-tray"
            variant="ghost"
            size="sm"
        >
            Kopie voor de auditor
        </flux:button>

        @if ($this->kopiefout !== null)
            <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ $this->kopiefout }}" />
        @endif
    </div>
@endif
