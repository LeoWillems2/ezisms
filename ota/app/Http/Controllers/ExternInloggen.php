<?php

namespace App\Http\Controllers;

use App\Models\ExterneIdentiteit;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use App\Support\Oidc\Aanvraag;
use App\Support\Oidc\Afgebroken;
use App\Support\Oidc\Claims;
use App\Support\Oidc\Configuratie;
use App\Support\Oidc\Provider;
use App\Support\Oidc\Storing;
use App\Support\Oidc\Weigering;
use App\Support\Uitnodiging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Inloggen via de externe identiteitsprovider (implementatie/01j §4).
 *
 * Een gewone controller en geen Livewire-component: beide acties eindigen in een
 * doorverwijzing, en die naar de IdP moet een echte 302 naar een ander domein
 * zijn. Livewire's `navigate` haalt een pagina met fetch op, en dat loopt bij de
 * IdP vast op CORS.
 */
class ExternInloggen extends Controller
{
    /**
     * De knop op het loginscherm. Altijd een verse aanvraag om in te loggen;
     * koppelen en herbevestigen zetten hun eigen aanvraag in de sessie en sturen
     * de browser rechtstreeks naar de IdP (§6.2, §7.5).
     */
    public function doorsturen(): RedirectResponse
    {
        abort_unless(Configuratie::isIngesteld(), 404);

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $url = self::autorisatieUrlOfNull($aanvraag = Aanvraag::inloggen());

        if ($url === null) {
            return $this->naarLogin(self::nietMogelijk());
        }

        $aanvraag->bewaar();

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Configuratie::isIngesteld(), 404);

        $aanvraag = Aanvraag::neemUitSessie();

        // Geen aanvraag in de sessie: verlopen sessie, tweede tabblad, of een
        // callback die hier nooit is begonnen. Alle drie zijn dezelfde
        // weigering als een state die niet klopt (§14).
        if ($aanvraag === null) {
            return $this->weiger(new Weigering('geen_aanvraag'), Aanvraag::INLOGGEN);
        }

        try {
            $claims = Provider::verwerkCallback($request, $aanvraag);
        } catch (Afgebroken $e) {
            return $this->afgebroken($aanvraag, $e);
        } catch (Weigering $e) {
            return $this->weiger($e, $aanvraag->doel, $aanvraag);
        } catch (Storing $e) {
            Log::error('Externe login: storing bij de identiteitsprovider', ['fout' => $e->getMessage()]);

            return $this->terug($aanvraag, self::nietMogelijk());
        }

