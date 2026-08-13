@component('mail::message')
# Training afronden

Een verplichte training staat voor u open of verloopt binnenkort.

**{{ $module->titel }}**

Rond de training op tijd af om aan de bewustzijnsverplichting (A.6.3) te voldoen.

@component('mail::button', ['url' => route('mijn-trainingen.index')])
Mijn trainingen openen
@endcomponent

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
