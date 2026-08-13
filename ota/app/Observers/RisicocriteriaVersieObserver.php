<?php

namespace App\Observers;

use App\Models\RisicocriteriaVersie;
use App\Support\TaakPlanner;

/**
 * Twee dingen die bij elke opgeslagen criteriaversie moeten gebeuren
 * (implementatie/04g §4.6).
 *
 * De activering zelf zit bewust niet hier maar in `ActiveerRisicocriteria`: dat
 * effect is meervoudig (vorige versie vervangen, taken plannen, KPI-versie
 * ophogen) en hoort zichtbaar te zijn op de plek waar het gebeurt. Dit zijn de
 * twee dingen die bij elke save gelden, ongeacht wie er schrijft.
 */
class RisicocriteriaVersieObserver
{
    public function saved(RisicocriteriaVersie $versie): void
    {
        // De gememoiseerde actieve versie is nu mogelijk verouderd. Ook bij een
        // save op een concept: dat concept kan zojuist de actieve zijn geworden.
        RisicocriteriaVersie::vergeet();

        // Alleen de actieve versie is te herzien. Wordt een versie vervangen,
        // dan verdwijnt de openstaande taak (deadline null = opruimen) — zelfde
        // patroon als bij de scope-verklaring.
        $deadline = $versie->status === 'actief'
            ? $versie->volgende_herziening_gepland
            : null;

        TaakPlanner::planVoorEntiteit(
            $versie,
            'risicocriteria-herziening',
            'Risicocriteria v'.$versie->versienummer.' herzien',
            $deadline,
            'risico-soa',
        );
    }

    public function deleted(RisicocriteriaVersie $versie): void
    {
        RisicocriteriaVersie::vergeet();
    }
}
