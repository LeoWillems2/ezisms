<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bewaakt dat koppelwijzigingen vanaf een scherm via `App\Support\Koppeling`
 * lopen (implementatie/06b §6).
 *
 * Een `sync()` op een relatie raakt de attributen van het model niet aan, dus
 * `Auditeerbaar` vuurt niet en er wordt niets gelogd. Dat is niet zichtbaar in
 * het scherm en niet met terugwerkende kracht te repareren: precies de fout die
 * een wikkel alleen niet tegenhoudt, want een wikkel kun je overslaan. Deze test
 * vangt de enige variant die er echt toe doet — iemand bouwt een koppelscherm en
 * denkt niet aan de trail.
 *
 * Geen uitzonderingenlijst: zodra die er is, groeit hij.
 *
 * Bewust een Unit-test (geen DB, geen app-bootstrap): het leest alleen bestanden.
 * Precedent: SuiteDekkingTest.
 */
class KoppelingenWordenGelogdTest extends TestCase
{
    /** De relatiemethodes die een koppeltabel wijzigen. */
    private const MUTATOREN = [
        'sync', 'syncWithoutDetaching', 'attach', 'detach', 'toggle', 'updateExistingPivot',
    ];

    public function test_geen_rauwe_koppelmutatie_in_livewire_componenten(): void
    {
        $patroon = '/->('.implode('|', self::MUTATOREN).')\(/';
        $map = dirname(__DIR__, 2).'/app/Livewire';

        $treffers = [];

        foreach (glob($map.'/*.php') as $pad) {
            foreach (file($pad) as $nummer => $regel) {
                if (preg_match($patroon, $regel)) {
                    $treffers[] = basename($pad).':'.($nummer + 1).' — '.trim($regel);
                }
            }
        }

        $this->assertSame([], $treffers,
            "Deze koppelwijzigingen gaan buiten de audit trail om. Gebruik App\\Support\\Koppeling:\n"
            .implode("\n", $treffers));
    }

    public function test_het_patroon_herkent_een_rauwe_aanroep(): void
    {
        // Anders is de test hierboven groen omdat hij niets meer vindt in plaats
        // van omdat er niets te vinden is.
        $patroon = '/->('.implode('|', self::MUTATOREN).')\(/';

        $this->assertMatchesRegularExpression($patroon, '$model->soaRegels()->sync($ids);');
        $this->assertMatchesRegularExpression($patroon, '$dienst->systemen()->detach($id);');
        $this->assertDoesNotMatchRegularExpression($patroon, 'Koppeling::sync($relatie, \'veld\', $ids);');
    }
}
