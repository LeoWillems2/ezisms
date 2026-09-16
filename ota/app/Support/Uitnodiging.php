<?php

namespace App\Support;

use App\Models\Gebruiker;
use Illuminate\Support\Facades\URL;

/**
 * Uitnodigingslinks voor nieuwe gebruikers (implementatie/01-identity-access.md §6/§8).
 *
 * De link is een signed URL met een geldigheidsduur van 7 dagen. Het token is
 * afgeleid van de wachtwoord-hash van de gebruiker: zodra de uitgenodigde een
 * eigen wachtwoord instelt verandert die hash en is de link automatisch
 * verbruikt. Dat scheelt een aparte tokentabel met opruimlogica.
 */
final class Uitnodiging
{
    public const GELDIGHEID_DAGEN = 7;

    /**
     * Voor een uitgenodigd account de uitnodiging; voor een actief account dat via
     * de identiteitsprovider inlogt en nog geen koppeling heeft, de koppellink
     * (01j §9.1). Zelfde scherm, zelfde token, zelfde geldigheid — daarom één
     * methode, zodat mail, brief en modal uit 01i allebei vanzelf meedoen.
     */
    public static function link(Gebruiker $gebruiker): string
    {
        return URL::temporarySignedRoute(
            $gebruiker->status === 'actief' ? 'koppeling.accepteren' : 'uitnodiging.accepteren',
            now()->addDays(self::GELDIGHEID_DAGEN),
            ['gebruiker' => $gebruiker->id, 'token' => self::token($gebruiker)],
        );
    }

    public static function token(Gebruiker $gebruiker): string
    {
        return hash_hmac('sha256', $gebruiker->id.'|'.$gebruiker->wachtwoord, config('app.key'));
    }

    public static function tokenIsGeldig(Gebruiker $gebruiker, string $token): bool
    {
        return hash_equals(self::token($gebruiker), $token);
    }

    /**
     * Valt er voor dit account een link uit te reiken? Een uitnodiging zolang het
     * account niet in gebruik is, en een koppellink zolang een actief extern
     * account zonder koppeling staat (01j §9.1).
     */
    public static function isUitTeReiken(Gebruiker $gebruiker): bool
    {
        return $gebruiker->status === 'uitgenodigd' || self::wachtOpKoppeling($gebruiker);
    }

    /** Actief, logt in via de IdP, en de koppeling is er (nog) niet. */
    public static function wachtOpKoppeling(Gebruiker $gebruiker): bool
    {
        return $gebruiker->status === 'actief'
            && $gebruiker->isExtern()
            && ! $gebruiker->externeIdentiteit()->exists();
    }
}
