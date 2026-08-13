<?php

namespace Database\Factories;

use App\Models\Notificatieregel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notificatieregel> */
class NotificatieregelFactory extends Factory
{
    protected $model = Notificatieregel::class;

    public function definition(): array
    {
        return [
            'gebeurtenis_type' => fake()->unique()->slug(2),
            'ontvanger_rol' => 'CISO',
            'actief' => true,
        ];
    }

    public function inactief(): static
    {
        return $this->state(fn () => ['actief' => false]);
    }

    /** Regel zonder vaste rol: de ontvanger komt uit de gebeurteniscontext. */
    public function betrokkene(): static
    {
        return $this->state(fn () => ['ontvanger_rol' => null]);
    }
}
