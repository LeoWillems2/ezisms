<?php

namespace App\Support;

use App\Models\SoaRegel;

/**
 * De verdeling van de Annex A-maatregelen over de vier thema's en de
 * implementatiestatus (implementatie/12c §3.4).
 *
 * De statusvolgorde is een **ordening** (niet gestart → in uitvoering →
 * geïmplementeerd) en die volgorde is hier de bron: de weergave kleurt hem als
 * één ramp licht-naar-donker, zodat de lezer de ordening in de kleur ziet.
 * `nvt` valt daarbuiten — dat is een besluit, geen fase in de voortgang.
 */
final class Maatregelverdeling
{
    /** In deze volgorde, want dit is de voortgang. */
    public const VOORTGANG = ['niet_gestart', 'in_uitvoering', 'geimplementeerd'];

    /** Buiten de ordening. */
    public const BUITEN_VOORTGANG = 'nvt';

    public const THEMA_LABELS = [
        'organisatorisch' => 'Organisatorisch',
        'mensgericht' => 'Mensgericht',
        'fysiek' => 'Fysiek',
        'technologisch' => 'Technologisch',
    ];

    public const STATUS_LABELS = [
        'niet_gestart' => 'Niet gestart',
        'in_uitvoering' => 'In uitvoering',
        'geimplementeerd' => 'Geïmplementeerd',
        'nvt' => 'Niet van toepassing',
    ];

    /**
     * @param  array<string, array<string, int>>  $perThema  [thema][status] => aantal
     */
    private function __construct(
        public readonly array $perThema,
        public readonly int $totaal,
    ) {}

    public static function huidige(): self
    {
        $rijen = SoaRegel::query()
            ->join('maatregelen', 'maatregelen.id', '=', 'soa_regels.maatregel_id')
            ->selectRaw('maatregelen.thema as thema, soa_regels.implementatiestatus as status, count(*) as aantal')
            ->groupBy('maatregelen.thema', 'soa_regels.implementatiestatus')
            ->get();

        // Alle thema's en alle statussen aanwezig, ook met nul. Een klasse die
        // vandaag leeg is moet morgen een plek hebben, en de legenda hoort niet
        // te verspringen zodra er één regel op 'niet gestart' komt.
        $perThema = [];
        foreach (array_keys(self::THEMA_LABELS) as $thema) {
            foreach (self::statussen() as $status) {
                $perThema[$thema][$status] = 0;
            }
        }

        $totaal = 0;
        foreach ($rijen as $rij) {
            if (isset($perThema[$rij->thema][$rij->status])) {
                $perThema[$rij->thema][$rij->status] = (int) $rij->aantal;
                $totaal += (int) $rij->aantal;
            }
        }

        return new self($perThema, $totaal);
    }

    /** @return list<string> de voortgangsstatussen, gevolgd door nvt */
    public static function statussen(): array
    {
        return [...self::VOORTGANG, self::BUITEN_VOORTGANG];
    }

    public function totaalVoorThema(string $thema): int
    {
        return array_sum($this->perThema[$thema] ?? []);
    }

    /** Het grootste thema; de balklengtes zijn hierop geschaald. */
    public function grootsteThema(): int
    {
        $totalen = array_map(
            fn (string $thema) => $this->totaalVoorThema($thema),
            array_keys(self::THEMA_LABELS),
        );

        return max([1, ...$totalen]);
    }

    /** Het aantal toepasselijke regels dat nog niet geïmplementeerd is. */
    public function nogNietGeimplementeerd(): int
    {
        $totaal = 0;

        foreach ($this->perThema as $statussen) {
            $totaal += ($statussen['niet_gestart'] ?? 0) + ($statussen['in_uitvoering'] ?? 0);
        }

        return $totaal;
    }

    /** Het aantal toepasselijke regels: alles behalve `nvt`. */
    public function toepasselijk(): int
    {
        $totaal = 0;

        foreach ($this->perThema as $statussen) {
            foreach (self::VOORTGANG as $status) {
                $totaal += $statussen[$status] ?? 0;
            }
        }

        return $totaal;
    }
}
