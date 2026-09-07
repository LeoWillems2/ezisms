<?php

namespace App\Support;

use App\Models\Beleidsdocument;
use App\Models\Gebruiker;
use Illuminate\Support\Carbon;

/**
 * De leesbevestigingsstand per document (implementatie/12i).
 *
 * Het paneel toont **alleen wat aandacht vraagt** (12c §4): documenten met een
 * achterstand, en documenten waarvan de bevestigingsplicht aan staat maar geen
 * mens raakt. Wat volledig bevestigd is, telt als één regel eronder mee — een
 * lijst met twintig groene vinkjes is een reclamefolder, geen meting.
 *
 * Dat tweede geval is de reden dat deze klasse bestaat.
 * `Beleidsdocument::bevestigingsgraad()` geeft daar terecht `null` terug: een
 * 0% zou lezen als falende lezers in plaats van als een falende inrichting
 * (05 §6). Maar in de kolom van `/beleid` is dat `null` niet te onderscheiden
 * van "nog geen actieve versie", en dus is een document met bevestigingsplicht,
 * mét actieve versie en zónder gekoppelde afdeling nu een beheersmaatregel die
 * niet werkt en er rustig bij staat. Hier wordt dat onderscheid wél gemaakt.
 */
final class Leesbevestigingsstand
{
    /** De plicht staat aan en raakt niemand: geen afdeling gekoppeld. */
    public const REDEN_GEEN_DOELGROEP = 'geen-doelgroep';

    /** De doelgroep is bekend, maar nog niet iedereen heeft bevestigd. */
    public const REDEN_ACHTERSTAND = 'achterstand';

    /**
     * @param  list<array{document: Beleidsdocument, versienummer: int, bevestigd: int, doelgroep: int, openstaand: int, graad: int|null, verstreken: bool, deadline: Carbon, reden: string}>  $aandacht
     * @param  int  $volledig  documenten waar de hele doelgroep heeft bevestigd
     * @param  int  $zonderAfdeling  actieve gebruikers die buiten élke doelgroep vallen
     */
    private function __construct(
        public readonly array $aandacht,
        public readonly int $volledig,
        public readonly int $zonderAfdeling,
    ) {}

    public static function huidige(): self
    {
        $documenten = Beleidsdocument::query()
            ->where('leesbevestiging_vereist', true)
            ->with(['actieveVersie', 'afdelingen'])
            ->orderBy('titel')
            ->get();

        $aandacht = [];
        $volledig = 0;

        foreach ($documenten as $document) {
            $versie = $document->actieveVersie;

            // Zonder actieve versie is er niets te bevestigen. Dat is een
            // concept in behandeling en geen falende beheersmaatregel; het
            // hoort thuis op /beleid, niet in een aandachtspaneel.
            if ($versie === null) {
                continue;
            }

            // Bewust via het model: `doelgroepGebruikerIds()` is de enige bron
            // voor "wie moet bevestigen" (05 §6). Een eigen telling hier zou uit
            // de takengeneratie kunnen lopen zonder dat één van beide er
            // verkeerd uitziet.
            $doelgroepIds = $document->doelgroepGebruikerIds();
            $doelgroep = count($doelgroepIds);

            $regel = [
                'document' => $document,
                'versienummer' => $versie->versienummer,
                'deadline' => $versie->leesdeadline(),
                'verstreken' => $versie->leestermijnVerstreken(),
            ];

            if ($doelgroep === 0) {
                $aandacht[] = $regel + [
                    'bevestigd' => 0,
                    'doelgroep' => 0,
                    'openstaand' => 0,
                    // `null` en niet 0: er ís geen graad, en een 0% hier zou de
                    // lezers de schuld geven van een inrichtingsfout.
                    'graad' => null,
                    'reden' => self::REDEN_GEEN_DOELGROEP,
                ];

                continue;
            }

            // Alleen de doelgroep telt mee, ook in de teller: wie bevestigde en
            // daarna van afdeling wisselde, telt niet meer — anders komt de
            // graad boven 100% uit (gelijk aan `bevestigingsgraad()`).
            $bevestigd = $versie->bevestigingen()->whereIn('gebruiker_id', $doelgroepIds)->count();

            if ($bevestigd >= $doelgroep) {
                $volledig++;

                continue;
            }

            $aandacht[] = $regel + [
                'bevestigd' => $bevestigd,
                'doelgroep' => $doelgroep,
                'openstaand' => $doelgroep - $bevestigd,
                'graad' => (int) round($bevestigd / $doelgroep * 100),
                'reden' => self::REDEN_ACHTERSTAND,
            ];
        }

        usort($aandacht, self::volgorde(...));

        return new self(
            $aandacht,
            $volledig,
            // De noemer-waarschuwing bij elk percentage in dit paneel: wie geen
            // afdeling heeft, valt buiten élke doelgroep en dus buiten élke
            // graad hierboven. Zie de kennisbank, "Een nieuwe medewerker komt
            // er niet vanzelf in".
            Gebruiker::query()
                ->where('status', 'actief')
                ->whereNull('organisatie_eenheid_id')
                ->count(),
        );
    }

    /** Zijn er documenten waarvan de bevestigingsplicht niemand raakt? */
    public function zonderDoelgroep(): int
    {
        return count(array_filter(
            $this->aandacht,
            fn (array $regel) => $regel['reden'] === self::REDEN_GEEN_DOELGROEP,
        ));
    }

    /** Documenten met openstaande bevestigingen waarvan de leestermijn voorbij is. */
    public function overDeTermijn(): int
    {
        return count(array_filter(
            $this->aandacht,
            fn (array $regel) => $regel['reden'] === self::REDEN_ACHTERSTAND && $regel['verstreken'],
        ));
    }

    /**
     * Zonder doelgroep bovenaan — dat is een kapotte inrichting en niet een
     * achterlopende afdeling. Daarna de laagste graad eerst, en bij een gelijke
     * graad het document waar de meeste mensen op wachten.
     */
    private static function volgorde(array $a, array $b): int
    {
        $kapot = fn (array $r) => $r['reden'] === self::REDEN_GEEN_DOELGROEP ? 0 : 1;

        return [$kapot($a), $a['graad'] ?? 0, -$a['openstaand']]
            <=> [$kapot($b), $b['graad'] ?? 0, -$b['openstaand']];
    }
}
