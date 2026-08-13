@component('mail::message')
# Taak geëscaleerd

Een taak staat langer dan de toegestane termijn open en is geëscaleerd naar niveau 2.

**{{ $taak->titel }}**

- Deadline: {{ $taak->deadline?->format('d-m-Y') ?? 'onbekend' }}
- Status: {{ ucfirst(str_replace('_', ' ', $taak->status)) }}

@component('mail::button', ['url' => route('taken.index')])
Taken openen
@endcomponent

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
