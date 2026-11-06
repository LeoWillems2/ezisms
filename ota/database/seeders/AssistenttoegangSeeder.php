<?php

namespace Database\Seeders;

use App\Models\IntegratieAdapter;
use App\Support\Assistent\Configuratie;
use Illuminate\Database\Seeder;

/**
 * De assistenttoegang in het integratieregister (implementatie/14b §8.3), zoals
 * 01j dat ook voor de identiteitsprovider voorschrijft: het register zegt
 * dát de koppeling bestaat, niet hoe hij werkt.
 *
 * Een seeder en geen handeling in een scherm, omdat het aan- of uitzetten in
 * `.env` gebeurt en er dus geen moment in de applicatie is om op te haken.
 * `db:seed` draait bij elke deploy; daarna volgt het register de instelling.
 * Staat de functie uit en is er nooit een rij geweest, dan gebeurt er niets.
 *
 * `laatste_synchronisatie_op` blijft leeg. Bijhouden per aanroep zou elke
 * opvraging een geauditeerde wijziging van de adapter maken; de aanroeplog is
 * de bron (14b §7).
 */
class AssistenttoegangSeeder extends Seeder
{
    public const NAAM = 'Assistenttoegang (MCP)';

    public function run(): void
    {
        $adapter = IntegratieAdapter::query()->where('naam', self::NAAM)->first();

        if (Configuratie::isAan()) {
            $adapter === null
                ? IntegratieAdapter::create(['naam' => self::NAAM, 'type' => 'overig', 'status' => 'actief'])
                : ($adapter->status !== 'actief' ? $adapter->update(['status' => 'actief']) : null);

            return;
        }

        if ($adapter !== null && $adapter->status !== 'inactief') {
            $adapter->update(['status' => 'inactief']);
        }
    }
}
