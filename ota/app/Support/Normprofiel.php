<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Welke norm deze installatie volgt, en hoe die heet
 * (implementatie/00h-normprofiel.md).
 *
 * **Dit is geen autorisatiecheck.** De vorm lijkt op `Gate::heeft-niveau`, maar
 * de vraag is een andere: een autorisatiecheck beantwoordt *mag deze gebruiker
 * dit*, dit profiel *kent deze installatie dit begrip*. Andere vraag, andere
 * levensduur — per verzoek versus per installatie. Ze in één helper duwen levert
 * een check op die je zowel voor rechten als voor functionaliteit gaat lezen, en
 * dan is niet meer te zien wat een beveiligingsgrens is en wat een productkeuze.
 * Het normprofiel raakt de rechtenmatrix dan ook nergens.
 *
 * **De keuze staat in de database** (tabel `normprofiel`, migratie 000048) en
 * niet in `.env`. Hij wordt daar gezet bij het opzetten van de installatie en
 * verandert daarna niet meer; er is geen scherm om hem te wijzigen. `ISMS_NORM`
 * bestaat nog, maar alleen als invoer voor `NormprofielSeeder` — tijdens het
 * draaien wordt die variabele nergens gelezen.
 *
 * `config('norm.actief')` is de cache binnen één proces: de eerste vraag leest de
 * database, de rest leest de config. Dat scheelt een query per view en het geeft
 * de testsuite één plek om het profiel om te zetten zonder een rij te schrijven.
 *
 * Drie dingen falen hier hard in plaats van zacht, en dat is de hele reden dat
 * deze klasse bestaat in plaats van losse `config()`-aanroepen:
 *
 * 1. **Een onbekend profiel gooit.** `nen7501` (typefout) mag niet stilzwijgend
 *    het ISO-profiel opleveren op een zorginstallatie. Liever een fout bij het
 *    opstarten dan een ISMS dat de verkeerde norm claimt.
 * 2. **Geen vastgelegd profiel gooit ook.** Terugvallen op ISO zou van een
 *    half opgezette zorginstallatie een ISO-installatie maken, en dat merkt
 *    niemand tot de eerste audit.
 * 3. **Een onbekende labelsleutel gooit.** Een typefout in een blade moet een
 *    fout zijn en geen lege plek in de zin — die merk je pas in productie, en
 *    dan staat er "De maatregelen uit  van ".
 */
final class Normprofiel
{
    /** Het actieve profiel. Gooit bij een onbekende of ontbrekende waarde. */
    public static function actief(): string
    {
        $profiel = config('norm.actief') ?? self::uitDatabase();

        if (! is_string($profiel) || $profiel === '') {
            throw new RuntimeException(
                'Deze installatie heeft geen normprofiel vastgelegd. Zet ISMS_NORM in .env en draai '
                .'`php artisan db:seed --class=NormprofielSeeder`, of bouw de database op met '
                .'`php artisan migrate:fresh --seed`. Draait de installatie met een gecachede '
                .'configuratie, ververs die dan eerst (`php artisan config:cache`) — anders blijft '
                .'.env ongelezen.'
            );
        }

        // Onthouden voor de rest van het verzoek: dit wordt per view meermaals
        // gevraagd en het antwoord kan binnen één proces niet veranderen.
        config(['norm.actief' => $profiel]);

        if (! array_key_exists($profiel, self::profielen())) {
            throw new RuntimeException(
                "Onbekend normprofiel '{$profiel}'. Geldige waarden voor ISMS_NORM: "
                .implode(', ', array_keys(self::profielen())).'.'
            );
        }

        return $profiel;
    }

    /**
     * De vastgelegde keuze, of null zolang die er niet is.
     *
     * De tabel kan ontbreken — tijdens `migrate` zelf, of in een commando dat
     * vóór de migraties draait. Dat is geen fout maar "nog niet vastgelegd"; de
     * aanroeper maakt er een leesbare melding van.
     */
    private static function uitDatabase(): ?string
    {
        try {
            return DB::table('normprofiel')->value('profiel');
        } catch (QueryException) {
            return null;
        }
    }

    public static function is(string $profiel): bool
    {
        return self::actief() === $profiel;
    }

    /**
     * Kent dit profiel de genoemde capaciteit?
     *
     * Het alternatief — `if (config('norm.actief') === 'nen7510')` door views en
     * controllers — is precies waar de dual-norm-notitie voor waarschuwt: dan
     * staat de normkeuze op honderd plekken en is bij een derde profiel niets
     * meer te overzien. Vraag naar wat je nodig hebt, niet naar wie je bent.
     */
    public static function heeft(string $capaciteit): bool
    {
        return in_array($capaciteit, self::instelling()['capaciteiten'], true);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return self::instelling()['labels'];
    }

    /** Het label bij een sleutel. Gooit bij een onbekende sleutel. */
    public static function label(string $sleutel): string
    {
        $labels = self::labels();

        if (! array_key_exists($sleutel, $labels)) {
            throw new RuntimeException(
                "Onbekende normlabel-sleutel '{$sleutel}'. Beschikbaar: "
                .implode(', ', array_keys($labels)).'.'
            );
        }

        return $labels[$sleutel];
    }

    /** @return array<string, array{labels: array<string, string>, capaciteiten: list<string>}> */
    public static function profielen(): array
    {
        return config('norm.profielen', []);
    }

    /** @return array{labels: array<string, string>, capaciteiten: list<string>} */
    private static function instelling(): array
    {
        return self::profielen()[self::actief()];
    }
}
