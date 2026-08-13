<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet een gebruiker die niet (meer) mag inloggen er bij het eerstvolgende
 * verzoek uit (implementatie/01f §4).
 *
 * Zonder deze controle wordt `magInloggen()` alleen op het loginscherm
 * aangeroepen, en merkt iemand die al ingelogd is niets van een blokkade of
 * deactivering tot zijn sessie verloopt. Bij een blokkade wegens een
 * security-incident is dat het hele punt van de maatregel weg.
 *
 * Dekt ook de remember-me-cookie: die logt iemand opnieuw in, en zou zonder
 * deze controle een geblokkeerd account weer binnenlaten.
 *
 * Op de hele `web`-groep en niet op een routegroep, om dezelfde reden als
 * {@see VereistTweefactor}: een route die later bijkomt hoort niet buiten de
 * afdwinging te vallen.
 */
class VereistActiefAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $gebruiker = $request->user();

        if ($gebruiker === null || $gebruiker->magInloggen()) {
            return $next($request);
        }

        // Geen uitzonderingslijst nodig: hierna is er geen ingelogde gebruiker
        // meer, dus de middleware gaat op het loginscherm niet opnieuw af.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Dezelfde toon als het loginscherm: wél zeggen dat het account
        // geblokkeerd is, niet waarom. De reden is geschreven voor de CISO,
        // over iemand die op dat moment verdacht wordt (01f §5).
        return redirect()->route('login')->withErrors([
            'email' => match ($gebruiker->status) {
                'geblokkeerd' => 'Uw account is geblokkeerd. Neem contact op met de CISO.',
                default => 'Uw account is niet meer actief.',
            },
        ]);
    }
}
