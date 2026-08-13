<?php

namespace Tests\Feature;

use App\Console\Commands\SchoonRaadplegingen;
use App\Livewire\BeleidsdocumentDetail;
use App\Livewire\BeleidsdocumentenOverzicht;
use App\Models\AuditLogregel;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\Maatregel;
use App\Models\OrganisatieEenheid;
use App\Models\Raadpleging;
use App\Models\SoaRegel;
use App\Models\Taak;
use App\Support\Beleidspublicatie;
use App\Support\Bewijsopslag;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class BeleidTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    /**
     * Plaatst de gebruikers op één afdeling en richt het document daarop, zodat
     * ze tot de doelgroep van de leesbevestiging horen (§6).
     */
    private function richtOpDoelgroep(Beleidsdocument $document, Gebruiker ...$gebruikers): OrganisatieEenheid
    {
        $afdeling = OrganisatieEenheid::factory()->afdeling()->create();

        foreach ($gebruikers as $gebruiker) {
            $gebruiker->update(['organisatie_eenheid_id' => $afdeling->id]);
        }

        $document->afdelingen()->attach($afdeling);

        return $afdeling;
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_ciso_stelt_op_maar_stelt_niet_vast(): void
    {
        // Functiescheiding uit implementatie/01c: `goedkeuren` staat niet meer
        // bovenaan de ladder, dus muteerrecht geeft geen publicatierecht meer.
        $this->assertTrue($this->ciso->can('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']));
        $this->assertFalse($this->ciso->can('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren']));
    }

    public function test_management_stelt_vast_maar_bewerkt_niet(): void
    {
        $management = Gebruiker::factory()->metRol('Management')->create();

        $this->assertTrue($management->can('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren']));
        $this->assertTrue($management->can('heeft-niveau', ['beleid-maatregelbeheer', 'lezen']));
        $this->assertFalse($management->can('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']));
    }

    public function test_ciso_mag_niet_publiceren(): void
    {
        $document = Beleidsdocument::factory()->create();
        Beleidsversie::factory()->actief()->for($document, 'document')->create();
        $nieuw = Beleidsversie::factory()->terGoedkeuring()->for($document, 'document')->create(['versienummer' => 2]);

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('publiceren', $nieuw->id)
            ->assertForbidden();

        $this->assertSame('ter_goedkeuring', $nieuw->fresh()->status);
    }

    public function test_management_ziet_ook_concepten(): void
    {
        // Beleid is record-scoped: zonder het `goedkeuren`-signaal in
        // Recordscope zou de directie alleen vastgesteld beleid zien en dus
        // nooit iets kúnnen vaststellen.
        $management = Gebruiker::factory()->metRol('Management')->create();
        Beleidsdocument::factory()->create(['titel' => 'Nog niet vastgesteld']);

        Livewire::actingAs($management)
            ->test(BeleidsdocumentenOverzicht::class)
            ->set('filterStatus', '')
            ->assertSee('Nog niet vastgesteld');
    }

    public function test_medewerker_mag_uitvoeren_maar_niet_goedkeuren(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->assertTrue($medewerker->can('heeft-niveau', ['beleid-maatregelbeheer', 'uitvoeren']));
        $this->assertTrue($medewerker->can('heeft-niveau', ['beleid-maatregelbeheer', 'lezen']));
        $this->assertFalse($medewerker->can('heeft-niveau', ['beleid-maatregelbeheer', 'muteren']));
        $this->assertFalse($medewerker->can('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren']));

        $this->actingAs($medewerker)->get('/beleid')->assertOk();
    }

    public function test_medewerker_ziet_alleen_vastgesteld_beleid(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $concept = Beleidsdocument::factory()->create(['titel' => 'Nog niet vastgesteld']);
        $actief = Beleidsdocument::factory()->create(['titel' => 'Vastgesteld beleid']);
        Beleidsversie::factory()->actief()->for($actief, 'document')->create();

        Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentenOverzicht::class)
            ->set('filterStatus', '')
            ->assertSee('Vastgesteld beleid')
            ->assertDontSee('Nog niet vastgesteld');

        // 404 en niet 403: een concept bestaat voor deze gebruiker niet.
        $this->actingAs($medewerker)->get('/beleid/'.$concept->id)->assertNotFound();
        $this->actingAs($medewerker)->get('/beleid/'.$actief->id)->assertOk();
    }

    // --- Publicatie (§4) ---------------------------------------------------

    public function test_publiceren_vervangt_de_vorige_versie_en_laat_er_een_actief(): void
    {
        $document = Beleidsdocument::factory()->create();
        $eerste = Beleidsversie::factory()->actief()->for($document, 'document')->create(['versienummer' => 1]);
        $tweede = Beleidsversie::factory()->terGoedkeuring()->for($document, 'document')->create(['versienummer' => 2]);

        Beleidspublicatie::publiceer($tweede, $this->ciso);

        $this->assertSame('vervangen', $eerste->fresh()->status);
        $this->assertSame('actief', $tweede->fresh()->status);
        $this->assertSame(1, $document->versies()->where('status', 'actief')->count());
        $this->assertSame($this->ciso->id, $tweede->fresh()->goedgekeurd_door_id);
    }

    /**
     * Regressie: bij het publiceren van een tweede versie via het scherm bleef
     * het document op 'ter_goedkeuring' staan. De observer laadde de
     * documentrelatie uit de cache, die de eerdere statuswijziging (versie 1
     * naar 'vervangen') niet had meegekregen, waardoor Eloquent de update als
     * "niets gewijzigd" oversloeg. Gevolg: de Medewerker zag een leeg /beleid
     * met wél de waarschuwing over openstaande leesbevestigingen.
     *
     * Bewust via het component en niet via losse factories: die delen één
     * documentinstantie, en juist dat verbergt de fout.
     *
     * Publiceren gebeurt als Management: sinds implementatie/01c is dat de rol
     * met `goedkeuren` op dit blok. Als CISO zou de aanroep stil een 403
     * opleveren en zouden de assertions hieronder tóch slagen — de eerste
     * versie staat immers al actief — waarmee de regressietest zichzelf
     * uitschakelt.
     */
    public function test_publiceren_via_het_scherm_zet_het_document_op_actief(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $directeur = Gebruiker::factory()->metRol('Management')->create();

        $document = Beleidsdocument::factory()->create([
            'titel' => 'Risicobeleid',
            'leesbevestiging_vereist' => true,
        ]);
        $this->richtOpDoelgroep($document, $medewerker);
        Beleidsversie::factory()->actief()->for($document, 'document')->create(['versienummer' => 1]);
        $tweede = Beleidsversie::factory()->terGoedkeuring()->for($document, 'document')->create(['versienummer' => 2]);

        Livewire::actingAs($directeur)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('publiceren', $tweede->id)
            ->assertHasNoErrors();

        $this->assertSame('actief', $tweede->fresh()->status);
        $this->assertSame('actief', $document->fresh()->status);

        // En daarmee ook het symptoom: de regel is weer zichtbaar, en de
        // waarschuwing slaat op iets dat de gebruiker daadwerkelijk ziet.
        $overzicht = Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentenOverzicht::class);

        $overzicht->assertSee('Risicobeleid');
        $this->assertSame(1, $overzicht->instance()->openstaandeBevestigingen());
    }

    public function test_publiceren_zonder_bestand_faalt(): void
    {
        $document = Beleidsdocument::factory()->create();
        $versie = Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'ter_goedkeuring',
            'bewijsstuk_id' => null,
        ]);

        $this->expectException(ValidationException::class);

        try {
            Beleidspublicatie::publiceer($versie, $this->ciso);
        } finally {
            $this->assertSame('ter_goedkeuring', $versie->fresh()->status);
        }
    }

    public function test_medewerker_mag_niet_publiceren(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $document = Beleidsdocument::factory()->create();
        Beleidsversie::factory()->actief()->for($document, 'document')->create();
        $nieuw = Beleidsversie::factory()->terGoedkeuring()->for($document, 'document')->create(['versienummer' => 2]);

        // ->fresh(): de observer heeft de documentstatus in de database op
        // 'actief' gezet, het in-memory model staat nog op 'concept'.
        Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('publiceren', $nieuw->id)
            ->assertForbidden();
    }

    // --- Afgeleide documentstatus (§3b) ------------------------------------

    public function test_documentstatus_volgt_de_versies_en_ingetrokken_wint(): void
    {
        $document = Beleidsdocument::factory()->create();
        $this->assertSame('concept', $document->fresh()->status);

        $versie = Beleidsversie::factory()->terGoedkeuring()->for($document, 'document')->create();
        $this->assertSame('ter_goedkeuring', $document->fresh()->status);

        Beleidspublicatie::publiceer($versie, $this->ciso);
        $this->assertSame('actief', $document->fresh()->status);

        // Ingetrokken wint van een nog actieve versie: intrekken is een
        // expliciete daad die niet stilzwijgend ongedaan mag worden gemaakt.
        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document])
            ->call('intrekken');

        $this->assertSame('ingetrokken', $document->fresh()->status);
        $this->assertSame('vervangen', $versie->fresh()->status);
    }

    // --- Bestandstoegang (§5) ----------------------------------------------

    public function test_medewerker_downloadt_bestand_van_actieve_versie_maar_niet_van_concept(): void
    {
        Storage::fake('bewijs');
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        // Door de CISO geüpload — zonder de uitzondering uit §5 zou de
        // Medewerker hier 403 krijgen op precies het document dat hij moet lezen.
        $this->actingAs($this->ciso);
        $actiefBestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10), 'Beleid v1');
        $conceptBestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('concept.pdf', 10), 'Beleid v2');

        $document = Beleidsdocument::factory()->create();
        Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'actief', 'bewijsstuk_id' => $actiefBestand->id, 'versienummer' => 1,
        ]);
        Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'concept', 'bewijsstuk_id' => $conceptBestand->id, 'versienummer' => 2,
        ]);

        $this->actingAs($medewerker)
            ->get(route('bewijsstukken.download', $actiefBestand))
            ->assertOk();

        $this->actingAs($medewerker)
            ->get(route('bewijsstukken.download', $conceptBestand))
            ->assertForbidden();

        // De geslaagde download is geregistreerd, de geweigerde niet: een 403 is
        // geen raadpleging (§14).
        $this->assertSame(1, Raadpleging::where('gebruiker_id', $medewerker->id)->count());
        $this->assertSame($actiefBestand->id, Raadpleging::sole()->bewijsstuk_id);
    }

    // --- Onderbouwing van de leesbevestiging (§14) --------------------------

    public function test_raadpleging_is_append_only(): void
    {
        Storage::fake('bewijs');
        $this->actingAs($this->ciso);
        $bestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10), 'Beleid v1');

        $raadpleging = Raadpleging::create([
            'bewijsstuk_id' => $bestand->id,
            'gebruiker_id' => $this->ciso->id,
            'geraadpleegd_op' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $raadpleging->update(['geraadpleegd_op' => now()->subYear()]);
    }

    public function test_raadplegingen_ouder_dan_de_bewaartermijn_worden_opgeruimd(): void
    {
        Storage::fake('bewijs');
        $this->actingAs($this->ciso);
        $bestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10), 'Beleid v1');

        $oud = Raadpleging::create([
            'bewijsstuk_id' => $bestand->id,
            'gebruiker_id' => $this->ciso->id,
            'geraadpleegd_op' => now()->subDays(SchoonRaadplegingen::BEWAARTERMIJN_DAGEN + 1),
        ]);
        $recent = Raadpleging::create([
            'bewijsstuk_id' => $bestand->id,
            'gebruiker_id' => $this->ciso->id,
            'geraadpleegd_op' => now()->subDays(SchoonRaadplegingen::BEWAARTERMIJN_DAGEN - 1),
        ]);

        // Uitloggen: het commando draait vanuit cron, zonder ingelogde
        // gebruiker. Ingelogd blijven zou de logregel op naam van de CISO
        // zetten en daarmee net het verkeerde gedrag bevestigen.
        auth()->logout();

        $this->artisan('isms:schoon-raadplegingen')->assertSuccessful();

        // De append-only guard staat een massa-delete niet in de weg: die
        // beschermt tegen sleutelen aan losse regels, niet tegen een
        // beleidsmatige opschoning (implementatie/05 §14).
        $this->assertNull($oud->fresh());
        $this->assertNotNull($recent->fresh());

        // Eén regel voor de hele opschoning, niet één per verwijderde rij:
        // per-rij loggen zou het leesgedrag naar de audit trail verplaatsen.
        $logregel = AuditLogregel::where('entiteit_type', 'raadpleging')->sole();
        $this->assertSame('verwijderd', $logregel->actie);
        $this->assertNull($logregel->entiteit_id);
        $this->assertSame(1, $logregel->oude_waarde['aantal']);
        $this->assertSame('Systeem (geplande taak)', $logregel->gebruiker_naam);

        // En een lege run laat de trail met rust — 365 lege regels per jaar
        // maken juist de echte gebeurtenissen onvindbaar.
        $this->artisan('isms:schoon-raadplegingen')->assertSuccessful();
        $this->assertSame(1, AuditLogregel::where('entiteit_type', 'raadpleging')->count());
    }

    /**
     * Het signaal, niet de poort: bevestigen kan zonder download, maar de CISO
     * ziet welke bevestigingen nergens op rusten.
     */
    public function test_bevestiging_zonder_download_wordt_gemarkeerd(): void
    {
        Storage::fake('bewijs');
        $zonder = Gebruiker::factory()->metRol('Medewerker')->create(['naam' => 'Zonder Download']);
        $met = Gebruiker::factory()->metRol('Medewerker')->create(['naam' => 'Met Download']);

        $this->actingAs($this->ciso);
        $bestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10), 'Beleid v1');

        $document = Beleidsdocument::factory()->create(['leesbevestiging_vereist' => true]);
        $this->richtOpDoelgroep($document, $zonder, $met);
        Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'actief', 'bewijsstuk_id' => $bestand->id,
        ]);

        // Eerst ophalen, dan bevestigen — de volgorde die telt.
        $this->actingAs($met)->get(route('bewijsstukken.download', $bestand))->assertOk();

        foreach ([$zonder, $met] as $gebruiker) {
            Livewire::actingAs($gebruiker)
                ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
                ->call('bevestig');
        }

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->assertViewHas('zonderRaadpleging', [
                $zonder->id => true,
                $met->id => false,
            ])
            ->assertSee('1 van de 2 bevestiging(en) zonder download');
    }

    public function test_download_na_de_bevestiging_telt_niet_als_onderbouwing(): void
    {
        Storage::fake('bewijs');
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $this->actingAs($this->ciso);
        $bestand = Bewijsopslag::bewaar(UploadedFile::fake()->create('beleid.pdf', 10), 'Beleid v1');

        $document = Beleidsdocument::factory()->create(['leesbevestiging_vereist' => true]);
        $this->richtOpDoelgroep($document, $medewerker);
        Beleidsversie::factory()->for($document, 'document')->create([
            'status' => 'actief', 'bewijsstuk_id' => $bestand->id,
        ]);

        Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('bevestig');

        // Achteraf alsnog ophalen maakt de bevestiging niet onderbouwd: op het
        // moment van tekenen had deze persoon het document niet.
        $this->travel(1)->minutes();
        $this->actingAs($medewerker)->get(route('bewijsstukken.download', $bestand))->assertOk();

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->assertViewHas('zonderRaadpleging', [$medewerker->id => true]);
    }

    public function test_archiveren_slaat_bestand_van_actief_beleid_over(): void
    {
        $verlopen = ['bewaren_tot' => now()->subDay()];

        $beleidsbestand = Bewijsstuk::factory()->create($verlopen);
        $losBestand = Bewijsstuk::factory()->create($verlopen);

        Beleidsversie::factory()->create(['status' => 'actief', 'bewijsstuk_id' => $beleidsbestand->id]);

        $this->artisan('isms:archiveer-bewijsstukken')->assertSuccessful();

        $this->assertSame('actief', $beleidsbestand->fresh()->status);
        $this->assertSame('gearchiveerd', $losBestand->fresh()->status);
    }

    // --- Leesbevestiging & taken (§6, §8) ----------------------------------

    public function test_bevestigen_is_idempotent_en_sluit_de_taak(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $document = Beleidsdocument::factory()->create(['leesbevestiging_vereist' => true]);
        $this->richtOpDoelgroep($document, $medewerker);
        $versie = Beleidsversie::factory()->actief()->for($document, 'document')->create();

        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $taak = Taak::where('soort', 'beleid-leesbevestiging')
            ->where('eigenaar_id', $medewerker->id)
            ->firstOrFail();
        $this->assertSame('open', $taak->status);

        Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document->fresh()])
            ->call('bevestig')
            ->call('bevestig');

        $this->assertSame(1, Leesbevestiging::where('beleidsversie_id', $versie->id)
            ->where('gebruiker_id', $medewerker->id)->count());
        $this->assertSame('voltooid', $taak->fresh()->status);
    }

    /**
     * Bevestigen kan alleen op het detailscherm. De knop in het overzicht is een
     * link daarheen; er hoort geen bevestig-actie meer op het component te
     * zitten, want die zou een leesbevestiging opleveren van iemand die het
     * document niet heeft geopend.
     */
    public function test_overzicht_kent_geen_bevestig_actie_meer(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $document = Beleidsdocument::factory()->create(['leesbevestiging_vereist' => true]);
        $versie = Beleidsversie::factory()->actief()->for($document, 'document')->create();

        $this->assertFalse(method_exists(BeleidsdocumentenOverzicht::class, 'bevestig'));

        Livewire::actingAs($medewerker)
            ->test(BeleidsdocumentenOverzicht::class)
            ->assertSeeHtml(route('beleid.detail', $document));

        $this->assertSame(0, Leesbevestiging::where('beleidsversie_id', $versie->id)->count());
    }

    public function test_geen_leesbevestigingstaken_zonder_bevestigingsplicht(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $document = Beleidsdocument::factory()->procedure()->create();
        Beleidsversie::factory()->actief()->for($document, 'document')->create();

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $this->assertSame(0, Taak::where('soort', 'beleid-leesbevestiging')->count());

        // Vlag omzetten én een afdeling richten: dezelfde versie levert nu wél
        // taken op, maar alleen voor de doelgroep — niet voor de CISO, die niet
        // op de afdeling zit.
        $this->richtOpDoelgroep($document, $medewerker);
        $document->update(['leesbevestiging_vereist' => true]);
        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertSame(1, Taak::where('soort', 'beleid-leesbevestiging')->count());
    }

    public function test_leesbevestigingstaken_zijn_idempotent_per_gebruiker(): void
    {
        // De sleutel (entiteit, soort, eigenaar) uit §8: een tweede run mag
        // geen tweede taak per gebruiker opleveren.
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $document = Beleidsdocument::factory()->create();
        $this->richtOpDoelgroep($document, $medewerker);
        Beleidsversie::factory()->actief()->for($document, 'document')->create();

        $this->artisan('isms:genereer-taken')->assertSuccessful();
        $eerste = Taak::where('soort', 'beleid-leesbevestiging')->count();

        $this->artisan('isms:genereer-taken')->assertSuccessful();

        $this->assertSame($eerste, Taak::where('soort', 'beleid-leesbevestiging')->count());
    }

    public function test_herziening_van_actieve_versie_levert_een_taak_op(): void
    {
        $document = Beleidsdocument::factory()->create(['eigenaar_id' => $this->ciso->id]);
        $versie = Beleidsversie::factory()->actief()->for($document, 'document')->create([
            'volgende_herziening_gepland' => now()->addMonths(6),
        ]);

        $taak = Taak::where('soort', 'beleid-herziening')->firstOrFail();
        $this->assertSame($this->ciso->id, $taak->eigenaar_id);

        // Vervangen versie: de openstaande herzieningstaak verdwijnt.
        $versie->update(['status' => 'vervangen']);
        $this->assertSame(0, Taak::where('soort', 'beleid-herziening')->count());
    }

    // --- SoA-koppeling (§7) ------------------------------------------------

    public function test_soa_koppeling_is_te_leggen_en_zichtbaar(): void
    {
        $document = Beleidsdocument::factory()->create();
        Beleidsversie::factory()->actief()->for($document, 'document')->create();

        $maatregel = Maatregel::factory()->create(['annex_a_referentie' => '5.1']);
        $regel = SoaRegel::create([
            'maatregel_id' => $maatregel->id,
            'van_toepassing' => true,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $document])
            ->set('geselecteerdeSoaRegels', [$regel->id])
            ->call('opslaanSoaKoppeling');

        $this->assertTrue($document->fresh()->soaRegels->contains($regel));
        $this->assertSame(
            '1 gekoppeld: '.$regel->auditOmschrijving(),
            $this->laatsteKoppelregel('beleidsdocument', 'maatregelen'),
        );

        // Het gap-signaal kijkt alleen naar ACTIEF beleid.
        $regel->load(['beleidsdocumenten' => fn ($q) => $q->where('status', 'actief')]);
        $this->assertFalse($regel->mistBeleid());
    }
}
