<?php

namespace Database\Factories;

use App\Models\Taak;
use App\Models\Toetsopdracht;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Uitsluitend bedoeld voor tests — geen referentiedata. Maakt standaard ook zijn
 * eigen taak (de toetsopdracht is 1-op-1 met een taak).
 *
 * @extends Factory<Toetsopdracht>
 */
class ToetsopdrachtFactory extends Factory
{
    protected $model = Toetsopdracht::class;

    public function definition(): array
    {
        return [
            'taak_id' => Taak::factory(),
            'trainingsmodule_id' => null,
            'toets_bestand' => 't1.html',
            'toets_titel' => 'Kennistoets Privacy & Veiligheid',
            'token' => Str::random(64),
            'status' => 'uitgezet',
            'pogingen' => 0,
        ];
    }
}
