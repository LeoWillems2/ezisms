<?php

namespace App\Listeners;

use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Auth\Events\Failed;

/**
 * Schrijft elke mislukte inlogpoging weg voor de audit trail (blok 5.1) en
 * blokkeert het account bij te veel pogingen binnen het tijdvenster.
 * Zie implementatie/01-identity-access.md §7 en §9.
 */
class RegistreerMislukteLoginpoging
{
    /** Drempel en tijdvenster: bevestigde keuze, implementatie/01 §9. */
    private const MAX_POGINGEN = 5;

    private const VENSTER_MINUTEN = 15;

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '';
        $gebruiker = $event->user instanceof Gebruiker ? $event->user : null;

        Loginpoging::create([
            'gebruiker_id' => $gebruiker?->id,
            'email_ingevoerd' => $email,
            'tijdstip' => now(),
            'succesvol' => false,
            // Onderscheid met een mislukte tweede factor (01d §7c): wachtwoord
            // goed maar code fout is het signaal dat een wachtwoord gelekt is.
            'reden' => 'wachtwoord',
            'ip_adres' => request()->ip(),
        ]);

        if (! $gebruiker || $gebruiker->status !== 'actief') {
            return;
        }

        $recenteMislukkingen = Loginpoging::where('email_ingevoerd', $email)
            ->where('succesvol', false)
            ->where('tijdstip', '>=', now()->subMinutes(self::VENSTER_MINUTEN))
            ->count();

        if ($recenteMislukkingen >= self::MAX_POGINGEN) {
            // `door` is null: dit is het systeem, niet de CISO. Dat ene lege
            // veld is het verschil met een handmatige blokkade, en bepaalt de
            // melding op het loginscherm (implementatie/01f §6).
            $gebruiker->blokkeer(door: null);
        }
    }
}
