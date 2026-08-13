<?php

namespace App\Observers;

use App\Models\Verbeteractie;
use App\Support\TaakPlanner;

/**
 * De deadline-bewaking van een verbeteractie via de taken-engine
 * (implementatie/13 §5). Veldgestuurd (deadline/status wijzigt), dus een
 * observer — net als CorrigerendeMaatregelObserver in blok 8.
 */
class VerbeteractieObserver
{
    public function saved(Verbeteractie $verbeteractie): void
    {
        // Voltooid: de handeling is verricht, dus de herinneringstaak sluit —
        // niet omdat de deadline verviel (zelfde onderscheid als blok 5).
        if ($verbeteractie->isVoltooid()) {
            TaakPlanner::voltooiVoorEntiteit(
                $verbeteractie,
                'verbeteractie-herinnering',
                $verbeteractie->eigenaar_id,
            );

            return;
        }

        // Open: plan of verzet de herinnering op de deadline. Een lege deadline
        // ruimt de taak op (de planner regelt dat).
        TaakPlanner::planVoorEntiteit(
            $verbeteractie,
            'verbeteractie-herinnering',
            'Verbeteractie afronden: '.$verbeteractie->auditOmschrijving(),
            $verbeteractie->deadline,
            'management-review-verbetercyclus',
            $verbeteractie->eigenaar_id,
            perEigenaar: true,
        );
    }

    public function deleted(Verbeteractie $verbeteractie): void
    {
        // Verwijderde actie: de herinnering heeft geen doel meer (null-deadline
        // laat de planner de openstaande taak opruimen).
        TaakPlanner::planVoorEntiteit(
            $verbeteractie,
            'verbeteractie-herinnering',
            '',
            null,
            'management-review-verbetercyclus',
            $verbeteractie->eigenaar_id,
            perEigenaar: true,
        );
    }
}
