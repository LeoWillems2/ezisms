<?php

namespace Database\Factories;

use App\Models\Auditplan;
use App\Models\Auditprogramma;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditplan> */
class AuditplanFactory extends Factory
{
    protected $model = Auditplan::class;

    public function definition(): array
    {
        return [
            'jaar' => fake()->unique()->numberBetween(2000, 2100),
            'status' => 'concept',
        ];
    }

    /**
     * Hangt het plan onder een programmajaar mét zijn venster. Sinds plan 11c is
     * een jaarplan zonder `programmajaar`/periode een los plan, en dat telt niet
     * mee in de dekkingsmatrix — een test die dat vergeet, meet niets.
     */
    public function voorProgramma(Auditprogramma $programma, int $nummer = 1): static
    {
        $jaar = $programma->programmajaren()[$nummer - 1];

        return $this->state([
            'auditprogramma_id' => $programma->id,
            'programmajaar' => $jaar['nummer'],
            'jaar' => $jaar['start']->year,
            'periode_start' => $jaar['start'],
            'periode_eind' => $jaar['eind'],
        ]);
    }
}
