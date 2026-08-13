<?php

namespace App\Observers;

use App\Models\Taak;
use App\Support\Stapbelemmering;
use App\Support\StapGeblokkeerd;
use App\Support\Stappenreeks;

/**
 * Schuift een stappenreeks door zodra een stap voltooid raakt
 * (implementatie/07b §7).
 *
 * Bewust een observer en geen regel op het scherm: een taak wordt op minstens
 * vier plekken voltooid — het takenscherm, de toetscallback,
 * `TaakPlanner::voltooiVoorEntiteit()` en straks het dossierscherm van blok 15.
 * Die regel op elk van die plekken herhalen is dezelfde faalvorm als in 06b §6:
 * iemand voegt een vijfde plek toe en de reeks blijft stilstaan zonder dat er
 * een fout uit komt.
 */
class TaakObserver
{
    /**
     * Vraagt het dossier of deze stap nú voltooid mag worden
     * (implementatie/15 §6).
     *
     * Moet `updating` zijn en niet `updated`: na afloop is er niets meer tegen
     * te houden. De engine kent geen dossiersoorten — hij kent de interface.
     */
    public function updating(Taak $taak): void
    {
        if (! $taak->isStap() || ! $taak->isDirty('status') || $taak->status !== 'voltooid') {
            return;
        }

        $dossier = $taak->entiteit;

        if (! $dossier instanceof Stapbelemmering) {
            return;
        }

        $belemmering = $dossier->belemmeringVoorStap($taak);

        if ($belemmering !== null) {
            throw new StapGeblokkeerd($belemmering);
        }
    }

    public function updated(Taak $taak): void
    {
        // Losse taken kennen geen reeks.
        if (! $taak->isStap()) {
            return;
        }

        // Geen recursie: het activeren van de volgende groep zet stappen van
        // `wachtend` naar `open`, en die overgang voldoet hier niet aan.
        if (! $taak->wasChanged('status') || $taak->status !== 'voltooid') {
            return;
        }

        Stappenreeks::naVoltooiing($taak);
    }
}
