<?php

namespace Tests\Feature;

use App\Models\AuditLogregel;
use App\Models\Beleidsdocument;
use App\Models\Dienst;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\Risico;
use App\Models\Systeem;
use App\Support\Koppeling;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * De wikkel uit implementatie/06b: koppelingen wijzigen komt in de audit trail.
 *
 * De schermtests staan bij hun eigen component; hier staat het gedrag van de
 * wikkel zelf.
 */
class KoppelingTrailTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->actingAs($this->ciso);
    }

    private function laatsteRegelVan(string $entiteitType): AuditLogregel
    {
        $regel = AuditLogregel::where('entiteit_type', $entiteitType)
            ->where('actie', 'gewijzigd')
            ->latest('id')
            ->first();

        $this->assertNotNull($regel, "Geen koppelregel gevonden voor {$entiteitType}.");

        return $regel;
    }

    public function test_een_sync_levert_een_regel_met_beide_kanten_en_met_namen(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $blijft = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);
        $weg = Issue::factory()->create(['aard' => 'extern', 'omschrijving' => 'Krappe arbeidsmarkt']);
        $erbij = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Geen reservecapaciteit']);

        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$blijft->id, $weg->id]);
        $voorAantal = AuditLogregel::count();

        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$blijft->id, $erbij->id]);

        // Eén regel voor de hele handeling, niet één per gekoppelde rij.
        $this->assertSame($voorAantal + 1, AuditLogregel::count());

        $regel = $this->laatsteRegelVan('risico');
        $this->assertSame(['aanleidingen'], $regel->gewijzigdeVelden());
        $this->assertSame('1 ontkoppeld: Krappe arbeidsmarkt', $regel->oude_waarde['aanleidingen']);
        $this->assertSame('1 gekoppeld: Geen reservecapaciteit', $regel->nieuwe_waarde['aanleidingen']);

        // Namen, geen id's: "Issue #2" is waardeloos voor een auditor.
        $this->assertStringNotContainsString('#', $regel->nieuwe_waarde['aanleidingen']);
    }

    public function test_een_sync_die_niets_verandert_levert_geen_regel(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $issue = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);

        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$issue->id]);
        $voorAantal = AuditLogregel::count();

        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$issue->id]);

        $this->assertSame($voorAantal, AuditLogregel::count());
    }

    public function test_het_aantal_staat_vooraan_zodat_het_afkappen_overleeft(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $issues = collect(range(1, 12))->map(fn (int $i) => Issue::factory()->create(['aard' => 'intern', 'omschrijving' => "Kwestie met een tamelijk lange omschrijving nummer {$i}"]));

        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', $issues->pluck('id')->all());

        $waarde = $this->laatsteRegelVan('risico')->nieuwe_waarde['aanleidingen'];
        $this->assertStringStartsWith('12 gekoppeld:', $waarde);

        // De opslag kapt niets af; alleen de weergave mag dat.
        $this->assertStringContainsString('nummer 12', $waarde);
    }

    public function test_de_regel_hangt_aan_het_model_van_het_scherm(): void
    {
        $document = Beleidsdocument::factory()->create(['titel' => 'Toegangsbeleid']);
        $issue = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);
        $risico = Risico::create(['titel' => 'Uitval fileserver']);

        // Dezelfde koppeling, maar gelegd vanaf het scherm van het document.
        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$issue->id], logOp: $document);

        $regel = $this->laatsteRegelVan('beleidsdocument');
        $this->assertSame($document->id, $regel->entiteit_id);
        $this->assertSame($document->auditBlok(), $regel->blok_naam);
        $this->assertSame($this->ciso->naam, $regel->gebruiker_naam);
    }

    public function test_detach_levert_alleen_de_verwijderde_kant(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $issue = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);
        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$issue->id]);

        Koppeling::detach($risico->aanleidingen(), 'aanleidingen', $issue->id);

        $regel = $this->laatsteRegelVan('risico');
        $this->assertSame('1 ontkoppeld: Verouderde hardware', $regel->oude_waarde['aanleidingen']);
        $this->assertNull($regel->nieuwe_waarde);
    }

    public function test_detach_van_een_koppeling_die_er_niet_is_levert_geen_regel(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $issue = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);
        $voorAantal = AuditLogregel::count();

        Koppeling::detach($risico->aanleidingen(), 'aanleidingen', $issue->id);

        $this->assertSame($voorAantal, AuditLogregel::count());
    }

    public function test_erbij_koppelen_laat_de_bestaande_koppelingen_staan(): void
    {
        $risico = Risico::create(['titel' => 'Uitval fileserver']);
        $eerste = Issue::factory()->create(['aard' => 'intern', 'omschrijving' => 'Verouderde hardware']);
        $tweede = Issue::factory()->create(['aard' => 'extern', 'omschrijving' => 'Krappe arbeidsmarkt']);
        Koppeling::sync($risico->aanleidingen(), 'aanleidingen', [$eerste->id]);

        Koppeling::koppelErbij($risico->aanleidingen(), 'aanleidingen', [$tweede->id]);

        $this->assertCount(2, $risico->aanleidingen()->get());
        $regel = $this->laatsteRegelVan('risico');
        $this->assertSame('1 gekoppeld: Krappe arbeidsmarkt', $regel->nieuwe_waarde['aanleidingen']);
        $this->assertNull($regel->oude_waarde);
    }

    public function test_loggen_op_een_niet_auditeerbaar_model_is_een_fout(): void
    {
        $dienst = Dienst::factory()->create();
        $systeem = Systeem::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Auditeerbaar-trait');

        Koppeling::sync($dienst->systemen(), 'systemen', [$systeem->id]);
    }
}
