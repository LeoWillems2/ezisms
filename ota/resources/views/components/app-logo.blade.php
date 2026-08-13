{{-- Sidebar-/headerlogo: het beeldmerk zelf is al kleurrijk, dus geen
     accent-gekleurd kader eromheen zoals de starter kit had. --}}
<img src="{{ asset('images/isms-logo.png') }}" alt="{{ config('app.name', 'EzISMS') }}"
    class="size-8 shrink-0 object-contain" />
{{-- `gap-1.5` in plaats van een marge op de bovenste regel: het grid regelt de
     tussenruimte, zodat de organisatienaam los komt te staan van de
     productnaam en niet als bijschrift leest. --}}
<div class="ml-2 grid flex-1 gap-1.5 text-left text-sm">
    {{-- Versie achter de naam, op dezelfde regel en een maat kleiner: het is
         een bijschrift bij het product en geen tweede naam. `items-baseline`
         zet de twee op één schriftlijn, anders hangt het kleinere nummer aan de
         bovenkant. Leeg ⇒ de span blijft weg (zie config/app.php). --}}
    <span class="flex items-baseline gap-1.5 truncate leading-none">
        <span class="font-semibold">{{ config('app.name', 'EzISMS') }}</span>
        @if (config('app.versie'))
            <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ config('app.versie') }}</span>
        @endif
    </span>
    {{-- Alleen tonen als ORGANISATIE gevuld is; leeg laat de regel weg in
         plaats van lege ruimte achter te laten. --}}
    @if (config('app.organisatie'))
        <span class="truncate text-xs leading-none text-zinc-500 dark:text-zinc-400">
            {{ config('app.organisatie') }}
        </span>
    @endif
</div>
