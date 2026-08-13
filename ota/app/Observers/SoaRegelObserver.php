<?php

namespace App\Observers;

use App\Models\Auditobject;
use App\Models\SoaRegel;

/**
 * Houdt de audit-universe live in de pas met de SoA (plan 11b §3). Verklaart de
 * CISO een control van toepassing, dan verschijnt het maatregel-object meteen —
 * geen aparte `isms:sync-auditobjecten` meer nodig om het in de rondescope te
 * zien. Wordt een control uit scope gehaald, dan gaat het object inactief (niet
 * verwijderd: gekoppelde rondes/historie blijven intact).
 *
 * Het drift-signaal "nieuw van toepassing, nog in geen programma" verhuist
 * hiermee naar de dekkingsmatrix en het sync-command; dat gaat over
 * programmadekking, niet over het bestaan van het object.
 */
class SoaRegelObserver
{
    public function saved(SoaRegel $regel): void
    {
        // Alleen reageren als de scope daadwerkelijk wijzigde (of bij aanmaak);
        // een motivatie-edit hoeft de universe niet aan te raken.
        if (! $regel->wasRecentlyCreated && ! $regel->wasChanged('van_toepassing')) {
            return;
        }

        $this->synchroniseer($regel, $regel->van_toepassing === true);
    }

    public function deleted(SoaRegel $regel): void
    {
        // De control valt weg uit de SoA → object inactief.
        $this->synchroniseer($regel, false);
    }

    private function synchroniseer(SoaRegel $regel, bool $vanToepassing): void
    {
        $maatregel = $regel->relationLoaded('maatregel')
            ? $regel->maatregel
            : $regel->maatregel()->first();

        if ($maatregel !== null) {
            Auditobject::synchroniseerMaatregel($maatregel, $vanToepassing);
        }
    }
}
