<?php

namespace App\Support;

use App\Models\Gebruiker;
use App\Models\Notificatie;
use App\Models\Notificatieregel;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Het centrale verzendpunt van de notificatielaag (implementatie/14 §4). Alle
 * event-uitzenders roepen deze aan in plaats van zelf ontvangers op te zoeken:
 * de aanroeper stelt de Mailable samen (die heeft de context), de dispatcher
 * bepaalt *wie* het krijgt en logt de uitkomst.
 */
final class NotificatieDispatcher
{
    /**
     * @param  Collection<int, Gebruiker>  $contextOntvangers  de betrokkene(n)
     *                                                         uit de gebeurtenis, voor regels zonder vaste ontvanger-rol.
     */
    public static function verzend(
        string $gebeurtenisType,
        Mailable $mail,
        Collection $contextOntvangers = new Collection,
    ): void {
        $regels = Notificatieregel::query()
            ->where('gebeurtenis_type', $gebeurtenisType)
            ->where('actief', true)
            ->get();

        // Geen actieve regel → de gebeurtenis is bewust niet geconfigureerd.
        foreach ($regels as $regel) {
            $ontvangers = $regel->ontvanger_rol !== null
                ? self::actieveGebruikersMetRol($regel->ontvanger_rol)
                : $contextOntvangers;

            foreach (self::uniek($ontvangers) as $ontvanger) {
                self::verzendAan($ontvanger, $gebeurtenisType, $regel, $mail);
            }
        }
    }

    private static function verzendAan(
        Gebruiker $ontvanger,
        string $gebeurtenisType,
        Notificatieregel $regel,
        Mailable $mail,
    ): void {
        $notificatie = Notificatie::create([
            'notificatieregel_id' => $regel->id,
            'gebeurtenis_type' => $gebeurtenisType,
            'gebruiker_id' => $ontvanger->id,
            'gegenereerd_op' => now(),
            'resultaat' => 'fout', // pessimistisch: pas 'succes' na verzenden
        ]);

        try {
            Mail::to($ontvanger->email)->send($mail);
            $notificatie->update(['verzonden_op' => now(), 'resultaat' => 'succes', 'fout' => null]);
        } catch (Throwable $e) {
            // Bewust gevangen en niet doorgegooid — zelfde afweging als de
            // oorspronkelijke Incidentmelding: een onbereikbare mailserver mag de
            // primaire handeling (incident registreren, taak escaleren) niet
            // blokkeren. De mislukking is terug te zien in de log en de
            // rapportage, niet als een crash (implementatie/14 §4).
            //
            // Let op: dit vangt een *fout* af, geen *traagheid*. Een mailserver
            // die niet antwoordt levert geen exception op maar blijft hangen, en
            // omdat hier synchroon wordt verzonden hangt de handelende gebruiker
            // mee. Daarom staat er een korte timeout op de SMTP-transport
            // (`config/mail.php`); die zet traagheid om in een fout die hier
            // wél terechtkomt.
            $notificatie->update(['resultaat' => 'fout', 'fout' => $e->getMessage()]);
            Log::error('Notificatie niet verzonden', [
                'gebeurtenis_type' => $gebeurtenisType,
                'gebruiker_id' => $ontvanger->id,
                'fout' => $e->getMessage(),
            ]);
        }
    }

    /** @return Collection<int, Gebruiker> */
    private static function actieveGebruikersMetRol(string $rol): Collection
    {
        return Gebruiker::query()
            ->where('status', 'actief')
            ->whereHas('rollen', fn ($q) => $q->where('naam', $rol))
            ->get();
    }

    /**
     * Eén mail per gebruiker, ook als meerdere regels of context-ontvangers
     * dezelfde persoon opleveren.
     *
     * @param  Collection<int, Gebruiker>  $ontvangers
     * @return Collection<int, Gebruiker>
     */
    private static function uniek(Collection $ontvangers): Collection
    {
        return $ontvangers->unique('id')->values();
    }
}
