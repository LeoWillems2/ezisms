<?php

namespace App\Observers;

use App\Models\Taak;
use App\Support\Stappenreeks;
use App\Support\Taakbelemmering;
use App\Support\TaakGeblokkeerd;

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
     * Vraagt het bronblok of deze taak nú voltooid mag worden
     * (implementatie/15 §6, verbreed in 05b §6).
     *
     * Moet `updating` zijn en niet `updated`: na afloop is er niets meer tegen
     * te houden. De engine kent geen dossiersoorten — hij kent de interface.
     *
     * Sinds 05b geldt dit voor élke gekoppelde taak en niet alleen voor een
     * stap uit een reeks. Aanleiding: een beheerde taak (`soort` ≠ null) is met
     * de hand af te vinken terwijl de handeling waar hij over gaat niet is
     * verricht — de knop doet dan niets zichtbaars en de sweep zet de taak de
     * volgende nacht gewoon terug. Dat is dezelfde faalvorm als in §6: een
     * controle die je langs een tweede knop kunt lopen.
     */
    public function updating(Taak $taak): void
    {
        if (! $taak->isDirty('status') || $taak->status !== 'voltooid') {
            return;
        }

        $dossier = $taak->entiteit;

        if (! $dossier instanceof Taakbelemmering) {
            return;
        }

        $belemmering = $dossier->belemmeringVoorTaak($taak);

        if ($belemmering !== null) {
            throw new TaakGeblokkeerd($belemmering);
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
