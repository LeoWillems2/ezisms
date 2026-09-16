@component('mail::message')
# Koppel uw ISMS-account

Beste {{ $gebruiker->naam }},

Uw account in het ISMS van {{ config('app.name') }} logt voortaan in via
{{ $idpNaam }}. Open onderstaande link en meld u aan met uw {{ $idpNaam }}-account
om de koppeling te leggen. Tot dat moment kunt u niet inloggen.

@component('mail::button', ['url' => $link])
Account koppelen
@endcomponent

Deze link is {{ $geldigheidDagen }} dagen geldig en werkt één keer. Is de link
verlopen, vraag dan de CISO om een nieuwe.

Heeft u deze wijziging niet verwacht? Neem dan contact op met de CISO.

Met vriendelijke groet,<br>
{{ config('app.name') }}
@endcomponent
