@component('mail::message')
@if ($proef)
# Proefsignaal

Dit is een proefbericht van `isms:beveiligingssignaal-proef`. Er is niets
gebeurd: het controleert alleen dat signalen van de beveiligingsbewaking u
bereiken.
@else
# Beveiligingssignaal

**{{ \App\Models\Beveiligingssignaal::SOORTEN[$signaal->soort] }}**

- Installatie: {{ \App\Support\Signaalkanaal::installatie() }}
- Tijdstip: {{ $signaal->tijdstip->lokaal()->format('d-m-Y H:i:s') }}
@if ($signaal->gebruiker || isset($signaal->details['account']))
- Account: {{ $signaal->gebruiker?->email ?? $signaal->details['account'] }}
@endif
@if ($signaal->ip_adres)
- IP-adres: {{ $signaal->ip_adres }}
@endif
@foreach ($signaal->details as $sleutel => $waarde)
@continue($sleutel === 'account')
- {{ str_replace('_', ' ', ucfirst($sleutel)) }}: {{ is_array($waarde) ? collect($waarde)->map(fn ($v, $k) => is_int($k) ? $v : "$k ($v)")->implode(', ') : $waarde }}
@endforeach

Dit bericht komt van de bewaking op misbruik van inloggegevens. Beoordeel of er
actie nodig is, bijvoorbeeld het account blokkeren of een incident registreren.
Het signaal staat als nummer {{ $signaal->id }} in de audit trail.
@endif

@component('mail::button', ['url' => route('audit-log.index', ['filterEntiteitType' => 'beveiligingssignaal'])])
Audit trail openen
@endcomponent

U ontvangt dit bericht omdat u de rol CISO heeft. Er is geen syslogserver
ingesteld; daarom gaat het signaal per mail.

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
