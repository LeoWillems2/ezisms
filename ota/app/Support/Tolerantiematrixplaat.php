<?php

namespace App\Support;

use App\Models\Risico;

/**
 * Tekent de tolerantiematrix als PNG, voor in de schermkopie van
 * `/risicos/matrix` (implementatie/04b, 12h §7a).
 *
 * **Waarom een plaatje en niet een tabel.** De tabel staat er ook in — die
 * draagt de cijfers. Maar de matrix ís de boodschap: waar de massa zit ten
 * opzichte van de acceptatiedrempel zie je in één oogopslag aan de kleur, en dat
 * overleeft geen omzetting naar rijen. Een auditor die om "de risicoverdeling"
 * vraagt, vraagt om dit beeld.
 *
 * **Waarom met GD en niet met de HTML van het scherm.** Hetzelfde uitgangspunt
 * als 12h §5: het scherm declareert zijn kopie, de kopie rendert het scherm
 * niet. Er is hier bovendien geen keuze — een screenshot maken vraagt een
 * headless browser, en die staat niet op de server (en zou een zware
 * afhankelijkheid zijn voor één plaatje).
 *
 * **De twee dingen die kunnen ontbreken.** GD hoeft niet gecompileerd te zijn,
 * en er hoeft geen TTF-lettertype op de server te staan. In beide gevallen komt
 * er `null` uit en blijft het document verder compleet: alle cijfers staan in de
 * tabel eronder. Dat is bewust geen uitzondering — een ontbrekend plaatje maakt
 * de kopie minder mooi, geen enkel getal onjuist.
 */
final class Tolerantiematrixplaat
{
    /**
     * Rendermultiplier. De maten hieronder zijn in "logische" punten; ze worden
     * hiermee vermenigvuldigd zodat het plaatje op ~170 dpi in het document
     * staat en dus ook op papier scherp is.
     */
    private const SCHAAL = 3;

    private const CEL = 46;

    private const GAT = 3;

    /** Ruimte voor de as-cijfers (1–5) en daarnaast voor het as-woord. */
    private const ASLABEL = 20;

    private const ASTITEL = 17;

    /** Onderste strook met de kleurbanden. */
    private const LEGENDA = 22;

    /** Breedte op de pagina; ~12 cm laat op A4 nog marge over. */
    private const BREEDTE_CM = 12.0;

    /**
     * Kandidaat-lettertypen, als [regulier, vet]. De eerste waarvan het
     * reguliere bestand bestaat wint; ontbreekt de vette variant, dan doet de
     * reguliere dubbel dienst. Bewust een lijst met vaste paden en geen
     * `fc-match`: dat is een extra binary voor een vraag die met `is_file()` te
     * beantwoorden is.
     */
    private const LETTERTYPEN = [
        ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'],
        ['/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'],
        ['/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf'],
        ['/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf', '/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf'],
        ['/usr/share/fonts/TTF/DejaVuSans.ttf', '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf'],
        ['/System/Library/Fonts/Supplemental/Arial.ttf', '/System/Library/Fonts/Supplemental/Arial Bold.ttf'],
    ];

    /**
     * De semafoorkleuren, gelijk aan de klassen in `risico-matrix.blade.php`:
     * band => [achtergrond, tekst] als rgb.
     *
     * @var array<string, array{array{int, int, int}, array{int, int, int}}>
     */
    private const KLEUREN = [
        'red' => [[239, 68, 68], [255, 255, 255]],      // red-500
        'amber' => [[251, 191, 36], [24, 24, 27]],      // amber-400
        'green' => [[34, 197, 94], [255, 255, 255]],    // green-500
        'zinc' => [[212, 212, 216], [24, 24, 27]],      // zinc-300
    ];

    private const ASKLEUR = [113, 113, 122];            // zinc-500

    /** Tekst in een lege (gedimde) cel; zie `dim()` voor waarom niet wit. */
    private const LEEGKLEUR = [82, 82, 91];             // zinc-600

