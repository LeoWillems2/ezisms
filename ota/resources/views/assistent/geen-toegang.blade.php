{{--
    De rol van de gebruiker heeft geen rij op `assistenttoegang`
    (implementatie/14b §3.3). Geen toestemmingsscherm en geen code.
--}}
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Geen assistenttoegang"
            description="Uw rol mag geen AI-assistent aan het ISMS koppelen."
        />

        <p class="text-sm">
            Welke rollen een assistent mogen koppelen, bepaalt de CISO. Neem contact op met de CISO
            als u denkt dat u deze toegang nodig hebt.
        </p>

        <flux:button href="{{ route('dashboard') }}" class="w-full">Naar het ISMS</flux:button>
    </div>
</x-layouts.auth>
