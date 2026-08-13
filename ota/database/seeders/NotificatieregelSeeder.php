<?php

namespace Database\Seeders;

use App\Models\Notificatieregel;
use Illuminate\Database\Seeder;

/**
 * De startregels van de notificatielaag (implementatie/14 §10).
 * Referentiedata: draait in beide omgevingen en is idempotent
 * (`updateOrCreate` op gebeurtenis_type + ontvanger_rol).
 *
 * Dit behoudt bestaand gedrag — met name de incident-mail, die vóór blok 14
 * altijd naar de CISO ging — en toont meteen de bekende event-types. De CISO kan
 * ze vrij aan-/uitzetten en aanvullen.
 */
class NotificatieregelSeeder extends Seeder
{
    public function run(): void
    {
        // ontvanger_rol null = de betrokkene uit de gebeurtenis (§5). Alleen
        // 'training_verloopt' richt zich op de betrokken gebruiker; de rest gaat
        // naar de CISO. 'review_termijn_verstreken' staat klaar, maar de
        // uitzender komt uit blok 13 zodra dat een sweep heeft.
        $regels = [
            ['gebeurtenis_type' => 'incident_gemeld', 'ontvanger_rol' => 'CISO'],
            ['gebeurtenis_type' => 'taak_geescaleerd', 'ontvanger_rol' => 'CISO'],
            ['gebeurtenis_type' => 'training_verloopt', 'ontvanger_rol' => null],
            ['gebeurtenis_type' => 'review_termijn_verstreken', 'ontvanger_rol' => 'CISO'],
            // Naar de betrokkene: het is zijn account en hij lost het zelf op
            // (implementatie/01d §9).
            ['gebeurtenis_type' => 'tweefactor_deadline', 'ontvanger_rol' => null],
            // Naar de eigenaar van de stap die aan de beurt komt; zonder dit
            // bericht is een stappenreeks in de praktijk een wachtrij
            // (implementatie/07b §9).
            ['gebeurtenis_type' => 'stap_actueel', 'ontvanger_rol' => null],
        ];

        foreach ($regels as $regel) {
            Notificatieregel::updateOrCreate(
                ['gebeurtenis_type' => $regel['gebeurtenis_type'], 'ontvanger_rol' => $regel['ontvanger_rol']],
                ['actief' => true],
            );
        }
    }
}