    /**
     * @return Schermafbeelding|null null als GD of een lettertype ontbreekt
     */
    public static function teken(Risicoverdeling $verdeling): ?Schermafbeelding
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }

        $lettertype = self::lettertype();

        if ($lettertype === null) {
            return null;
        }

        return new Schermafbeelding(
            png: self::png($verdeling, ...$lettertype),
            bijschrift: 'Tolerantiematrix: het aantal risico\'s per combinatie van kans (horizontaal) '
                .'en impact (verticaal), gekleurd naar de score kans × impact.',
            breedteCm: self::BREEDTE_CM,
        );
    }

    /** @return array{string, string}|null [regulier, vet] */
    private static function lettertype(): ?array
    {
        foreach (self::LETTERTYPEN as [$regulier, $vet]) {
            if (is_file($regulier)) {
                return [$regulier, is_file($vet) ? $vet : $regulier];
            }
        }

        return null;
    }

    private static function png(Risicoverdeling $verdeling, string $regulier, string $vet): string
    {
        $schaal = Risicoverdeling::SCHAAL;
        $drempel = Risico::drempelwaarde();
        $waarschuwing = Risico::waarschuwingsdrempel();

        $cel = self::CEL * self::SCHAAL;
        $gat = self::GAT * self::SCHAAL;
        $aslabel = self::ASLABEL * self::SCHAAL;
        $astitel = self::ASTITEL * self::SCHAAL;

        $legenda = self::LEGENDA * self::SCHAAL;
        $raster = $schaal * $cel + ($schaal - 1) * $gat;
        $links = $astitel + $aslabel;

        $plaat = imagecreatetruecolor($links + $raster, $raster + $aslabel + $astitel + $legenda);
        imagefill($plaat, 0, 0, imagecolorallocate($plaat, 255, 255, 255));

        // Rijen van hoogste impact (boven) naar laagste (onder), net als op het
        // scherm: het zwaarste risico ligt rechtsboven (04b §3).
        for ($impact = $schaal; $impact >= 1; $impact--) {
            $y = ($schaal - $impact) * ($cel + $gat);

            self::tekst($plaat, (string) $impact, $astitel + $aslabel / 2, $y + $cel / 2, 8, $regulier, self::ASKLEUR);

            for ($kans = 1; $kans <= $schaal; $kans++) {
                $score = $kans * $impact;
                $aantal = $verdeling->aantalIn($kans, $impact);
                [$vlak, $inkt] = self::KLEUREN[Risico::scoreKleur($score)];

                // Een lege cel dimt, precies als de `opacity-40` op het scherm:
                // de kleur blijft leidend, de bezetting bepaalt de nadruk. De
                // tekst dimt níet mee — wit op lichtgroen is op papier
                // onleesbaar, dus daar wint donkergrijs van de gelijkenis.
                if ($aantal === 0) {
                    $vlak = self::dim($vlak);
                    $inkt = self::LEEGKLEUR;
                }

                $x = $links + ($kans - 1) * ($cel + $gat);
                imagefilledrectangle($plaat, $x, $y, $x + $cel, $y + $cel, self::kleur($plaat, $vlak));

                self::tekst($plaat, (string) $aantal, $x + $cel / 2, $y + $cel / 2 - 5 * self::SCHAAL, 13, $vet, $inkt);
                self::tekst($plaat, (string) $score, $x + $cel / 2, $y + $cel / 2 + 11 * self::SCHAAL, 7, $regulier, $inkt);
            }
        }

        for ($kans = 1; $kans <= $schaal; $kans++) {
            $x = $links + ($kans - 1) * ($cel + $gat) + $cel / 2;
            self::tekst($plaat, (string) $kans, $x, $raster + $aslabel / 2, 8, $regulier, self::ASKLEUR);
        }

        // As-woorden, zodat het plaatje ook los van de tekst eromheen te lezen
        // is — het staat straks als figuur in een Word-document.
        self::tekst($plaat, 'Kans', $links + $raster / 2, $raster + $aslabel + $astitel / 2, 8, $vet, self::ASKLEUR);
        self::tekst($plaat, 'Impact', $astitel / 2, $raster / 2, 8, $vet, self::ASKLEUR, hoek: 90);

        // De drempel hoort bij het beeld: zonder die grens zegt een kleur niets,
        // en het plaatje kan los van de tekst eromheen belanden.
        self::legenda($plaat, $links, $raster, $raster + $aslabel + $astitel, $regulier, [
            'green' => '< '.$waarschuwing.' aanvaardbaar',
            'amber' => $waarschuwing.'–'.$drempel.' aandacht',
            'red' => '> '.$drempel.' boven de drempel',
        ]);

        ob_start();
        imagepng($plaat);
        $png = (string) ob_get_clean();
        imagedestroy($plaat);

        return $png;
    }

    /**
     * De kleurbanden onderaan: een blokje met een label, als geheel gecentreerd
     * onder het raster.
     *
     * @param  array<string, string>  $banden  bandnaam => label
     */
    private static function legenda(
        \GdImage $plaat,
        int $links,
        int $raster,
        int $top,
        string $ttf,
        array $banden,
    ): void {
        $blok = 8 * self::SCHAAL;
        $tussen = 5 * self::SCHAAL;
        $ruimte = 13 * self::SCHAAL;
        $vast = count($banden) * ($blok + $tussen) + (count($banden) - 1) * $ruimte;

        // De labels bevatten de ingestelde drempels, en die zijn instelbaar:
        // "> 15 boven de drempel" is breder dan "> 8 boven de drempel". Krimpen
        // tot het past is daarom geen luxe — anders valt bij de ene installatie
        // de helft van de legenda buiten het plaatje.
        $punten = 7;
        $breedtes = self::labelbreedtes($banden, $punten, $ttf);

        while ($punten > 4 && array_sum($breedtes) + $vast > $raster) {
            $punten--;
            $breedtes = self::labelbreedtes($banden, $punten, $ttf);
        }

        $totaal = array_sum($breedtes) + $vast;
        $x = (int) round($links + $raster / 2 - $totaal / 2);
        $midden = (int) round($top + self::LEGENDA * self::SCHAAL / 2);

        foreach ($banden as $band => $label) {
            [$vlak] = self::KLEUREN[$band];
            imagefilledrectangle(
                $plaat,
                $x,
                (int) round($midden - $blok / 2),
                $x + $blok,
                (int) round($midden + $blok / 2),
                self::kleur($plaat, $vlak),
            );

            $x += $blok + $tussen;
            self::tekst($plaat, $label, $x + $breedtes[$band] / 2, $midden, $punten, $ttf, self::ASKLEUR);
            $x += $breedtes[$band] + $ruimte;
        }
    }

    /**
     * @param  array<string, string>  $banden
     * @return array<string, int>
     */
    private static function labelbreedtes(array $banden, int $punten, string $ttf): array
    {
        $breedtes = [];

        foreach ($banden as $band => $label) {
            $doos = imagettfbbox($punten * self::SCHAAL, 0, $ttf, $label);
            $breedtes[$band] = $doos === false ? 0 : $doos[2] - $doos[0];
        }

        return $breedtes;
    }

    /**
     * Zet tekst gecentreerd op (x, y). `imagettftext` neemt de basislijn en de
     * linkerkant als ankerpunt, dus het midden moet uit de bounding box komen.
     *
     * @param  array{int, int, int}  $rgb
     */
    private static function tekst(
        \GdImage $plaat,
        string $tekst,
        float $x,
        float $y,
        int $punten,
        string $ttf,
        array $rgb,
        int $hoek = 0,
    ): void {
        $grootte = $punten * self::SCHAAL;

        // Altijd de ongedraaide doos opmeten: die is direct te lezen als
        // breedte, stok- en staarthoogte. De gedraaide doos van `imagettfbbox`
        // geeft dezelfde vier hoeken in een andere volgorde, en dan wordt het
        // rekenwerk hieronder een raadsel in plaats van een formule.
        $doos = imagettfbbox($grootte, 0, $ttf, $tekst);

        if ($doos === false) {
            return;
        }

        $breedte = $doos[2] - $doos[0];
        $boven = $doos[7];   // negatief: boven de basislijn
        $onder = $doos[1];   // positief bij staartletters (de 'p' van Impact)

        // Bij 90° draait de tekst tegen de klok in om het ankerpunt: hij loopt
        // dan omhoog vanaf y, en de letterhoogte ligt naast x.
        [$ankerX, $ankerY] = $hoek === 90
            ? [$x - ($onder + $boven) / 2, $y + $breedte / 2]
            : [$x - $breedte / 2, $y - ($onder + $boven) / 2];

        imagettftext(
            $plaat,
            $grootte,
            $hoek,
            (int) round($ankerX),
            (int) round($ankerY),
            self::kleur($plaat, $rgb),
            $ttf,
            $tekst,
        );
    }

    /**
     * Mengt een kleur voor 40% met wit — de rekensom achter `opacity-40` op een
     * witte pagina.
     *
     * @param  array{int, int, int}  $rgb
     * @return array{int, int, int}
     */
    private static function dim(array $rgb): array
    {
        return array_map(fn (int $waarde) => (int) round($waarde * 0.4 + 255 * 0.6), $rgb);
    }

    /** @param  array{int, int, int}  $rgb */
    private static function kleur(\GdImage $plaat, array $rgb): int
    {
        return (int) imagecolorallocate($plaat, ...$rgb);
    }
}
