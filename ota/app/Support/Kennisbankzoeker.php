<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Zoeken door de kennisbank (implementatie/00g).
 *
 * Geen index, geen zoekdienst, geen pakket: de hele kennisbank is 140 KB en een
 * volledige scan kost een fractie van een milliseconde. Elke indexeer-oplossing
 * zou machinerie zijn om iets te versnellen wat al niet te meten is.
 *
 * **Dit zoekt uitsluitend door de kennisbank.** Dat is een ontwerpkeuze en geen
 * beginpunt. Zoeken door de ISMS-gegevens zelf moet langs de blok-permissie
 * (`heeft-niveau`) én de record-scope ({@see Recordscope}); vergeet één van
 * beide en een zoekresultaat lekt precies wat de rechtenmatrix afschermt. De
 * kennisbank kent die complicatie niet — elke ingelogde gebruiker mag alles
 * erin lezen. Wordt zoeken door de gegevens ooit gevraagd, dan is dat een eigen
 * plan met een eigen autorisatie-analyse, en geen uitbreiding van deze klasse.
 *
 * Wat het zoeken bruikbaar maakt:
 *
 * - **Hoofdletter- en accentongevoelig.** Zowel de zoekterm als de tekst gaan
 *   door {@see self::vouw()}: 'BEËINDIGING', 'Beëindiging' en 'beeindiging'
 *   zijn dezelfde zoekopdracht.
 * - **Deelwoordmatching vooraan.** 'beoordel' vindt 'beoordelen', 'beoordeling'
 *   en 'beoordeeld'. Nederlandse verbuigingen zitten vrijwel altijd achteraan,
 *   dus dit dekt verrassend veel zonder een stemmer erbij.
 * - **Markdown-ruis eruit vóór het zoeken.** Anders levert zoeken op
 *   'kennisbank' vooral de link-URL's tussen de artikelen op, en zoeken op een
 *   veelvoorkomend woord de SVG-sitemap.
 * - **Een passage, geen vinkje.** 'Dit artikel bevat het woord' helpt niemand;
 *   de zin eromheen wel.
 */
final class Kennisbankzoeker
{
    /** Tekens rond de treffer in de passage. */
    private const PASSAGE_VOOR = 60;

    private const PASSAGE_NA = 100;

    /**
     * Wie 'audit trail' typt wil het artikel dat zo heet bovenaan, niet het
     * artikel dat de term twee keer terloops noemt. Vandaar het gewicht op de
     * titel, en het plafond op tekst-treffers: een lang artikel mag niet winnen
     * op herhaling alleen.
     */
    private const WEEG_TITEL = 10.0;

    private const WEEG_CATEGORIE = 4.0;

    private const WEEG_TEKST = 1.0;

    private const MAX_TEKSTTREFFERS = 5;

    /** Meer woorden dan dit maakt de opdracht niet preciezer, alleen trager. */
    private const MAX_ZOEKWOORDEN = 6;

    /** Leestekens die aan het begin of eind van een woord niet meetellen. */
    private const RANDTEKENS = " \t\n\r\0\x0B\"'`(){}[]<>,.;:!?*_/\\|…«»„“”‘’—–-";

    /**
     * Ontlede artikelen, per verzoek, gesleuteld op bestandspad + mtime. Zie
     * {@see self::document()} voor waarom dit geen `Cache` is en waarom de
     * sleutel niet de slug is.
     *
     * @var array<string, array{tekst: string, woorden: list<array{0: int, 1: int, 2: string}>, koppen: list<array{0: int, 1: string}>}>
     */
    private static array $onthouden = [];

