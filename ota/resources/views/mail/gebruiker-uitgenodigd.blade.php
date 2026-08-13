@component('mail::message')
# Welkom bij het ISMS

Beste {{ $gebruiker->naam }},

Er is voor u een account aangemaakt in het ISMS van {{ config('app.name') }}.
Stel via onderstaande knop uw wachtwoord in om het account te activeren.

@component('mail::button', ['url' => $link])
Wachtwoord instellen
@endcomponent

Deze link is {{ $geldigheidDagen }} dagen geldig. Is de link verlopen, vraag
dan de CISO om een nieuwe uitnodiging te versturen.

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
