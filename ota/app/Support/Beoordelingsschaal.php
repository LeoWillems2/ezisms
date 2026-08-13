<?php

namespace App\Support;

use App\Models\RisicocriteriaVersie;
use RuntimeException;

/**
 * De betekenis van de niveaus 1 t/m 5 van kans en impact
 * (implementatie/00j §1.2), uit de actieve risicocriteriaversie.
 *
 * **Sinds 04g leest deze klasse de database en niet meer `config()`.** De schaal
 * is onderdeel van het kader dat de organisatie zelf vaststelt: één versie
 * draagt de drempels én wat de cijfers betekenen, en Management activeert die in
 * één handeling. `config/beoordelingsschaal.php` is nog wel de seedbron, maar
 * wat er tijdens het draaien geldt staat in `risicocriteria_versies` +
 * `beoordelingsniveaus`.
 *
 * Het publieke oppervlak is daarbij niet veranderd: alle aanroepplekken (het
 * risicodetail, de matrix, de export, het criteriascherm) vragen nog steeds
 * "geef me de impactschaal" en merken van de verhuizing niets. De
 * profielafhandeling is verdwenen — het profiel bepaalt wat er bij het opzetten
 * in de tabel komt, niet wat er tijdens het draaien uit gelezen wordt.
 *
 * Alles wat misgaat gooit, en dat is de reden dat deze klasse bestaat in plaats
 * van losse queries. Alle drie de gevallen zijn stil:
 *
 * 1. **Een onbekende as.** `as('ernst')` zou leeg opleveren en dan verdwijnt de
 *    hele tabel van het scherm zonder melding.
 * 2. **Geen actieve criteriaversie.** Dan is er geen vastgestelde schaal en zou
 *    iedereen op een lege keuzelijst scoren.
 * 3. **Een gat in de niveaus.** Ontbreekt niveau 5, dan verdwijnt die optie uit
 *    de keuzelijst en scoort niemand er nog een — terwijl de matrix, de
 *    drempels en de formule wél tot 5 lopen.
 */
final class Beoordelingsschaal
{
    /**
     * De aanduiding van elke as. Een constante en geen kolom: dit zegt wélke
     * vraag je beantwoordt en niet wat het antwoord betekent — dat laatste is
     * wat de organisatie vaststelt.
     *
     * @var array<string, string>
     */
    private const LABELS = ['kans' => 'Kans', 'impact' => 'Impact'];

    /**
     * De schaal van één as, zoals hij nu geldt.
     *
     * @return array{label: string, leidraad: string, niveaus: array<int, array{naam: string, omschrijving: string, kwantitatieve_band: ?string}>}
     */
    public static function as(string $as): array
    {
        if (! array_key_exists($as, self::LABELS)) {
            throw new RuntimeException(
                "Onbekende beoordelingsas '{$as}'. Beschikbaar: "
                .implode(', ', array_keys(self::LABELS)).'.'
            );
        }

        $versie = RisicocriteriaVersie::actief();

        if ($versie === null) {
            throw new RuntimeException(
                'Deze installatie heeft geen actieve risicocriteria, dus ook geen vastgestelde '
                .'beoordelingsschaal. Draai `php artisan db:seed --class=RisicocriteriaSeeder`, of '
                .'activeer een versie op /risicos/criteria.'
            );
        }

        $niveaus = [];

        foreach ($versie->niveausVan($as) as $niveau => $definitie) {
            $niveaus[$niveau] = [
                'naam' => $definitie->naam,
                'omschrijving' => $definitie->omschrijving,
                'kwantitatieve_band' => $definitie->kwantitatieve_band,
            ];
        }

        self::bewaakVolledigheid($as, $niveaus);

        return [
            'label' => self::LABELS[$as],
            'leidraad' => $as === 'kans' ? $versie->leidraad_kans : $versie->leidraad_impact,
            'niveaus' => $niveaus,
        ];
    }

    /**
     * De niveaus van een as, gesleuteld op 1 t/m 5.
     *
     * @return array<int, array{naam: string, omschrijving: string, kwantitatieve_band: ?string}>
     */
    public static function niveaus(string $as): array
    {
        return self::as($as)['niveaus'];
    }

    /** De naam van één niveau; `null` bij een niet-beoordeeld risico. */
    public static function naam(string $as, ?int $niveau): ?string
    {
        return $niveau === null ? null : (self::niveaus($as)[$niveau]['naam'] ?? null);
    }

    /**
     * De keuzelijstopties: `[3 => '3 — Middelmatig']`. Het cijfer blijft
     * zichtbaar, want dat is wat overal elders staat — in de matrix, in de
     * score, in de export.
     *
     * @return array<int, string>
     */
    public static function opties(string $as): array
    {
        $opties = [];

        foreach (self::niveaus($as) as $niveau => $definitie) {
            $opties[$niveau] = $niveau.' — '.$definitie['naam'];
        }

        return $opties;
    }

    /**
     * @param  array<int, mixed>  $niveaus
     */
    private static function bewaakVolledigheid(string $as, array $niveaus): void
    {
        $verwacht = range(1, Risicoverdeling::SCHAAL);

        if (array_keys($niveaus) !== $verwacht) {
            throw new RuntimeException(
                "De {$as}-schaal dekt niet precies de niveaus "
                .implode(', ', $verwacht).'; gevonden: '
                .(($niveaus === []) ? 'geen' : implode(', ', array_keys($niveaus))).'.'
            );
        }
    }
}
