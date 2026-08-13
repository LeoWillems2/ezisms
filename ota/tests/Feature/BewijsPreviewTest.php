<?php

namespace Tests\Feature;

use App\Livewire\BewijsstukkenOverzicht;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Support\Bewijsopslag;
use App\Support\Pandoc;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * HTML-preview van RTF-, DOCX- en ODT-bewijsstukken (blok 5/6). Pandoc wordt
 * gemockt: de tests draaien zonder dat de binary geïnstalleerd is.
 */
class BewijsPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        Storage::fake('bewijs');
    }

    private function mockPandoc(string $html): void
    {
        $this->mock(Pandoc::class, fn ($mock) => $mock->shouldReceive('naarHtml')->andReturn($html));
    }

    private function previewbaarBewijsstuk(string $bestandsnaam = 'beleid.rtf', string $mime = 'application/rtf'): Bewijsstuk
    {
        $this->actingAs($this->ciso);

        return Bewijsopslag::bewaar(
            UploadedFile::fake()->create($bestandsnaam, 10, $mime),
            'Beleid v1',
        );
    }

    public function test_preview_toont_geconverteerde_html_en_telt_als_raadpleging(): void
    {
        $this->mockPandoc('<p>Hallo beleid</p>');
        $bestand = $this->previewbaarBewijsstuk();

        $response = $this->actingAs($this->ciso)->get(route('bewijsstukken.preview', $bestand));

        $response->assertOk();
        $response->assertSee('Hallo beleid', false);
        $this->assertStringContainsString(
            "default-src 'none'",
            $response->headers->get('Content-Security-Policy'),
        );

        // Preview telt als raadpleging (§14): wie previewt, heeft het gezien.
        $this->assertDatabaseHas('raadplegingen', [
            'bewijsstuk_id' => $bestand->id,
            'gebruiker_id' => $this->ciso->id,
        ]);
    }

    public function test_sanitizer_verwijdert_scripts_en_externe_bronnen(): void
    {
        $this->mockPandoc('<p>Veilige tekst</p><script>alert(1)</script><img src="http://evil/track">');
        $bestand = $this->previewbaarBewijsstuk();

        $response = $this->actingAs($this->ciso)->get(route('bewijsstukken.preview', $bestand));

        $response->assertOk();
        $response->assertSee('Veilige tekst', false);
        $response->assertDontSee('<script', false);
        $response->assertDontSee('alert(1)', false);
        $response->assertDontSee('evil', false);
    }

    public function test_niet_previewbaar_bestand_heeft_geen_preview(): void
    {
        $this->actingAs($this->ciso);
        $plaatje = Bewijsopslag::bewaar(UploadedFile::fake()->create('schermafdruk.png', 10), 'Schermafdruk');

        $this->actingAs($this->ciso)
            ->get(route('bewijsstukken.preview', $plaatje))
            ->assertNotFound();
    }

    /**
     * Een PDF gaat niet door pandoc heen — conversie naar HTML gooit de opmaak
     * weg, en juist bij beleid hoort de opmaak bij het document. Hij wordt dus
     * uitgeleverd zoals hij is, met een expliciet content-type.
     */
    public function test_pdf_wordt_inline_getoond_en_telt_als_raadpleging(): void
    {
        $this->actingAs($this->ciso);
        $pdf = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10, 'application/pdf'), 'Beleid');

        $response = $this->actingAs($this->ciso)->get(route('bewijsstukken.preview', $pdf));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('raadplegingen', [
            'bewijsstuk_id' => $pdf->id,
            'gebruiker_id' => $this->ciso->id,
        ]);
    }

    /**
     * Zonder `nosniff` mag de browser de inhoud zelf beoordelen, en dan wordt
     * een als PDF geüpload HTML-bestand een pagina op ons eigen domein.
     */
    public function test_inline_preview_verbiedt_content_sniffing(): void
    {
        $this->actingAs($this->ciso);
        $pdf = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10, 'application/pdf'), 'Beleid');

        $this->actingAs($this->ciso)
            ->get(route('bewijsstukken.preview', $pdf))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * De demo schrijft zijn bewijsstukken als Markdown weg; zonder deze regel
     * heeft het gevulde ISMS geen enkele previewbare bijlage.
     */
    public function test_markdown_gaat_via_pandoc(): void
    {
        $this->mock(Pandoc::class, fn ($mock) => $mock->shouldReceive('naarHtml')
            ->once()->with(Mockery::any(), 'markdown')->andReturn('<p>Beleidstekst</p>'));

        $bestand = $this->previewbaarBewijsstuk('beleid.md', 'text/markdown');

        $this->actingAs($this->ciso)
            ->get(route('bewijsstukken.preview', $bestand))
            ->assertOk()
            ->assertSee('Beleidstekst', false);
    }

    public function test_docx_en_odt_krijgen_het_juiste_pandoc_formaat(): void
    {
        foreach ([['beleid.docx', 'docx'], ['beleid.odt', 'odt']] as [$bestandsnaam, $formaat]) {
            // Cache leeg: anders serveert een gelijke bestandshash de vorige
            // conversie en wordt naarHtml niet opnieuw aangeroepen.
            Cache::flush();

            $this->mock(Pandoc::class, fn ($mock) => $mock->shouldReceive('naarHtml')
                ->once()->with(Mockery::any(), $formaat)->andReturn('<p>ok</p>'));

            $bestand = $this->previewbaarBewijsstuk($bestandsnaam, 'application/octet-stream');

            $this->actingAs($this->ciso)
                ->get(route('bewijsstukken.preview', $bestand))
                ->assertOk()
                ->assertSee('ok', false);
        }
    }

    public function test_medewerker_mag_preview_van_actief_beleidsbestand(): void
    {
        $this->mockPandoc('<p>Beleid</p>');
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $bestand = $this->previewbaarBewijsstuk();

        $document = Beleidsdocument::factory()->create();
        Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'actief', 'bewijsstuk_id' => $bestand->id,
        ]);

        $this->actingAs($medewerker)
            ->get(route('bewijsstukken.preview', $bestand))
            ->assertOk();
    }

    public function test_medewerker_mag_geen_preview_van_vreemd_bestand(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        // Door de CISO geüpload, niet gekoppeld aan actief beleid: buiten de
        // leespoort. Een 403 is geen raadpleging (§14).
        $bestand = $this->previewbaarBewijsstuk();

        $this->actingAs($medewerker)
            ->get(route('bewijsstukken.preview', $bestand))
            ->assertForbidden();

        $this->assertDatabaseCount('raadplegingen', 0);
    }

    public function test_previewknop_alleen_bij_previewbaar_bestand_in_de_lijst(): void
    {
        $rtf = $this->previewbaarBewijsstuk();
        $this->actingAs($this->ciso);
        $plaatje = Bewijsopslag::bewaar(UploadedFile::fake()->create('foto.png', 10), 'Foto');

        Livewire::actingAs($this->ciso)
            ->test(BewijsstukkenOverzicht::class)
            ->assertSeeHtml(route('bewijsstukken.preview', $rtf))
            ->assertDontSeeHtml(route('bewijsstukken.preview', $plaatje));
    }

    public function test_conversiefout_geeft_geen_raadpleging(): void
    {
        $this->mock(Pandoc::class, fn ($mock) => $mock->shouldReceive('naarHtml')
            ->andThrow(new \RuntimeException('pandoc ontbreekt')));
        $bestand = $this->previewbaarBewijsstuk();

        $response = $this->actingAs($this->ciso)->get(route('bewijsstukken.preview', $bestand));

        $response->assertStatus(503);
        $response->assertSee('Preview niet beschikbaar', false);
        $this->assertDatabaseCount('raadplegingen', 0);
    }
}
