@component('mail::message')
# Nieuw incident gemeld

**{{ $incident->titel }}**

- Ernst: {{ $incident->ernst }}
- Gemeld door: {{ $incident->melder?->naam ?? 'onbekend' }}
- Gemeld op: {{ $incident->gemeld_op->lokaal()->format('d-m-Y H:i') }}

@if ($incident->omschrijving)
{{ $incident->omschrijving }}
@endif

@component('mail::button', ['url' => route('incidenten.detail', $incident)])
Incident openen
@endcomponent

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
