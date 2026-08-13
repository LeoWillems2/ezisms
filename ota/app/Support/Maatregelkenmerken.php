<?php

namespace App\Support;

/**
 * Toegang tot het classificatieschema uit `config/maatregelkenmerken.php`.
 *
 * Alles wat over dimensies loopt — weergave, validatie, export, de
 * vocabulairebewaking in de tests — gaat hierlangs, zodat `actief` op precies
 * één plek staat en er geen tweede lijst kan ontstaan die gaat afwijken.
 *
 * Een dimensie kan waarden hebben die alleen bij een bepaalde capaciteit van het
 * normprofiel horen (`waarden_extra`); die worden hier aangeplakt, inclusief hun
 * zin bij de herkomst. Zie {@see verrijk()}.
 *
 * Een dimensie kan haar vocabulaire uit een lokaal bronbestand halen in plaats
 * van uit de config (`bron`). Dat is er voor `capaciteiten`: die waardenlijst is
 * ISO-eigen en hoort niet in het repo, maar wie de norm bezit kan hem in de
 * eigen installatie neerzetten. Zie `isms:capaciteiten`.
 */
final class Maatregelkenmerken
{
    /** @var array<string, list<string>> Gelezen bronbestanden, per dimensie. */
    private static array $bronCache = [];

    /**
     * De actieve dimensies, in schemavolgorde.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function dimensies(): array
    {
        return array_filter(
            self::alleDimensies(),
            fn (array $dimensie) => ($dimensie['actief'] ?? false) === true,
        );
    }

    /** Alle dimensies, dus inclusief de uitgeschakelde. Voor guards en uitleg. */
    public static function alleDimensies(): array
    {
        return array_map(self::verrijk(...), config('maatregelkenmerken', []));
    }

    public static function isActief(string $dimensie): bool
    {
        return (config("maatregelkenmerken.{$dimensie}.actief") ?? false) === true;
    }

    /**
     * Het toegestane vocabulaire van een dimensie; leeg als die niet bestaat of
     * niet actief is — een uitgeschakelde dimensie levert nooit waarden, ook al
     * ligt er een bronbestand.
     *
     * @return list<string>
     */
    public static function waarden(string $dimensie): array
    {
        if (! self::isActief($dimensie)) {
            return [];
        }

        $uitConfig = self::alleDimensies()[$dimensie]['waarden'] ?? [];

        return $uitConfig !== [] ? $uitConfig : self::uitBron($dimensie);
    }

    /**
     * Vult een dimensie aan met de waarden die alleen bij een capaciteit van dit
     * normprofiel horen (00j §2.1), en zet hun herkomst achter de bestaande.
     *
     * De aanvulling loopt via de dimensie en niet via een aparte methode, zodat
     * élke lezer hem meekrijgt: de weergave, de validatie op het SoA-scherm, de
     * export en de vocabulairebewaking in de tests. Een tweede ingang zou precies
     * het soort tweede lijst zijn dat de rest van deze klasse voorkomt.
     *
     * @param  array<string, mixed>  $dimensie
     * @return array<string, mixed>
     */
    private static function verrijk(array $dimensie): array
    {
        foreach ($dimensie['waarden_extra'] ?? [] as $capaciteit => $extra) {
            if (! Normprofiel::heeft($capaciteit)) {
                continue;
            }

            $dimensie['waarden'] = array_merge($dimensie['waarden'] ?? [], $extra['waarden'] ?? []);
            $dimensie['herkomst'] = trim(($dimensie['herkomst'] ?? '').' '.($extra['herkomst'] ?? ''));
        }

        unset($dimensie['waarden_extra']);

        return $dimensie;
    }

    /**
     * Het absolute pad van het lokale bronbestand van een dimensie, of null als
     * die er geen heeft. Het bestand hoeft niet te bestaan.
     *
     * Een absoluut pad in de config wordt ongewijzigd overgenomen. Dat is er voor
     * de tests: die mogen nooit in `database/seeders/data/` schrijven, want daar
     * staat het echte bestand van de installatie.
     */
    public static function bronpad(string $dimensie): ?string
    {
        $bron = config("maatregelkenmerken.{$dimensie}.bron");

        if ($bron === null) {
            return null;
        }

        return str_starts_with($bron, '/') ? $bron : database_path("seeders/data/{$bron}");
    }

    /** Leegt de bestandscache; nodig na het schrijven van een bronbestand. */
    public static function vergeetBron(): void
    {
        self::$bronCache = [];
    }

    /**
     * Het vocabulaire uit het lokale bronbestand. Ontbreekt het bestand of is het
     * onleesbaar, dan leeg — een dimensie zonder waarden keurt in de validatie
     * alles af, en dat is hier de veilige kant.
     *
     * @return list<string>
     */
    private static function uitBron(string $dimensie): array
    {
        if (array_key_exists($dimensie, self::$bronCache)) {
            return self::$bronCache[$dimensie];
        }

        $pad = self::bronpad($dimensie);

        if ($pad === null || ! is_file($pad)) {
            return self::$bronCache[$dimensie] = [];
        }

        try {
            $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::$bronCache[$dimensie] = [];
        }

        return self::$bronCache[$dimensie] = array_values($data['vocabulaire'] ?? []);
    }
}
