<?php

namespace Database\Seeders;

use App\Models\Wijzigingssjabloon;
use App\Support\Wijzigingsroutes;
use Illuminate\Database\Seeder;

/**
 * De meegeleverde wijzigingsroutes neerzetten (implementatie/15 §9).
 *
 * **Aanmaken, niet overschrijven.** Dezelfde afweging als bij
 * `KpiDefinitieSeeder` sinds 12e: de CISO beheert deze routes zelf, en
 * `db:seed --force` draait bij elke containerstart mee (`deploy-docker.sh`).
 * Met `updateOrCreate` zou elke aanpassing van de organisatie bij de
 * eerstvolgende herstart stilzwijgend zijn teruggedraaid — en een hernoemde
 * stap zou er als dubbele naast komen te staan, want de sleutel bevat de titel.
 *
 * De stappen horen bij het aanmaken van het sjabloon en worden daarna met rust
 * gelaten. Wie wil terug naar de geleverde route, gebruikt de knop op het
 * beheerscherm; die leest uit dezelfde bron (`Wijzigingsroutes`).
 */
class WijzigingssjabloonSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Wijzigingsroutes::alle() as $route) {
            $stappen = $route['stappen'];
            unset($route['stappen']);

            $sjabloon = Wijzigingssjabloon::firstOrCreate(
                ['naam' => $route['naam']],
                $route + ['actief' => true, 'geleverd' => true],
            );

            // Alleen bij een verse route. Bestond hij al, dan is wat erin staat
            // een besluit van de organisatie.
            if ($sjabloon->wasRecentlyCreated) {
                Wijzigingsroutes::legStappenVast($sjabloon, $stappen);
            }
        }
    }
}
