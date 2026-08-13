<?php

namespace App\Observers;

use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Support\TaakPlanner;

/**
 * De risicoscore is een puur afgeleid veld (kans x impact) en hoort daarom in
 * een observer — net als de statusafleiding bij Asset in blok 3, en anders dan
 * de bewuste, meervoudige scope-activering in blok 2 (die is een actie).
 */
class RisicoObserver
{
    public function saving(Risico $risico): void
    {
        $risico->risicoscore = ($risico->kans_niveau && $risico->impact_niveau)
            ? $risico->kans_niveau * $risico->impact_niveau
            : null;

        // Het beoordelingsmoment stempelen met het kader dat op dat moment gold
        // (04g §2.6a). Alleen bij een wijziging van kans of impact: bij elke save
        // zou een titelwijziging het beoordelingsmoment verzetten, en dan is de
        // stempel weer een functie van vandaag in plaats van van toen.
        //
        // Hier en niet in `RisicoDetail::opslaanBeoordeling()`, omdat de score
        // hier ook vandaan komt en de demo-simulatie langs dezelfde weg schrijft.
        if ($risico->risicoscore !== null
            && ($risico->isDirty('kans_niveau') || $risico->isDirty('impact_niveau'))) {
            $risico->risicocriteria_versie_id = RisicocriteriaVersie::actief()?->id;
        }
    }

    /**
     * Het veldgestuurde herbeoordelingssignaal wordt een taak (blok 7 §7).
     * Leeggemaakte datum = taak opruimen; dat handelt de planner af.
     */
    public function saved(Risico $risico): void
    {
        TaakPlanner::planVoorEntiteit(
            $risico,
            'risico-herbeoordeling',
            'Risico herbeoordelen: '.$risico->titel,
            $risico->volgende_beoordeling_gepland,
            'risico-soa',
            $risico->risico_eigenaar_id,
        );
    }
}
