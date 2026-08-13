<?php

namespace Database\Seeders;

use App\Models\Systeemhartslag;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Seeder;

/**
 * De startlijn van deze installatie (implementatie/00m §3).
 *
 * Bij een verse installatie is er geen historie, dus zou de detectie álles
 * lezen als één groot gat sinds 1970. Daarom krijgt elk gepland commando één rij
 * met `resultaat = 'nulpunt'`: vóór dat moment wordt er niets verwacht.
 *
 * Idempotent — bestaat er al een rij voor een sleutel, dan gebeurt er niets.
 * Daarmee krijgt ook een commando dat later aan `routes/console.php` wordt
 * toegevoegd bij de eerstvolgende `db:seed` zijn eigen startlijn, in plaats van
 * met terugwerkende kracht een gat te melden dat nooit bestond.
 *
 * Werkt op beide uitrolroutes zonder aanpassing: `deploy.sh` en
 * `deploy-docker.sh` draaien allebei `db:seed --force`.
 */
class SysteemhartslagSeeder extends Seeder
{
    public function run(): void
    {
        $nu = now();

        foreach (app(Schedule::class)->events() as $taak) {
            $sleutel = Systeemhartslag::sleutelVoor($taak);

            if ($sleutel === null) {
                continue;
            }

            // Bewust `exists()` op de sleutel en niet `firstOrCreate` op de hele
            // rij: zodra er één echte run staat, is de startlijn niet meer
            // nodig en zou een tweede nulpunt de detectie juist terugzetten.
            if (Systeemhartslag::where('taak_sleutel', $sleutel)->exists()) {
                continue;
            }

            Systeemhartslag::create([
                'taak_sleutel' => $sleutel,
                'weergavenaam' => $taak->getSummaryForDisplay(),
                'gedraaid_op' => $nu,
                'resultaat' => 'nulpunt',
            ]);
        }
    }
}
