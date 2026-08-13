<?php

namespace Tests\Feature;

use App\Livewire\BeleidsdocumentDetail;
use App\Livewire\BeleidsdocumentenOverzicht;
use App\Livewire\GebruikersOverzicht;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\OrganisatieEenheid;
use App\Models\Rol;
use App\Models\Taak;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De leesbevestigingsplicht is afdelingsgericht (implementatie/05 §6): de
 * doelgroep is de actieve gebruikers wier afdeling het document heeft
 * aangevinkt, niet langer de hele organisatie.
 */
class LeesbevestigingDoelgroepTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    private OrganisatieEenheid $verkoop;

    private OrganisatieEenheid $techniek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->verkoop = OrganisatieEenheid::factory()->afdeling()->create(['naam' => 'Verkoop']);
        $this->techniek = OrganisatieEenheid::factory()->afdeling()->create(['naam' => 'Techniek']);
    }

    private function actiefDocumentVoor(OrganisatieEenheid ...$afdelingen): Beleidsdocument
    {
        $document = Beleidsdocument::factory()
            ->voorAfdelingen(...$afdelingen)
            ->create(['leesbevestiging_vereist' => true]);

        Beleidsversie::factory()->actief()->for($document, 'document')->create();

        return $document;
    }

    private function medewerkerOp(OrganisatieEenheid $afdeling): Gebruiker
    {
        return Gebruiker::factory()->metRol('Medewerker')->opAfdeling($afdeling)->create();
    }

    // --- Taakgeneratie -----------------------------------------------------

    public function test_taak_alleen_voor_de_doelgroepafdeling(): void
    {
        $inScope = $this->medewerkerOp($this->verkoop);
        $buitenScope = $this->medewerkerOp($this->techniek);
        $zonderAfdeling = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actiefDocumentVoor($this->verkoop);
        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $eigenaren = Taak::where('soort', 'beleid-leesbevestiging')->pluck('eigenaar_id')->all();

        $this->assertSame([$inScope->id], $eigenaren);
        $this->assertNotContains($buitenScope->id, $eigenaren);
        $this->assertNotContains($zonderAfdeling->id, $eigenaren);
    }

    public function test_taak_wordt_ingetrokken_als_gebruiker_de_doelgroep_verlaat(): void
    {
        $gebruiker = $this->medewerkerOp($this->verkoop);
        $this->actiefDocumentVoor($this->verkoop);

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $this->assertSame(1, Taak::where('soort', 'beleid-leesbevestiging')->count());

        // Naar een afdeling die het document niet raakt: de openstaande taak
        // hoort te verdwijnen.
        $gebruiker->update(['organisatie_eenheid_id' => $this->techniek->id]);
        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertSame(0, Taak::where('soort', 'beleid-leesbevestiging')
            ->whereIn('status', Taak::OPENSTAAND)->count());
    }

    public function test_taak_wordt_ingetrokken_als_afdeling_van_document_af_gaat(): void
    {
        $this->medewerkerOp($this->verkoop);
        $document = $this->actiefDocumentVoor($this->verkoop);

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $this->assertSame(1, Taak::where('soort', 'beleid-leesbevestiging')->count());

        $document->afdelingen()->detach($this->verkoop);
        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertSame(0, Taak::where('soort', 'beleid-leesbevestiging')
            ->whereIn('status', Taak::OPENSTAAND)->count());
    }

    // --- Bevestigingsgraad & bevestigen ------------------------------------

    public function test_bevestigingsgraad_rekent_met_de_doelgroep(): void
    {
        $a = $this->medewerkerOp($this->verkoop);
        $this->medewerkerOp($this->verkoop);          // tweede doelgroeplid
        $this->medewerkerOp($this->techniek);         // buiten de doelgroep

        $document = $this->actiefDocumentVoor($this->verkoop);

        Leesbevestiging::create([
            'beleidsversie_id' => $document->actieveVersie->id,
            'gebruiker_id' => $a->id,
            'bevestigd_op' => now(),
        ]);

        // 1 van de 2 doelgroepleden — de technicus telt niet mee.
        $this->assertSame(50, $document->fresh()->bevestigingsgraad());
    }

    public function test_bevestigen_buiten_de_doelgroep_mag_niet(): void
    {
        $buiten = $this->medewerkerOp($this->techniek);
        $document = $this->actiefDocumentVoor($this->verkoop);

        Livewire::actingAs($buiten)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('bevestig')
            ->assertForbidden();

        $this->assertSame(0, Leesbevestiging::count());
    }

    public function test_waarschuwing_alleen_voor_de_eigen_afdeling(): void
    {
        $verkoper = $this->medewerkerOp($this->verkoop);
        $technicus = $this->medewerkerOp($this->techniek);
        $this->actiefDocumentVoor($this->verkoop);

        $this->assertSame(1, Livewire::actingAs($verkoper)
            ->test(BeleidsdocumentenOverzicht::class)->instance()->openstaandeBevestigingen());

        $this->assertSame(0, Livewire::actingAs($technicus)
            ->test(BeleidsdocumentenOverzicht::class)->instance()->openstaandeBevestigingen());
    }

    // --- Aanmaakformulier --------------------------------------------------

    public function test_bevestigingsplicht_vereist_minstens_een_afdeling(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentenOverzicht::class)
            ->call('nieuwDocument')
            ->set('titel', 'Beleid zonder doelgroep')
            ->set('type', 'beleid')
            ->set('leesbevestigingVereist', true)
            ->set('afdelingIds', [])
            ->call('opslaan')
            ->assertHasErrors('afdelingIds');

        $this->assertDatabaseMissing('beleidsdocumenten', ['titel' => 'Beleid zonder doelgroep']);
    }

    public function test_aanmaken_koppelt_de_gekozen_afdelingen(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentenOverzicht::class)
            ->call('nieuwDocument')
            ->set('titel', 'Toegangsbeleid')
            ->set('type', 'beleid')
            ->set('leesbevestigingVereist', true)
            ->set('afdelingIds', [$this->verkoop->id, $this->techniek->id])
            ->call('opslaan')
            ->assertHasNoErrors();

        $document = Beleidsdocument::where('titel', 'Toegangsbeleid')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->verkoop->id, $this->techniek->id],
            $document->afdelingen->pluck('id')->all(),
        );

        // De doelgroep bepaalt wie moet bevestigen; die keuze hoort in de trail (06b).
        $this->assertStringStartsWith('2 gekoppeld:', (string) $this->laatsteKoppelregel('beleidsdocument', 'afdelingen'));
    }

    public function test_uitzetten_bevestigingsplicht_maakt_afdelingen_leeg(): void
    {
        $document = $this->actiefDocumentVoor($this->verkoop);

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentenOverzicht::class)
            ->call('bewerk', $document->id)
            ->set('leesbevestigingVereist', false)
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertCount(0, $document->fresh()->afdelingen);
        $this->assertSame(
            '1 ontkoppeld: '.$this->verkoop->naam,
            $this->laatsteKoppelregel('beleidsdocument', 'afdelingen', 'oude_waarde'),
        );
    }

    // --- Gebruikers-UI -----------------------------------------------------

    public function test_uitnodigen_met_afdeling(): void
    {
        Mail::fake();

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->set('naam', 'Nieuwe Verkoper')
            ->set('email', 'verkoper@example.com')
            ->set('rolId', Rol::where('naam', 'Medewerker')->value('id'))
            ->set('afdelingId', (string) $this->verkoop->id)
            ->call('uitnodigen')
            ->assertHasNoErrors();

        $this->assertSame(
            $this->verkoop->id,
            Gebruiker::where('email', 'verkoper@example.com')->value('organisatie_eenheid_id'),
        );
    }

    public function test_afdeling_inline_wijzigen_en_wissen(): void
    {
        $gebruiker = $this->medewerkerOp($this->verkoop);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('stelAfdelingIn', $gebruiker->id, (string) $this->techniek->id);

        $this->assertSame($this->techniek->id, $gebruiker->fresh()->organisatie_eenheid_id);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('stelAfdelingIn', $gebruiker->id, '');

        $this->assertNull($gebruiker->fresh()->organisatie_eenheid_id);
    }

    public function test_een_locatie_is_geen_geldige_afdeling(): void
    {
        $locatie = OrganisatieEenheid::factory()->create(['type' => 'locatie']);
        $gebruiker = $this->medewerkerOp($this->verkoop);

        Livewire::actingAs($this->ciso)
            ->test(GebruikersOverzicht::class)
            ->call('stelAfdelingIn', $gebruiker->id, (string) $locatie->id);

        // Genegeerd: de bestaande afdeling blijft staan.
        $this->assertSame($this->verkoop->id, $gebruiker->fresh()->organisatie_eenheid_id);
    }
}
