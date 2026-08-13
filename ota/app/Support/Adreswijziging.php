<?php

namespace App\Support;

use App\Models\Gebruiker;
use Illuminate\Support\Facades\URL;

/**
 * De bevestigingslink bij een adreswijziging van een actief account
 * (implementatie/01h §2).
 *
 * Naar het model van {@see Uitnodiging}, met één belangrijk verschil: het token
 * is gebonden aan het **aangevraagde adres en het aanvraagmoment**, niet aan de
 * wachtwoord-hash. Bij een uitnodiging is die hash het juiste anker — de link
 * hoort te sterven zodra de ontvanger een wachtwoord instelt. Hier zou dat anker
 * verkeerd zijn: het wachtwoord van een actief account verandert niet bij een
 * adreswijziging, en mág daar ook niet door veranderen (§0).
 *
 * Uit dit anker volgen drie dingen vanzelf, en alle drie zijn ze gewenst:
 *
 * 1. een tweede aanvraag maakt de eerste link dood (adres of tijdstip wijzigt);
 * 2. intrekken maakt de link dood (`nieuw_email` wordt null);
 * 3. bevestigen maakt de link dood (idem).
 *
 * De link is daarmee eenmalig zonder dat er iets af te vinken valt.
 */
final class Adreswijziging
{
    /**
     * Gelijk aan {@see Uitnodiging::GELDIGHEID_DAGEN}, maar als eigen constante:
     * het zijn twee verschillende gebeurtenissen en ze horen los te kunnen
     * bewegen.
     */
    public const GELDIGHEID_DAGEN = 7;

    /**
     * De vervaltijd wordt gerekend vanaf het aanvraagmoment en niet vanaf `now()`.
     * Anders herstart de klok elke keer dat de mail opnieuw gerenderd wordt.
     */
    public static function link(Gebruiker $gebruiker): string
    {
        return URL::temporarySignedRoute(
            'adreswijziging.bevestigen',
            $gebruiker->nieuw_email_aangevraagd_op->copy()->addDays(self::GELDIGHEID_DAGEN),
            ['gebruiker' => $gebruiker->id, 'token' => self::token($gebruiker)],
        );
    }

    public static function token(Gebruiker $gebruiker): string
    {
        return hash_hmac(
            'sha256',
            $gebruiker->id.'|'.$gebruiker->nieuw_email.'|'.$gebruiker->nieuw_email_aangevraagd_op,
            config('app.key'),
        );
    }

    public static function tokenIsGeldig(Gebruiker $gebruiker, string $token): bool
    {
        // Loopt er geen wijziging, dan is er niets te bevestigen — en dat is
        // meteen de reden dat intrekken en bevestigen de link doodmaken.
        return $gebruiker->nieuw_email !== null
            && hash_equals(self::token($gebruiker), $token);
    }

    /**
     * Het aangevraagde adres, half zichtbaar, voor het bericht aan het oude adres
     * (§6).
     *
     * Het **domein blijft heel** en het lokale deel gaat achter bolletjes. Dat is
     * niet willekeurig: de lezer moet kunnen zien óf de wijziging klopt, en dat
     * beslist het domein — het eigen nieuwe bedrijfsdomein of dat van een
     * vreemde. Het lokale deel voegt daar niets aan toe en zou van een verkeerd
     * bezorgd bericht een compleet bruikbaar alternatief adres maken.
     *
     * Een lokaal deel van één of twee tekens gaat helemaal weg: daar valt niets
     * te maskeren zonder het alsnog prijs te geven.
     */
    public static function gemaskeerd(string $email): string
    {
        $apenstaartje = strrpos($email, '@');

        if ($apenstaartje === false) {
            return str_repeat('•', mb_strlen($email));
        }

        $lokaal = substr($email, 0, $apenstaartje);
        $domein = substr($email, $apenstaartje);

        if (mb_strlen($lokaal) <= 2) {
            return str_repeat('•', mb_strlen($lokaal)).$domein;
        }

        return mb_substr($lokaal, 0, 1)
            .str_repeat('•', mb_strlen($lokaal) - 2)
            .mb_substr($lokaal, -1)
            .$domein;
    }
}
