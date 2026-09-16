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

        // Langs welke weg: het event zegt het niet, dus zet de externe callback
        // het in de sessie (01j §5.3). Bij een challenge ligt deze login een
        // verzoek later dan de callback, en daarom de sessie en geen attribuut
        // op het verzoek.
        $methode = request()->hasSession()
            ? request()->session()->pull('inloggen.methode', 'wachtwoord')
            : 'wachtwoord';

        Loginpoging::create([
            'gebruiker_id' => $gebruiker->id,
            'email_ingevoerd' => $gebruiker->email,
            'tijdstip' => now(),
            'succesvol' => true,
            'methode' => $methode,
            // Wat het ISMS op dat moment geloofde, niet wat de IdP deed (§7.3).
            // Een wijziging in .env staat niet in de audit trail; dit wel.
            'mfa_bij_idp' => $methode === 'extern' ? (bool) config('tweefactor.idp_dwingt_af') : null,
            'ip_adres' => request()->ip(),
        ]);

        $gebruiker->forceFill(['laatst_ingelogd_op' => now()])->save();
    }
}
