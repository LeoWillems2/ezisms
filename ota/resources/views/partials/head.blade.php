<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name', 'EzISMS') }}</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

{{-- Barlow (body) en Barlow Condensed (koppen) staan in public/fonts en niet bij
     fonts.bunny.net of fonts.googleapis.com: een installatie zonder uitgaand
     verkeer moet hetzelfde lettertype tonen, en de app hoort bij elke
     paginaweergave geen externe host aan te roepen. Voorgeladen worden de twee
     snedes die op élke pagina staan: Barlow 400 voor de lopende tekst en Barlow
     Condensed 600 voor de koppen. --}}
<link rel="preload" href="{{ asset('fonts/barlow/barlow-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/barlow/barlow-condensed-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link href="{{ asset('fonts/barlow.css') }}" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Hier stond @fluxAppearance. Dat is de directive die de donkere modus
     werkelijk aanzette: hij plaatste een script dat vóór het schilderen `.dark`
     op <html> zet op grond van localStorage of de systeemvoorkeur. Zolang die
     directive er stond had het hardgezette `class="dark"` op <html> nauwelijks
     betekenis — het script haalde de klasse er net zo goed weer af, dus wie een
     licht bureaublad had zag de app al licht, na een donkere flits.

     Industry is een lichte band. In plaats van de directive staat hier de kale
     haak die Flux zelf aanbiedt: flux.min.js leest bij `alpine:init` één keer
     `window.Flux.applyAppearance` uit en roept die daarna aan bij elke wijziging
     van de weergavevoorkeur. Onze versie zet nooit een `.dark`, dus er komt
     nergens meer een donkere band op de pagina.

     Waarom niet gewoon niets? Zonder deze functie valt flux.min.js terug op een
     variant die `window.Flux.appearance` op null zet. De keuzeknoppen op
     /settings/appearance wissen zichzelf dan zichtbaar bij elke klik. Met deze
     haak blijft die pagina rustig staan; dat hij niets meer uitricht is een los
     op te lossen punt — de route zit in AutorisatieTest.

     `data-navigate-once` omdat wire:navigate scripts in de head opnieuw
     uitvoert: bij een tweede keer zou dit `window.Flux` overschrijven, en dat is
     na `alpine:init` het reactieve object waar toasts en modals aan hangen. --}}
<script data-navigate-once>
    window.Flux = {
        applyAppearance () { document.documentElement.classList.remove('dark') }
    }
</script>
