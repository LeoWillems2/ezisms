<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetToewijzing;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Besluit;
use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\Gebruiker;
use App\Models\Incident;
use App\Models\Issue;
use App\Models\KpiDefinitie;
use App\Models\Leesbevestiging;
use App\Models\Maatregel;
use App\Models\Meting;
use App\Models\Reviewsessie;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Models\ScopeVerklaring;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Models\Verbeteractie;
use Database\Seeders\RisicocriteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExporteerIsmsTest extends TestCase
{
    use RefreshDatabase;

    private string $doel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doel = sys_get_temp_dir().'/isms-export-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->doel);
        parent::tearDown();
    }

    /** De enige map die de run onder het doel aanmaakt. */
    private function exportMap(): string
    {
        $mappen = File::directories($this->doel);
        $this->assertCount(1, $mappen, 'Verwacht precies één exportmap.');

        return $mappen[0];
    }

    public function test_maakt_een_datumstempel_map_met_domeinbestanden(): void
    {
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        $map = $this->exportMap();
        $this->assertStringContainsString('isms-export-'.now()->format('Y-m-d'), basename($map));
        $this->assertFileExists($map.'/00-overzicht.md');
        $this->assertFileExists($map.'/03-risico-en-soa.md');
        $this->assertFileExists($map.'/07-audits.md');
    }

    public function test_anonimiseert_persoonsgegevens_standaard(): void
    {
        $eigenaar = Gebruiker::factory()->create(['naam' => 'Jan de Vries']);
        Risico::factory()->create(['titel' => 'Testrisico', 'risico_eigenaar_id' => $eigenaar->id]);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $risico = File::get($this->exportMap().'/03-risico-en-soa.md');

        $this->assertStringNotContainsString('Jan de Vries', $risico);
        $this->assertStringContainsString('JdV', $risico); // initialen
    }

    public function test_met_persoonsgegevens_toont_de_volledige_naam(): void
    {
        $eigenaar = Gebruiker::factory()->create(['naam' => 'Jan de Vries']);
        Risico::factory()->create(['titel' => 'Testrisico', 'risico_eigenaar_id' => $eigenaar->id]);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-persoonsgegevens' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('Jan de Vries', File::get($this->exportMap().'/03-risico-en-soa.md'));
    }

    /*
     |--------------------------------------------------------------------------
     | Plan 00c fase 1: de deelnamegegevens achter --met-persoonsgegevens
     |--------------------------------------------------------------------------
     | Deze twee horen als PAAR te bestaan. De bug die dit plan repareert was
     | precies wat je krijgt als alleen de negatieve kant getest is: de export
     | meldde dat deelname en leesbevestigingen achter de vlag zaten, terwijl ze
     | in geen enkele stand werden geschreven.
     */

    /** Een voltooiing en een leesbevestiging, klaar om te exporteren. */
    private function deelnameVastleggen(): Gebruiker
    {
        $lid = Gebruiker::factory()->create(['naam' => 'Jan de Vries']);

        $module = Trainingsmodule::factory()->create(['titel' => 'Basis informatiebeveiliging']);
        Trainingsvoltooiing::factory()->create([
            'trainingsmodule_id' => $module->id,
            'gebruiker_id' => $lid->id,
            'bron' => 'zelfregistratie',
        ]);

        $document = Beleidsdocument::factory()->create(['titel' => 'Informatiebeveiligingsbeleid']);
        $versie = Beleidsversie::factory()->create([
            'beleidsdocument_id' => $document->id,
            'versienummer' => '1.2',
        ]);
        Leesbevestiging::create([
            'beleidsversie_id' => $versie->id,
            'gebruiker_id' => $lid->id,
            'bevestigd_op' => now(),
        ]);

        return $lid;
    }

    public function test_met_persoonsgegevens_bevat_de_deelnamegegevens(): void
    {
        $this->deelnameVastleggen();

        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-persoonsgegevens' => true])
            ->assertSuccessful();

        $md = File::get($this->exportMap().'/09-bewustzijn-en-training.md');

        $this->assertStringContainsString('Trainingsdeelname', $md);
        $this->assertStringContainsString('Basis informatiebeveiliging', $md);
        $this->assertStringContainsString('zelfregistratie', $md);

        $this->assertStringContainsString('Leesbevestigingen', $md);
        $this->assertStringContainsString('Informatiebeveiligingsbeleid', $md);
        // Het versienummer is het halve punt: een bevestiging op 1.2 zegt niets
        // over een latere versie.
        $this->assertStringContainsString('1.2', $md);

        $this->assertStringContainsString('Jan de Vries', $md);
    }

    public function test_zonder_de_vlag_geen_deelnamegegevens_en_de_tekst_klopt(): void
    {
        $this->deelnameVastleggen();

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        $md = File::get($this->exportMap().'/09-bewustzijn-en-training.md');

        $this->assertStringNotContainsString('Jan de Vries', $md);
        $this->assertStringNotContainsString('Trainingsdeelname', $md);
        $this->assertStringNotContainsString('Leesbevestigingen', $md);
        $this->assertStringContainsString('Draai met `--met-persoonsgegevens`', $md);
    }

    /*
     |--------------------------------------------------------------------------
     | Plan 00c fase 2: het issue-register (§4.1)
     |--------------------------------------------------------------------------
     */

    public function test_de_export_toont_het_issue_register_en_waar_het_landt(): void
    {
        $gekoppeld = Issue::factory()->create(['omschrijving' => 'Alles loopt via één platform']);
        $gekoppeld->risicos()->attach(Risico::factory()->create(['titel' => 'Uitval van het platform']));

        Issue::factory()->create(['omschrijving' => 'Kwestie zonder risico']);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/01-context-scope.md');

        $this->assertStringContainsString('Issues (§4.1)', $md);
        $this->assertStringContainsString('Alles loopt via één platform', $md);
        // De doorvertaling is de reden dat het register in de export staat.
        $this->assertStringContainsString('Uitval van het platform', $md);
        // En een kwestie die nergens landt is als zodanig herkenbaar.
        $this->assertStringContainsString('Kwestie zonder risico', $md);
    }

    /*
     |--------------------------------------------------------------------------
     | Plan 00d: de resterende registers
     |--------------------------------------------------------------------------
     */

    public function test_de_risicocriteria_staan_boven_de_risicos(): void
    {
        $this->seed(RisicocriteriaSeeder::class);
        RisicocriteriaVersie::actief()->update([
            'omschrijving' => 'Boven de drempel tekent de directie.',
            'goedgekeurd_door' => 'Directie',
        ]);
        RisicocriteriaVersie::vergeet();
        Risico::factory()->create(['titel' => 'Testrisico']);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/03-risico-en-soa.md');

        $this->assertStringContainsString('Risicocriteria', $md);
        $this->assertStringContainsString('Boven de drempel tekent de directie.', $md);

        // Wanneer het kader is vastgesteld en onder welke versie: zonder dat is
        // het een instelling en geen criterium (04g §8.1). De goedkeurder is een
        // vrij naamveld en volgt dus de anonimisering hierboven — die staat er
        // pas bij `--met-persoonsgegevens`.
        $this->assertStringContainsString('**Versie 1**', $md);
        $this->assertStringNotContainsString('Directie', $md);
        // De kwantitatieve band krijgt een eigen kolom; een auditor die op
        // cijfers stuurt leest die en niet het proza ernaast.
        $this->assertStringContainsString('Kwantitatieve band', $md);

        // Ook de schaal hoort bij het kader (00j §1.3): zonder de betekenis van
        // de niveaus staat er straks "kans 3 × impact 4" en is niet vast te
        // stellen wát een 4 was. De schaal hangt bovendien aan het normprofiel
        // van de installatie, dus uit de code is hij niet af te leiden.
        $this->assertStringContainsString('### Schaal — Kans', $md);
        $this->assertStringContainsString('### Schaal — Impact', $md);
        $this->assertStringContainsString('3 — Middelmatig', $md);
        $this->assertLessThan(
            strpos($md, 'Testrisico'),
            strpos($md, '### Schaal — Kans'),
            'De schaal hoort boven de risico\'s te staan.'
        );
        // Het kader hoort vóór de risico's te staan, niet als bijlage erachter.
        $this->assertLessThan(
            strpos($md, 'Testrisico'),
            strpos($md, 'Risicocriteria'),
            'De criteria horen boven de risico\'s te staan.'
        );
    }

    public function test_zonder_criteria_noemt_de_export_de_standaarddrempels(): void
    {
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        // Een lezer moet niet in de code hoeven kijken waar de scores tegen zijn
        // afgezet; de terugval hoort in de export te staan.
        $this->assertStringContainsString(
            (string) Risico::DREMPEL_STANDAARD,
            File::get($this->exportMap().'/03-risico-en-soa.md')
        );
    }

    public function test_bewijs_staat_bij_de_entiteit_in_beide_standen(): void
    {
        Storage::fake(Bewijsstuk::DISK);
        Storage::disk(Bewijsstuk::DISK)->put('bewijs/pentest.pdf', 'x');

        $stuk = Bewijsstuk::factory()->create([
            'bestandsnaam' => 'pentest.pdf',
            'opslaglocatie_referentie' => 'bewijs/pentest.pdf',
        ]);
        $incident = Incident::factory()->create(['titel' => 'Testincident']);
        BewijsKoppeling::create([
            'bewijsstuk_id' => $stuk->id,
            'blok_naam' => 'incident-afwijkingenbeheer',
            'entiteit_type' => $incident->getMorphClass(),
            'entiteit_id' => $incident->id,
        ]);

        // Zonder --met-bewijs: de bestandsnaam wijst de vindplaats aan.
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/06-incidenten-en-afwijkingen.md');
        $this->assertStringContainsString('Bewijs: pentest.pdf', $md);
        $this->assertStringNotContainsString('_bewijs/', $md);

        File::deleteDirectory($this->doel);

        // Mét de vlag: een werkende link naar het meegekopieerde bestand.
        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-bewijs' => true])->assertSuccessful();
        $this->assertStringContainsString(
            '](_bewijs/'.$stuk->id.'-pentest.pdf)',
            File::get($this->exportMap().'/06-incidenten-en-afwijkingen.md')
        );
    }

    public function test_de_kpi_meethistorie_gaat_mee(): void
    {
        $kpi = KpiDefinitie::create([
            'sleutel' => 'soa_beoordeeld',
            'naam' => 'SoA beoordeeld',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Beoordeelde SoA-regels gedeeld door het totaal.',
        ]);
        foreach ([['2026-01-31', 40], ['2026-02-28', 55]] as [$datum, $teller]) {
            Meting::create([
                'kpi_definitie_id' => $kpi->id, 'gemeten_op' => $datum,
                'teller' => $teller, 'noemer' => 93, 'definitie_versie' => 1,
            ]);
        }

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/08-meten-en-directiebeoordeling.md');

        $this->assertStringContainsString('Meethistorie', $md);
        // Beide metingen, niet alleen de laatste — dat is het hele punt.
        $this->assertStringContainsString('31-01-2026', $md);
        $this->assertStringContainsString('28-02-2026', $md);
        $this->assertStringContainsString('Vastgelegd door', $md);
    }

    // --- De onderbouwing onder de cijfers (plan 00e) ------------------------

    /** @param array<string, mixed> $extra */
    private function kpi(array $extra = []): KpiDefinitie
    {
        return KpiDefinitie::create($extra + [
            'sleutel' => 'soa_beoordeeld',
            'meetbron' => 'soa_beoordeeld',
            'naam' => 'SoA beoordeeld',
            'fase' => 'check',
            'eenheid' => 'ratio',
            'richting' => 'omhoog',
            'berekeningswijze' => 'Beoordeelde SoA-regels gedeeld door het totaal aantal Annex A-maatregelen.',
            'definitie_versie' => 3,
            'actief' => true,
        ]);
    }

    private function metenMd(): string
    {
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        return File::get($this->exportMap().'/08-meten-en-directiebeoordeling.md');
    }

    /**
     * §9.1 vraagt letterlijk om de méthoden. Zonder de berekeningswijze krijgt
     * het ontvangende ISMS cijfers die het niet kan uitleggen of voortzetten —
     * dit is de hoofdreden van plan 00e.
     */
    public function test_de_meetaanpak_per_kpi_gaat_mee(): void
    {
        $this->kpi();

        $md = $this->metenMd();

        $this->assertStringContainsString('Meetaanpak per KPI', $md);
        $this->assertStringContainsString('gedeeld door het totaal aantal Annex A-maatregelen', $md);
        $this->assertStringContainsString('richting: omhoog (hoger is beter)', $md);
        $this->assertStringContainsString('Definitieversie: 3', $md);
        $this->assertStringContainsString('berekend door de applicatie', $md);
    }

    public function test_een_vastgestelde_norm_reist_mee_met_haar_datum(): void
    {
        $this->kpi([
            'streefwaarde' => 95, 'signaalwaarde' => 85,
            'streefwaarde_vastgesteld_op' => '2026-03-01',
        ]);

        $md = $this->metenMd();

        $this->assertStringContainsString('95% / 85% — vastgesteld op 01-03-2026', $md);
        $this->assertStringNotContainsString('voorstel, niet vastgesteld', $md);
    }

    /**
     * De borging van 00e §3. Reist de streefwaarde mee zonder haar status, dan
     * erft de ontvangende organisatie een voorstel als vastgesteld beleid —
     * precies de fout waarvoor `streefwaarde_vastgesteld_op` bestaat, verplaatst
     * naar het ontvangende systeem.
     */
    public function test_een_voorstel_reist_mee_als_voorstel_en_niet_als_norm(): void
    {
        $this->kpi(['streefwaarde' => 95, 'signaalwaarde' => 85]);

        $md = $this->metenMd();

        $this->assertStringContainsString('**voorstel, niet vastgesteld**', $md);
        $this->assertStringNotContainsString('vastgesteld op', $md);
    }

    public function test_een_handmatige_en_een_inactieve_kpi_zijn_als_zodanig_herkenbaar(): void
    {
        $this->kpi(['meetbron' => null, 'actief' => false]);

        $md = $this->metenMd();

        $this->assertStringContainsString('handmatig ingevoerd', $md);
        $this->assertStringContainsString('inactief: wordt niet meer gemeten', $md);
    }

    /**
     * Elke meetrij moet te interpreteren zijn zonder de definitie erbij te
     * halen — ook nadat de norm of de berekening is bijgesteld.
     */
    public function test_elke_meetrij_draagt_de_norm_en_de_versie_van_dat_moment(): void
    {
        $kpi = $this->kpi(['streefwaarde' => 95, 'streefwaarde_vastgesteld_op' => '2026-01-01']);

        Meting::create([
            'kpi_definitie_id' => $kpi->id, 'gemeten_op' => '2026-01-31',
            'teller' => 40, 'noemer' => 93, 'definitie_versie' => 2,
            'streefwaarde' => 80, 'signaalwaarde' => 60,
        ]);

        $md = $this->metenMd();

        $this->assertStringContainsString('| Norm toen | Def. |', $md);
        // De norm van tóén (80/60), niet de huidige (95).
        $this->assertStringContainsString('| 80% / 60% | v2 |', $md);
    }

    public function test_uitgifte_van_middelen_alleen_met_de_vlag(): void
    {
        $lid = Gebruiker::factory()->create(['naam' => 'Jan de Vries']);
        $asset = Asset::factory()->create(['naam' => 'Laptop L-042']);

        // Een geretourneerde uitgifte hoort er volgens het besluit in plan 00d §8
        // bij te staan: juist die registratie bewíjst dat de retourstap is gezet.
        AssetToewijzing::create([
            'asset_id' => $asset->id,
            'gebruiker_id' => $lid->id,
            'toegewezen_op' => '2025-01-06',
            'geretourneerd_op' => '2026-03-31',
        ]);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-persoonsgegevens' => true])
            ->assertSuccessful();
        $md = File::get($this->exportMap().'/02-assets.md');
        $this->assertStringContainsString('Laptop L-042', $md);
        $this->assertStringContainsString('31-03-2026', $md);

        File::deleteDirectory($this->doel);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $this->assertStringNotContainsString(
            'Uitgifte van bedrijfsmiddelen',
            File::get($this->exportMap().'/02-assets.md')
        );
    }

    public function test_verbeteracties_hangen_onder_hun_besluit(): void
    {
        $sessie = Reviewsessie::factory()->create();
        $besluit = Besluit::factory()->create([
            'reviewsessie_id' => $sessie->id,
            'omschrijving' => 'De KPI-set wordt herzien.',
        ]);
        Verbeteractie::create([
            'besluit_id' => $besluit->id,
            'omschrijving' => 'Twee dubbele indicatoren samenvoegen.',
            'status' => 'open',
            'deadline' => '2026-09-30',
        ]);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/08-meten-en-directiebeoordeling.md');

        $this->assertStringContainsString('De KPI-set wordt herzien.', $md);
        // Zonder dit loopt een besluit in de export dood.
        $this->assertStringContainsString('Twee dubbele indicatoren samenvoegen.', $md);
    }

    public function test_het_overzicht_benoemt_wat_er_bewust_niet_in_staat(): void
    {
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $md = File::get($this->exportMap().'/00-overzicht.md');

        $this->assertStringContainsString('Bewust niet opgenomen', $md);
        $this->assertStringContainsString('audit-trail', $md);
        $this->assertStringContainsString('notificaties', $md);
    }

    /**
     * Plan 04d fase 4: de export toont de effectieve classificatie mét de
     * herkomst. Een auditor moet in het geëxporteerde SoA-bewijs kunnen zien wat
     * de organisatie zelf heeft bepaald en wat er nog op het meegeleverde
     * uitgangspunt staat.
     */
    public function test_export_toont_de_effectieve_classificatie_met_herkomst(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create([
            'annex_a_referentie' => '5.1',
            'kenmerken' => ['type' => ['Preventief']],
        ])->soaRegel;

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $soa = File::get($this->exportMap().'/03-risico-en-soa.md');

        $this->assertStringContainsString('meegeleverd uitgangspunt', $soa);
        $this->assertStringContainsString('Type: Preventief', $soa);

        // Nu een eigen vaststelling die afwijkt: dat moet er anders in staan.
        $regel->update(['kenmerken_eigen' => ['type' => ['Detectief']]]);
        File::deleteDirectory($this->doel);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $soa = File::get($this->exportMap().'/03-risico-en-soa.md');

        $this->assertStringContainsString('afwijkend van het meegeleverde uitgangspunt', $soa);
        $this->assertStringContainsString('Type: Detectief', $soa);
        $this->assertStringNotContainsString('Type: Preventief', $soa);
    }

    public function test_vrije_tekst_naamveld_alleen_met_de_vlag(): void
    {
        ScopeVerklaring::factory()->create(['goedgekeurd_door' => 'Directeur Pietersen', 'status' => 'actief']);

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $this->assertStringNotContainsString('Directeur Pietersen', File::get($this->exportMap().'/01-context-scope.md'));

        File::deleteDirectory($this->doel);
        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-persoonsgegevens' => true])->assertSuccessful();
        $this->assertStringContainsString('Directeur Pietersen', File::get($this->exportMap().'/01-context-scope.md'));
    }

    public function test_met_bewijs_kopieert_bestanden(): void
    {
        Storage::fake('bewijs');
        $stuk = Bewijsstuk::factory()->create(['bestandsnaam' => 'beleid.pdf']);
        Storage::disk('bewijs')->put($stuk->opslaglocatie_referentie, 'PDF-inhoud');

        $this->artisan('isms:exporteer', ['--doel' => $this->doel, '--met-bewijs' => true])->assertSuccessful();

        $bewijsMap = $this->exportMap().'/_bewijs';
        $this->assertDirectoryExists($bewijsMap);
        $this->assertNotEmpty(File::files($bewijsMap), 'Verwacht minstens één gekopieerd bewijsbestand.');
    }

    public function test_zonder_bewijs_geen_bewijsmap(): void
    {
        Storage::fake('bewijs');
        $stuk = Bewijsstuk::factory()->create();
        Storage::disk('bewijs')->put($stuk->opslaglocatie_referentie, 'inhoud');

        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->exportMap().'/_bewijs');
    }

    public function test_tweede_run_zelfde_dag_overschrijft_niet(): void
    {
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();
        $this->artisan('isms:exporteer', ['--doel' => $this->doel])->assertSuccessful();

        $this->assertCount(2, File::directories($this->doel), 'Elke run hoort een eigen map te maken.');
    }
}
