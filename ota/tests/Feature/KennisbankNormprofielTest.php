<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use App\Support\Kennisartikelen;
use App\Support\Kennisbankzoeker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * De kennisbank per normprofiel (implementatie/00i).
 *
 * De suite draait standaard in ISO-modus (vastgepind in phpunit.xml); de
 * zorgtesten zetten `norm.actief` per test om. Dat mag hier omdat het profiel
 * puur uit `config()` komt en `Kennisartikelen` niets memoriseert — de rest van
 * de applicatie leest het profiel op dezelfde manier. De ISO-kant wordt net zo
 * expliciet gezet als de zorgkant; zie de klassenkop van NormprofielTest.
 */
#[Group('nen7510')]
class KennisbankNormprofielTest extends TestCase
{
    use RefreshDatabase;

    private function zorgmodus(): void
    {
        config()->set('norm.actief', 'nen7510');
    }

    private function isomodus(): void
    {
        config()->set('norm.actief', 'iso27001');
    }

    public function test_het_nen_artikel_bestaat_niet_in_iso_modus(): void
    {
        $this->isomodus();
        $this->assertFalse(Kennisartikelen::bestaat('wat-nen-7510-toevoegt'));
        $this->assertArrayNotHasKey('wat-nen-7510-toevoegt', Kennisartikelen::alles());
    }

    /**
     * De kern van 00i §1: het filter zit in het register, dus een rechtstreekse
     * URL naar het artikel van het andere profiel geeft 404. Zat het filter in de
     * Livewire-component of alleen in de lijst, dan rendeerde deze pagina gewoon.
     */
    public function test_rechtstreekse_url_naar_het_nen_artikel_geeft_404_in_iso_modus(): void
    {
        $this->isomodus();
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/wat-nen-7510-toevoegt')->assertNotFound();
    }

    public function test_het_nen_artikel_rendert_in_zorgmodus_met_de_afbakening(): void
    {
        $this->zorgmodus();
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/wat-nen-7510-toevoegt')
            ->assertOk()
            ->assertSee('Wat NEN 7510 toevoegt bovenop ISO 27001')   // titel uit het register
            ->assertSee('Zorgontvangers op unieke wijze identificeren')
            ->assertSee('Break-glass-toegang')                        // de afbakening
            ->assertSee('verwerkt zelf geen persoonlijke gezondheidsinformatie');
    }

    public function test_de_gedeelde_slug_levert_per_profiel_een_ander_bestand(): void
    {
        $this->isomodus();
        $gebruiker = Gebruiker::factory()->create();

        // Dit artikel moet in beide profielen bestaan: sinds 04f is
        // Maatregel::GEEN_OMSCHRIJVING_AANHEF de enige verwijzing ernaar vanuit
        // de SoA, en die staat in code en niet in data. Eén linkbron, dus als
        // het artikel in één profiel ontbreekt, loopt daar élke maatregel dood.
        $this->actingAs($gebruiker)->get('/kennisbank/verantwoording-en-disclaimer')
            ->assertOk()
            ->assertSee('Dit is geen kopie van ISO/IEC 27001')
            ->assertDontSee('Deze installatie draait op het');

        $this->zorgmodus();

        $this->actingAs($gebruiker)->get('/kennisbank/verantwoording-en-disclaimer')
            ->assertOk()
            ->assertSee('Dit is geen kopie van NEN 7510')
            ->assertDontSee('Dit is geen kopie van ISO/IEC 27001');
    }

    /**
     * Sinds 04f geldt dit in béíde profielen: het systeem levert nergens een
     * eigen maatregelomschrijving. Een verantwoordingspagina die het
     * tegenovergestelde belooft is precies de fout die deze pagina hoort te
     * voorkomen — en dat zou nu in twee bestanden tegelijk mis kunnen gaan.
     */
    public function test_geen_van_beide_verantwoordingen_belooft_eigen_omschrijvingen(): void
    {
        foreach (['iso27001' => $this->isomodus(...), 'nen7510' => $this->zorgmodus(...)] as $profiel => $zet) {
            $zet();

            $tekst = Kennisartikelen::inhoud('verantwoording-en-disclaimer') ?? '';

            $this->assertStringNotContainsString('formuleringen zijn van ons', $tekst, $profiel);
            $this->assertStringContainsString('geen enkele maatregel', $tekst, $profiel);
        }
    }

    public function test_de_zoeker_vindt_niets_uit_een_artikel_van_het_andere_profiel(): void
    {
        $this->isomodus();
        // Kennisbankzoeker::zoek() loopt over Kennisartikelen::alles(); dít is de
        // test die bewijst dat het filter daar hoort. Zou het in de component
        // zitten, dan gaf de zoeker treffers in pagina's die 404 geven.
        $treffers = collect(Kennisbankzoeker::zoek('zorgontvangers'))->pluck('slug');
        $this->assertNotContains('wat-nen-7510-toevoegt', $treffers);

        $this->zorgmodus();

        $treffers = collect(Kennisbankzoeker::zoek('zorgontvangers'))->pluck('slug');
        $this->assertContains('wat-nen-7510-toevoegt', $treffers);
    }

