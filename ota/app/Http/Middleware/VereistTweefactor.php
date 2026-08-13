<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt tweefactorauthenticatie af na de respijtperiode
 * (implementatie/01d §9).
 *
 * Op de hele `web`-groep en niet op een routegroep: een route die later wordt
 * toegevoegd hoort niet per ongeluk buiten de afdwinging te vallen.
 */
class VereistTweefactor
{
    /**
     * Routes die bereikbaar moeten blijven. Zonder deze uitzondering stuurt de
     * middleware de gebruiker naar een scherm dat de middleware zelf blokkeert.
     *
     * @var list<string>
     */
    private const ALTIJD_TOEGANKELIJK = [
        'logout',
        'settings.tweefactor',
        'tweefactor.challenge',
        'password.confirm',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tweefactor.afdwingen')) {
            return $next($request);
        }

        $gebruiker = $request->user();

        if ($gebruiker === null || $gebruiker->tweefactorActief()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALTIJD_TOEGANKELIJK)) {
            return $next($request);
        }

        // Eerste keer zonder 2FA: de klok gaat nú lopen. Per gebruiker en niet
        // globaal — een vaste datum in config zou iemand die volgende maand
        // wordt uitgenodigd een deadline in het verleden geven.
        if ($gebruiker->tweefactor_deadline === null) {
            $gebruiker->forceFill([
                'tweefactor_deadline' => now()->addDays(config('tweefactor.respijt_dagen'))->toDateString(),
            ])->save();

            return $next($request);
        }

        // **Een ontbrekende deadline blokkeert nooit**, en dat is geen detail:
        // elke test met `actingAs()` maakt een verse gebruiker zonder deadline.
        // De omgekeerde volgorde — eerst blokkeren, dan de datum zetten — breekt
        // in één klap de hele suite en verleidt tot het aanpassen van tests die
        // niets met 2FA te maken hebben.
        if (! $gebruiker->tweefactorRespijtVerlopen()) {
            return $next($request);
        }

        return redirect()->route('settings.tweefactor');
    }
}
