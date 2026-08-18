<?php

namespace App\Support;

use App\Models\SoaRegel;

/**
 * De balans in de maatregelenset, over de dimensies die de BIO2 in deel 1 §15
 * benoemt (deelproducten/04b-bio-overheidsmaatregelen.md §1).
 *
 * De norm vraagt evenwicht tussen vertrouwelijkheid, integriteit en
 * beschikbaarheid; tussen organisatorische, menselijke en technische maatregelen;
 * en over identificeren-beschermen-detecteren-reageren-herstellen. Dat zijn
 * letterlijk de dimensies `eigenschappen`, `thema` en `concepten` die
 * `config/maatregelkenmerken.php` al per maatregel vastlegt — dus dit is een
 * presentatievraag en geen datavraag.
 *
 * Gerekend over de **toepasselijke** maatregelen (`van_toepassing !== false`), en
 * over de **effectieve** classificatie: de eigen vaststelling van de organisatie
 * als die er is, anders het meegeleverde uitgangspunt. Wie de balans over álle 93
 * zou rekenen, meet de norm en niet zijn eigen ISMS.
 *
 * Alleen zinvol als de betreffende dimensies actief zijn. Een uitgeschakelde
 * dimensie levert een lege reeks op en de weergave laat hem dan weg — beter dan
 * een balansplaatje met een lege as, want dat leest als "nul".
 */
final class Maatregelbalans
{
    /** De dimensies die §15 noemt, in de volgorde waarin de norm ze opsomt. */
    public const DIMENSIES = ['eigenschappen', 'thema', 'concepten'];

    /**
     * @param  array<string, array<string, int>>  $verdeling  dimensie => waarde => aantal
     */
    private function __construct(
        public readonly array $verdeling,
        public readonly int $totaal,
    ) {}

    public static function huidige(): self
    {
        $regels = SoaRegel::with('maatregel')
            ->where(fn ($q) => $q->whereNull('van_toepassing')->orWhere('van_toepassing', true))
            ->get();

        $verdeling = [];

        foreach (self::DIMENSIES as $dimensie) {
            // `thema` staat op de maatregel en niet in `kenmerken`: het is een
            // eigenschap van de norm en geen classificatie die de organisatie
            // vaststelt. Vandaar twee bronnen in één weergave.
            $verdeling[$dimensie] = $dimensie === 'thema'
                ? self::perThema($regels)
                : self::perKenmerk($regels, $dimensie);
        }

        return new self(array_filter($verdeling), $regels->count());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SoaRegel>  $regels
     * @return array<string, int>
     */
    private static function perThema($regels): array
    {
        $verdeling = [];

        foreach (Maatregelverdeling::THEMA_LABELS as $sleutel => $label) {
            $aantal = $regels->filter(fn (SoaRegel $r) => $r->maatregel?->thema === $sleutel)->count();

            if ($aantal > 0) {
                $verdeling[$label] = $aantal;
            }
        }

        return $verdeling;
    }

    /**
     * Eén maatregel kan meerdere waarden in een dimensie hebben, dus de som over
     * een dimensie is doorgaans hoger dan het aantal maatregelen. Dat is geen fout:
     * de vraag is of een aspect achterblijft, niet hoe de 93 zijn opgedeeld.
     *
     * @param  \Illuminate\Support\Collection<int, SoaRegel>  $regels
     * @return array<string, int>
     */
    private static function perKenmerk($regels, string $dimensie): array
    {
        if (! array_key_exists($dimensie, Maatregelkenmerken::dimensies())) {
            return [];
        }

        $verdeling = [];

        // Op vocabulairevolgorde en niet op frequentie: de reeks
        // identificeren → herstellen is een ordening, en die volgorde is wat de
        // lezer nodig heeft om een gat te zien.
        foreach (Maatregelkenmerken::waarden($dimensie) as $waarde) {
            $verdeling[$waarde] = 0;
        }

        foreach ($regels as $regel) {
            foreach ($regel->kenmerken()[$dimensie] ?? [] as $waarde) {
                if (array_key_exists($waarde, $verdeling)) {
                    $verdeling[$waarde]++;
                }
            }
        }

        return $verdeling;
    }

    /** Het label van een dimensie, voor de kop boven de reeks. */
    public function label(string $dimensie): string
    {
        if ($dimensie === 'thema') {
            return 'Thema';
        }

        return Maatregelkenmerken::dimensies()[$dimensie]['label'] ?? $dimensie;
    }

    /** De hoogste waarde in een dimensie; de weergave schaalt de balken hierop. */
    public function piek(string $dimensie): int
    {
        return max([0, ...array_values($this->verdeling[$dimensie] ?? [])]);
    }
}
