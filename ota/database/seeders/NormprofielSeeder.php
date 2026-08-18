<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Legt vast welke norm deze installatie volgt (implementatie/00h §7).
 *
 * Draait als eerste van alle seeders: `MaatregelSeeder` kiest zijn bronbestanden
 * op basis van het profiel, dus dat moet er dan al staan.
 *
 * **Schrijft alleen als de rij nog niet bestaat.** De norm is een eigenschap van
 * de installatie en geen instelling die je bij elke deploy opnieuw bevestigt; een
 * `deploy.sh` die de seeders herhaalt mag hem dus nooit stilzwijgend omzetten
 * omdat er toevallig een andere `ISMS_NORM` in de omgeving staat. Wisselen kan
 * alleen door de database opnieuw op te bouwen — `php artisan migrate:fresh --seed`
 * — en dat is precies de bedoeling: bij een wissel klopt de hele SoA niet meer.
 *
 * De keuze komt uit `config('norm.keuze')` en niet rechtstreeks uit `env()`: op
 * een uitgerolde installatie is de configuratie gecached en leest Laravel `.env`
 * dan niet meer. Zie de toelichting in `config/norm.php`.
 */
class NormprofielSeeder extends Seeder
{
    public function run(): void
    {
        $bestaand = DB::table('normprofiel')->value('profiel');

        if ($bestaand !== null) {
            $this->command?->info("Normprofiel stond al vast: {$bestaand}.");

            return;
        }

        // De uitgeleverde standaard staat hier en niet in config/norm.php: dáár
        // is het een doorgeefluik van .env, hier is het de beslissing wat een
        // installatie zonder uitgesproken keuze wordt.
        //
        // De terugval is er voor wie geen keuze uitspreekt, en die blijft. Maar hij
        // moet zichtbaar zijn: een ontbrekende `ISMS_NORM` was tot 17-08-2026 niet
        // te onderscheiden van een bewuste keuze voor ISO, en dan legt de seeder
        // stilzwijgend het verkeerde profiel vast. Dat is onomkeerbaar — deze rij
        // wordt nooit meer omgezet — en het kostte twee keer een halve
        // BIO-installatie voordat iemand het opmerkte.
        $keuze = config('norm.keuze');
        $gekozen = (string) ($keuze ?: 'iso27001');
        $bekend = array_keys(config('norm.profielen', []));

        if (blank($keuze)) {
            $this->command?->warn(
                "ISMS_NORM is leeg; deze installatie wordt vastgelegd op de standaard '{$gekozen}'. "
                .'Is dat niet de bedoeling, breek dan nu af: een vastgelegd profiel wordt nooit '
                .'omgezet en van norm wisselen betekent de database opnieuw opbouwen. Let op dat '
                .'`artisan config:cache` vóór `db:seed` draait — staat ISMS_NORM alleen in .env en is '
                .'de configuratie al gecached, dan komt hij hier niet aan.'
            );
        }

        // Weigeren en niet terugvallen: `ISMS_NORM=nen7501` (typefout) mag geen
        // ISO-installatie opleveren waar een zorginstelling om NEN 7510 vroeg.
        // Dit is het enige moment waarop die waarde nog te corrigeren is.
        if (! in_array($gekozen, $bekend, true)) {
            throw new RuntimeException(
                "Onbekend normprofiel '{$gekozen}' in ISMS_NORM. Geldige waarden: "
                .implode(', ', $bekend).'.'
            );
        }

        DB::table('normprofiel')->insert([
            'profiel' => $gekozen,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info("Normprofiel vastgelegd: {$gekozen}.");
    }
}
