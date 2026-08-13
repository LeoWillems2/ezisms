<?php

namespace App\Support;

use App\Models\Risico;

/**
 * De verdeling van het risicoregister over de tolerantiematrix
 * (implementatie/04b §3, 12c §3.4).
 *
 * Bestaat als aparte klasse omdat twee schermen dezelfde telling nodig hebben:
 * de volledige matrix op `/risicos/matrix` en het compacte paneel op het
 * dashboard. Twee kopieën van deze telling lopen op den duur uit elkaar, en het
 * verschil zou nergens opvallen — beide schermen zien er dan even plausibel uit.
 * Een test dwingt af dat ze gelijk blijven (12c §6).
 */
final class Risicoverdeling
{
    /** De hoogste as-waarde; rechtsboven ligt het zwaarste risico. */
    public const SCHAAL = 5;

    /**
     * @param  array<int, array<int, int>>  $tellers  [impact][kans] => aantal
     */
    private function __construct(
        public readonly array $tellers,
        public readonly int $beoordeeld,
        public readonly int $nietBeoordeeld,
    ) {}

    public static function huidige(): self
    {
        // Het register is klein: één query, in PHP groeperen op (kans, impact).
        $beoordeeld = Risico::query()
            ->whereNotNull('kans_niveau')
            ->whereNotNull('impact_niveau')
            ->get(['kans_niveau', 'impact_niveau']);

        $tellers = [];
        foreach ($beoordeeld as $risico) {
            $tellers[$risico->impact_niveau][$risico->kans_niveau] =
                ($tellers[$risico->impact_niveau][$risico->kans_niveau] ?? 0) + 1;
        }

        $nietBeoordeeld = Risico::query()
            ->where(fn ($q) => $q->whereNull('kans_niveau')->orWhereNull('impact_niveau'))
            ->count();

        return new self($tellers, $beoordeeld->count(), $nietBeoordeeld);
    }

    public function aantalIn(int $kans, int $impact): int
    {
        return $this->tellers[$impact][$kans] ?? 0;
    }

    /**
     * Het aantal beoordeelde risico's boven de acceptatiedrempel. Afgeleid uit
     * dezelfde tellers en niet uit een eigen query, zodat het getal onder de
     * matrix niet los kan lopen van de cellen erin.
     */
    public function bovenDrempel(): int
    {
        $drempel = Risico::drempelwaarde();
        $totaal = 0;

        foreach ($this->tellers as $impact => $perKans) {
            foreach ($perKans as $kans => $aantal) {
                if ($kans * $impact > $drempel) {
                    $totaal += $aantal;
                }
            }
        }

        return $totaal;
    }
}
