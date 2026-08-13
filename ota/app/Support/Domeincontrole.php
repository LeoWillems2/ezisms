<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * De bijna-treffer op een maildomein (implementatie/01g §5).
 *
 * Er wordt **niet** gewaarschuwd voor een onbekend domein. Externen — een
 * auditor, een leverancier, een ingehuurde — hebben legitiem een ander domein,
 * en een melding die in de helft van de gevallen onterecht is wordt weggeklikt
 * zonder gelezen te worden. Gewaarschuwd wordt alleen de typefout die niet
 * bounct maar bij een vreemde aankomt: `fruibv.nl` naast `fruitbv.nl`.
 *
 * Een eigen klasse en geen methode op de component, zodat de regel los van
 * Livewire te toetsen is.
 */
final class Domeincontrole
{
    /**
     * Hoeveel accounts een domein moet hebben om als referentie te tellen.
     *
     * Zonder deze ondergrens wordt de eerste typefout zelf een "bekend domein",
     * en meet de tweede typefout zich daaraan.
     */
    private const MINIMUM_ACCOUNTS = 2;

    /**
     * Onder deze lengte levert een afstand van twee onzin op: `bv.nl` tegen
     * `be.nl` is twee bewerkingen en twee volstrekt verschillende domeinen.
     */
    private const MINIMUM_LENGTE = 8;

    /** Één of twee tekens verschil is een typefout; meer is een ander domein. */
    private const MAXIMUM_AFSTAND = 2;

    /**
     * Het bekende domein waar dit adres verdacht dicht bij zit, of `null`.
     *
     * @param  array<string, int>  $tellingen  domein => aantal accounts
     */
    public static function bijnaTreffer(string $email, array $tellingen): ?string
    {
        $domein = self::domeinVan($email);

        if ($domein === null || strlen($domein) < self::MINIMUM_LENGTE) {
            return null;
        }

        // Een exacte treffer is geen bijna-treffer: het domein is in gebruik,
        // klaar. Ook als het maar één account heeft — dan is het geen referentie
        // om anderen aan te meten, maar het is wél bestaand.
        if (isset($tellingen[$domein])) {
            return null;
        }

        $dichtstbij = null;
        $kleinste = PHP_INT_MAX;

        foreach ($tellingen as $bekend => $aantal) {
            if ($aantal < self::MINIMUM_ACCOUNTS || strlen($bekend) < self::MINIMUM_LENGTE) {
                continue;
            }

            $afstand = levenshtein($domein, $bekend);

            if ($afstand <= self::MAXIMUM_AFSTAND && $afstand < $kleinste) {
                $dichtstbij = $bekend;
                $kleinste = $afstand;
            }
        }

        return $dichtstbij;
    }

    /**
     * De domeinen die al in gebruik zijn, met hun aantal accounts.
     *
     * Neemt een lijst adressen aan en geen query: de aanroeper heeft de
     * gebruikerscollectie al geladen voor de tabel eromheen, en een tweede keer
     * naar de database gaan voor dezelfde rijen is verspilling.
     *
     * @param  iterable<string>  $adressen
     * @return array<string, int>
     */
    public static function tellingen(iterable $adressen): array
    {
        $tellingen = [];

        foreach ($adressen as $adres) {
            $domein = self::domeinVan($adres);

            if ($domein !== null) {
                $tellingen[$domein] = ($tellingen[$domein] ?? 0) + 1;
            }
        }

        return $tellingen;
    }

    /** Alles ná de laatste `@`, kleingeschreven. Null als dat er niet is. */
    private static function domeinVan(string $email): ?string
    {
        $domein = Str::lower(trim(Str::afterLast($email, '@')));

        return $domein === '' || ! str_contains($email, '@') ? null : $domein;
    }
}
