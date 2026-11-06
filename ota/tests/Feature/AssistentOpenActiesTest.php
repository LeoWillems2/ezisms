<?php

namespace Tests\Feature;

use App\Mcp\IsmsServer;
use App\Mcp\Tools\OpenActies;
use App\Models\Afwijking;
use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\CorrigerendeMaatregel;
use App\Models\Gebruiker;
use App\Models\Taak;
use App\Models\Verbeteractie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De tool `open-acties` (implementatie/14b §5, §12.2): per soort de check en
 * de scope van het scherm waar die actie staat.
 */
class AssistentOpenActiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class, RisicocriteriaSeeder::class]);
    }

    public function test_medewerker_krijgt_dezelfde_rijen_als_de_schermscopes(): void
    {
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();
        $collega = Gebruiker::factory()->metRol('Medewerker')->create();

        $eigenTaak = Taak::factory()->create(['eigenaar_id' => $medewerker->id]);
        $collegaTaak = Taak::factory()->create(['eigenaar_id' => $collega->id]);
        Taak::factory()->create(['eigenaar_id' => $medewerker->id, 'status' => 'voltooid', 'voltooid_op' => now()]);

        // Zichtbaar via de eigen maatregel; de maatregel van de collega bij
        // dezelfde afwijking staat op het detailscherm ook.
        $afwijking = Afwijking::factory()->create();
        $eigenMaatregel = CorrigerendeMaatregel::factory()->create(['afwijking_id' => $afwijking->id, 'eigenaar_id' => $medewerker->id]);
        $collegaMaatregel = CorrigerendeMaatregel::factory()->create(['afwijking_id' => $afwijking->id, 'eigenaar_id' => $collega->id]);
        CorrigerendeMaatregel::factory()->create(['eigenaar_id' => $collega->id]);

        Verbeteractie::factory()->create(['eigenaar_id' => $medewerker->id, 'deadline' => now()->addWeek()]);

        $this->actingAs($medewerker);
        $verwachtTaken = Taak::query()->zichtbaar()->whereIn('status', Taak::OPENSTAAND)->pluck('id')->all();
        $verwachtMaatregelen = CorrigerendeMaatregel::query()
            ->whereIn('afwijking_id', Afwijking::query()->zichtbaar()->pluck('id'))
            ->pluck('id')->sort()->values()->all();
        // De eigen taak, plus de herinneringstaken die observers aanmaakten
        // (verbeteractie, corrigerende maatregel). Niet die van de collega.
        $this->assertContains($eigenTaak->id, $verwachtTaken);
        $this->assertNotContains($collegaTaak->id, $verwachtTaken);
        sort($verwachtTaken);
        $this->assertSame([$eigenMaatregel->id, $collegaMaatregel->id], $verwachtMaatregelen);

        IsmsServer::actingAs($medewerker)
            ->tool(OpenActies::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($verwachtTaken, $verwachtMaatregelen) {
                $json->where('acties', function ($acties) use ($verwachtTaken, $verwachtMaatregelen) {
                    $acties = collect($acties);

                    return $acties->where('soort', 'taak')->pluck('id')->sort()->values()->all() === $verwachtTaken
                        && $acties->where('soort', 'corrigerende_maatregel')->pluck('id')->sort()->values()->all() === $verwachtMaatregelen
                        && $acties->where('soort', 'verbeteractie')->isEmpty();
                })
                    ->missing('per_soort.verbeteractie')
                    ->where('weggelaten', fn ($w) => collect($w)->contains(fn ($r) => str_starts_with($r, 'Verbeteracties')))
                    ->etc();
            });
    }

    public function test_administrator_krijgt_alles_weggelaten_met_uitleg(): void
    {
        // De tool spant meerdere blokken en weigert dus niet als geheel; hij
        // zegt per soort waarom die ontbreekt (14b §4.3).
        $beheerder = Gebruiker::factory()->metRol('Administrator')->create();
        Taak::factory()->create();

        IsmsServer::actingAs($beheerder)
            ->tool(OpenActies::class)
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('totaal', 0)
                    ->where('acties', [])
                    ->where('weggelaten', fn ($w) => count($w) === 4)
                    ->etc();
            });
    }

    public function test_ciso_ziet_alle_soorten_met_eigenaar_deadline_en_termijn(): void
    {
        $ciso = Gebruiker::factory()->metRol('CISO')->create();
        $eigenaar = Gebruiker::factory()->create(['naam' => 'Piet Pieters']);

        Taak::factory()->verstreken(3)->create(['eigenaar_id' => $eigenaar->id, 'titel' => 'Toegangsreview Q3']);
        $verbeteractie = Verbeteractie::factory()->create(['eigenaar_id' => $eigenaar->id, 'deadline' => now()->addDays(10)]);
        $document = Beleidsdocument::factory()->create(['status' => 'actief', 'eigenaar_id' => $eigenaar->id]);
        Beleidsversie::factory()->actief()->create([
            'beleidsdocument_id' => $document->id,
            'volgende_herziening_gepland' => now()->addDays(30)->toDateString(),
        ]);
        // Ver vooruit: valt buiten de standaardhorizon van herzieningen.
        $later = Beleidsdocument::factory()->create(['status' => 'actief']);
        Beleidsversie::factory()->actief()->create([
            'beleidsdocument_id' => $later->id,
            'volgende_herziening_gepland' => now()->addYear()->toDateString(),
        ]);

        IsmsServer::actingAs($ciso)
            ->tool(OpenActies::class, ['soorten' => ['taak', 'verbeteractie', 'beleidsherziening']])
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($verbeteractie, $document) {
                $json->where('weggelaten', [])
                    ->where('acties', function ($acties) use ($verbeteractie, $document) {
                        $acties = collect($acties);
                        $taak = $acties->firstWhere('titel', 'Toegangsreview Q3');
                        $va = $acties->firstWhere('soort', 'verbeteractie');
                        $herziening = $acties->where('soort', 'beleidsherziening');

                        return $taak['soort'] === 'taak'
                            && $taak['eigenaar'] === 'Piet Pieters'
                            && $taak['over_termijn'] === true
                            && $va['id'] === $verbeteractie->id
                            && $va['over_termijn'] === false
                            && $va['url'] === route('management-review.detail', $verbeteractie->besluit->reviewsessie_id)
                            && $herziening->count() === 1
                            && $herziening->first()['eigenaar'] === 'Piet Pieters'
                            && $herziening->first()['url'] === route('beleid.detail', $document);
                    })
                    // Over alle soorten heen op deadline: de verlopen taak eerst.
                    ->where('acties.0.titel', 'Toegangsreview Q3')
                    ->etc();
            });

        IsmsServer::actingAs($ciso)
            ->tool(OpenActies::class, ['alleen_over_termijn' => true])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('acties', fn ($a) => collect($a)->every(fn ($r) => $r['over_termijn'] === true) && count($a) === 1)
                ->etc());
    }
}
