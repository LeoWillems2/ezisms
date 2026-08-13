@component('mail::message')
# Tweede factor instellen

Uw ISMS-account is nog niet beveiligd met een tweede factor. Dat is verplicht:
naast uw wachtwoord vraagt het systeem om een code van zes cijfers uit een
authenticator-app op uw telefoon.

@if ($dagenResterend > 0)
U heeft nog **{{ $dagenResterend }} {{ $dagenResterend === 1 ? 'dag' : 'dagen' }}**
(tot {{ $betrokkene->tweefactor_deadline->format('d-m-Y') }}). Daarna komt u niet
verder dan het instelscherm.
@else
**De termijn is verstreken.** U kunt pas weer verder werken zodra de tweede
factor is ingesteld. Dat doet u zelf; er is geen beheerder voor nodig.
@endif

Het instellen kost een minuut: u scant een QR-code met uw app en voert één keer
de code in die de app toont. Daarna krijgt u acht herstelcodes voor als u uw
telefoon niet bij de hand heeft — bewaar die ergens anders dan op die telefoon.

@component('mail::button', ['url' => route('settings.tweefactor')])
Nu instellen
@endcomponent

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
