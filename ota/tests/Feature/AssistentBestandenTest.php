<?php

namespace Tests\Feature;

use App\Mcp\IsmsServer;
use App\Mcp\Resources\Beleidsbestand;
use App\Mcp\Resources\Bewijsbestand;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Raadpleging;
use App\Support\Assistent\Bestandsuitlevering;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bestandsinhoud als MCP-resource (implementatie/14b §6, §12.3).
 */
class AssistentBestandenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
        Storage::fake(Bewijsstuk::DISK);
    }

    public function test_de_ciso_leest_een_tekstbestand_met_de_kop_ervoor(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('Hersteltest geslaagd op 3 maart.', 'text/markdown');

        IsmsServer::actingAs($ciso)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertOk()
            ->assertSee(trim(Bestandsuitlevering::KOP))
            ->assertSee('Hersteltest geslaagd op 3 maart.');
    }

    public function test_ophalen_is_een_raadpleging_via_de_assistent(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('x', 'text/plain');

        IsmsServer::actingAs($ciso)->resource(Bewijsbestand::class, ['id' => $bewijs->id])->assertOk();

        $raadpleging = Raadpleging::sole();
        $this->assertSame($bewijs->id, $raadpleging->bewijsstuk_id);
        $this->assertSame($ciso->id, $raadpleging->gebruiker_id);
        $this->assertSame('assistent', $raadpleging->kanaal);
    }

    public function test_ophalen_door_een_assistent_telt_niet_als_zelf_gelezen(): void
    {
        // Het signaal "bevestigd zonder het document te openen" (05 §14) gaat
        // over de persoon; een samenvatting door een assistent is geen lezen.
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('x', 'text/plain');

        IsmsServer::actingAs($ciso)->resource(Bewijsbestand::class, ['id' => $bewijs->id])->assertOk();

        $this->assertSame(1, Raadpleging::count());
        $this->assertTrue(Raadpleging::eersteRaadplegingPerGebruiker($bewijs->id)->isEmpty());
    }

    public function test_een_pdf_gaat_als_blob(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('%PDF-1.4 nep', 'application/pdf');

        IsmsServer::actingAs($ciso)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertOk()
            ->assertSee(base64_encode('%PDF-1.4 nep'))
            ->assertDontSee(trim(Bestandsuitlevering::KOP));
    }

    public function test_andermans_upload_bestaat_niet_voor_een_medewerker(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $bewijs = $this->bewijsstuk('geheim', 'text/plain');

        IsmsServer::actingAs($medewerker)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertHasErrors(['Bestand niet gevonden.']);

        $this->assertSame(0, Raadpleging::count());
    }

    public function test_eigen_upload_mag_een_medewerker_wel_lezen(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $bewijs = $this->bewijsstuk('mijn verslag', 'text/plain', ['geupload_door' => $medewerker->id]);

        IsmsServer::actingAs($medewerker)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertOk()
            ->assertSee('mijn verslag');
    }

    public function test_een_onbekend_id_is_niet_gevonden(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        IsmsServer::actingAs($ciso)
            ->resource(Bewijsbestand::class, ['id' => 999999])
            ->assertHasErrors(['Bestand niet gevonden.']);
    }

    public function test_een_gewijzigd_bestand_wordt_niet_uitgeleverd(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('origineel', 'text/plain');
        Storage::disk(Bewijsstuk::DISK)->put($bewijs->opslaglocatie_referentie, 'achteraf aangepast');

        IsmsServer::actingAs($ciso)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertHasErrors()
            ->assertDontSee('achteraf aangepast');

        $this->assertSame(0, Raadpleging::count());
    }

    public function test_een_te_groot_bestand_geeft_geen_inhoud(): void
    {
        config(['assistent.max_bestand_mb' => 1]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $bewijs = $this->bewijsstuk('klein', 'text/plain');
        $bewijs->forceFill(['bestandsgrootte' => 5 * 1024 * 1024])->saveQuietly();

        IsmsServer::actingAs($ciso)
            ->resource(Bewijsbestand::class, ['id' => $bewijs->id])
            ->assertHasErrors()
            ->assertDontSee('klein');
    }

    public function test_een_medewerker_leest_het_bestand_van_een_actief_beleid(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        [$document, $versie] = $this->beleid('actief', 'Iedereen vergrendelt zijn scherm.');

        IsmsServer::actingAs($medewerker)
            ->resource(Beleidsbestand::class, ['document' => $document->id, 'versie' => $versie->versienummer])
            ->assertOk()
            ->assertSee('Iedereen vergrendelt zijn scherm.');
    }

    public function test_een_medewerker_ziet_geen_concept_van_een_beleid(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        [$document, $versie] = $this->beleid('concept', 'Nog niet vastgesteld.');

        IsmsServer::actingAs($medewerker)
            ->resource(Beleidsbestand::class, ['document' => $document->id, 'versie' => $versie->versienummer])
            ->assertHasErrors(['Bestand niet gevonden.']);
    }

    public function test_de_ciso_ziet_ook_het_concept(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        [$document, $versie] = $this->beleid('concept', 'Nog niet vastgesteld.');

        IsmsServer::actingAs($ciso)
            ->resource(Beleidsbestand::class, ['document' => $document->id, 'versie' => $versie->versienummer])
            ->assertOk()
            ->assertSee('Nog niet vastgesteld.');
    }

    /** @param array<string, mixed> $extra */
    private function bewijsstuk(string $inhoud, string $mime, array $extra = []): Bewijsstuk
    {
        $pad = 'test/'.uniqid().'.bin';
        Storage::disk(Bewijsstuk::DISK)->put($pad, $inhoud);

        return Bewijsstuk::factory()->create([
            'bestandstype' => $mime,
            'bestandsgrootte' => strlen($inhoud),
            'opslaglocatie_referentie' => $pad,
            'bestandshash' => hash('sha256', $inhoud),
            ...$extra,
        ]);
    }

    /** @return array{0: Beleidsdocument, 1: Beleidsversie} */
    private function beleid(string $status, string $inhoud): array
    {
        $document = Beleidsdocument::factory()->create(['status' => $status]);
        $versie = Beleidsversie::factory()->create([
            'beleidsdocument_id' => $document->id,
            'status' => $status,
            'bewijsstuk_id' => $this->bewijsstuk($inhoud, 'text/markdown')->id,
        ]);

        return [$document, $versie];
    }
}
