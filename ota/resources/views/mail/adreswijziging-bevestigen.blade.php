@component('mail::message')
# Bevestig uw nieuwe e-mailadres

Beste {{ $gebruiker->naam }},

De CISO heeft aangevraagd om het e-mailadres van uw account in het ISMS van
{{ config('app.name') }} te wijzigen in **{{ $nieuwEmail }}**.

Er verandert nog niets. Uw account werkt gewoon door op uw huidige adres totdat
u hieronder bevestigt dat dit bericht is aangekomen.

@component('mail::button', ['url' => $link])
Nieuw adres bevestigen
@endcomponent

Deze link is {{ $geldigheidDagen }} dagen geldig. Is hij verlopen, vraag de CISO
dan om de wijziging opnieuw aan te vragen.

Uw wachtwoord, uw tweede factor en uw lopende sessies veranderen hier niet door.

Heeft u hier niet om gevraagd? Neem dan contact op met de CISO en druk niet op de
knop.

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
