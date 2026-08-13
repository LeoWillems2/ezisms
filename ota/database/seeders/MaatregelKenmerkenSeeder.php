<?php

namespace Database\Seeders;

use App\Models\Maatregel;
use Illuminate\Database\Seeder;

/**
 * Vult de meegeleverde **uitgangsclassificatie** op de bestaande maatregelen:
 * per maatregel de dimensies uit `config/maatregelkenmerken.php`.
 *
 * Dit is een startwaarde, geen normtabel. Elke organisatie stelt in het ISMS
 * haar eigen classificatie vast; die leeft op de SoA-regel (plan 04d) en wordt
 * hier dus nooit aangeraakt. Deze seeder werkt uitsluitend op `maatregelen` —
 * dat is wat het mogelijk maakt dat `deploy.sh` hem bij elke deploy draait
 * zonder klantdata te overschrijven. Houd dat zo.
 *
 * Bewust een aparte seeder met een eigen databestand, los van MaatregelSeeder.
 * De maatregelbestanden zijn er één per normprofiel en worden op een installatie
 * met de norm bewerkt (04f); de kenmerken gelden voor beide profielen en komen
 * van ons. Zaten ze in die bestanden, dan zou de ene set ze bij een profielwissel
 * kwijtraken en zou een handmatige bewerking ze kunnen raken.
 *
 * Draait ná MaatregelSeeder (de maatregelen moeten bestaan) en is idempotent:
 * opnieuw draaien werkt de kenmerken bij zonder iets anders te raken.
 *
 * Let op: deze seeder schrijft alleen. Wijzigt een meegeleverde uitgangswaarde,
 * dan bereikt die correctie niet de SoA-regels met een eigen classificatie —
 * `kenmerken_eigen` is alles-of-niets. Het opmerken daarvan zit in
 * `isms:kenmerken` (04f §4.1) en bewust niet hier: een taak aanmaken is
 * operationele data, en `DatabaseSeeder` levert uitsluitend referentiedata.
 */
class MaatregelKenmerkenSeeder extends Seeder
{
    public function run(): void
    {
        $pad = database_path('seeders/data/maatregel-kenmerken.json');

        if (! is_file($pad)) {
            $this->command?->warn('Geen maatregel-kenmerken.json — kenmerken overgeslagen.');

            return;
        }

        // Het bestand heeft een `_over`-kopblok dat uitlegt wát het is (een
        // uitgangspunt, geen normtabel); de data zelf staat onder `regels`.
        $data = json_decode(file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR);
        $regels = $data['regels'] ?? [];

        $bijgewerkt = 0;

        foreach ($regels as $regel) {
            // Alleen bestaande maatregelen krijgen kenmerken; een referentie
            // zonder maatregel (andere tekstbron, afwijkende set) slaan we stil
            // over in plaats van een lege maatregel aan te maken.
            $maatregel = Maatregel::where('annex_a_referentie', $regel['annex_a_referentie'])->first();

            if ($maatregel === null) {
                continue;
            }

            // Samenvoegen en niet overschrijven: dimensies die dit bestand niet
            // levert, blijven staan. Concreet gaat het om `capaciteiten`, die een
            // installatie met de norm zelf heeft gevuld via isms:capaciteiten —
            // een plat overschrijven zou die bij elke deploy weggooien.
            $maatregel->update([
                'kenmerken' => array_merge($maatregel->kenmerken ?? [], $regel['kenmerken']),
            ]);

            $bijgewerkt++;
        }

        $this->command?->info("Kenmerken geseed op {$bijgewerkt} maatregelen.");
    }
}
