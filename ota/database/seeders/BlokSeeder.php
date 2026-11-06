<?php

namespace Database\Seeders;

use App\Models\Blok;
use Illuminate\Database\Seeder;

/**
 * De gebouwde blokken. Uit te breiden zodra een volgend blok gebouwd wordt —
 * de code is de deelproduct-bestandsnaam zonder nummer (conventies §3).
 *
 * Eén uitzondering op die regel: `installatiebeheer` heeft geen deelproduct,
 * want het is geen ISMS-domein maar een beheerdomein — het bestaat om technisch
 * beheer buiten het ISMS te kunnen houden (implementatie/01e §0). Een blokcode
 * is hier het enige mechanisme dat "geen enkel ISMS-recht" kan uitdrukken.
 *
 * Een tweede: `assistenttoegang` (implementatie/14b §2.2). Geen domein maar een
 * vergunning — een rij met `lezen` betekent "deze rol mag een AI-assistent
 * koppelen". `RolPermissieSeeder` zet er nooit een rij op; dat doet de CISO.
 */
class BlokSeeder extends Seeder
{
    public function run(): void
    {
        $blokken = [
            ['code' => 'identity-access', 'naam' => 'Identity, Access & Rollen'],
            ['code' => 'context-scope', 'naam' => 'Context & Scope'],
            ['code' => 'asset-classificatie', 'naam' => 'Asset & Informatie-classificatie'],
            ['code' => 'risico-soa', 'naam' => 'Risicomanagement & SoA'],
            ['code' => 'bewijsrepository-audit-trail', 'naam' => 'Bewijsrepository & Audit Trail'],
            ['code' => 'taken-workflow-engine', 'naam' => 'Taken & Workflow'],
            ['code' => 'beleid-maatregelbeheer', 'naam' => 'Beleid & Maatregelbeheer'],
            ['code' => 'incident-afwijkingenbeheer', 'naam' => 'Incident- & Afwijkingenbeheer'],
            ['code' => 'leveranciers-derdenrisico', 'naam' => 'Leveranciers & Derdenrisico'],
            ['code' => 'bewustzijn-training', 'naam' => 'Bewustzijn, Training & Toetsen'],
            ['code' => 'auditmanagement', 'naam' => 'Auditmanagement'],
            ['code' => 'management-review-verbetercyclus', 'naam' => 'Management Review & Verbetercyclus'],
            ['code' => 'notificatie-integratielaag', 'naam' => 'Notificatie & Integratie'],
            ['code' => 'wijzigingsbeheer', 'naam' => 'Wijzigingsbeheer'],
            ['code' => 'installatiebeheer', 'naam' => 'Installatiebeheer'],
            ['code' => 'assistenttoegang', 'naam' => 'Assistenttoegang (MCP)'],
        ];

        foreach ($blokken as $blok) {
            Blok::updateOrCreate(['code' => $blok['code']], $blok);
        }
    }
}
