<?php

namespace App\Support;

use App\Models\Taak;

/**
 * Laat het dossier achter een stappenreeks een stap tegenhouden
 * (implementatie/15 §6).
 *
 * Bestaat omdat een stap op twee schermen af te ronden is: op het dossierscherm
 * van het bronblok én op `/taken`. Een inhoudelijke eis — het terugvalplan van
 * A.8.32 f) bijvoorbeeld — die alleen op het eerste scherm wordt gecontroleerd,
 * is langs het tweede te lopen en dus geen eis.
 *
 * De eenrichtingskoppeling uit `Stappenreeks` blijft hiermee overeind: de
 * engine kent geen dossiersoorten, hij kent deze interface.
 */
interface Stapbelemmering
{
    /**
     * De reden waarom deze stap nu niet voltooid mag worden, of null als er
     * niets in de weg staat. De tekst is bedoeld voor de gebruiker.
     */
    public function belemmeringVoorStap(Taak $stap): ?string;
}
