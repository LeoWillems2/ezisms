{{-- Licentievermelding, op elke pagina onderaan. Staat in één component zodat
     de tekst en de vier CC-iconen niet per layout uit elkaar gaan lopen.

     De iconen komen uit public/images/cc en niet van mirrors.creativecommons.org:
     een installatie zonder uitgaand verkeer moet de footer volledig tonen, en
     de app hoort geen externe host aan te roepen bij elke paginaweergave. --}}
<footer class="mt-10 border-t border-zinc-200 pt-4 text-center text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
    This work is licensed under <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" class="underline">CC BY-NC-SA 4.0</a>@foreach (['cc', 'by', 'nc', 'sa'] as $icoon)<img src="{{ asset('images/cc/'.$icoon.'.svg') }}" alt="" class="inline dark:invert" style="max-width: 1em;max-height:1em;margin-left: .2em;">@endforeach
</footer>
