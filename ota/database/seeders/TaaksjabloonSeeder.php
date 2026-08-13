<?php

namespace Database\Seeders;

use App\Models\Taaksjabloon;
use Illuminate\Database\Seeder;

/**
 * Referentiedata (implementatie/07 §10): zeven terugkerende taken die
 * rechtstreeks uit de norm volgen. Draait dus ook in productie.
 *
 * `standaard_eigenaar_id` blijft leeg — dat is organisatie-specifiek, net als
 * de omgangsregels bij het classificatieschema in blok 3.
 *
 * De laatste drie zijn op 29-07-2026 toegevoegd vanuit het saasdemo-traject.
 * Bewust hier en niet in een demo-seeder: het zijn taken die elke organisatie
 * met een ISMS voert, dus bestaande installaties krijgen ze er bij de
 * eerstvolgende deploy bij.
 */
class TaaksjabloonSeeder extends Seeder
{
    public function run(): void
    {
        $sjablonen = [
            ['naam' => 'Directiebeoordeling scope', 'herhaling' => 'jaarlijks', 'bron_blok' => 'context-scope',
                'omschrijving' => 'Beoordeel of de vastgestelde scope nog aansluit op de organisatie (§4.3).'],
            ['naam' => 'Herbeoordeling risicoregister', 'herhaling' => 'per_kwartaal', 'bron_blok' => 'risico-soa',
                'omschrijving' => 'Loop het risicoregister na op nieuwe, gewijzigde en vervallen risico\'s (§6.1).'],
            ['naam' => 'Review asset-classificatie', 'herhaling' => 'jaarlijks', 'bron_blok' => 'asset-classificatie',
                'omschrijving' => 'Controleer of de C/I/B-classificaties nog kloppen (Annex A 5.12).'],
            ['naam' => 'Controle toegangsrechten', 'herhaling' => 'per_kwartaal', 'bron_blok' => 'identity-access',
                'omschrijving' => 'Controleer rollen en accounts op juistheid (Annex A 5.18).'],
            ['naam' => 'Hersteltest back-up', 'herhaling' => 'jaarlijks', 'bron_blok' => 'bewijsrepository-audit-trail',
                'omschrijving' => 'Zet een back-up daadwerkelijk terug en leg de uitkomst vast als bewijs '
                    .'(Annex A 8.13). Een back-up die nooit is teruggezet is geen aangetoonde maatregel.'],
            ['naam' => 'Leveranciersbeoordeling', 'herhaling' => 'jaarlijks', 'bron_blok' => 'leveranciers-derdenrisico',
                'omschrijving' => 'Beoordeel de dienstverlening en beveiligingsafspraken van elke leverancier '
                    .'in scope (Annex A 5.22).'],
            ['naam' => 'Patch- en kwetsbaarhedenronde', 'herhaling' => 'maandelijks', 'bron_blok' => 'beleid-maatregelbeheer',
                'omschrijving' => 'Inventariseer bekende kwetsbaarheden, bepaal de prioriteit en voer de '
                    .'openstaande updates door (Annex A 8.8).'],
        ];

        foreach ($sjablonen as $sjabloon) {
            Taaksjabloon::updateOrCreate(['naam' => $sjabloon['naam']], $sjabloon);
        }
    }
}
