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

    public static function link(Gebruiker $gebruiker): string
    {
        return URL::temporarySignedRoute(
            'uitnodiging.accepteren',
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
}
