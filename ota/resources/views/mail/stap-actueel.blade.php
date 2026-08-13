@component('mail::message')
# Een stap is aan de beurt

De vorige stap is afgerond, dus deze staat nu op uw naam open.

**{{ $stap->titel }}**

@if ($stap->gekoppeldOmschrijving())
Hoort bij: {{ $stap->gekoppeldOmschrijving() }}
@endif

Uiterlijk af te ronden op {{ $stap->deadline->format('d-m-Y') }}.

@component('mail::button', ['url' => route('taken.index')])
Mijn taken openen
@endcomponent

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
