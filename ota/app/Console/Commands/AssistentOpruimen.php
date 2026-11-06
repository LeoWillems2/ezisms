<?php

namespace App\Console\Commands;

use App\Models\AssistentAanroep;
use App\Models\AuditLogregel;
use App\Support\Assistent\Koppelingen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Onderhoud van de assistenttoegang (implementatie/14b §7.3). Dagelijks via de
 * scheduler, en met de hand met `--alles` bij het uitzetten van de functie.
 *
 * - de aanroeplog op zijn bewaartermijn houden;
 * - verlopen en ingetrokken tokens en codes weghalen (wat `passport:purge`
 *   doet, maar met een week marge, zodat een intrekking nog terug te vinden
 *   is als iemand ernaar vraagt);
 * - clients weghalen die zich registreerden maar al 30 dagen geen geldig
 *   token hebben. Het registratie-endpoint is anoniem (§3.2); zonder dit
 *   groeit de tabel met elke verbinding die nooit werd afgemaakt.
 *
 * Draait ook als de functie uit staat: de tabellen bestaan altijd, en een
 * installatie die het uitzette, moet zijn log nog steeds op termijn houden.
 */
class AssistentOpruimen extends Command
{
    protected $signature = 'isms:assistent-opruimen
        {--alles : alle koppelingen intrekken en alle clients verwijderen, bij het uitzetten van ISMS_ASSISTENT}';

    protected $description = 'Ruimt de aanroeplog, verlopen tokens en ongebruikte clients van de assistenttoegang op';

    /** Marge voordat een verlopen of ingetrokken token echt weg is. */
    private const TOKEN_MARGE_DAGEN = 7;

    /** Een client zonder geldig token wordt na zoveel dagen verwijderd. */
    private const CLIENT_ONGEBRUIKT_DAGEN = 30;

    public function handle(): int
    {
        if ($this->option('alles')) {
            $uitkomst = Koppelingen::trekAllesIn();
            $this->info("{$uitkomst['tokens']} token(s) ingetrokken, {$uitkomst['clients']} client(s) verwijderd.");
        }

        $grens = now()->subDays((int) config('assistent.log_bewaren_dagen', 365));
        $log = AssistentAanroep::verwijderOuderDan($grens);

        // Eén regel per opschoning en alleen als er iets was, zoals bij de
        // raadplegingen: per rij loggen verhuist het leesgedrag naar de trail.
        if ($log > 0) {
            AuditLogregel::legVerzamelingVast(
                blokNaam: 'notificatie-integratielaag',
                entiteitType: 'assistent_aanroep',
                actie: 'verwijderd',
                omschrijving: "Bewaartermijn aanroeplog assistenttoegang ({$log} regels)",
                details: [
                    'aantal' => $log,
                    'bewaartermijn_dagen' => (int) config('assistent.log_bewaren_dagen', 365),
                    'ouder_dan' => $grens->toDateTimeString(),
                ],
            );
        }

        $tokenGrens = now()->subDays(self::TOKEN_MARGE_DAGEN);
        $weg = fn ($q) => $q->where('revoked', true)->orWhere('expires_at', '<', $tokenGrens);

        [$tokens, $codes] = DB::transaction(function () use ($weg, $tokenGrens) {
            RefreshToken::query()->where($weg)->delete();
            $tokens = Token::query()
                ->where($weg)
                // Een token met een nog geldig verversingstoken blijft staan:
                // dat verversingstoken wijst ernaar.
                ->whereNotIn('id', RefreshToken::query()->select('access_token_id'))
                ->delete();
            $codes = DB::table('oauth_auth_codes')
                ->where(fn ($q) => $q->where('revoked', true)->orWhere('expires_at', '<', $tokenGrens))
                ->delete();

            return [$tokens, $codes];
        });

        $clients = Client::query()
            ->where('created_at', '<', now()->subDays(self::CLIENT_ONGEBRUIKT_DAGEN))
            ->whereDoesntHave('tokens', fn ($q) => $q->where('revoked', false)->where('expires_at', '>', now()))
            ->whereNotIn('id', Token::query()
                ->whereIn('id', RefreshToken::query()->where('revoked', false)->where('expires_at', '>', now())->select('access_token_id'))
                ->select('client_id'))
            ->get();

        foreach ($clients as $client) {
            DB::table('oauth_auth_codes')->where('client_id', $client->id)->delete();
            Token::query()->where('client_id', $client->id)->delete();
            $client->delete();
        }

        $this->info(sprintf(
            '%d aanroep(en), %d token(s), %d code(s) en %d client(s) opgeruimd.',
            $log, $tokens, $codes, $clients->count(),
        ));

        return self::SUCCESS;
    }
}
