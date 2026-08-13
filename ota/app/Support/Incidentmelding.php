<?php

namespace App\Support;

use App\Mail\IncidentGemeld;
use App\Models\Incident;

/**
 * Verzending van de incidentnotificatie (implementatie/08 §7). Sinds blok 14
 * loopt dit via de centrale `NotificatieDispatcher`: die zoekt de ontvangers op
 * (via de actieve regel `incident_gemeld` → CISO) en logt de uitkomst. Deze
 * dunne wrapper blijft bestaan zodat de aanroeper in blok 8 ongewijzigd is.
 */
final class Incidentmelding
{
    public static function meldAanCiso(Incident $incident): void
    {
        NotificatieDispatcher::verzend('incident_gemeld', new IncidentGemeld($incident));
    }
}
