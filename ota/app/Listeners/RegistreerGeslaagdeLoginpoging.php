<?php

namespace App\Listeners;

use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Auth\Events\Login;

/**
 * Tegenhanger van RegistreerMislukteLoginpoging: legt de geslaagde login vast
 * en werkt laatst_ingelogd_op bij (deelproducten/01-identity-access.md §6:
 * "wie had wanneer toegang" is zelf een Annex A 5.15-5.18-vereiste).
 */
class RegistreerGeslaagdeLoginpoging
{
    public function handle(Login $event): void
    {
        $gebruiker = $event->user;

        if (! $gebruiker instanceof Gebruiker) {
            return;
        }

        Loginpoging::create([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'tijdstip' => now(),
            'succesvol' => true,
            'ip_adres' => request()->ip(),
        ]);

        $gebruiker->forceFill(['laatst_ingelogd_op' => now()])->save();
    }
}
