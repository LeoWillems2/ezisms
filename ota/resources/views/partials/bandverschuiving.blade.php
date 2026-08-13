{{-- Wat er met het bestaande register gebeurt onder de voorgestelde drempels
     (implementatie/04g §2.6b).

     Dit is de vraag die een auditor bij een aangescherpte acceptatiedrempel
     stelt: niet "waar staat het", maar "wat heeft u toen gedaan met de risico's
     die daardoor onaanvaardbaar werden". Hem hier tonen is het enige moment
     waarop hij nog vóór het besluit te beantwoorden is. --}}
@if ($verschuiving && $verschuiving->heeftVerschuiving())
    @php
        $omhoog = $verschuiving->omhoog();
        $omlaag = $verschuiving->omlaag();
        $nieuwRood = $verschuiving->aantalNieuwBovenDrempel();
    @endphp

    <flux:callout :variant="$nieuwRood > 0 ? 'warning' : 'secondary'" icon="arrows-up-down"
        heading="Gevolgen voor het bestaande risicoregister">
        <flux:callout.text>
            @if ($omhoog->isNotEmpty())
                {{ $omhoog->count() }} risico('s) gaan zwaarder wegen@if ($nieuwRood > 0), waarvan {{ $nieuwRood }} boven de acceptatiedrempel uitkomen@endif.
                Die krijgen bij het activeren een herbeoordelingstaak bij hun eigenaar.
            @endif
            @if ($omlaag->isNotEmpty())
                {{ $omlaag->count() }} risico('s) gaan lichter wegen; daar volgt geen taak uit — dat is een besluit dat u hiermee neemt.
            @endif
        </flux:callout.text>

        @if ($omhoog->isNotEmpty())
            <ul class="mt-2 list-disc pl-5">
                @foreach ($omhoog->take(10) as $risico)
                    <li><flux:text>{{ $risico->referentie() }} — {{ $risico->titel }} (score {{ $risico->risicoscore }})</flux:text></li>
                @endforeach
                @if ($omhoog->count() > 10)
                    <li><flux:text>… en {{ $omhoog->count() - 10 }} meer</flux:text></li>
                @endif
            </ul>
        @endif
    </flux:callout>
@endif
