<?php

namespace App\Http\Middleware\Assistent;

use App\Http\Middleware\VereistActiefAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * {@see VereistActiefAccount} voor /mcp (implementatie/14b §4.1).
 *
 * Die hangt aan de web-groep en werkt met een sessie en een redirect naar het
 * loginscherm. Een MCP-client heeft geen van beide. Hier is een geblokkeerd of
 * gedeactiveerd account een 401: het token geldt niet meer, en de client mag
 * opnieuw proberen te koppelen. Dat stuit dan op het loginscherm.
 *
 * Draait op elk verzoek, ook als het token nog niet is ingetrokken. Intrekken
 * (14b §3.5) ruimt op; dit is de blokkade.
 */
class VereistActiefAccountViaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $gebruiker = $request->user();

        if ($gebruiker === null || $gebruiker->magInloggen()) {
            return $next($request);
        }

        // Dezelfde toon als het loginscherm: zeggen dát het niet mag, niet waarom.
        return response()->json([
            'error' => 'invalid_token',
            'error_description' => 'Dit account is niet (meer) actief.',
        ], 401);
    }
}
