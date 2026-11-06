<?php

namespace App\Support\Assistent;

use App\Models\AssistentAanroep;
use App\Models\Gebruiker;
use App\Support\Autorisatiegeheugen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Welke assistenten een gebruiker heeft gekoppeld, en het intrekken daarvan
 * (implementatie/14b §3.5, §8).
 *
 * Een "koppeling" is hier: een client met ten minste één niet-ingetrokken
 * token van deze gebruiker. Passport kent het begrip niet; het zijn tokens.
 * Intrekken zet de toegangstokens én hun verversingstokens op ingetrokken,
 * zodat de client ook geen nieuw toegangstoken meer kan halen.
 *
 * De blokkade zit niet hier maar in de middleware van /mcp: die toetst bij
 * elk verzoek account, rol en scope. Intrekken ruimt op, zodat er geen tokens
 * blijven liggen die bij een terugdraaiing (rol weer terug, functie weer aan)
 * ongemerkt weer werken.
 */
final class Koppelingen
{
    /**
     * @return Collection<int, array{client_id: string, naam: string, hosts: list<string>, gekoppeld_op: ?Carbon, laatst_gebruikt: ?Carbon}>
     */
    public static function van(Gebruiker $gebruiker): Collection
    {
        $tokens = Token::query()
            ->where('user_id', $gebruiker->id)
            ->where('revoked', false)
            ->get(['id', 'client_id', 'created_at']);

        if ($tokens->isEmpty()) {
            return collect();
        }

        $clients = Client::query()->whereKey($tokens->pluck('client_id')->unique())->get()->keyBy('id');

        $laatst = AssistentAanroep::query()
            ->where('gebruiker_id', $gebruiker->id)
            ->whereIn('client_id', $clients->keys())
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(aangeroepen_op) as laatst')
            ->pluck('laatst', 'client_id');

        return $tokens->groupBy('client_id')
            ->map(function (Collection $perClient, string $clientId) use ($clients, $laatst) {
                $client = $clients->get($clientId);

                return [
                    'client_id' => $clientId,
                    'naam' => $client?->name ?? 'Onbekende assistent',
                    'hosts' => collect($client?->redirect_uris ?? [])
                        ->map(fn ($uri) => parse_url((string) $uri, PHP_URL_HOST))
                        ->filter()->unique()->values()->all(),
                    'gekoppeld_op' => $perClient->min('created_at'),
                    'laatst_gebruikt' => isset($laatst[$clientId]) ? Carbon::parse($laatst[$clientId]) : null,
                ];
            })
            ->sortBy('naam')
            ->values();
    }

    /**
     * Trekt alle tokens van deze gebruiker in, of alleen die van één client.
     * Schrijft één regel in de audit trail van de gebruiker als er iets was.
     *
     * @return int het aantal ingetrokken toegangstokens
     */
    public static function trekIn(Gebruiker $gebruiker, ?string $clientId = null, string $reden = 'handmatig'): int
    {
        $query = Token::query()->where('user_id', $gebruiker->id)->where('revoked', false);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $namen = Client::query()
            ->whereKey((clone $query)->pluck('client_id')->unique())
            ->pluck('name')
            ->sort()
            ->implode(', ');

        DB::transaction(function () use ($ids) {
            Token::query()->whereKey($ids)->update(['revoked' => true]);
            RefreshToken::query()->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        });

        // Op de gebruiker, zoals de tweefactorgebeurtenissen (01d §5): het
        // token is geen auditeerbaar model, de gebruiker wel.
        $gebruiker->schrijfAuditregel('gewijzigd', oud: null, nieuw: [
            'assistentkoppeling' => "ingetrokken ({$reden}): {$namen}",
        ]);

        return $ids->count();
    }

    /**
     * Trekt de koppelingen in van iedereen die ze niet meer zou mogen maken:
     * account niet actief, of geen rol met een rij op `assistenttoegang`.
     * Aangeroepen na een wijziging in rollen of in de rechtenmatrix.
     *
     * Gaat alleen langs gebruikers met een actief token; dat zijn er weinig.
     */
    public static function ruimOpVoorWieNietMeerMag(): int
    {
        app(Autorisatiegeheugen::class)->vergeet();

        $ids = Token::query()->where('revoked', false)->distinct()->pluck('user_id')->filter();
        $totaal = 0;

        foreach (Gebruiker::query()->whereKey($ids)->with('rollen')->get() as $gebruiker) {
            if (! self::magKoppelen($gebruiker)) {
                $totaal += self::trekIn($gebruiker, reden: 'recht vervallen');
            }
        }

        return $totaal;
    }

    /**
     * Trekt alles in en verwijdert alle clients (14b §3.5): voor het uitzetten
     * van de functie, zodat opnieuw aanzetten geen oude tokens tot leven wekt.
     *
     * @return array{tokens: int, clients: int}
     */
    public static function trekAllesIn(): array
    {
        $tokens = 0;
        $ids = Token::query()->where('revoked', false)->distinct()->pluck('user_id')->filter();

        foreach (Gebruiker::query()->whereKey($ids)->get() as $gebruiker) {
            $tokens += self::trekIn($gebruiker, reden: 'functie uitgezet');
        }

        $clients = Client::query()->count();
        DB::transaction(function () {
            DB::table('oauth_auth_codes')->delete();
            RefreshToken::query()->delete();
            Token::query()->delete();
            Client::query()->delete();
        });

        return ['tokens' => $tokens, 'clients' => $clients];
    }

    public static function magKoppelen(Gebruiker $gebruiker): bool
    {
        return $gebruiker->magInloggen()
            && Gate::forUser($gebruiker)->allows('heeft-niveau', [Configuratie::BLOK, 'lezen']);
    }
}
