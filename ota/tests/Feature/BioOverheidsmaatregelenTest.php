<?php

namespace Tests\Feature;

use App\Livewire\SoaOverzicht;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\Overheidsmaatregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\SoaRegel;
use App\Support\Koppelbaar;
use App\Support\Overheidsmaatregeldekking;
use App\Support\Schermkopie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\MaatregelSeeder;
use Database\Seeders\OverheidsmaatregelSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use Tests\TestCase;

/**
 * De BIO-overheidsmaatregelen (deelproducten/04b-bio-overheidsmaatregelen.md).
 *
 * De teksten van de verplichtingen worden nooit meegeleverd — de BIO staat onder
 * CC BY-NC-SA 4.0 en of het Cyberbeveiligingsbesluit die beperking opheft is een
 * open vraag (implementatie/00q §8). `overheidsmaatregelen-bio2.json` draagt dus
 * alleen de structuur, en de tests die over de tekst gaan zetten die zelf.
 *
 * Elke test zet zijn eigen profiel, ook de ISO-kant: zie de klassenkop van
 * NormprofielTest voor waarom leunen op `phpunit.xml` hier niet werkt.
 */
#[Group('bio2')]
class BioOverheidsmaatregelenTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    private string $map;

    protected function setUp(): void
    {
        parent::setUp();

        $this->map = $this->mapZonderTeksten();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->map.'/*') ?: [] as $pad) {
            // Alleen de eigen koppelingen en het eigen tekstbestand; de originelen
            // in database/seeders/data/ blijven staan. Zou hier ooit een echt
            // bestand tussen zitten, dan gooit deze test de invoer van de
            // installatie weg — vandaar de expliciete controle.
            if (is_link($pad) || basename($pad) === 'overheidsmaatregel-teksten.json') {
                unlink($pad);
            }
        }

        @rmdir($this->map);

        parent::tearDown();
    }

    /**
     * De seedmap zoals het repo hem uitlevert: de structuurbestanden wél, het
     * lokale tekstbestand niet.
     *
     * Op een ontwikkelmachine of bij een klant die de BIO heeft gedownload staat
     * `overheidsmaatregel-teksten.json` naast de seeddata, en dan zou de seeder
     * hier echte normtekst inlezen. De tests over de markering en over de
     * mededeling op het scherm zouden dan omvallen op precies het moment dat de
     * voorziening doet wat hij moet doen. Symbolische koppelingen in plaats van
     * kopieën, zodat een nieuw bestand in de seedmap hier niet vergeten kan worden.
     */
    private function mapZonderTeksten(): string
    {
        $map = sys_get_temp_dir().'/bio-seed-'.uniqid();
        mkdir($map);

        foreach (glob(database_path('seeders/data').'/*') ?: [] as $bron) {
            if (basename($bron) !== 'overheidsmaatregel-teksten.json') {
                symlink($bron, $map.'/'.basename($bron));
            }
        }

        config()->set('norm.maatregelenmap', $map);

        return $map;
    }

    private function biomodus(): void
    {
        config()->set('norm.actief', 'bio2');
    }

    private function isomodus(): void
    {
        config()->set('norm.actief', 'iso27001');
    }

    /** Het volledige BIO-profiel: 93 beheersmaatregelen plus de laag eronder. */
    private function seedBio(): void
    {
        $this->biomodus();
        $this->seed([MaatregelSeeder::class, OverheidsmaatregelSeeder::class]);
    }

    // --- Referentiedata ----------------------------------------------------

    public function test_bio_levert_dezelfde_93_beheersmaatregelen_als_iso(): void
    {
        $this->seedBio();

        $this->assertSame(93, Maatregel::count());

        // De kern van 00q §4: de BIO voegt op dit niveau niets toe. Zou het bestand
        // ooit gaan afwijken van de ISO-set, dan valt deze test om — en dat is dan
        // een normwijziging en geen testfout.
        $bio = Maatregel::orderBy('annex_a_referentie')->pluck('annex_a_referentie');

        $this->isomodus();
        Maatregel::query()->delete();
        $this->seed(MaatregelSeeder::class);

        $this->assertSame(
            Maatregel::orderBy('annex_a_referentie')->pluck('annex_a_referentie')->all(),
            $bio->all(),
        );
    }

    public function test_de_bronlijst_bevat_127_regels_waarvan_118_geldend(): void
    {
        $this->seedBio();

        $this->assertSame(127, Overheidsmaatregel::count());
        $this->assertSame(118, Overheidsmaatregel::where('status', 'geldend')->count());
        $this->assertSame(4, Overheidsmaatregel::where('status', 'vervallen')->count());
        $this->assertSame(5, Overheidsmaatregel::where('status', 'verplaatst')->count());
    }

    public function test_elke_geldende_verplichting_krijgt_een_onbeoordeelde_rij(): void
    {
        $this->seedBio();

        $this->assertSame(118, OverheidsmaatregelBeoordeling::count());
        $this->assertSame(118, OverheidsmaatregelBeoordeling::where('status', 'niet_beoordeeld')->count());

        // Vervallen en verplaatste nummers dragen géén beoordeling: er valt niets
        // meer over vast te stellen. Ze blijven wel bestaan, want "verplaatst naar
        // 5.26.02" is een ander antwoord dan "bestaat niet".
        $vervallen = Overheidsmaatregel::where('status', 'vervallen')->first();
        $this->assertSame(0, $vervallen->beoordelingen()->count());
    }

    public function test_de_nummering_hangt_aan_de_juiste_beheersmaatregel(): void
    {
        $this->seedBio();

        $om = Overheidsmaatregel::where('nummer', '5.24.03')->first();

        $this->assertNotNull($om, 'Overheidsmaatregel 5.24.03 hoort in BIO2 v1.3 te bestaan.');
        $this->assertSame('5.24', $om->maatregel->annex_a_referentie);
        $this->assertSame(3, $om->volgnummer);
    }

    public function test_verplaatste_nummers_wijzen_naar_hun_opvolger(): void
    {
        $this->seedBio();

        $om = Overheidsmaatregel::where('nummer', '5.25.01')->first();

        $this->assertSame('verplaatst', $om->status);
        $this->assertSame('5.26.02', $om->verwezen_naar);
        $this->assertSame('Verplaatst naar 5.26.02.', $om->statusMededeling());
        $this->assertFalse($om->isGeldend());
    }

    public function test_de_seeder_doet_niets_buiten_een_bio_installatie(): void
    {
        $this->isomodus();
        $this->seed([MaatregelSeeder::class, OverheidsmaatregelSeeder::class]);

        $this->assertSame(0, Overheidsmaatregel::count());
        $this->assertSame(0, OverheidsmaatregelBeoordeling::count());

        // En de Cbw-vlag blijft op de onschuldige stand: buiten de BIO is de
        // reikwijdte van die wet geen onderwerp.
        $this->assertSame(0, Maatregel::where('cbw_reikwijdte', false)->count());
    }

    public function test_de_seeder_is_idempotent(): void
    {
        $this->seedBio();
        $this->seed(OverheidsmaatregelSeeder::class);

        $this->assertSame(127, Overheidsmaatregel::count());
        $this->assertSame(118, OverheidsmaatregelBeoordeling::count());
    }

    // --- Cbw-reikwijdte ----------------------------------------------------

    public function test_drie_beheersmaatregelen_vallen_buiten_de_cbw_reikwijdte(): void
    {
        $this->seedBio();

        // Intellectueel eigendom, archivering en privacy: die hebben hun eigen wet
        // en zijn geen cyberbeveiligingszorgplicht (00q §9).
        $this->assertSame(
            ['5.32', '5.33', '5.34'],
            Maatregel::where('cbw_reikwijdte', false)
                ->orderBy('annex_a_referentie')
                ->pluck('annex_a_referentie')
                ->all(),
        );

        // Van die drie draagt er maar één een werkelijke overheidsmaatregel; de
        // andere twee zijn precies de reden dat de vlag óók op de beheersmaatregel
        // staat en niet alleen op de verplichting (04b §2).
        $this->assertSame(
            ['5.33.01'],
            Overheidsmaatregel::where('cbw_reikwijdte', false)->pluck('nummer')->all(),
        );
    }

    public function test_een_referentie_die_uit_de_lijst_verdwijnt_krijgt_de_vlag_terug(): void
    {
        $this->seedBio();

        // Alsof een vorige uitgave 8.1 buiten bereik plaatste. De seeder moet dat
        // terugdraaien, anders blijft het ISMS beweren wat de norm niet meer zegt.
        Maatregel::where('annex_a_referentie', '8.1')->update(['cbw_reikwijdte' => false]);

        $this->seed(OverheidsmaatregelSeeder::class);

        $this->assertTrue(Maatregel::where('annex_a_referentie', '8.1')->first()->cbw_reikwijdte);
    }

    // --- Tekst en licentie -------------------------------------------------

    public function test_het_repo_levert_geen_bio_tekst_mee(): void
    {
        // Dit is de licentiebewaking, en die gaat over het bestand in versiebeheer
        // — niet over wat er in deze database staat. Een installatie die de BIO
        // heeft gedownload hoort hier juist wél tekst te hebben; het bestand dat
        // wij uitleveren nooit.
        $bron = json_decode(
            file_get_contents(database_path('seeders/data/'.OverheidsmaatregelSeeder::bestandsnaam())),
            true,
        );

        $geldend = array_filter(
            $bron['overheidsmaatregelen'],
            fn (array $regel) => $regel['status'] === 'geldend',
        );

        $this->assertCount(118, $geldend);

        foreach ($geldend as $regel) {
            $this->assertSame(Overheidsmaatregel::TEKST_NIET_MEEGELEVERD, $regel['tekst'], $regel['nummer']);
        }

        // En de weg van bestand naar scherm: zonder lokaal tekstbestand blijft de
        // markering staan en toont het model hem niet als normtekst.
        $this->seedBio();

        $this->assertSame(
            118,
            Overheidsmaatregel::where('status', 'geldend')
                ->where('tekst', Overheidsmaatregel::TEKST_NIET_MEEGELEVERD)
                ->count(),
        );

        $this->assertFalse(Overheidsmaatregel::where('nummer', '5.01.01')->first()->toontTekst());
    }

    public function test_de_markering_in_de_generator_is_dezelfde_als_in_de_code(): void
    {
        // Zelfde bewaking als bij de zorgaanvulling: twee literals die hetzelfde
        // moeten zeggen, in twee talen. Lopen ze uit elkaar, dan schrijft de
        // generator een tekst die het model niet als markering herkent — en dan
        // toont de SoA hem als normtekst.
        $script = base_path('../scripts/genereer_overheidsmaatregelen_seed.py');

        if (! is_file($script)) {
            $this->markTestSkipped('De generator zit niet in de uitlevering.');
        }

        $this->assertStringContainsString(
            'TEKST_NIET_MEEGELEVERD = "'.Overheidsmaatregel::TEKST_NIET_MEEGELEVERD.'"',
            file_get_contents($script),
        );
    }

    public function test_een_lokaal_tekstbestand_wordt_ingelezen(): void
    {
        // Het enige bestand dat de map van setUp niet heeft; hier zet de test hem
        // er zelf naast, zoals een installatie die de BIO gedownload heeft.
        file_put_contents($this->map.'/overheidsmaatregel-teksten.json', json_encode([
            'teksten' => ['5.01.01' => 'De entiteit heeft een informatiebeveiligingsbeleid opgesteld.'],
        ]));

        $this->seedBio();

        $om = Overheidsmaatregel::where('nummer', '5.01.01')->first();

        $this->assertTrue($om->toontTekst());
        $this->assertSame('De entiteit heeft een informatiebeveiligingsbeleid opgesteld.', $om->tekst);

        // De rest blijft op de markering staan: gedeeltelijk invullen mag, net als
        // bij de normteksten van de beheersmaatregelen.
        $this->assertFalse(Overheidsmaatregel::where('nummer', '5.01.02')->first()->toontTekst());
    }

    // --- Het scherm --------------------------------------------------------

    public function test_de_modal_toont_de_verplichtingen_alleen_in_bio_modus(): void
    {
        $this->seedBio();

        $regel = SoaRegel::whereHas('maatregel', fn ($q) => $q->where('annex_a_referentie', '5.24'))->first();

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->assertSee('Overheidsmaatregelen')
            ->assertSee('5.24.03')
            // De markering zelf komt nooit op het scherm; de mededeling wél.
            ->assertDontSee(Overheidsmaatregel::TEKST_NIET_MEEGELEVERD)
            ->assertSee(Overheidsmaatregel::GEEN_TEKST_AANHEF);
    }

    public function test_in_iso_modus_staat_er_geen_bio_blok(): void
    {
        $this->isomodus();
        $this->seed(MaatregelSeeder::class);

        $regel = SoaRegel::first();

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            // Ook geen kolom in de tabel: een lege kolom in een ISO-installatie
            // zou de lezer laten zoeken naar iets wat er niet is.
            ->assertDontSee('Verplichtingen')
            ->call('bewerk', $regel->id)
            ->assertDontSee('Overheidsmaatregelen')
            ->assertSet('beoordelingen', []);
    }

    public function test_een_verplichting_beoordelen_zet_status_en_datum(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Van toepassing op onze omgeving.',
                "beoordelingen.{$beoordeling->id}.status" => 'belegd',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertSame('belegd', $beoordeling->status);
        $this->assertNotNull($beoordeling->laatst_beoordeeld_op);
        $this->assertSame($this->ciso->id, $beoordeling->beoordeeld_door_id);
    }

    public function test_niet_belegd_en_niet_van_toepassing_vragen_een_onderbouwing(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        foreach (['niet_belegd', 'niet_van_toepassing'] as $status) {
            Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
                ->call('bewerk', $beoordeling->soa_regel_id)
                ->set([
                    'vanToepassing' => '1',
                    'motivatie' => 'Onderbouwing van de beheersmaatregel.',
                    "beoordelingen.{$beoordeling->id}.status" => $status,
                    "beoordelingen.{$beoordeling->id}.motivatie" => '',
                ])
                ->call('opslaan')
                ->assertHasErrors('beoordelingen');

            $this->assertSame('niet_beoordeeld', $beoordeling->refresh()->status);
        }
    }

    public function test_belegd_vraagt_geen_onderbouwing(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Onderbouwing van de beheersmaatregel.',
                "beoordelingen.{$beoordeling->id}.status" => 'belegd',
                "beoordelingen.{$beoordeling->id}.motivatie" => '',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('belegd', $beoordeling->refresh()->status);
    }

    public function test_een_beoordeling_van_een_andere_maatregel_wordt_niet_bijgewerkt(): void
    {
        $this->seedBio();

        $eigen = $this->beoordelingVan('5.24.01');
        $vreemd = $this->beoordelingVan('5.01.01');

        // Het formulier stuurt id's mee en die komen van de client. Zonder de filter
        // in `slaBeoordelingenOp()` zou dit een verplichting onder een héél andere
        // beheersmaatregel bijwerken.
        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $eigen->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Onderbouwing.',
                "beoordelingen.{$vreemd->id}.status" => 'belegd',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('niet_beoordeeld', $vreemd->refresh()->status);
    }

    public function test_opslaan_zonder_wijziging_verzet_de_beoordelingsdatum_niet(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');
        $beoordeling->update(['status' => 'belegd', 'laatst_beoordeeld_op' => '2026-01-15']);

        // De datum moet "wanneer is naar déze verplichting gekeken" betekenen. Zou
        // elke opslag van de modal hem bijwerken, dan verjaart er nooit iets en is
        // de teller "verouderd" waardeloos.
        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Alleen de bovenliggende motivatie gewijzigd.',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertSame('2026-01-15', $beoordeling->refresh()->laatst_beoordeeld_op->toDateString());
    }

    public function test_terug_naar_onbeoordeeld_wist_de_datum(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');
        $beoordeling->update([
            'status' => 'belegd',
            'laatst_beoordeeld_op' => now(),
            'beoordeeld_door_id' => $this->ciso->id,
        ]);

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Onderbouwing.',
                "beoordelingen.{$beoordeling->id}.status" => 'niet_beoordeeld',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertNull($beoordeling->laatst_beoordeeld_op);
        $this->assertNull($beoordeling->beoordeeld_door_id);
    }

    public function test_een_risicoanalyse_van_een_andere_control_wordt_geweigerd(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        // Een behandeling die níét aan deze control hangt. Zo'n verwijzing ziet
        // eruit als een onderbouwing en is dat niet.
        $risico = Risico::factory()->create();
        $los = Risicobehandeling::create(['risico_id' => $risico->id, 'behandeloptie' => 'accepteren']);

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Onderbouwing.',
                "beoordelingen.{$beoordeling->id}.status" => 'niet_van_toepassing',
                "beoordelingen.{$beoordeling->id}.motivatie" => 'Kan hier niet van toepassing zijn.',
                "beoordelingen.{$beoordeling->id}.risicobehandeling_id" => (string) $los->id,
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertSame('niet_van_toepassing', $beoordeling->status);
        $this->assertNull($beoordeling->risicobehandeling_id);
        $this->assertTrue($beoordeling->mistRisicoanalyse());
    }

    public function test_een_gekoppelde_risicoanalyse_wordt_vastgelegd(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        $risico = Risico::factory()->create();
        $behandeling = Risicobehandeling::create(['risico_id' => $risico->id, 'behandeloptie' => 'accepteren']);
        $behandeling->soaRegels()->attach($beoordeling->soa_regel_id);

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Onderbouwing.',
                "beoordelingen.{$beoordeling->id}.status" => 'niet_van_toepassing',
                "beoordelingen.{$beoordeling->id}.motivatie" => 'Kan hier niet van toepassing zijn.',
                "beoordelingen.{$beoordeling->id}.risicobehandeling_id" => (string) $behandeling->id,
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertSame($behandeling->id, $beoordeling->risicobehandeling_id);
        $this->assertFalse($beoordeling->mistRisicoanalyse());
    }

    // --- Dekking en signalen -----------------------------------------------

    public function test_de_dekking_telt_alleen_toepasselijke_verplichtingen(): void
    {
        $this->seedBio();

        $dekking = Overheidsmaatregeldekking::huidige();

        $this->assertSame(118, $dekking->totaal);
        $this->assertSame(118, $dekking->onbeoordeeld);
        $this->assertSame(0, $dekking->percentageBelegd());

        // Een uitgesloten beheersmaatregel haalt zijn verplichtingen uit de
        // noemer: die hebben geen betekenis meer (04b §3.2).
        $regel = $this->beoordelingVan('5.24.01')->soaRegel;
        $onder = $regel->overheidsmaatregelBeoordelingen()->count();
        $regel->update(['van_toepassing' => false, 'motivatie' => 'Uitgesloten.']);

        $this->assertSame(118 - $onder, Overheidsmaatregeldekking::huidige()->totaal);
    }

    public function test_een_uitzondering_zonder_risicoanalyse_is_een_signaal(): void
    {
        $this->seedBio();

        $this->beoordelingVan('5.24.01')->update([
            'status' => 'niet_van_toepassing',
            'motivatie' => 'Kan hier niet van toepassing zijn.',
        ]);

        $dekking = Overheidsmaatregeldekking::huidige();

        $this->assertSame(1, $dekking->zonderRisicoanalyse);
        $this->assertArrayHasKey('uitzondering zonder risicoanalyse', $dekking->signalen());
    }

    public function test_een_gewijzigde_verplichting_maakt_de_beoordeling_verouderd(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');
        $beoordeling->update(['status' => 'belegd', 'laatst_beoordeeld_op' => now()]);

        $this->assertSame(0, Overheidsmaatregeldekking::huidige()->verouderd);

        // Er is geen kolom voor "verouderd": `overheidsmaatregelen.updated_at`
        // beweegt alleen als de seeder werkelijk iets veranderde, en die
        // vergelijking ís het antwoord (04b §3.4).
        //
        // Langs de query builder en niet via het model, om `updated_at` zelf te
        // kunnen zetten: de vergelijking is op de dag afgerond, dus een wijziging
        // en een beoordeling op dezelfde dag gelden bewust niet als verouderd.
        // Dit is dus een uitgave van morgen.
        Overheidsmaatregel::where('id', $beoordeling->overheidsmaatregel_id)->update([
            'tekst' => 'Een gewijzigde verplichting.',
            'updated_at' => now()->addDay(),
        ]);

        $dekking = Overheidsmaatregeldekking::huidige();

        $this->assertSame(1, $dekking->verouderd);
        $this->assertArrayHasKey('beoordeling verouderd na een normwijziging', $dekking->signalen());
    }

    public function test_de_dekking_is_leeg_buiten_een_bio_installatie(): void
    {
        $this->isomodus();
        $this->seed(MaatregelSeeder::class);

        $this->assertSame(0, Overheidsmaatregeldekking::huidige()->totaal);
        $this->assertNull(Overheidsmaatregeldekking::huidige()->percentageBelegd());
    }

    // --- Koppelvlakken -----------------------------------------------------

    public function test_bewijs_koppelen_kan_alleen_in_een_bio_installatie(): void
    {
        $this->seedBio();
        $this->actingAs($this->ciso);

        $this->assertArrayHasKey('overheidsmaatregel_beoordeling', Koppelbaar::toegestaneTypes());

        $this->isomodus();

        // Een type aanbieden waar nooit iets in kan zitten is verwarrender dan het
        // weglaten.
        $this->assertArrayNotHasKey('overheidsmaatregel_beoordeling', Koppelbaar::toegestaneTypes());
    }

    public function test_de_beoordeling_staat_in_de_audit_trail(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');
        $beoordeling->update(['status' => 'belegd', 'laatst_beoordeeld_op' => now()]);

        // De referentiedata is níét auditeerbaar — een nieuwe BIO-uitgave hoort geen
        // 127 auditregels op te leveren — maar wat de organisatie vaststelt wél.
        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'overheidsmaatregel_beoordeling',
            'entiteit_id' => $beoordeling->id,
            'actie' => 'gewijzigd',
        ]);
    }

    public function test_het_profiel_heeft_een_eigen_impactschaal(): void
    {
        $this->biomodus();

        // `config/beoordelingsschaal.php` gooit bij een ontbrekend profiel, dus
        // deze test bewaakt dat de schaal er is én dat hij niet die van ISO is.
        $bio = config('beoordelingsschaal.impact.profielen.bio2');
        $iso = config('beoordelingsschaal.impact.profielen.iso27001');

        $this->assertIsArray($bio);
        $this->assertCount(5, $bio['niveaus']);
        $this->assertNotSame($iso['leidraad'], $bio['leidraad']);
        $this->assertStringContainsString('publiek vertrouwen', $bio['leidraad']);
    }

    // --- Export ------------------------------------------------------------

    public function test_de_vvt_export_noemt_de_verplichtingen_per_maatregel(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.03');
        $beoordeling->update([
            'status' => 'belegd',
            'motivatie' => 'Belegd via het kwetsbaarhedenproces.',
            'laatst_beoordeeld_op' => now(),
        ]);

        $soa = $this->exporteer();

        $this->assertStringContainsString('**5.24.03** — Belegd', $soa);
        // De markering uit het seedbestand hoort niet in een auditdossier.
        $this->assertStringNotContainsString(Overheidsmaatregel::TEKST_NIET_MEEGELEVERD, $soa);
        // En de reikwijdte staat er waar ze afwijkt: dat bepaalt wat de RDI kan
        // handhaven.
        $this->assertStringContainsString('buiten de Cyberbeveiligingswet', $soa);
    }

    public function test_de_export_behoudt_de_regelovergangen_in_de_normtekst(): void
    {
        // 40 van de 118 teksten dragen een opsomming. Die liep tot 17-08-2026 als
        // één lange regel de export in, omdat `cel()` elke regelovergang naar een
        // spatie plat sloeg — juist in een tabelcel, fout in een opsommingsregel.
        file_put_contents($this->map.'/overheidsmaatregel-teksten.json', json_encode([
            'teksten' => ['5.24.03' => "De melding wordt afgehandeld op:\n• dag één;\n• dag twee."],
        ]));

        $this->seedBio();

        $this->beoordelingVan('5.24.03')->update([
            'status' => 'belegd',
            'motivatie' => "Belegd via:\n• het meldproces;\n• de dienstdoende beheerder.",
        ]);

        $soa = $this->exporteer();

        $this->assertStringContainsString("    - De melding wordt afgehandeld op:\n      • dag één;", $soa);
        $this->assertStringContainsString("    - Onderbouwing: Belegd via:\n      • het meldproces;", $soa);
    }

    public function test_de_export_noemt_de_referenties_per_verplichting(): void
    {
        $this->seedBio();

        $this->beoordelingVan('5.24.03')->update([
            'status' => 'belegd',
            'beleidreferentie' => 'Incidentbeleid §3.2',
            'procesreferentie' => 'Meldproces, stap 4',
        ]);

        $this->assertStringContainsString(
            'Verwijzing — beleid: Incidentbeleid §3.2; proces: Meldproces, stap 4',
            $this->exporteer(),
        );
    }

    public function test_de_bijlage_uitzonderingen_noemt_beide_niveaus(): void
    {
        $this->seedBio();

        // Niveau 1: een beheersmaatregel die niet van toepassing is. Voor de 39
        // maatregelen zonder overheidsmaatregel is dit de enige route.
        SoaRegel::whereHas('maatregel', fn ($q) => $q->where('annex_a_referentie', '7.4'))
            ->first()
            ->update(['van_toepassing' => false, 'motivatie' => 'Geen eigen fysieke locatie.']);

        // Niveau 2: een overheidsmaatregel die niet van toepassing kán zijn.
        $this->beoordelingVan('5.24.01')->update([
            'status' => 'niet_van_toepassing',
            'motivatie' => 'Kan hier niet van toepassing zijn.',
        ]);

        $soa = $this->exporteer();

        $this->assertStringContainsString('Bijlage: uitzonderingen op de VvT', $soa);
        $this->assertStringContainsString('A.7.4', $soa);
        $this->assertStringContainsString('5.24.01', $soa);
        // Een ontbrekende risicoanalyse staat er met zoveel woorden. Weglaten zou de
        // bijlage completer laten lijken dan ze is.
        $this->assertStringContainsString('**ontbreekt**', $soa);
    }

    public function test_zonder_uitzonderingen_zegt_de_bijlage_dat(): void
    {
        $this->seedBio();

        $this->assertStringContainsString('Geen uitzonderingen', $this->exporteer());
    }

    public function test_de_iso_export_krijgt_geen_bio_bijlage(): void
    {
        $this->isomodus();
        $this->seed(MaatregelSeeder::class);

        $soa = $this->exporteer();

        $this->assertStringContainsString('Verklaring van Toepasselijkheid', $soa);
        $this->assertStringNotContainsString('uitzonderingen op de VvT', $soa);
        $this->assertStringNotContainsString('Overheidsmaatregelen', $soa);
    }

    /** Exporteert en geeft het SoA-bestand terug. */
    private function exporteer(): string
    {
        $doel = sys_get_temp_dir().'/bio-export-'.uniqid();

        $this->artisan('isms:exporteer', ['--doel' => $doel])->assertSuccessful();

        // De export maakt onder het doel één map met een datumstempel.
        $mappen = File::directories($doel);
        $this->assertCount(1, $mappen, 'Verwacht precies één exportmap.');

        $inhoud = file_get_contents($mappen[0].'/03-risico-en-soa.md');

        File::deleteDirectory($doel);

        return $inhoud;
    }

    /** De beoordeling bij één nummer, met zijn relaties. */
    private function beoordelingVan(string $nummer): OverheidsmaatregelBeoordeling
    {
        $overheidsmaatregel = Overheidsmaatregel::where('nummer', $nummer)->firstOrFail();

        return OverheidsmaatregelBeoordeling::where('overheidsmaatregel_id', $overheidsmaatregel->id)
            ->firstOrFail();
    }

    // --- De twee referentievelden (04c §2 en §4.1) --------------------------

    public function test_de_referenties_worden_opgeslagen_en_teruggeladen(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Van toepassing op onze omgeving.',
                "beoordelingen.{$beoordeling->id}.status" => 'belegd',
                "beoordelingen.{$beoordeling->id}.beleidreferentie" => 'Incidentbeleid §3.2',
                "beoordelingen.{$beoordeling->id}.procesreferentie" => 'Meldproces, stap 4',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertSame('Incidentbeleid §3.2', $beoordeling->beleidreferentie);
        $this->assertSame('Meldproces, stap 4', $beoordeling->procesreferentie);

        // En terug in het formulier, want anders wist een volgende opslag ze.
        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->assertSet("beoordelingen.{$beoordeling->id}.beleidreferentie", 'Incidentbeleid §3.2');
    }

    public function test_alleen_een_gewijzigde_referentie_telt_als_beoordeling(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');
        $beoordeling->update([
            'status' => 'belegd',
            'laatst_beoordeeld_op' => now()->subMonths(2),
        ]);

        $eerder = $beoordeling->laatst_beoordeeld_op;

        // De val uit 04c §4.1: `slaBeoordelingenOp()` slaat een rij over die het
        // als ongewijzigd ziet. Staan de referenties niet in die vergelijking, dan
        // wordt dit veld stil niet opgeslagen en verspringt de datum niet.
        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Van toepassing op onze omgeving.',
                "beoordelingen.{$beoordeling->id}.beleidreferentie" => 'Wachtwoordbeleid §4.2',
            ])
            ->call('opslaan')
            ->assertHasNoErrors();

        $beoordeling->refresh();

        $this->assertSame('Wachtwoordbeleid §4.2', $beoordeling->beleidreferentie);
        $this->assertTrue($beoordeling->laatst_beoordeeld_op->gt($eerder));
        $this->assertSame($this->ciso->id, $beoordeling->beoordeeld_door_id);
    }

    public function test_een_te_lange_referentie_noemt_het_nummer(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.01');

        $component = Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('bewerk', $beoordeling->soa_regel_id)
            ->set([
                'vanToepassing' => '1',
                'motivatie' => 'Van toepassing op onze omgeving.',
                "beoordelingen.{$beoordeling->id}.procesreferentie" => str_repeat('x', 256),
            ])
            ->call('opslaan')
            ->assertHasErrors('beoordelingen');

        $this->assertStringContainsString(
            '5.24.01',
            implode(' ', $component->errors()->get('beoordelingen')),
        );

        $this->assertNull($beoordeling->refresh()->procesreferentie);
    }

    // --- De regellaag in de tabel (04c §3) ---------------------------------

    public function test_de_kolom_telt_belegd_tegenover_toepasselijk(): void
    {
        $this->seedBio();

        $regel = $this->beoordelingVan('5.24.01')->soaRegel;

        $this->assertSame('0 / 7', $this->kolomwaarde($regel->id));

        // Eén uitzondering haalt de noemer omlaag én moet zichtbaar blijven: zonder
        // dat tweede getal leest "0 / 6" als het volledige beeld.
        $this->beoordelingVan('5.24.02')->update([
            'status' => 'niet_van_toepassing',
            'motivatie' => 'Deze verplichting kan hier niet van toepassing zijn.',
        ]);
        $this->beoordelingVan('5.24.01')->update(['status' => 'belegd']);

        $this->assertSame('1 / 6 · 1 uitgezonderd', $this->kolomwaarde($regel->id));
    }

    public function test_een_maatregel_zonder_verplichtingen_wijst_naar_de_andere_route(): void
    {
        $this->seedBio();

        // A.5.3 is er één van de 39 zonder overheidsmaatregel. Een streepje is
        // daar het juiste antwoord, maar niet zonder de reden erbij (04b §3.3).
        $regel = SoaRegel::whereHas('maatregel', fn ($q) => $q->where('annex_a_referentie', '5.3'))
            ->firstOrFail();

        $component = Livewire::actingAs($this->ciso)->test(SoaOverzicht::class);

        $this->assertSame('—', $component->instance()->bioLabel($regel));
        $this->assertStringContainsString(
            'risicoanalyse',
            $component->instance()->bioTitel($regel),
        );
    }

    public function test_de_regellaag_verschijnt_pas_na_uitklappen(): void
    {
        $this->seedBio();

        $regel = $this->beoordelingVan('5.24.03')->soaRegel;

        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->assertDontSee('5.24.03')
            ->call('klapUit', $regel->id)
            ->assertSee('5.24.03')
            ->call('klapUit', $regel->id)
            ->assertDontSee('5.24.03');
    }

    public function test_een_uitgesloten_beheersmaatregel_toont_zijn_verplichtingen_toch(): void
    {
        $this->seedBio();

        $regel = $this->beoordelingVan('5.24.03')->soaRegel;
        $regel->update(['van_toepassing' => false, 'motivatie' => 'Niet van toepassing.']);

        // Weglaten zou een overzicht opleveren waarin verplichtingen zonder uitleg
        // ontbreken, en dat is de gevaarlijkste soort onvolledigheid.
        Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->call('klapUit', $regel->id)
            ->assertSee('5.24.03')
            ->assertSee('Beheersmaatregel uitgesloten');
    }

    // --- De schermkopie voor de auditor (04c §5) ---------------------------

    public function test_de_schermkopie_draagt_de_verplichtingen_als_eigen_regels(): void
    {
        $this->seedBio();

        $beoordeling = $this->beoordelingVan('5.24.03');
        $beoordeling->update([
            'status' => 'belegd',
            'beleidreferentie' => 'Incidentbeleid §3.2',
            'laatst_beoordeeld_op' => now(),
        ]);

        // Uitdrukkelijk zonder uitklappen: de kopie hangt niet af van wat iemand
        // toevallig had opengeklikt (04c §7).
        $markdown = $this->schermkopie()->markdown();

        $this->assertStringContainsString('## Overheidsmaatregelen (BIO2)', $markdown);
        $this->assertStringContainsString('| 5.24.03 | A.5.24 | Belegd |', $markdown);
        $this->assertStringContainsString('Incidentbeleid §3.2', $markdown);
        $this->assertStringContainsString('118 verplichtingen bij 54 van de 93', $markdown);
        // De kolomkop van de hoofdtabel heet nu hetzelfde als op het scherm.
        $this->assertStringContainsString('Verplichtingen', $markdown);
    }

    public function test_de_schermkopie_draagt_geen_normtekst(): void
    {
        file_put_contents($this->map.'/overheidsmaatregel-teksten.json', json_encode([
            'teksten' => ['5.24.03' => 'De proceseigenaar draagt zorg voor het oplossen van meldingen.'],
        ]));

        $this->seedBio();

        $markdown = $this->schermkopie()->markdown();

        $this->assertStringContainsString('5.24.03', $markdown);
        $this->assertStringNotContainsString('De proceseigenaar draagt zorg', $markdown);
    }

    public function test_de_iso_schermkopie_heeft_geen_bijlage(): void
    {
        $this->isomodus();
        $this->seed(MaatregelSeeder::class);

        $kopie = $this->schermkopie();

        $this->assertNull($kopie->bijlage);
        $this->assertNotContains('Verplichtingen', $kopie->kolommen);
    }

    /** De celwaarde van de kolom "Verplichtingen" voor één SoA-regel. */
    private function kolomwaarde(int $regelId): string
    {
        $regel = SoaRegel::with('overheidsmaatregelBeoordelingen')->findOrFail($regelId);

        return Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)
            ->instance()->bioLabel($regel);
    }

    /**
     * De kopie zoals het scherm hem aanbiedt. `schermkopie()` is `protected` —
     * dat hoort zo (12h: het scherm declareert zijn kopie, niemand anders), dus
     * de test kijkt er met reflectie in in plaats van er een publieke methode
     * voor te openen die alleen tests gebruiken.
     */
    private function schermkopie(): Schermkopie
    {
        $component = Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)->instance();

        return (new ReflectionMethod($component, 'schermkopie'))->invoke($component);
    }
}
