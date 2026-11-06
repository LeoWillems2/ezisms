{{--
    Toestemming voor een AI-assistent (implementatie/14b §3.3). Vervangt het
    scherm van Passport; zie AppServiceProvider::registreerAssistenttoegang().

    De naam van de client kiest de client zelf bij het registreren, dus die
    zegt niets. De host waar de code naartoe gaat wel; die staat ernaast.
--}}
@php
    $hosts = collect($client->redirect_uris ?? [])
        ->map(fn ($uri) => parse_url($uri, PHP_URL_HOST))
        ->filter()
        ->unique()
        ->values();
@endphp
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Assistent koppelen"
            description="Een AI-assistent vraagt toegang tot het ISMS namens u."
        />

        <div class="flex flex-col gap-1 text-sm">
            <div><span class="font-medium">Assistent:</span> {{ $client->name }}</div>
            <div><span class="font-medium">Stuurt terug naar:</span> {{ $hosts->implode(', ') ?: 'onbekend' }}</div>
            <div><span class="font-medium">Ingelogd als:</span> {{ $user->naam }} ({{ $user->email }})</div>
        </div>

        <div class="flex flex-col gap-3 text-sm">
            <p>
                Deze assistent kan alles <strong>lezen</strong> wat u in het ISMS kunt zien, inclusief
                bijlagen en bewijsstukken. Hij kan niets wijzigen.
            </p>
            <p>
                Wat de assistent opvraagt, gaat naar de AI-dienst die u met deze assistent gebruikt.
                Koppel alleen een assistent die uw organisatie daarvoor heeft toegestaan.
            </p>
            <p>
                Elke opvraging wordt vastgelegd. U kunt de koppeling later weer intrekken.
            </p>
            <p>
                <a href="{{ route('kennisbank', 'assistenttoegang') }}" class="underline" target="_blank" rel="noopener">Meer over assistenttoegang</a>
            </p>
        </div>

        <div class="flex gap-3">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <flux:button variant="primary" type="submit" class="w-full">Toestaan</flux:button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <flux:button type="submit" class="w-full">Weigeren</flux:button>
            </form>
        </div>
    </div>
</x-layouts.auth>
