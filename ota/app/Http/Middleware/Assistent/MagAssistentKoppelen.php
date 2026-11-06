<?php

namespace App\Http\Middleware\Assistent;

use App\Support\Assistent\Configuratie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staat vóór de toestemmingsroutes van Passport (implementatie/14b §3.3).
 *
 * De check hoort hier en niet in het toestemmingsscherm: Passport slaat dat
 * scherm over als de gebruiker deze client al eerder toestemming gaf en er
 * nog een geldig token is. Dan wordt er meteen een code uitgegeven, ook als
 * de rol inmiddels geen rij op `assistenttoegang` meer heeft.
 *
 * Een gast laat hij door: de controller van Passport stuurt die naar het
 * loginscherm, en na het inloggen (en de tweefactorstap) komt hij hier terug.
 */
class MagAssistentKoppelen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        if (Gate::allows('heeft-niveau', [Configuratie::BLOK, 'lezen'])) {
            return $next($request);
        }

        return response()->view('assistent.geen-toegang', status: 403);
    }
}
