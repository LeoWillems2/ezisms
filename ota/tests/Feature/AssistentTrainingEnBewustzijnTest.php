<?php

namespace Tests\Feature;

use App\Livewire\BeleidsdocumentDetail;
use App\Livewire\BeleidsdocumentenOverzicht;
use App\Livewire\MijnTrainingen;
use App\Livewire\ToetsenResultaten;
use App\Livewire\TrainingenOverzicht;
use App\Mcp\IsmsServer;
use App\Mcp\Tools\TrainingEnBewustzijn;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\OrganisatieEenheid;
use App\Models\Taak;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * De tool `training-en-bewustzijn` (implementatie/14b §5, §12.2): per rol
 * hetzelfde als de trainingsschermen en de leesbevestigingen op /beleid.
 */
class AssistentTrainingEnBewustzijnTest extends TestCase
{
    use RefreshDatabase;

    private OrganisatieEenheid $verkoop;

    private Gebruiker $medewerker;

    private Gebruiker $collega;

    private Trainingsmodule $module;

    private Beleidsdocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);

        $this->verkoop = OrganisatieEenheid::factory()->afdeling()->create(['naam' => 'Verkoop']);
        $this->medewerker = Gebruiker::factory()->metRol('Medewerker')->opAfdeling($this->verkoop)
            ->create(['naam' => 'Mia Medewerker']);
        $this->collega = Gebruiker::factory()->metRol('Medewerker')->opAfdeling($this->verkoop)
            ->create(['naam' => 'Cor Collega']);

        // Een module voor beide, waarvan alleen de collega hem voltooide.
        $this->module = Trainingsmodule::factory()->create(['titel' => 'Phishing herkennen', 'actief' => true]);
        $doelgroep = Doelgroep::factory()->create(['naam' => 'Iedereen']);
        $doelgroep->gebruikers()->attach([$this->medewerker->id, $this->collega->id]);
        $this->module->doelgroepen()->attach($doelgroep);
        Trainingsvoltooiing::factory()->create([
            'trainingsmodule_id' => $this->module->id,
            'gebruiker_id' => $this->collega->id,
        ]);
        // Een module buiten de doelgroep van de medewerker.
        Trainingsmodule::factory()->create(['titel' => 'Beheerdersopleiding', 'actief' => true]);

        // Een toets voor de collega: de medewerker hoort die niet te zien.
        Toetsopdracht::factory()->create([
            'taak_id' => Taak::factory()->create(['eigenaar_id' => $this->collega->id])->id,
            'status' => 'gezakt',
            'laatste_score' => 4,
            'laatste_totaal' => 10,
            'pogingen' => 1,
        ]);

        // Een actief document voor Verkoop, alleen door de collega bevestigd.
        $this->document = Beleidsdocument::factory()->voorAfdelingen($this->verkoop)
            ->create(['titel' => 'Acceptabel gebruik', 'status' => 'actief', 'leesbevestiging_vereist' => true]);
        $versie = Beleidsversie::factory()->actief()->for($this->document, 'document')->create();
        Leesbevestiging::create([
            'beleidsversie_id' => $versie->id,
            'gebruiker_id' => $this->collega->id,
            'bevestigd_op' => now(),
        ]);
    }

    public function test_medewerker_krijgt_alleen_wat_zijn_eigen_schermen_tonen(): void
    {
        $eigenModules = Livewire::actingAs($this->medewerker)
            ->test(MijnTrainingen::class)
            ->viewData('rijen')
            ->map(fn ($rij) => $rij['module']->id)->values()->all();

        $openstaand = Livewire::actingAs($this->medewerker)
            ->test(BeleidsdocumentenOverzicht::class)
            ->instance()
            ->openstaandeBevestigingen();

        IsmsServer::actingAs($this->medewerker)
            ->tool(TrainingEnBewustzijn::class, ['beleidsdocument_id' => $this->document->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($eigenModules, $openstaand) {
                $json->where('mijn_trainingen.modules', fn ($m) => collect($m)->pluck('id')->all() === $eigenModules)
                    ->where('mijn_trainingen.modules.0.status.code', 'te_doen')
                    ->missing('programma')
                    ->missing('toetsresultaten')
                    ->missing('leesbevestigingen.stand')
                    ->missing('leesbevestigingen.per_persoon')
                    ->where('leesbevestigingen.zelf_nog_te_bevestigen', fn ($l) => count($l) === $openstaand)
                    ->where('leesbevestigingen.zelf_nog_te_bevestigen.0.titel', 'Acceptabel gebruik')
                    ->where('weggelaten', fn ($w) => count($w) === 2)
                    ->etc();
            });
    }

    public function test_auditor_krijgt_programma_toetsresultaten_en_bevestigingen_zoals_de_schermen(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $modules = Livewire::actingAs($auditor)->test(TrainingenOverzicht::class)
            ->viewData('rijen')->map(fn ($rij) => [$rij['module']->id, $rij['graad'], $rij['verlopen']])->values()->all();
        $toetsen = Livewire::actingAs($auditor)->test(ToetsenResultaten::class)
            ->viewData('opdrachten')->pluck('id')->all();
        $detail = Livewire::actingAs($auditor)->test(BeleidsdocumentDetail::class, ['beleidsdocument' => $this->document]);
        $bevestigd = $detail->viewData('bevestigingen')->map(fn ($b) => $b->gebruiker->naam)->all();
        $nietBevestigd = $detail->viewData('nietBevestigd')->pluck('naam')->all();

        IsmsServer::actingAs($auditor)
            ->tool(TrainingEnBewustzijn::class, ['beleidsdocument_id' => $this->document->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($modules, $toetsen, $bevestigd, $nietBevestigd) {
                $json->where('programma.modules', fn ($m) => collect($m)
                    ->map(fn ($r) => [$r['id'], $r['voltooiingsgraad'], $r['verlopen']])->all() === $modules)
                    // Het bewerkformulier (met de beleidskoppeling) is er alleen voor wie muteert.
                    ->where('programma.modules.0.beleidsdocumenten', null)
                    ->where('toetsresultaten.lijst', fn ($l) => collect($l)->pluck('id')->all() === $toetsen)
                    ->where('leesbevestigingen.per_persoon.bevestigd', fn ($b) => collect($b)->pluck('naam')->all() === $bevestigd)
                    ->where('leesbevestigingen.per_persoon.niet_bevestigd', $nietBevestigd)
                    // De Auditor heeft geen 'uitvoeren' op training: geen Mijn trainingen.
                    ->missing('mijn_trainingen')
                    ->etc();
            });
    }

    public function test_administrator_zonder_rechten_krijgt_niets_en_hoort_waarom(): void
    {
        // De tool raakt twee blokken (vereist() is null) en weigert dus niet als
        // geheel; zonder recht op beide blijft er alleen `weggelaten` over.
        IsmsServer::actingAs(Gebruiker::factory()->metRol('Administrator')->create())
            ->tool(TrainingEnBewustzijn::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->missing('programma')
                    ->missing('toetsresultaten')
                    ->missing('mijn_trainingen')
                    ->missing('leesbevestigingen')
                    ->where('weggelaten', fn ($w) => count($w) === 2)
                    ->etc();
            });
    }

    public function test_ciso_ziet_graad_toetsscore_en_achterstand(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();

        IsmsServer::actingAs($ciso)
            ->tool(TrainingEnBewustzijn::class, ['toets_status' => 'alle'])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('programma.modules', fn ($m) => collect($m)->firstWhere('id', $this->module->id)['voltooiingsgraad'] === 50)
                    ->where('programma.signalen.actieve_modules_zonder_doelgroep', 1)
                    ->where('toetsresultaten.lijst.0.deelnemer', 'Cor Collega')
                    ->where('toetsresultaten.lijst.0.status.code', 'gezakt')
                    ->where('toetsresultaten.lijst.0.laatste_score', 4)
                    ->where('leesbevestigingen.stand.aandacht.0.beleidsdocument_id', $this->document->id)
                    ->where('leesbevestigingen.stand.aandacht.0.openstaand', 1)
                    ->where('leesbevestigingen.stand.aandacht.0.url', route('beleid.detail', $this->document))
                    ->etc();
            });
    }
}
