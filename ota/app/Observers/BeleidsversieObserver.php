<?php

namespace App\Observers;

use App\Models\Beleidsversie;
use App\Support\TaakPlanner;

/**
 * Twee afgeleide gevolgen van een versiewijziging (implementatie/05 §3b en §8):
 * de status van het document, en de herzieningstaak.
 *
 * De herziening is veldgestuurd (`volgende_herziening_gepland` wijzigt) en
 * hoort daarom hier, net als bij ScopeVerklaring. De leesbevestigingstaken zijn
 * tijdgestuurd en zitten juist in `isms:genereer-taken`.
 */
class BeleidsversieObserver
{
    public function saved(Beleidsversie $versie): void
    {
        $this->werkDocumentstatusBij($versie);
        $this->planHerziening($versie);
    }

    public function deleted(Beleidsversie $versie): void
    {
        $this->werkDocumentstatusBij($versie);
    }

    /**
     * De afleidingsregel uit implementatie/05 §3b. `ingetrokken` wint van
     * alles: intrekken is een expliciete daad die niet stilzwijgend ongedaan
     * mag worden gemaakt door een nog actieve oude versie.
     */
    private function werkDocumentstatusBij(Beleidsversie $versie): void
    {
        // Bewust `document()->first()` en niet `$versie->document`: de gecachte
        // relatie is hier aantoonbaar verouderd. Bij het publiceren wijzigen
        // twee versies achter elkaar, en de eerste wijziging verandert de
        // documentstatus in de database — maar niet in een instantie die
        // Beleidspublicatie daarvóór had geladen. Eloquent zag daardoor bij de
        // tweede versie geen wijziging (isDirty() false) en sloeg de update
        // over: het document bleef op `ter_goedkeuring` staan terwijl er een
        // actieve versie was, en verdween zo uit scopeZichtbaar().
        $document = $versie->document()->first();

        if ($document === null) {
            return;
        }

        // Terugzetten zodat aanroepers ná deze observer (en planHerziening
        // hieronder) niet alsnog met de oude instantie verder werken.
        $versie->setRelation('document', $document);

        $statussen = $document->versies()->pluck('status');

        $document->update(['status' => match (true) {
            $document->isIngetrokken() => 'ingetrokken',
            $statussen->contains('actief') => 'actief',
            $statussen->contains('ter_goedkeuring') => 'ter_goedkeuring',
            default => 'concept',
        }]);
    }

    private function planHerziening(Beleidsversie $versie): void
    {
        // Alleen de actieve versie is te herzien. Wordt een versie vervangen,
        // dan verdwijnt de openstaande taak (deadline null = opruimen).
        $deadline = $versie->status === 'actief'
            ? $versie->volgende_herziening_gepland
            : null;

        TaakPlanner::planVoorEntiteit(
            $versie,
            'beleid-herziening',
            'Beleid herzien: '.$versie->auditOmschrijving(),
            $deadline,
            'beleid-maatregelbeheer',
            $versie->document?->eigenaar_id,
        );
    }
}
