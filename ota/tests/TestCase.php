<?php

namespace Tests;

use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\RisicocriteriaVersie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Feature\Concerns\VultDemoEenKeer;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bewaakt dat een testklasse met een lege database begint.
     *
     * De eindstandtests van de demo (`VultDemoEenKeer`) vullen één keer buiten
     * de transactie om — dat scheelt per klasse ruim een minuut, maar de gevulde
     * tabellen overleven die klasse. Draait er daarna nog een gewone klasse in
     * hetzelfde proces, dan toetst die stilzwijgend op demogegevens. Vandaag gaat
     * dat goed omdat `demo` de laatste suite in `phpunit.xml` is; deze controle
     * zorgt dat het opvalt zodra dat verandert, in plaats van dat een test om
     * onbegrijpelijke redenen omvalt.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Het normprofiel staat in de database (migratie 000048) en de meeste
        // tests seeden die tabel niet. Hier de standaard zetten houdt de suite
        // draaiend zonder een rij te schrijven. Tests die een profiel nodig
        // hebben zetten deze sleutel zelf om; die over de tabel zélf gaan, zetten
        // hem op null en seeden NormprofielSeeder.
        //
        // De waarde komt uit `ISMS_NORM` in phpunit.xml, zodat de hele suite met
        // één regel op het andere profiel te draaien is — de controle of de
        // applicatie profielvast is (00k §3). Dat is het enige wat die variabele
        // in de tests doet; de applicatie zelf leest hem nergens meer.
        config(['norm.actief' => env('ISMS_NORM', 'iso27001')]);

        // De actieve risicocriteria worden statisch onthouden (04g §5) en zo'n
        // memo overleeft de testtransactie. Zonder deze reset krijgt de tweede
        // test in een klasse de criteria van de eerste te zien — en dat valt op
        // de vreemdste plaatsen om.
        RisicocriteriaVersie::vergeet();

        $traits = class_uses_recursive(static::class);

        if (in_array(VultDemoEenKeer::class, $traits, true)
            || ! in_array(RefreshDatabase::class, $traits, true)) {
            return;
        }

        $this->assertSame(0, Gebruiker::count(), sprintf(
            '%s begint met een gevulde database. Waarschijnlijk draait een eindstandtest van de demo '
            .'(VultDemoEenKeer) in ditzelfde proces vóór deze klasse; die suite hoort als laatste in phpunit.xml te staan.',
            class_basename(static::class),
        ));
    }

    /**
     * De koppelwijziging die `App\Support\Koppeling` als laatste voor dit veld
     * heeft weggeschreven, of null als er geen is (implementatie/06b).
     *
     * Staat hier en niet in één testbestand: elk koppelscherm controleert zijn
     * eigen regel, en dat zijn negen bestanden verspreid over vijf suites.
     */
    protected function laatsteKoppelregel(
        string $entiteitType,
        string $veld,
        string $kant = 'nieuwe_waarde',
    ): ?string {
        return AuditLogregel::where('entiteit_type', $entiteitType)
            ->where('actie', 'gewijzigd')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AuditLogregel $regel) => $regel->{$kant}[$veld] ?? null)
            ->first(fn (?string $waarde) => $waarde !== null);
    }
}
