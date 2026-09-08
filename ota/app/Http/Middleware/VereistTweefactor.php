<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
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

        if ($this->blijftBereikbaar($request)) {
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

    /**
     * Hoort dit verzoek bij een van de routes die bereikbaar moeten blijven?
     *
     * Een Livewire-verzoek loopt niet over de route van de pagina zelf maar over
     * het update-eindpunt, en dat staat nooit in de lijst. Zonder de vertaling
     * hieronder redirect elke knop op het instelscherm naar datzelfde
     * instelscherm: de gebruiker geeft zijn wachtwoord op, de pagina herlaadt en
     * de QR-code komt er nooit — precies de toestand waarin iemand met een
     * verlopen respijtperiode terechtkomt, want vóór de deadline redirect de
     * middleware niet en valt er niets op.
     */
    private function blijftBereikbaar(Request $request): bool
    {
        if ($request->routeIs(self::ALTIJD_TOEGANKELIJK)) {
            return true;
        }

        if (! Livewire::isLivewireRequest()) {
            return false;
        }

        // De herkomst komt uit de snapshot van de component en niet uit een kop
        // die de browser kan verzinnen. Livewire controleert de checksum van die
        // snapshot pas ná de middleware, maar het pad zit erin: wie het omschrijft
        // om hier langs te komen, strandt een stap later alsnog.
        $pad = trim(Livewire::originalPath(), '/');

        foreach (self::ALTIJD_TOEGANKELIJK as $naam) {
            // Vergelijken op de URI en niet met `match()`: die bindt de gevonden
            // route aan een verzonnen verzoek, en die binding blijft aan het
            // gedeelde route-object hangen. Alle vier de uitzonderingen hebben
            // een vaste URI; een route met parameters hoort hier niet thuis.
            $route = app('router')->getRoutes()->getByName($naam);

            if ($route !== null && trim($route->uri(), '/') === $pad) {
                return true;
            }
        }

        return false;
    }
}