    /**
     * De artikelen waarin álle woorden uit de term voorkomen, aflopend op
     * relevantie.
     *
     * @return list<Zoekresultaat>
     */
    public static function zoek(string $term): array
    {
        $zoekwoorden = self::zoekwoorden($term);

        if ($zoekwoorden === []) {
            return [];
        }

        $resultaten = [];

        foreach (Kennisartikelen::alles() as $slug => $meta) {
            $document = self::document($slug);

            if ($document === null) {
                continue;
            }

            $titel = self::vouw($meta['titel']);
            $categorie = self::vouw($meta['categorie']);

            $score = 0.0;
            $beste = null;
            $zeldzaamst = PHP_INT_MAX;

            foreach ($zoekwoorden as $woord) {
                $raak = false;

                if (self::bevat($titel, $woord)) {
                    $score += self::WEEG_TITEL;
                    $raak = true;
                }

                if (self::bevat($categorie, $woord)) {
                    $score += self::WEEG_CATEGORIE;
                    $raak = true;
                }

                $treffers = self::treffers($document['woorden'], $woord);

                if ($treffers !== []) {
                    $score += self::WEEG_TEKST * min(count($treffers), self::MAX_TEKSTTREFFERS);
                    $raak = true;

                    // De passage komt van het zeldzaamste woord uit de term en
                    // niet van het eerste. Wie 'twee normclausules' zoekt heeft
                    // niets aan de eerste 'twee' in de inleiding; 'normclausules'
                    // wijst de plek aan waar het antwoord staat.
                    if (count($treffers) < $zeldzaamst) {
                        $zeldzaamst = count($treffers);
                        $beste = $treffers[0];
                    }
                }

                // AND over de woorden: één woord dat nergens staat laat het hele
                // artikel afvallen. Voorspelbaarder dan OR met ranking, en bij
                // achttien artikelen levert OR vooral ruis op.
                if (! $raak) {
                    continue 2;
                }
            }

            $resultaten[] = new Zoekresultaat(
                slug: $slug,
                titel: $meta['titel'],
                categorie: $meta['categorie'],
                score: $score,
                passage: self::passage($document['tekst'], $beste),
                anker: $beste === null ? null : self::ankerBij($document['koppen'], $beste[0]),
            );
        }

        // Gelijke score: op titel, zodat de volgorde stabiel is en niet afhangt
        // van de volgorde in het register.
        usort($resultaten, fn (Zoekresultaat $a, Zoekresultaat $b) => [$b->score, $a->titel] <=> [$a->score, $b->titel]);

        return $resultaten;
    }

    /**
     * De tekst waarin dit artikel doorzocht wordt: markdown zonder opmaak.
     *
     * Publiek omdat een artikel dat wél in het register staat maar geen
     * doorzoekbare tekst oplevert, onvindbaar is zonder dat iets stukgaat.
     * KennisbankZoekenTest controleert daarop voor elk artikel.
     */
    public static function doorzoekbareTekst(string $slug): string
    {
        return self::document($slug)['tekst'] ?? '';
    }

