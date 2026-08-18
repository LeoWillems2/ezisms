<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Uitsluitend referentiedata (rollen, blokken, rechtenmatrix). Deze hoort in
 * beide omgevingen te draaien — zie implementatie/00-stack-en-conventies.md §1.
 * Eventuele test-/demodata komt in een aparte seeder die hier niet in staat.
 *
 * Het eerste CISO-account wordt bewust niet geseed (dat zou een vast
 * wachtwoord in versiebeheer betekenen); gebruik `php artisan isms:eerste-ciso`.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Als eerste: MaatregelSeeder kiest zijn bronbestanden op het
            // normprofiel, dus dat moet dan al vastliggen.
            NormprofielSeeder::class,
            RolSeeder::class,
            BlokSeeder::class,
            RolPermissieSeeder::class,
            ClassificatieschemaSeeder::class,
            MaatregelSeeder::class,
            MaatregelKenmerkenSeeder::class,
            // Ná MaatregelSeeder: de overheidsmaatregelen hangen aan de
            // beheersmaatregelen én aan hun SoA-regels, dus die moeten er zijn.
            // Doet niets buiten een BIO-installatie.
            OverheidsmaatregelSeeder::class,
            RisicocriteriaSeeder::class,
            TaaksjabloonSeeder::class,
            KpiDefinitieSeeder::class,
            NotificatieregelSeeder::class,
            AuditobjectClausuleSeeder::class,
            WijzigingssjabloonSeeder::class,
            // Als laatste: de startlijn van de schedulerbewaking
            // (implementatie/00m §3). Hij hangt niet aan de andere seeders, maar
            // hoort wél ná de migraties én ná een eventuele verse vulling te
            // vallen — dan staat het nulpunt op het moment dat deze installatie
            // echt begon te draaien.
            SysteemhartslagSeeder::class,
        ]);
    }
}