    public function test_de_zoeker_leest_per_profiel_de_juiste_variant(): void
    {
        $this->isomodus();
        // NEN 7512 komt alleen in de zorgvariant van het integratieartikel voor.
        // In ISO-modus mag de zoeker daar niets van vinden: die tekst staat dan
        // niet op het scherm. Dekt meteen de memo-sleutel in de zoeker — één slug
        // wijst hier naar twee bestanden.
        $this->assertSame([], Kennisbankzoeker::zoek('7512'));

        $this->zorgmodus();

        $treffers = collect(Kennisbankzoeker::zoek('7512'))->pluck('slug');
        $this->assertContains('integraties-en-normeis', $treffers);
    }

    public function test_eerste_slug_bestaat_in_elk_profiel(): void
    {
        foreach (self::profielen() as $profiel) {
            config()->set('norm.actief', $profiel);

            $slug = Kennisartikelen::eersteSlug();

            $this->assertNotNull($slug, "Geen eerste artikel in profiel {$profiel}.");
            $this->assertTrue(Kennisartikelen::bestaat($slug));
            $this->assertNotNull(Kennisartikelen::pad($slug), "Het eerste artikel van {$profiel} heeft geen bestand.");
        }
    }

    /**
     * Elk artikel moet in élk profiel waarin het bestaat een leesbaar bestand
     * hebben. Zonder deze test valt een ontbrekende variant pas op bij de
     * oplevering van de andere uitvoering — en dan rendert de pagina leeg zonder
     * dat iets faalt.
     */
    public function test_elk_artikel_heeft_in_elk_profiel_een_bestaand_bestand(): void
    {
        foreach (self::profielen() as $profiel) {
            config()->set('norm.actief', $profiel);

            foreach (Kennisartikelen::alles() as $slug => $meta) {
                $this->assertNotNull(
                    Kennisartikelen::pad($slug),
                    "Artikel '{$slug}' verwijst in profiel {$profiel} naar {$meta['bestand']}, en dat bestand is er niet."
                );

                $this->assertNotSame(
                    '',
                    trim(Kennisbankzoeker::doorzoekbareTekst($slug)),
                    "Artikel '{$slug}' levert in profiel {$profiel} geen doorzoekbare tekst."
                );
            }
        }
    }

    /**
     * Elke variant-slug moet in élk profiel een ánder bestand opleveren.
     * Twee identieke paden zouden betekenen dat iemand een variant heeft
     * ingekort tot een kopie — dan hoort de array weg.
     */
    public function test_elke_variant_wijst_per_profiel_naar_een_ander_bestand(): void
    {
        $register = (new ReflectionClass(Kennisartikelen::class))->getConstant('ARTIKELEN');
        $varianten = 0;

        foreach ($register as $slug => $meta) {
            if (! is_array($meta['bestand'])) {
                continue;
            }

            $varianten++;
            $this->assertSame(
                self::profielen(),
                array_keys($meta['bestand']),
                "Artikel '{$slug}' heeft geen bestand voor elk normprofiel."
            );
            $this->assertCount(
                count(self::profielen()),
                array_unique($meta['bestand']),
                "Artikel '{$slug}' wijst twee profielen naar hetzelfde bestand.",
            );
        }

        $this->assertGreaterThan(0, $varianten);
    }

    public function test_een_bestand_zonder_het_actieve_profiel_gooit(): void
    {
        // Stilzwijgend overslaan zou een artikel in één profiel laten verdwijnen
        // zonder dat iets dat meldt — en dan mist er een verantwoordingspagina
        // waar 93 maatregelomschrijvingen naar verwijzen.
        config()->set('norm.profielen.iso9001', ['labels' => [], 'capaciteiten' => []]);
        config()->set('norm.actief', 'iso9001');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("geen bestand voor normprofiel 'iso9001'");

        Kennisartikelen::alles();
    }

    public function test_de_lijst_toont_in_zorgmodus_het_nen_artikel(): void
    {
        $this->zorgmodus();
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank')
            ->assertOk()
            ->assertSee(route('kennisbank', 'wat-nen-7510-toevoegt'));
    }
    /**
     * De profielen die deze installatie kent, in de volgorde van
     * `config/norm.php`.
     *
     * Uit de configuratie en niet hardgecodeerd: bij het derde profiel (bio2)
     * faalden drie tests hier op een lijst van twee, terwijl ze juist bedoeld zijn
     * om te bewaken dat élk profiel compleet is. Een lijst die niet meegroeit
     * bewaakt op den duur het verkeerde.
     *
     * @return list<string>
     */
    private static function profielen(): array
    {
        return array_keys(config('norm.profielen'));
    }
}
