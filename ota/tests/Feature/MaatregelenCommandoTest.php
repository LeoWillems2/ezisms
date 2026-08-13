<?php

namespace Tests\Feature;

use App\Models\Maatregel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `isms:maatregelen` (implementatie/04f §4).
 *
 * Sinds 04f bewerkt de CISO `maatregelen-<profiel>.json` zelf, met de gekochte
 * norm ernaast. Dit commando is de ondersteunde weg om dat werk in te lezen; de
 * controles vooraf bestaan omdat een typefout in de JSON een leesbare melding
 * hoort op te leveren en geen half geseede database.
 *
 * De bestanden staan in een tijdelijke map. Ze zouden anders in
 * `database/seeders/data/` moeten staan, en daar staat het bestand van de
 * installatie zelf — een test die dat overschrijft gooit overgetypte normtekst
 * weg.
 */
class MaatregelenCommandoTest extends TestCase
{
    use RefreshDatabase;

    private string $map;

    protected function setUp(): void
    {
        parent::setUp();

        $this->map = sys_get_temp_dir().'/maatregelen-'.uniqid();
        mkdir($this->map);

        config(['norm.actief' => 'iso27001', 'norm.maatregelenmap' => $this->map]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->map.'/*') ?: []);
        @rmdir($this->map);

        parent::tearDown();
    }

    /** @param list<array<string, string>> $maatregelen */
    private function schrijf(array $maatregelen, string $profiel = 'iso27001'): void
    {
        file_put_contents(
            $this->map."/maatregelen-{$profiel}.json",
            json_encode(['maatregelen' => $maatregelen], JSON_UNESCAPED_UNICODE),
        );
    }

    /** @return list<array<string, string>> 93 geldige regels. */
    private function volledig(): array
    {
        return array_map(fn (int $n) => [
            'annex_a_referentie' => "5.{$n}",
            'thema' => 'organisatorisch',
            'naam' => "Maatregel {$n}",
            'omschrijving' => Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD,
            'zorgaanvulling' => Maatregel::ZORGAANVULLING_GEEN,
        ], range(1, 93));
    }

    public function test_een_geldig_bestand_wordt_ingelezen(): void
    {
        $this->schrijf($this->volledig());

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('93 maatregelen, bestand is in orde.')
            ->assertSuccessful();

        $this->assertSame(93, Maatregel::count());
        $this->assertNotNull(Maatregel::where('annex_a_referentie', '5.1')->sole()->soaRegel);
    }

    /**
     * De kern van besluit 04f §1.2: gedeeltelijk invullen mag. Tot 06-08-2026
     * keek de seeder naar de eerste rij om te bepalen of het bestand nog de
     * niet-ingevulde huls was, en dan sloeg hij een half ingevuld bestand in zijn
     * geheel over.
     */
    public function test_een_half_ingevuld_bestand_wordt_gewoon_ingelezen(): void
    {
        $maatregelen = $this->volledig();
        $maatregelen[0]['omschrijving'] = 'De letterlijke normtekst bij 5.1.';
        $maatregelen[1]['omschrijving'] = 'De letterlijke normtekst bij 5.2.';
        $this->schrijf($maatregelen);

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('2 met een eigen normtekst, 91 met de meegeleverde mededeling.')
            ->assertSuccessful();

        $this->assertSame(
            'De letterlijke normtekst bij 5.1.',
            Maatregel::where('annex_a_referentie', '5.1')->sole()->omschrijving,
        );
    }

    public function test_controleer_schrijft_niets(): void
    {
        $this->schrijf($this->volledig());

        $this->artisan('isms:maatregelen', ['--controleer' => true])->assertSuccessful();

        $this->assertSame(0, Maatregel::count());
    }

    public function test_een_ontbrekend_bestand_is_een_installatiefout(): void
    {
        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('De norm kopen lost dit niet op.')
            ->assertFailed();

        $this->assertSame(0, Maatregel::count());
    }

    public function test_stukke_json_levert_een_leesbare_melding_en_geen_halve_seed(): void
    {
        file_put_contents($this->map.'/maatregelen-iso27001.json', '{"maatregelen": [ , ]}');

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('geen geldige JSON')
            ->assertFailed();

        $this->assertSame(0, Maatregel::count());
    }

    public function test_een_verdwenen_maatregel_wordt_geweigerd(): void
    {
        $this->schrijf(array_slice($this->volledig(), 0, 92));

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('Dit profiel verwacht 93 maatregelen, het bestand heeft 92.')
            ->assertFailed();

        $this->assertSame(0, Maatregel::count());
    }

    /**
     * Alles wordt gecontroleerd vóór er iets wordt geschreven. Een leeg naamveld
     * halverwege mag geen database achterlaten waarin de eerste veertig
     * maatregelen nieuw zijn en de rest oud.
     */
    public function test_een_leeg_veld_wordt_geweigerd_voordat_er_iets_is_geschreven(): void
    {
        $maatregelen = $this->volledig();
        $maatregelen[40]['naam'] = '';
        $this->schrijf($maatregelen);

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain("veld 'naam' ontbreekt of is leeg.")
            ->assertFailed();

        $this->assertSame(0, Maatregel::count());
    }

    /** Twee keer dezelfde referentie levert stilzwijgend 92 maatregelen op. */
    public function test_een_dubbele_referentie_wordt_geweigerd(): void
    {
        $maatregelen = $this->volledig();
        $maatregelen[5]['annex_a_referentie'] = '5.1';
        $this->schrijf($maatregelen);

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('deze referentie staat er twee keer in.')
            ->assertFailed();
    }

    /** In zorgmodus hoort het NEN-bestand gelezen te worden, met 101 regels. */
    public function test_het_profiel_bepaalt_welk_bestand_gelezen_wordt(): void
    {
        config(['norm.actief' => 'nen7510']);

        $nen = $this->volledig();
        foreach (range(94, 101) as $n) {
            $nen[] = [
                'annex_a_referentie' => "8.{$n}",
                'thema' => 'technologisch',
                'naam' => "Zorgmaatregel {$n}",
                'omschrijving' => Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD,
                'zorgaanvulling' => Maatregel::ZORGAANVULLING_NIET_MEEGELEVERD,
            ];
        }

        $this->schrijf($this->volledig(), 'iso27001');
        $this->schrijf($nen, 'nen7510');

        $this->artisan('isms:maatregelen')
            ->expectsOutputToContain('Zorgspecifieke beheersmaatregelen: 8 van de 101')
            ->assertSuccessful();

        $this->assertSame(101, Maatregel::count());
    }
}
