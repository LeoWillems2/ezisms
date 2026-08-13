<?php

namespace Database\Factories;

use App\Models\Gebruiker;
use App\Models\Notificatie;
use App\Models\Notificatieregel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notificatie> */
class NotificatieFactory extends Factory
{
    protected $model = Notificatie::class;

    public function definition(): array
    {
        return [
            'notificatieregel_id' => Notificatieregel::factory(),
            'gebeurtenis_type' => fake()->slug(2),
            'gebruiker_id' => Gebruiker::factory(),
            'gegenereerd_op' => now(),
            'verzonden_op' => now(),
            'resultaat' => 'succes',
            'fout' => null,
        ];
    }

    public function mislukt(): static
    {
        return $this->state(fn () => [
            'verzonden_op' => null,
            'resultaat' => 'fout',
            'fout' => 'Mailserver onbereikbaar',
        ]);
    }
}
