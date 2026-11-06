<?php

namespace App\Http\Middleware\Assistent;

use App\Mcp\IsmsServer;
use App\Models\AssistentAanroep;
use App\Support\Assistent\Aanroepcontext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Eén regel in `assistent_aanroepen` per `tools/call` en `resources/read`
 * (implementatie/14b §7.1). Protocolverkeer (`initialize`, `tools/list`,
 * `ping`) niet: dat zegt niets over wat er is opgevraagd.
 *
 * De uitkomst komt uit de {@see Aanroepcontext} als de tool die zette, anders
 * uit het antwoord: een JSON-RPC-fout of `isError` is `fout`, de rest `ok`.
 *
 * Een fout bij het vastleggen laat het antwoord door. Het alternatief, de
 * assistent niets teruggeven omdat de log niet lukte, maakt van een
 * databaseprobleem een storing zonder dat er iets te verbergen valt: de
 * gebruiker had dezelfde gegevens in de UI kunnen zien.
 */
class LegAssistentAanroepVast
{
    private const VASTGELEGD = [
        'tools/call' => 'tool',
        'resources/read' => 'resource',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $bericht = json_decode($request->getContent(), true);
        $methode = is_array($bericht) ? ($bericht['method'] ?? null) : null;

        if (! is_string($methode) || ! isset(self::VASTGELEGD[$methode])) {
            return $next($request);
        }

        $start = hrtime(true);
        $response = $next($request);

        try {
            $this->leg($request, $bericht, self::VASTGELEGD[$methode], $response, $start);
        } catch (Throwable $e) {
            Log::error('Assistentaanroep niet vastgelegd: '.$e->getMessage());
        }

        return $response;
    }

    /** @param array<string, mixed> $bericht */
    private function leg(Request $request, array $bericht, string $soort, Response $response, int $start): void
    {
        $gebruiker = $request->user();

        if ($gebruiker === null) {
            return;
        }

        $parameters = is_array($bericht['params'] ?? null) ? $bericht['params'] : [];
        $context = app(Aanroepcontext::class);

        [$clientId, $clientNaam] = $this->client($gebruiker->token());

        AssistentAanroep::create([
            'gebruiker_id' => $gebruiker->id,
            'client_id' => $clientId,
            'client_naam' => $clientNaam,
            'soort' => $soort,
            'naam' => mb_substr((string) ($soort === 'tool' ? ($parameters['name'] ?? '') : ($parameters['uri'] ?? '')), 0, 64),
            'argumenten' => $this->argumenten($soort === 'tool' ? ($parameters['arguments'] ?? null) : null),
            'aantal_resultaten' => $context->aantalResultaten,
            'bewijsstuk_id' => $context->bewijsstukId,
            'uitkomst' => $context->uitkomst ?? $this->uitkomstUit($response, $soort, (string) ($parameters['name'] ?? '')),
            'duur_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            'aangeroepen_op' => now(),
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function client(mixed $token): array
    {
        if (! $token instanceof AccessToken) {
            return [null, null];
        }

        $id = $token->oauth_client_id;

        return [$id, Client::query()->whereKey($id)->value('name')];
    }

    /**
     * Ingekort tot `assistent.log_argumenten_bytes` (14b §7.1). Wat niet past,
     * wordt een notitie; een afgekapte JSON-string is niet terug te lezen.
     *
     * @return array<string, mixed>|null
     */
    private function argumenten(mixed $argumenten): ?array
    {
        if (! is_array($argumenten) || $argumenten === []) {
            return null;
        }

        $json = json_encode($argumenten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $grens = (int) config('assistent.log_argumenten_bytes', 2048);

        if ($json === false) {
            return ['_ingekort' => 'argumenten niet te coderen'];
        }

        if (strlen($json) <= $grens) {
            return $argumenten;
        }

        return ['_ingekort' => mb_strcut($json, 0, $grens)];
    }

    private function uitkomstUit(Response $response, string $soort, string $toolNaam): string
    {
        // Een bestaande tool die niet in de lijst van deze gebruiker staat, komt
        // terug als "not found": laravel/mcp laat hem weg via shouldRegister.
        if ($soort === 'tool' && $response->getStatusCode() >= 400 && in_array($toolNaam, IsmsServer::toolNamen(), true)) {
            return Aanroepcontext::GEWEIGERD;
        }

        if ($response->getStatusCode() >= 400) {
            return Aanroepcontext::FOUT;
        }

        // Een gestreamd antwoord (SSE) is hier niet te lezen. Geen van onze
        // tools streamt; komt er een bij, dan zet die zijn uitkomst zelf.
        $inhoud = $response->getContent();

        if ($inhoud === false) {
            return Aanroepcontext::OK;
        }

        $antwoord = json_decode($inhoud, true);

        if (! is_array($antwoord) || isset($antwoord['error']) || ($antwoord['result']['isError'] ?? false) === true) {
            return Aanroepcontext::FOUT;
        }

        return Aanroepcontext::OK;
    }
}
