<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name', 'EzISMS') }}</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

{{-- Instrument Sans staat in public/fonts en niet bij fonts.bunny.net: een
     installatie zonder uitgaand verkeer moet hetzelfde lettertype tonen, en de
     app hoort bij elke paginaweergave geen externe host aan te roepen. --}}
<link rel="preload" href="{{ asset('fonts/instrument-sans/instrument-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link href="{{ asset('fonts/instrument-sans.css') }}" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
