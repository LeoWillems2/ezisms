<?php

namespace Database\Factories;

use App\Models\CorrigerendeMaatregel;
use App\Models\Effectiviteitstoets;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Effectiviteitstoets> */
class EffectiviteitstoetsFactory extends Factory
{
    protected $model = Effectiviteitstoets::class;

    public function definition(): array
    {
        return [
            'corrigerende_maatregel_id' => CorrigerendeMaatregel::factory(),
            'uitgevoerd_op' => now(),
            'resultaat' => 'effectief',
            'toelichting' => fake()->sentence(),
        ];
    }

    public function nietEffectief(): static
    {
        return $this->state(fn () => ['resultaat' => 'niet_effectief']);
    }
}
