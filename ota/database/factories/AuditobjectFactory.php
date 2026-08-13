<?php

namespace Database\Factories;

use App\Models\Auditobject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Auditobject> */
class AuditobjectFactory extends Factory
{
    protected $model = Auditobject::class;

    public function definition(): array
    {
        return [
            'soort' => 'clausule',
            'clausule_nummer' => $this->faker->numerify('#.#'),
            'titel' => $this->faker->sentence(3),
            'groep' => '9 Evaluatie',
            'volgorde' => $this->faker->numberBetween(1, 100),
            'actief' => true,
        ];
    }

    public function maatregel(int $maatregelId): static
    {
        return $this->state(fn () => [
            'soort' => 'maatregel',
            'clausule_nummer' => null,
            'titel' => null,
            'maatregel_id' => $maatregelId,
            'groep' => 'A.8 Technologisch',
        ]);
    }

    public function inactief(): static
    {
        return $this->state(fn () => ['actief' => false]);
    }
}
