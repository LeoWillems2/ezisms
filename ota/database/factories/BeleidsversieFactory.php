<?php

namespace Database\Factories;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Beleidsversie> */
class BeleidsversieFactory extends Factory
{
    protected $model = Beleidsversie::class;

    public function definition(): array
    {
        return [
            'beleidsdocument_id' => Beleidsdocument::factory(),
            'versienummer' => 1,
            'status' => 'concept',
        ];
    }

    public function metBestand(): static
    {
        return $this->state(fn () => ['bewijsstuk_id' => Bewijsstuk::factory()]);
    }

    public function terGoedkeuring(): static
    {
        return $this->metBestand()->state(fn () => ['status' => 'ter_goedkeuring']);
    }

    /**
     * Publiceert buiten Beleidspublicatie om — alleen voor tests die een
     * bestaande situatie opzetten, niet als voorbeeld voor productiecode.
     */
    public function actief(): static
    {
        return $this->metBestand()->state(fn () => [
            'status' => 'actief',
            'gepubliceerd_op' => now(),
        ]);
    }
}