        return match ($aanvraag->doel) {
            Aanvraag::KOPPELEN => $this->koppelen($claims, $aanvraag),
            Aanvraag::HERBEVESTIGEN => $this->herbevestigen($claims, $aanvraag),
            default => $this->inloggen($claims),
        };
    }

    /**
     * De autorisatie-URL, of null bij een fout in de configuratie of een IdP
     * die niet antwoordt. De fout gaat naar het log; de gebruiker krijgt
     * {@see self::nietMogelijk()}.
     */
    public static function autorisatieUrlOfNull(Aanvraag $aanvraag): ?string
    {
        if (($fouten = Configuratie::fouten()) !== []) {
            Log::error('Externe login: de configuratie klopt niet', ['fouten' => $fouten]);

            return null;
        }

        try {
            return Provider::autorisatieUrl($aanvraag);
        } catch (Storing $e) {
            Log::error('Externe login: storing bij de identiteitsprovider', ['fout' => $e->getMessage()]);

            return null;
        }
    }

    public static function nietMogelijk(): string
    {
        return 'Inloggen via '.Configuratie::weergavenaam().' is op dit moment niet mogelijk. Probeer het later opnieuw of neem contact op met de CISO.';
    }

    // --- De drie doelen ------------------------------------------------------

    private function inloggen(Claims $claims): RedirectResponse
    {
        $identiteit = $this->identiteitVan($claims);
        $gebruiker = $identiteit?->gebruiker;

        // Een identiteitsrij is geen toestemming, de inlogmethode is dat. Een rij
        // bij een wachtwoordaccount kan alleen door handwerk ontstaan (§14).
        if ($gebruiker === null || ! $gebruiker->isExtern()) {
            $this->registreer($gebruiker, $claims->gebruikersnaam ?? '', 'extern_onbekend');

            return $this->naarLogin('Er is in dit ISMS geen account gekoppeld aan dit '.Configuratie::weergavenaam().'-account. Neem contact op met de CISO.');
        }

        // De IdP heeft de identiteit al bewezen, dus de specifieke melding mag
        // hier — dezelfde afweging als op het loginscherm ná het wachtwoord.
        if (! $gebruiker->magInloggen()) {
            $this->registreer($gebruiker, $gebruiker->email, 'status');

            return $this->naarLogin($gebruiker->inlogweigering());
        }

        return $this->meldAan($gebruiker, $identiteit);
    }

    /**
     * Een uitnodigings- of koppellink afmaken (§6.3, §9.1).
     */
    private function koppelen(Claims $claims, Aanvraag $aanvraag): RedirectResponse
    {
        $gebruiker = $aanvraag->gebruikerId !== null ? Gebruiker::find($aanvraag->gebruikerId) : null;

        // Opnieuw controleren: tussen de klik en de terugkeer kan de CISO de
        // uitnodiging hebben gecorrigeerd (01g) of de koppeling opnieuw hebben
        // uitgereikt, en dan hoort de oude link niet meer te werken.
        if ($gebruiker === null || ! self::linkIsTeKoppelen($gebruiker, (string) $aanvraag->token)) {
            return $this->naarLogin('Deze link is niet meer geldig. Vraag de CISO om een nieuwe.');
        }

        // Nooit overschrijven: dat zou een actieve gebruiker ongemerkt uitsluiten.
        if ($this->identiteitVan($claims) !== null) {
            return $this->naarLogin('Dit '.Configuratie::weergavenaam().'-account is al aan een ander account in dit ISMS gekoppeld. Neem contact op met de CISO.');
        }

        // De trail leest de actor uit `auth()`, en die is hier nog leeg: het
        // inloggen volgt pas na de koppeling. Zonder deze regel staat het
        // koppelen op naam van "Systeem (geplande taak)", terwijl het de
        // betrokkene zelf is — en dat is precies wat een auditor hier vraagt.
        // `setUser` zet alleen de gebruiker van dit verzoek en start geen sessie;
        // het echte inloggen gebeurt in `meldAan()`.
        Auth::setUser($gebruiker);

        $identiteit = DB::transaction(function () use ($gebruiker, $claims) {
            $identiteit = $gebruiker->externeIdentiteit()->create([
                'issuer' => $claims->issuer,
                'subject' => $claims->subject,
                'idp_gebruikersnaam' => $claims->gebruikersnaam,
                'gekoppeld_op' => now(),
            ]);

            // De rotatie maakt de link dood door zijn eigen constructie: het
            // token hangt aan de wachtwoordhash (01g §0). Een statuscontrole die
            // later iemand versoepelt, is daar geen vervanging voor.
            $gebruiker->update($gebruiker->status === 'uitgenodigd'
                ? [
                    'status' => 'actief',
                    // Het accepteren van de uitnodiging is het bewijs dat de link
                    // is aangekomen — niet anders dan bij het wachtwoord.
                    'email_geverifieerd_op' => now(),
                    'wachtwoord' => Str::random(32),
                ]
                : [
                    'wachtwoord' => Str::random(32),
                    'koppeling_uitgereikt_op' => null,
                ]);

            return $identiteit;
        });

        $gebruiker->refresh();

        // Is een tweede factor bij het ISMS vereist en nog niet ingesteld, dan is
        // dat de laatste stap, en niet het dashboard.
        $moetTweefactorInstellen = $gebruiker->tweefactorVereist() && ! $gebruiker->tweefactorActief();

        return $this->meldAan(
            $gebruiker,
            $identiteit,
            naarNaLogin: $moetTweefactorInstellen ? route('settings.tweefactor') : null,
            melding: $moetTweefactorInstellen
                ? 'Uw account is gekoppeld. Stel als laatste stap de tweede factor in.'
                : 'Uw account is gekoppeld aan '.Configuratie::weergavenaam().'.',
        );
    }

    /**
     * Opnieuw aanmelden bij de IdP in plaats van een wachtwoord (§7.5). Er wordt
     * niet ingelogd, dus er komt geen loginpoging.
     */
    private function herbevestigen(Claims $claims, Aanvraag $aanvraag): RedirectResponse
    {
        $gebruiker = Auth::user();
        $terug = $aanvraag->terugNaar ?? route('dashboard');

        if (! $gebruiker instanceof Gebruiker) {
            return redirect()->route('login');
        }

        $identiteit = $gebruiker->externeIdentiteit;

        // Anders is dit een manier om met een ándere identiteit de sessie van
        // deze gebruiker op te waarderen.
        if ($identiteit === null || $identiteit->issuer !== $claims->issuer || ! hash_equals($identiteit->subject, $claims->subject)) {
            Log::warning('Externe login: herbevestiging met een andere identiteit', ['gebruiker_id' => $gebruiker->id]);

            return redirect()->to($terug)->with('fout', 'Dat is niet het '.Configuratie::weergavenaam().'-account waarmee u bent ingelogd.');
        }

        $identiteit->markeerGebruikt();
        Session::put('extern.aangemeld_op', now()->getTimestamp());

        return redirect()->to($terug);
    }

    // --- Hulpmethodes --------------------------------------------------------

    /**
     * Klopt de link nog voor dit account? Voor een uitnodiging (§6.3) en voor een
     * koppellink bij een actief account (§9.1).
     */
    public static function linkIsTeKoppelen(Gebruiker $gebruiker, string $token): bool
    {
        return $gebruiker->isExtern()
            && Uitnodiging::tokenIsGeldig($gebruiker, $token)
            && Uitnodiging::isUitTeReiken($gebruiker);
    }

    private function identiteitVan(Claims $claims): ?ExterneIdentiteit
    {
        return ExterneIdentiteit::where('issuer', $claims->issuer)
            ->where('subject', $claims->subject)
            ->first();
    }

    private function meldAan(Gebruiker $gebruiker, ExterneIdentiteit $identiteit, ?string $naarNaLogin = null, ?string $melding = null): RedirectResponse
    {
        $identiteit->markeerGebruikt();

        // Het Login-event weet niet langs welke weg er is ingelogd, en bij een
        // challenge ligt de login een verzoek later. De listener haalt dit op
        // (§5.3).
        Session::put('inloggen.methode', 'extern');
        Session::put('extern.aangemeld_op', now()->getTimestamp());

        if ($melding !== null) {
            Session::flash('melding', $melding);
        }

        // De challenge alleen als TOTP actief is — net als na het wachtwoord.
        // Wat §7 regelt is de plicht, niet de vraag.
        if ($gebruiker->tweefactorActief()) {
            Session::put('tweefactor.gebruiker_id', $gebruiker->id);
            Session::put('tweefactor.remember', false);

            return redirect()->route('tweefactor.challenge');
        }

        // Nooit "Aangemeld blijven": bij een extern account bepaalt de IdP hoe
        // lang iemand aangemeld blijft (§5.2).
        Auth::login($gebruiker, remember: false);
        Session::regenerate();

        return $naarNaLogin !== null
            ? redirect()->to($naarNaLogin)
            : redirect()->intended(route('dashboard', absolute: false));
    }

    private function weiger(Weigering $weigering, string $doel, ?Aanvraag $aanvraag = null): RedirectResponse
    {
        Log::warning('Externe login geweigerd', ['stap' => $weigering->getMessage(), 'doel' => $doel]);

        // Herbevestigen is geen login; de weigering staat in het log.
        if ($doel !== Aanvraag::HERBEVESTIGEN) {
            // Zonder adres: de claims zijn op dit moment niet te vertrouwen.
            $this->registreer(null, '', 'extern_geweigerd');
        }

        $melding = 'Aanmelden via '.Configuratie::weergavenaam().' is mislukt. Probeer het opnieuw.';

        return $aanvraag !== null ? $this->terug($aanvraag, $melding) : $this->naarLogin($melding);
    }

    private function afgebroken(Aanvraag $aanvraag, Afgebroken $e): RedirectResponse
    {
        Log::info('Externe login afgebroken door de identiteitsprovider', ['error' => $e->getMessage()]);

        return $this->terug($aanvraag, 'Aanmelden via '.Configuratie::weergavenaam().' is afgebroken.');
    }

    /** Terug naar waar de aanvraag vandaan kwam, met een melding. */
    private function terug(Aanvraag $aanvraag, string $melding): RedirectResponse
    {
        if ($aanvraag->doel === Aanvraag::HERBEVESTIGEN && Auth::check()) {
            return redirect()->to($aanvraag->terugNaar ?? route('dashboard'))->with('fout', $melding);
        }

        return $this->naarLogin($melding);
    }

    private function naarLogin(string $melding): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $melding]);
    }

    private function registreer(?Gebruiker $gebruiker, string $email, string $reden): void
    {
        Loginpoging::create([
            'gebruiker_id' => $gebruiker?->id,
            'email_ingevoerd' => $email,
            'tijdstip' => now(),
            'succesvol' => false,
            'methode' => 'extern',
            'reden' => $reden,
            'ip_adres' => request()->ip(),
        ]);
    }
}
