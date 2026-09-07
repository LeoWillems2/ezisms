<?php

namespace App\Support;

use App\Models\Taak;

/**
 * Laat het bronblok achter een taak die taak tegenhouden (implementatie/15 §6,
 * verbreed in 05b §6).
 *
 * Bestaat omdat een stap op twee schermen af te ronden is: op het dossierscherm
 * van het bronblok én op `/taken`. Een inhoudelijke eis — het terugvalplan van
 * A.8.32 f) bijvoorbeeld — die alleen op het eerste scherm wordt gecontroleerd,
 * is langs het tweede te lopen en dus geen eis.
 *
 * Heette tot 05b `Stapbelemmering` en gold alleen voor stappen uit een reeks.
 * Een beheerde taak kent hetzelfde probleem in een scherpere vorm: daar is de
 * knop *Voltooien* niet alleen langs de eis te lopen, hij doet ook niets — de
 * status is een afgeleide van het bronblok, dus de sweep zet hem terug. Dat is
 * dezelfde interface met een breder bereik, geen tweede mechanisme.
 *
 * De eenrichtingskoppeling uit `Stappenreeks` blijft hiermee overeind: de
 * engine kent geen dossiersoorten, hij kent deze interface.
 */
interface Taakbelemmering
{
    /**
     * De reden waarom deze taak nu niet voltooid mag worden, of null als er
     * niets in de weg staat. De tekst is bedoeld voor de gebruiker en hoort te
     * zeggen wat er dan wél moet gebeuren.
     */
    public function belemmeringVoorTaak(Taak $taak): ?string;
}
