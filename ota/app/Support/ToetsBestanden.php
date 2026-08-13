<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Leest de beschikbare toetsen van de `toetsen`-disk
 * (`storage/app/private/toetsen`), met de `<title>` van het bestand als naam.
 *
 * Bewust geen `Toets`-entiteit: de map ís de lijst. Tot 01e stond daar als reden
 * bij dat een registratietabel een tweede waarheid zou zijn naast de map — dat
 * argument verviel toen de map uit `public/` verhuisde, maar er is ook geen
 * reden een tabel toe te voegen. Wat een tabel wél had gegeven, een echte
 * relatie met `toetsopdrachten`, is vervangen door de telling in
 * `ToetsbestandenBeheer::verwijder()` (implementatie/01e §1.2, §2.2).
 */
final class ToetsBestanden
{
    public const DISK = 'toetsen';

    /** Alleen HTML. De extensie is ook de enige die de uploadcontrole doorlaat. */
    private const PATROON = '/\.html$/i';

    /**
     * Bestandsnaam => titel, gesorteerd op titel.
     *
     * @return array<string, string>
     */
    public static function beschikbaar(): array
    {
        $resultaat = [];

        foreach (self::bestanden() as $bestand) {
            $resultaat[$bestand] = self::titelVan($bestand);
        }

        asort($resultaat);

        return $resultaat;
    }

    /** Bestaat dit toetsbestand? */
    public static function bestaat(string $bestand): bool
    {
        return Storage::disk(self::DISK)->exists(basename($bestand));
    }

    /** De titel voor één bestand; valt terug op de bestandsnaam. */
    public static function titelVoor(string $bestand): string
    {
        return self::titelVan(basename($bestand));
    }

    /** De HTML zelf, of null als het bestand er niet is. */
    public static function inhoud(string $bestand): ?string
    {
        $bestand = basename($bestand);

        if (! self::bestaat($bestand)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($bestand);
    }

    /**
     * De bestandsnamen op de disk, alleen `.html`, zonder pad.
     *
     * @return list<string>
     */
    private static function bestanden(): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $pad) => basename($pad),
                Storage::disk(self::DISK)->files(),
            ),
            fn (string $bestand) => (bool) preg_match(self::PATROON, $bestand),
        ));
    }

    private static function titelVan(string $bestand): string
    {
        $inhoud = self::inhoud($bestand);

        if ($inhoud !== null && preg_match('/<title>(.*?)<\/title>/is', $inhoud, $m)) {
            return trim(html_entity_decode($m[1]));
        }

        return $bestand;
    }
}
