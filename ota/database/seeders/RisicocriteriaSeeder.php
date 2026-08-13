<?php

namespace Database\Seeders;

use App\Models\RisicocriteriaVersie;
use App\Support\Normprofiel;
use Illuminate\Database\Seeder;

/**
 * Versie 1 van de risicocriteria (ISO 27001 §6.1.2 a) — implementatie/04g §7.
 *
 * Bij een 5x5-matrix loopt de score van 1 t/m 25; 15 is de rode grens waarboven
 * acceptatie een expliciete, met naam vastgelegde beslissing vereist, 10 de
 * amber-waarschuwingsgrens. De tien niveaudefinities komen uit
 * `config/beoordelingsschaal.php`, met het normprofiel van deze installatie als
 * sleutel voor de impact-as.
 *
 * **Uitgeleverd als vastgestelde versie 1, zonder goedkeurder.** Dat is bewust
 * de zwakste vorm: `goedgekeurd_door` blijft leeg, zodat op het scherm en in de
 * export te zien is dat dit kader nog van niemand is. De organisatie maakt er
 * met een tweede versie haar eigen kader van.
 *
 * `firstOrCreate` op `versienummer = 1` houdt de seeder idempotent én zorgt dat
 * een herseed een door de organisatie aangepast kader niet overschrijft — ook
 * niet de niveaus, want die worden alleen bij het aanleggen geschreven.
 */
class RisicocriteriaSeeder extends Seeder
{
    public function run(): void
    {
        if (RisicocriteriaVersie::where('versienummer', 1)->exists()) {
            return;
        }

        $schaal = config('beoordelingsschaal');
        $profiel = Normprofiel::actief();

        // Een profiel zonder impactschaal gooit hier, en niet tijdens het
        // draaien: stil terugvallen op ISO zou een zorginstallatie laten scoren
        // op een schaal die de cliënt niet noemt. Dit is het moment waarop dat
        // nog te corrigeren is (zelfde faalrichting als NormprofielSeeder).
        if (! isset($schaal['impact']['profielen'][$profiel])) {
            throw new \RuntimeException(
                "De impactschaal heeft geen definitie voor normprofiel '{$profiel}'. Aanwezig: "
                .implode(', ', array_keys($schaal['impact']['profielen'])).'.'
            );
        }

        $impact = $schaal['impact']['profielen'][$profiel];

        $versie = RisicocriteriaVersie::create([
            'versienummer' => 1,
            'status' => 'actief',
            'omschrijving' => 'Risico\'s met een score boven de acceptatiedrempel vallen buiten de '
                .'risicobereidheid (risk appetite) en vereisen een expliciete, vastgelegde acceptatie.',
            'drempelwaarde_score' => 15,
            'waarschuwingsdrempel_score' => 10,
            'leidraad_kans' => $schaal['kans']['leidraad'],
            'leidraad_impact' => $impact['leidraad'],
            'geldig_vanaf' => now(),
        ]);

        foreach (['kans' => $schaal['kans']['niveaus'], 'impact' => $impact['niveaus']] as $as => $niveaus) {
            foreach ($niveaus as $niveau => $inhoud) {
                $versie->niveaus()->create([
                    'as' => $as,
                    'niveau' => $niveau,
                    'naam' => $inhoud['naam'],
                    'omschrijving' => $inhoud['omschrijving'],
                    // Leeg, en dat is de bedoeling: het ISMS levert geen
                    // omzetpercentage mee (04g §2.3).
                    'kwantitatieve_band' => null,
                ]);
            }
        }

        RisicocriteriaVersie::vergeet();
    }
}
