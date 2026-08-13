<?php

namespace Tests\Unit;

use App\Support\Domeincontrole;
use PHPUnit\Framework\TestCase;

/**
 * De bijna-treffer op een maildomein (implementatie/01g §5).
 *
 * Bewust een Unit-test: de regel raakt geen database en geen Livewire, en dat is
 * precies waarom hij een eigen klasse kreeg. Wat hier misgaat is niet een fout
 * maar een váls signaal — een melding die te vaak onterecht komt wordt
 * weggeklikt, en dan werkt hij ook niet meer op het moment dat het ertoe doet.
 */
class DomeincontroleTest extends TestCase
{
    /** @var array<string, int> */
    private const TELLINGEN = ['fruitbv.nl' => 5, 'zorggroep-noord.nl' => 3];

    public function test_een_exacte_treffer_is_geen_bijna_treffer(): void
    {
        $this->assertNull(Domeincontrole::bijnaTreffer('jan@fruitbv.nl', self::TELLINGEN));
    }

    public function test_een_teken_verschil_wijst_het_bekende_domein_aan(): void
    {
        $this->assertSame('fruitbv.nl', Domeincontrole::bijnaTreffer('jan@fruibv.nl', self::TELLINGEN));
    }

    public function test_twee_tekens_verschil_telt_nog_mee(): void
    {
        $this->assertSame('fruitbv.nl', Domeincontrole::bijnaTreffer('jan@friubv.nl', self::TELLINGEN));
    }

    public function test_een_volstrekt_ander_domein_geeft_niets(): void
    {
        // Het geval dat de functie bruikbaar houdt: externen — een auditor, een
        // leverancier — hebben legitiem een ander domein.
        $this->assertNull(Domeincontrole::bijnaTreffer('auditor@gmail.com', self::TELLINGEN));
    }

    public function test_een_domein_met_een_enkel_account_is_geen_referentie(): void
    {
        // Zonder deze ondergrens wordt de eerste typefout zelf een "bekend
        // domein", en meet de tweede typefout zich daaraan.
        $this->assertNull(Domeincontrole::bijnaTreffer('jan@fruibv.nl', ['fruitbv.nl' => 1]));
    }

    public function test_korte_domeinen_geven_geen_treffer(): void
    {
        // Op korte domeinen levert een afstand van twee onzin op.
        $this->assertNull(Domeincontrole::bijnaTreffer('jan@be.nl', ['bv.nl' => 9]));
    }

    public function test_een_adres_zonder_apenstaartje_geeft_niets(): void
    {
        $this->assertNull(Domeincontrole::bijnaTreffer('geen-adres', self::TELLINGEN));
    }

    public function test_het_dichtstbijzijnde_domein_wint(): void
    {
        $tellingen = ['fruitbv.nl' => 5, 'fruitbz.nl' => 4];

        $this->assertSame('fruitbv.nl', Domeincontrole::bijnaTreffer('jan@fruitbw.nl', $tellingen));
    }

    public function test_tellingen_groepeert_hoofdletterongevoelig(): void
    {
        $tellingen = Domeincontrole::tellingen(['a@Fruitbv.NL', 'b@fruitbv.nl', 'c@gmail.com']);

        $this->assertSame(['fruitbv.nl' => 2, 'gmail.com' => 1], $tellingen);
    }
}
