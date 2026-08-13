@component('mail::message')
# Wijziging van uw e-mailadres aangevraagd

Beste {{ $gebruiker->naam }},

Er is aangevraagd om het e-mailadres van uw account in het ISMS van
{{ config('app.name') }} te wijzigen in **{{ $gemaskeerd }}**.

Aangevraagd door: **{{ $aangevraagdDoor }}**.

Er is nog niets veranderd. De wijziging gaat pas in als op het nieuwe adres wordt
bevestigd, en de aanvraag vervalt vanzelf na {{ $geldigheidDagen }} dagen.

**Heeft u hier niet om gevraagd?** Neem dan meteen contact op met de CISO. Zolang
u dit bericht ontvangt werkt uw huidige adres nog; de aanvraag kan worden
ingetrokken en uw account kan zo nodig worden geblokkeerd.

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
