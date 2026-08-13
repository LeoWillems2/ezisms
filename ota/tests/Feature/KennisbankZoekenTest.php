<?php

namespace Tests\Feature;

use App\Livewire\Kennisbank;
use App\Models\Gebruiker;
use App\Support\Kennisartikelen;
use App\Support\Kennisbankzoeker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zoeken in de kennisbank (implementatie/00g).
 */
class KennisbankZoekenTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function slugs(string $term): array
    {
        return array_map(fn ($resultaat) => $resultaat->slug, Kennisbankzoeker::zoek($term));
    }

    public function test_een_term_uit_de_titel_zet_dat_artikel_bovenaan(): void
    {
        $slugs = $this->slugs('audit trail');

        // Het artikel dat zo heet wint van de artikelen die de term terloops
        // noemen; dat is precies waar het titelgewicht voor is.
        $this->assertSame('de-audit-trail', $slugs[0]);
        $this->assertGreaterThan(1, count($slugs));
    }

    public function test_een_term_uit_de_tekst_levert_een_passage_met_de_term_erin(): void
    {
        $resultaten = Kennisbankzoeker::zoek('normclausules');

        $this->assertNotEmpty($resultaten);

        $treffer = $resultaten[0];

        $this->assertSame('issues-en-risicos', $treffer->slug);
        // Een passage en geen vinkje: de zin eromheen hoort erbij, met de
        // treffer gemarkeerd.
        $this->assertStringContainsStringIgnoringCase('<mark>normclausules</mark>', $treffer->passage);
        $this->assertGreaterThan(40, strlen(strip_tags($treffer->passage)));
    }

    /**
     * Hoofdletterongevoelig, en in beide richtingen: een term in kapitalen vindt
     * kleine letters in de tekst, en een term in kleine letters vindt een woord
     * dat in de tekst met een hoofdletter begint.
     */
    public function test_het_zoeken_is_hoofdletterongevoelig(): void
    {
        $verwacht = $this->slugs('normclausules');

        $this->assertNotEmpty($verwacht);
        $this->assertSame($verwacht, $this->slugs('NORMCLAUSULES'));
        $this->assertSame($verwacht, $this->slugs('NormClausules'));

        // Andersom: 'Beslissingen' staat met een hoofdletter in een kop.
        $this->assertContains('open-punten', $this->slugs('beslissingen'));
    }

    public function test_accenten_doen_er_niet_toe(): void
    {
        $metAccent = $this->slugs('beëindiging');

        $this->assertNotEmpty($metAccent);
        $this->assertSame($metAccent, $this->slugs('beeindiging'));
        $this->assertSame($metAccent, $this->slugs('BEËINDIGING'));
    }

    public function test_een_deel_van_een_woord_vindt_de_verbuigingen(): void
    {
        // Nederlandse verbuigingen zitten achteraan, dus deelwoordmatching
        // vooraan dekt 'beoordelen', 'beoordeling' en 'beoordeeld' in één keer.
        $this->assertNotEmpty($this->slugs('beoordel'));
        $this->assertContains('soa-onderbouwen-en-restrisico', $this->slugs('restrisico'));
    }

    public function test_markdown_syntax_levert_geen_treffers(): void
    {
        // Kopmarkeringen, tabelpijpen en code-fences zijn opmaak, geen inhoud.
        $this->assertSame([], Kennisbankzoeker::zoek('##'));
        $this->assertSame([], Kennisbankzoeker::zoek('|'));
        $this->assertSame([], Kennisbankzoeker::zoek('```'));
        $this->assertSame([], Kennisbankzoeker::zoek('   '));
    }

    public function test_een_naam_met_een_liggend_streepje_blijft_vindbaar(): void
    {
        // Underscores zijn geen opmaak maar onderdeel van de naam; wie
        // `status_gewijzigd` zoekt hoort het artikel over de audit trail te
        // vinden en niet elk artikel met het woord 'status'.
        $treffers = $this->slugs('status_gewijzigd');

        $this->assertContains('de-audit-trail', $treffers);

        // Geen vaste lijst: elk artikel dat de valkuil noemt hoort mee te komen.
        // Waar het om gaat is dat de underscore niet wegvalt — dan zou de term
        // als 'status' zoeken en veel breder treffen.
        $this->assertLessThan(count($this->slugs('status')), count($treffers));
    }

    public function test_twee_woorden_leveren_alleen_artikelen_die_ze_allebei_bevatten(): void
    {
        $links = $this->slugs('leverancier');
        $rechts = $this->slugs('teruggave');
        $samen = $this->slugs('leverancier teruggave');

        $this->assertNotEmpty($samen);
        $this->assertSame(array_values(array_intersect($links, $rechts)), array_values(array_intersect($links, $samen)));

        foreach ($samen as $slug) {
            $this->assertContains($slug, $links);
            $this->assertContains($slug, $rechts);
        }

        // Een tweede woord kan de uitkomst alleen kleiner maken, nooit groter.
        $this->assertLessThanOrEqual(count($links), count($samen));
    }

    public function test_een_term_die_nergens_staat_levert_niets_op(): void
    {
        $this->assertSame([], Kennisbankzoeker::zoek('zwaluwstaartverbinding'));
    }

    /**
     * Het anker uit een zoekresultaat moet echt in de gerenderde HTML staan.
     * De zoeker berekent hem met dezelfde SlugNormalizer als de
     * markdown-conversie, maar dat is een afspraak tussen twee klassen — deze
     * test is wat hem overeind houdt.
     */
    public function test_het_kopanker_van_een_resultaat_bestaat_in_het_artikel(): void
    {
        $metAnker = 0;

        foreach (Kennisartikelen::alles() as $slug => $meta) {
            $eersteKop = $this->eersteKop($slug);

            if ($eersteKop === null) {
                continue;
            }

            $resultaten = array_values(array_filter(
                Kennisbankzoeker::zoek($eersteKop),
                fn ($resultaat) => $resultaat->slug === $slug,
            ));

            $this->assertNotEmpty($resultaten, "Zoeken op de kop '{$eersteKop}' vindt {$slug} niet.");

            if ($resultaten[0]->anker === null) {
                continue;
            }

            $metAnker++;

            $this->assertStringContainsString(
                'id="'.$resultaten[0]->anker.'"',
                (string) Kennisartikelen::html($slug),
                "Het anker #{$resultaten[0]->anker} bestaat niet in {$slug}.",
            );
            $this->assertStringEndsWith('#'.$resultaten[0]->anker, $resultaten[0]->url());
        }

        $this->assertGreaterThan(0, $metAnker, 'Geen enkel resultaat kreeg een kopanker.');
    }

    public function test_koppen_in_een_artikel_krijgen_een_id(): void
    {
        $html = (string) Kennisartikelen::html('de-audit-trail');

        $this->assertMatchesRegularExpression('/<h2 id="[a-z0-9-]+">/', $html);
        // Alleen het anker, geen ¶-symbool ernaast.
        $this->assertStringNotContainsString('heading-permalink', $html);
    }

    /**
     * Elk artikel uit het register levert doorzoekbare tekst op, en is vindbaar
     * op zijn eigen langste woord. Afgeleid uit `Kennisartikelen::alles()` en
     * niet uit een lijst hier: een nieuw artikel dat door de zoekfunctie heen
     * valt — bijvoorbeeld omdat het alleen uit HTML bestaat — hoort te falen.
     */
    public function test_elk_artikel_is_doorzoekbaar(): void
    {
        foreach (array_keys(Kennisartikelen::alles()) as $slug) {
            $tekst = Kennisbankzoeker::doorzoekbareTekst($slug);

            $this->assertGreaterThan(200, strlen($tekst), "{$slug} levert nauwelijks doorzoekbare tekst op.");

            $this->assertContains($slug, $this->slugs($this->langsteWoord($tekst)), "{$slug} is niet vindbaar op zijn eigen tekst.");
        }
    }

    public function test_het_scherm_toont_de_treffers_en_de_zoekterm_staat_in_de_url(): void
    {
        Livewire::actingAs(Gebruiker::factory()->create())
            ->test(Kennisbank::class)
            ->assertSee('Incidenten')                          // de categorielijst
            ->set('zoekterm', 'normclausules')
            ->assertSee('Issues')
            ->assertDontSee('Naslag')                          // de lijst maakt plaats
            ->assertSeeHtml('<mark>normclausules</mark>')
            ->assertSet('zoekterm', 'normclausules')
            // Leeg veld → de categorielijst komt terug.
            ->set('zoekterm', '')
            ->assertSee('Naslag');
    }

    public function test_het_scherm_zegt_het_als_er_niets_gevonden_is(): void
    {
        Livewire::actingAs(Gebruiker::factory()->create())
            ->test(Kennisbank::class)
            ->set('zoekterm', 'zwaluwstaartverbinding')
            ->assertSee('Geen artikel gevonden')
            ->assertSee('zwaluwstaartverbinding');
    }

    public function test_de_zoekterm_wordt_uit_de_url_overgenomen(): void
    {
        $this->actingAs(Gebruiker::factory()->create())
            ->get('/kennisbank?q=normclausules')
            ->assertOk()
            ->assertSee('Issues');
    }

    /** De eerste `##`-kop van een artikel, als het er een heeft. */
    private function eersteKop(string $slug): ?string
    {
        preg_match('/^\s{0,3}##\s+(.*)$/mu', Kennisartikelen::inhoud($slug) ?? '', $treffer);

        return isset($treffer[1]) ? trim($treffer[1]) : null;
    }

    /** Het langste woord in een tekst — daarmee is een artikel te onderscheiden. */
    private function langsteWoord(string $tekst): string
    {
        preg_match_all('/[\p{L}]{5,}/u', $tekst, $treffers);

        $woorden = $treffers[0];
        usort($woorden, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $this->assertNotEmpty($woorden);

        return $woorden[0];
    }
}