    /**
     * De zoekterm in losse, gevouwen woorden.
     *
     * @return list<string>
     */
    private static function zoekwoorden(string $term): array
    {
        $woorden = [];

        foreach (preg_split('/\s+/u', self::vouw($term), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $woord) {
            $woord = trim($woord, self::RANDTEKENS);

            if ($woord !== '' && ! in_array($woord, $woorden, true)) {
                $woorden[] = $woord;
            }
        }

        return array_slice($woorden, 0, self::MAX_ZOEKWOORDEN);
    }

    /**
     * Hoofdletters en accenten weg. `Str::ascii()` is niet gratis, dus alleen
     * voor tekst die daadwerkelijk buiten ASCII valt — de rest van de kennisbank
     * is gewoon Nederlands met af en toe een trema.
     */
    private static function vouw(string $tekst): string
    {
        return preg_match('/[\x80-\xFF]/', $tekst) === 1
            ? Str::lower(Str::ascii($tekst))
            : strtolower($tekst);
    }

    /** Staat het woord (als woordbegin) in dit gevouwen veld? */
    private static function bevat(string $veld, string $woord): bool
    {
        return str_contains($veld, $woord)
            && preg_match('/(?<![a-z0-9])'.preg_quote($woord, '/').'/', $veld) === 1;
    }

    /**
     * De posities van de woorden in de tekst die met dit zoekwoord beginnen.
     *
     * @param  list<array{0: int, 1: int, 2: string}>  $woorden  offset, lengte, gevouwen vorm
     * @return list<array{0: int, 1: int}> offset en lengte in de platte tekst
     */
    private static function treffers(array $woorden, string $zoekwoord): array
    {
        $treffers = [];

        foreach ($woorden as [$offset, $lengte, $gevouwen]) {
            if (self::bevat($gevouwen, $zoekwoord)) {
                // Het hele woord wordt gemarkeerd, niet alleen het getypte deel:
                // wie 'beoordel' zoekt wil 'beoordeling' oplichten zien staan.
                $treffers[] = [$offset, $lengte];
            }
        }

        return $treffers;
    }

    /**
     * Het kopanker waaronder deze positie valt: de laatste kop ervóór.
     *
     * @param  list<array{0: int, 1: string}>  $koppen  positie, anker
     */
    private static function ankerBij(array $koppen, int $offset): ?string
    {
        $anker = null;

        foreach ($koppen as [$positie, $kopanker]) {
            if ($positie > $offset) {
                break;
            }

            $anker = $kopanker;
        }

        return $anker;
    }

    /**
     * De passage rond de treffer, als HTML met de treffer in `<mark>`.
     *
     * @param  array{0: int, 1: int}|null  $treffer  null bij een treffer die alleen in de titel staat
     */
    private static function passage(string $tekst, ?array $treffer): string
    {
        [$offset, $lengte] = $treffer ?? [0, 0];

        $start = self::naarWoordgrens($tekst, max(0, $offset - self::PASSAGE_VOOR), vooruit: true);
        $start = min($start, $offset);

        $eind = self::naarWoordgrens($tekst, min(strlen($tekst), $offset + $lengte + self::PASSAGE_NA), vooruit: false);
        $eind = max($eind, $offset + $lengte);

        $stuk = fn (int $van, int $tot) => str_replace("\n", ' ', e(substr($tekst, $van, $tot - $van)));

        $passage = $stuk($start, $offset)
            .($lengte > 0 ? '<mark>'.$stuk($offset, $offset + $lengte).'</mark>' : '')
            .$stuk($offset + $lengte, $eind);

        return ($start > 0 ? '… ' : '').trim($passage).($eind < strlen($tekst) ? ' …' : '');
    }

    /**
     * Schuif een knippunt naar de dichtstbijzijnde witruimte, zodat de passage
     * niet middenin een woord begint of eindigt. Witruimte is hier altijd ASCII,
     * dus dit valt vanzelf op een geldige UTF-8-grens.
     */
    private static function naarWoordgrens(string $tekst, int $positie, bool $vooruit): int
    {
        if ($positie <= 0 || $positie >= strlen($tekst)) {
            return $positie;
        }

        if ($vooruit) {
            return $positie + strcspn($tekst, " \n", $positie) + 1;
        }

        $voor = substr($tekst, 0, $positie);
        $grens = max(
            ($spatie = strrpos($voor, ' ')) === false ? -1 : $spatie,
            ($regel = strrpos($voor, "\n")) === false ? -1 : $regel,
        );

        return $grens < 0 ? $positie : $grens;
    }

    /**
     * De doorzoekbare vorm van een artikel, onthouden binnen het verzoek.
     *
     * **Bewust geen `Cache`.** Dat stond wel in het plan, en de meting heeft het
     * omgedraaid: de woordposities van de hele kennisbank zijn megabytes, en die
     * door de database-cachestore duwen kostte tien seconden aan schrijfwerk om
     * daarna per zoekopdracht nog steeds te moeten deserialiseren. Ontleden
     * kost 33 ms voor alle achttien artikelen samen; een tweede zoekopdracht
     * binnen hetzelfde verzoek 5 ms. Een cache zou hier dus trager zijn dan geen
     * cache. De mtime zit in de sleutel zodat een bewerkt artikel binnen
     * hetzelfde verzoek niet blijft hangen.
     *
     * @return array{tekst: string, woorden: list<array{0: int, 1: int, 2: string}>, koppen: list<array{0: int, 1: string}>}|null
     */
    private static function document(string $slug): ?array
    {
        $pad = Kennisartikelen::pad($slug);

        if ($pad === null) {
            return null;
        }

        // De sleutel is het páád en niet de slug: bij een artikel met een variant
        // per normprofiel wijst één slug naar twee bestanden, en die zouden
        // elkaars ontleding krijgen zodra hun mtime toevallig gelijk is.
        return self::$onthouden[$pad.':'.filemtime($pad)] ??= self::ontleed(Kennisartikelen::inhoud($slug) ?? '');
    }

    /**
     * Markdown → platte tekst, met de woordposities en de kopposities erbij.
     *
     * De platte tekst is de tekst waarin gezocht én waaruit geciteerd wordt, dus
     * de offsets hoeven alleen ten opzichte van elkaar te kloppen — niet ten
     * opzichte van het bronbestand.
     *
     * @return array{tekst: string, woorden: list<array{0: int, 1: int, 2: string}>, koppen: list<array{0: int, 1: string}>}
     */
    private static function ontleed(string $markdown): array
    {
        $tekst = '';
        $koppen = [];
        $inCodeblok = false;

        foreach (preg_split('/\R/u', $markdown) ?: [] as $regel) {
            // De fence-markering telt niet mee, de inhoud wél: `isms:meet-kpis`
            // staat in een codeblok en hoort gewoon vindbaar te zijn.
            if (preg_match('/^\s{0,3}(```|~~~)/', $regel) === 1) {
                $inCodeblok = ! $inCodeblok;

                continue;
            }

            if ($inCodeblok) {
                $tekst .= $regel."\n";

                continue;
            }

            // De scheidingsregel van een tabel (|---|:--:|) draagt geen tekst.
            if (preg_match('/^\s*\|?[\s:|-]*\|[\s:|-]*$/', $regel) === 1) {
                continue;
            }

            if (preg_match('/^\s{0,3}#{1,6}\s+(.*?)\s*#*\s*$/u', $regel, $treffer) === 1) {
                // Zonder blokmarkering: in `## 1. Runtime & infrastructuur` is
                // de '1.' onderdeel van de kop en geen opsommingsteken. Wie hem
                // hier wegpoetst krijgt een ander anker dan de HTML, en dan
                // wijst elk zoekresultaat naar een fragment dat niet bestaat.
                $kop = self::schoon($treffer[1], blokmarkering: false);
                $koppen[] = [strlen($tekst), Kennisartikelen::anker($kop)];
                $tekst .= $kop."\n";

                continue;
            }

            $schoon = self::schoon($regel);

            if ($schoon !== '') {
                $tekst .= $schoon."\n";
            }
        }

        return ['tekst' => $tekst, 'woorden' => self::woordposities($tekst), 'koppen' => $koppen];
    }

    /**
     * Eén regel markdown ontdaan van opmaak. Wat blijft staan is wat een mens
     * op het scherm zou lezen.
     */
    private static function schoon(string $regel, bool $blokmarkering = true): string
    {
        if ($blokmarkering) {
            // Blockquote-tekens en opsommingstekens vooraan.
            $regel = preg_replace('/^\s*(>\s*)+/u', '', $regel);
            $regel = preg_replace('/^\s*([*+-]|\d+[.)])\s+/u', '', (string) $regel);
        }

        // Links en afbeeldingen: de tekst blijft, de URL gaat weg. Anders levert
        // zoeken op 'kennisbank' vooral de onderlinge verwijzingen op.
        $regel = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', (string) $regel);
        $regel = preg_replace('/<https?:[^>\s]*>/u', '', (string) $regel);

        // HTML — in de praktijk de SVG-sitemap. De tags en hun attributen weg,
        // de tekst ertussen blijft; anders is elke coördinaat een zoekresultaat.
        $regel = strip_tags((string) $regel);

        // Tabelpijpen als scheiding, nadruk- en codemarkering eraf. Let op: `_`
        // en losse `*` blijven staan — die zitten in namen als
        // `status_gewijzigd`, en die moeten juist vindbaar zijn.
        $regel = str_replace(['|', '**', '~~', '`'], [' ', '', '', ''], $regel);

        return trim($regel);
    }

    /**
     * Elk woord in de tekst met zijn positie, lengte en gevouwen vorm.
     *
     * Op woordniveau en niet op tekenniveau: zo blijven de posities die van de
     * bróntekst, terwijl het zoeken op de gevouwen vorm gebeurt. Vouwen verandert
     * de lengte (ë wordt e), dus een tekenpositie in de gevouwen tekst zou niet
     * terug te rekenen zijn naar de tekst die we citeren.
     *
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private static function woordposities(string $tekst): array
    {
        preg_match_all('/\S+/u', $tekst, $treffers, PREG_OFFSET_CAPTURE);

        $woorden = [];

        foreach ($treffers[0] as [$ruw, $offset]) {
            $kaal = trim($ruw, self::RANDTEKENS);

            if ($kaal === '') {
                continue;
            }

            $woorden[] = [
                $offset + strpos($ruw, $kaal),
                strlen($kaal),
                self::vouw($kaal),
            ];
        }

        return $woorden;
    }
}
