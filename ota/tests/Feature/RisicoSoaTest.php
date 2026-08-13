<?php

namespace Tests\Feature;

use App\Livewire\RisicoDetail;
use App\Livewire\RisicosOverzicht;
use App\Livewire\SoaOverzicht;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\Risico;
use App\Support\Maatregelkenmerken;
use App\Support\Schermkopie;
use Database\Seeders\BlokSeeder;
use Database\Seeders\MaatregelKenmerkenSeeder;
use Database\Seeders\MaatregelSeeder;
use Database\Seeders\RisicocriteriaSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RisicoSoaTest extends TestCase
{
    use RefreshDatabase;

    private Gebruiker $ciso;

    /** Accepteert restrisico's boven de drempel — zie implementatie/01c §4. */
    private Gebruiker $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class,
            RisicocriteriaSeeder::class,
        ]);
        $this->ciso = Gebruiker::factory()->metRol('CISO')->create();
        $this->directeur = Gebruiker::factory()->metRol('Management')->create();
    }

    // --- Schermkopie voor de auditor (12h) ---------------------------------

    /** De kopie is `protected`; het scherm bouwt hem, de trait roept hem aan. */
    private function soaKopie(SoaOverzicht $component): Schermkopie
    {
        return (fn (): Schermkopie => $this->schermkopie())->call($component);
    }

    public function test_de_soa_kopie_bevat_de_kolommen_van_het_scherm_plus_de_motivatie(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'annex_a_referentie' => '5.1',
            'naam' => 'Beleidsregels voor informatiebeveiliging',
        ]);
        $maatregel->soaRegel->update([
            'van_toepassing' => true,
            'motivatie' => 'Vereist voor de certificering.',
            'implementatiestatus' => 'geimplementeerd',
        ]);

        $component = Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)->instance();
        $markdown = $this->soaKopie($component)->markdown();

        // De kolommen van het scherm (Acties is een knop, geen gegeven).
        foreach (['Ref.', 'Naam', 'Van toepassing', 'Implementatiestatus',
            'Restrisico', 'Beleid', 'Laatst beoordeeld'] as $kolom) {
            $this->assertStringContainsString($kolom, $markdown);
        }

        // Zonder de motivatie is dit geen SoA maar een lijst met vinkjes.
        $this->assertStringContainsString('Motivatie', $markdown);
        $this->assertStringContainsString('Vereist voor de certificering.', $markdown);

        $this->assertStringContainsString('| A.5.1 | Beleidsregels voor informatiebeveiliging | Ja |', $markdown);
        $this->assertStringContainsString('Geïmplementeerd', $markdown);
    }

    /**
     * De scherpste regel uit 12h §4: een gefilterde kopie die zich als het
     * volledige register presenteert, is het gevaarlijkst denkbare document in
     * een auditdossier.
     */
    public function test_een_gefilterde_soa_kopie_zegt_dat_er_gefilterd_is(): void
    {
        Maatregel::factory()->metSoaRegel()->create(['annex_a_referentie' => '5.1', 'thema' => 'organisatorisch']);
        Maatregel::factory()->metSoaRegel()->create(['annex_a_referentie' => '7.1', 'thema' => 'fysiek']);

        $component = Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->set('filterThema', 'fysiek')
            ->instance();

        $this->assertStringContainsString(
            '| Omvang | 1 van 2 regels — filter: thema fysiek. |',
            $this->soaKopie($component)->markdown(),
        );

        // Zonder filter hoort er geen twijfel te bestaan over de volledigheid.
        $ongefilterd = Livewire::actingAs($this->ciso)->test(SoaOverzicht::class)->instance();

        $this->assertStringContainsString('| Omvang | Alle 2 regels. |', $this->soaKopie($ongefilterd)->markdown());
    }

    // --- Autorisatie -------------------------------------------------------

    public function test_auditor_mag_lezen_maar_niet_muteren(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        $this->actingAs($auditor)->get('/soa')->assertOk();
        $this->actingAs($auditor)->get('/risicos')->assertOk();

        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        Livewire::actingAs($auditor)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertForbidden();
    }

    // --- Schermkopie: het risicoregister (12h §11 test 7) ------------------

    private function risicoKopie(RisicosOverzicht $component): Schermkopie
    {
        return (fn (): Schermkopie => $this->schermkopie())->call($component);
    }

    public function test_de_risicokopie_bevat_de_kolommen_van_het_scherm(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Management')->create(['naam' => 'Dana Wolters']);

        Risico::factory()->beoordeeld(4, 5)->create([
            'titel' => 'Uitval van de loonadministratie',
            'status' => 'behandelplan_opgesteld',
            'risico_eigenaar_id' => $eigenaar->id,
            'volgende_beoordeling_gepland' => now()->addMonths(3),
        ]);

        $component = Livewire::actingAs($this->ciso)->test(RisicosOverzicht::class);
        $markdown = $this->risicoKopie($component->instance())->markdown();

        $this->assertStringContainsString('# Risicoregister', $markdown);
        $this->assertStringContainsString(
            '| Ref. | Titel | Score | Status | Eigenaar | Volgende beoordeling |',
            $markdown,
        );
        $this->assertStringContainsString('| Uitval van de loonadministratie | 20 |', $markdown);
        $this->assertStringContainsString('Behandelplan opgesteld', $markdown);
        $this->assertStringContainsString(now()->addMonths(3)->format('d-m-Y'), $markdown);
    }

    /**
     * De test bij het anonimiseringsschema: hetzelfde als `isms:exporteer` —
     * initialen plus rol, en nergens de naam.
     */
    public function test_de_eigenaar_staat_als_initialen_en_rol_en_niet_als_naam(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Management')->create(['naam' => 'Dana Wolters']);
        Risico::factory()->beoordeeld(3, 3)->create(['risico_eigenaar_id' => $eigenaar->id]);

        $component = Livewire::actingAs($this->ciso)->test(RisicosOverzicht::class);
        $markdown = $this->risicoKopie($component->instance())->markdown();

        $this->assertStringContainsString('DW (Management)', $markdown);
        $this->assertStringNotContainsString('Dana Wolters', $markdown);

        // Het scherm zelf toont de naam wél — de kopie is de plek waar hij weggaat.
        $component->assertSee('Dana Wolters');
    }

    public function test_een_risico_zonder_eigenaar_of_beoordeling_levert_een_streepje(): void
    {
        Risico::factory()->create([
            'risico_eigenaar_id' => null,
            'kans_niveau' => null,
            'impact_niveau' => null,
            'volgende_beoordeling_gepland' => null,
        ]);

        $component = Livewire::actingAs($this->ciso)->test(RisicosOverzicht::class);
        $markdown = $this->risicoKopie($component->instance())->markdown();

        $this->assertStringContainsString('| Niet beoordeeld |', $markdown);
        $this->assertStringContainsString('| — | — |', $markdown);
    }

    /**
     * Een risico dat belegd is bij een gedeactiveerd account is feitelijk
     * onbelegd. Het scherm zet er een badge bij; de kopie mag dat niet
     * verliezen door te anonimiseren.
     */
    public function test_een_niet_actieve_eigenaar_blijft_zichtbaar_in_de_kopie(): void
    {
        $eigenaar = Gebruiker::factory()->metRol('Management')
            ->create(['naam' => 'Dana Wolters', 'status' => 'gedeactiveerd']);
        Risico::factory()->beoordeeld(3, 3)->create(['risico_eigenaar_id' => $eigenaar->id]);

        $component = Livewire::actingAs($this->ciso)->test(RisicosOverzicht::class);

        $this->assertStringContainsString(
            'DW (Management, gedeactiveerd)',
            $this->risicoKopie($component->instance())->markdown(),
        );
    }

    /** De test bij §4: gefilterd meegeven mag, verzwijgen dát er gefilterd is niet. */
    public function test_de_kop_noemt_het_risicofilter_en_hoeveel_van_hoeveel(): void
    {
        Risico::factory()->beoordeeld(5, 5)->create(['status' => 'geaccepteerd']);
        Risico::factory()->beoordeeld(1, 1)->count(2)->create(['status' => 'geidentificeerd']);

        $component = Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class)
            ->set('filterStatus', 'geaccepteerd');

        $markdown = $this->risicoKopie($component->instance())->markdown();

        $this->assertStringContainsString(
            '| Omvang | 1 van 3 regels — filter: status Geaccepteerd. |',
            $markdown,
        );
    }

    public function test_de_auditor_mag_het_risicoregister_kopieren_en_de_medewerker_niet(): void
    {
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();
        $medewerker = Gebruiker::factory()->metRol('Medewerker')->create();

        $component = Livewire::actingAs($auditor)->test(RisicosOverzicht::class);
        $this->assertTrue($component->instance()->magKopieren());

        $this->actingAs($medewerker);
        $this->assertFalse($component->instance()->magKopieren());
    }

    // --- De omschrijving in de modal (04f §1.1 en §8.4) --------------------

    /**
     * Twee toestanden, en meer zijn er sinds 04f niet. Het rode voorbehoud dat
     * hier tot 06-08-2026 stond, hoorde bij de meegeleverde omschrijvingen in
     * eigen bewoordingen; die zijn er niet meer. Wat de organisatie zelf invoert
     * ís de normtekst, en daar hoort geen voorbehoud bij — dat was al de
     * vastgelegde redenering achter de oude `DISCLAIMER`.
     */
    public function test_de_modal_toont_de_ingevoerde_normtekst_zonder_voorbehoud(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'omschrijving' => 'De letterlijke normtekst.',
        ]);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee('De letterlijke normtekst.')
            ->assertDontSee(Maatregel::GEEN_OMSCHRIJVING_AANHEF)
            ->assertDontSee('text-red-600', escape: false);
    }

    /**
     * Zonder ingevoerde tekst zegt de modal dát, met de link naar de
     * verantwoording. Dat is na 04f de enige verwijzing vanuit de SoA naar dat
     * artikel, en dus de enige plek waar de herkomst van de tekst wordt
     * verantwoord.
     */
    public function test_de_modal_zegt_het_als_er_geen_normtekst_is(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'omschrijving' => Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD,
        ]);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->assertSee(Maatregel::GEEN_OMSCHRIJVING_AANHEF)
            ->assertSee('href="'.route('kennisbank', Maatregel::DISCLAIMER_SLUG).'"', escape: false)
            // De markering is voor wie het bestand bewerkt, niet voor de lezer.
            ->assertDontSee(Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD);
    }

    /**
     * De markering telt als "niets", net als `null` en de lege string. Voor de
     * lezer maken die drie geen verschil; ze verschillen alleen in herkomst.
     */
    public function test_drie_schrijfwijzen_voor_geen_omschrijving(): void
    {
        $maatregel = Maatregel::factory()->create(['omschrijving' => null]);
        $this->assertFalse($maatregel->toontOmschrijving());

        $maatregel->update(['omschrijving' => '']);
        $this->assertFalse($maatregel->fresh()->toontOmschrijving());

        $maatregel->update(['omschrijving' => Maatregel::OMSCHRIJVING_NIET_MEEGELEVERD]);
        $this->assertFalse($maatregel->fresh()->toontOmschrijving());

        $maatregel->update(['omschrijving' => 'De letterlijke normtekst.']);
        $this->assertTrue($maatregel->fresh()->toontOmschrijving());
    }

    // --- Statement of Applicability ---------------------------------------

    public function test_soa_regel_start_onbeslist_en_niet_op_nee(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        $regel = $maatregel->soaRegel;
        $this->assertNull($regel->van_toepassing);
        $this->assertTrue($regel->isOnbeslist());
        $this->assertNull($regel->laatst_beoordeeld_op);
    }

    public function test_beslissing_zonder_motivatie_wordt_geweigerd(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->set('vanToepassing', '1')
            ->set('motivatie', '')
            ->call('opslaan')
            ->assertHasErrors(['motivatie' => 'required']);

        $this->assertNull($maatregel->soaRegel->fresh()->van_toepassing);
    }

    public function test_maatregel_van_toepassing_verklaren_zet_beoordelingsdatum(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->set('vanToepassing', '1')
            ->set('motivatie', 'Volgt uit de risicobeoordeling.')
            ->set('implementatiestatus', 'in_uitvoering')
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel = $maatregel->soaRegel->fresh();
        $this->assertTrue($regel->van_toepassing);
        $this->assertSame('in_uitvoering', $regel->implementatiestatus);
        $this->assertNotNull($regel->laatst_beoordeeld_op);
    }

    public function test_terug_naar_onbeslist_wist_de_beoordelingsdatum(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        $component = Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->set('vanToepassing', '0')
            ->set('motivatie', 'Geen mobiele apparatuur in gebruik.')
            ->call('opslaan')
            ->assertHasNoErrors();

        $this->assertNotNull($maatregel->soaRegel->fresh()->laatst_beoordeeld_op);

        $component->call('bewerk', $maatregel->soaRegel->id)
            ->set('vanToepassing', '')
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel = $maatregel->soaRegel->fresh();
        $this->assertNull($regel->van_toepassing);
        $this->assertNull($regel->laatst_beoordeeld_op);
    }

    public function test_referenties_opslaan_en_worden_geauditeerd(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->set('beleidreferentie', 'Beleid §4.2')
            ->set('procesreferentie', 'Werkinstructie WI-07')
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel = $maatregel->soaRegel->fresh();
        $this->assertSame('Beleid §4.2', $regel->beleidreferentie);
        $this->assertSame('Werkinstructie WI-07', $regel->procesreferentie);

        $this->assertDatabaseHas('audit_logregels', [
            'entiteit_type' => 'soa_regel',
            'entiteit_id' => $regel->id,
            'blok_naam' => 'risico-soa',
        ]);
    }

    public function test_referenties_zijn_optioneel(): void
    {
        // Zonder beslissing (onbeslist) hoeft er geen motivatie te zijn en mogen
        // de referenties leeg blijven; leeg wordt null, niet ''.
        $maatregel = Maatregel::factory()->metSoaRegel()->create();

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $maatregel->soaRegel->id)
            ->set('beleidreferentie', '')
            ->set('procesreferentie', '')
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel = $maatregel->soaRegel->fresh();
        $this->assertNull($regel->beleidreferentie);
        $this->assertNull($regel->procesreferentie);
    }

    public function test_kenmerkenseeder_zet_de_uitgangsclassificatie(): void
    {
        // De uitgangsclassificatie komt uit een eigen distribueerbaar bestand,
        // los van de tekstbron, en landt als array op de maatregel.
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $maatregel = Maatregel::where('annex_a_referentie', '5.1')->firstOrFail();

        $this->assertIsArray($maatregel->kenmerken);
        $this->assertSame(['Preventief'], $maatregel->kenmerken['type']);
        $this->assertContains('Governance en Ecosysteem', $maatregel->kenmerken['domeinen']);
    }

    public function test_kenmerken_bevatten_uitsluitend_geldige_vocabulaire(): void
    {
        // Guard tegen corruptie in het distribueerbare bestand: elke dimensie
        // heeft een vaste, kleine woordenlijst. Het vocabulaire komt uit het
        // schema en niet uit een kopie hier, zodat de twee niet uiteen kunnen
        // lopen. Elke actieve dimensie is bij elke maatregel gevuld.
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $this->assertNotEmpty(Maatregelkenmerken::dimensies(), 'Geen actieve dimensies in het schema');

        foreach (Maatregel::all() as $maatregel) {
            $ref = $maatregel->annex_a_referentie;
            foreach (array_keys(Maatregelkenmerken::dimensies()) as $dimensie) {
                $this->assertNotEmpty(
                    $maatregel->kenmerken[$dimensie] ?? [],
                    "A.{$ref} heeft geen {$dimensie}"
                );
                $onbekend = array_diff($maatregel->kenmerken[$dimensie], Maatregelkenmerken::waarden($dimensie));
                $this->assertEmpty($onbekend, "A.{$ref} heeft ongeldige {$dimensie}: ".implode(', ', $onbekend));
            }
        }
    }

    /**
     * Bewaakt het besluit uit plan 04d §0: het repo levert `capaciteiten` niet
     * mee. Zonder de norm is niet te bepalen welke maatregel welke capaciteit
     * heeft, en een plausibele invulling is hier erger dan een lege — die is
     * namelijk niet van een correcte te onderscheiden.
     *
     * Deze test faalt zodra de dimensie in het repo aan komt te staan of het
     * meegeleverde bronbestand hem vult. Wat een installatie mét de norm zelf
     * doet valt hier bewust buiten: die zet hem aan via `isms:capaciteiten`, met
     * een gitignored bronbestand en een schakelaar in `.env` — geen van beide
     * raakt deze assertions. Zie CapaciteitenCommandoTest.
     */
    public function test_uitgeschakelde_dimensie_is_nergens_gevuld(): void
    {
        $uit = array_diff_key(Maatregelkenmerken::alleDimensies(), Maatregelkenmerken::dimensies());

        $this->assertArrayHasKey('capaciteiten', $uit, 'capaciteiten hoort uitgeschakeld te zijn');

        foreach ($uit as $dimensie => $schema) {
            $this->assertSame([], $schema['waarden'], "Schema levert waarden voor uitgeschakelde {$dimensie}");
            $this->assertSame([], Maatregelkenmerken::waarden($dimensie));
        }

        $bron = json_decode(
            file_get_contents(database_path('seeders/data/maatregel-kenmerken.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach ($bron['regels'] as $regel) {
            foreach (array_keys($uit) as $dimensie) {
                $this->assertArrayNotHasKey(
                    $dimensie,
                    $regel['kenmerken'],
                    "A.{$regel['annex_a_referentie']} vult de uitgeschakelde dimensie {$dimensie}"
                );
            }
        }

        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        foreach (Maatregel::all() as $maatregel) {
            foreach (array_keys($uit) as $dimensie) {
                $this->assertArrayNotHasKey($dimensie, $maatregel->kenmerken ?? []);
            }
        }
    }

    // --- Eigen maatregelclassificatie (plan 04d fase 2) --------------------

    public function test_kenmerken_vallen_terug_op_het_uitgangspunt(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'kenmerken' => ['type' => ['Preventief'], 'domeinen' => ['Bescherming']],
        ]);
        $regel = $maatregel->soaRegel;

        $this->assertFalse($regel->heeftEigenClassificatie());
        $this->assertSame(['type' => ['Preventief'], 'domeinen' => ['Bescherming']], $regel->kenmerken());
        $this->assertFalse($regel->wijktAfVanUitgangspunt());
    }

    public function test_eigen_classificatie_wint_van_het_uitgangspunt(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'kenmerken' => ['type' => ['Preventief']],
        ]);
        $regel = $maatregel->soaRegel;

        $regel->update(['kenmerken_eigen' => ['type' => ['Detectief']]]);
        $regel->refresh();

        $this->assertTrue($regel->heeftEigenClassificatie());
        $this->assertSame(['type' => ['Detectief']], $regel->kenmerken());
        $this->assertTrue($regel->wijktAfVanUitgangspunt());
    }

    /**
     * Voorvullen is geen vaststellen (plan 04d §7): het scherm toont bij de
     * eerste bewerking het uitgangspunt, en opslaan zonder wijziging is een
     * expliciete bevestiging. Die mag geen "afgeweken"-badge opleveren — anders
     * betekent de badge niets meer.
     */
    public function test_bevestiging_zonder_wijziging_is_geen_afwijking(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'kenmerken' => ['type' => ['Preventief'], 'eigenschappen' => ['Integriteit', 'Vertrouwelijkheid']],
        ]);
        $regel = $maatregel->soaRegel;

        // Zelfde inhoud, andere volgorde binnen de dimensie én tussen de
        // dimensies — dat is hoe een formulier het nu eenmaal aanlevert.
        $regel->update(['kenmerken_eigen' => [
            'eigenschappen' => ['Vertrouwelijkheid', 'Integriteit'],
            'type' => ['Preventief'],
        ]]);
        $regel->refresh();

        $this->assertTrue($regel->heeftEigenClassificatie());
        $this->assertFalse($regel->wijktAfVanUitgangspunt());
    }

    public function test_eigen_classificatie_komt_in_de_audit_trail(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        $this->actingAs($this->ciso);
        $regel->update(['kenmerken_eigen' => ['type' => ['Corrigerend']]]);

        $this->assertDatabaseHas('audit_logregels', [
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'soa_regel',
            'entiteit_id' => $regel->id,
            'actie' => 'gewijzigd',
        ]);
    }

    // --- Het scherm (plan 04d fase 3) --------------------------------------

    /**
     * Het formulier vult voor met de effectieve classificatie, en opslaan legt
     * die vast — ook zonder wijziging. Dat is de expliciete vaststelling: van
     * "er stond nog niets" naar "wij hebben hiernaar gekeken".
     */
    public function test_opslaan_legt_de_classificatie_vast_ook_zonder_wijziging(): void
    {
        $uitgangspunt = [
            'type' => ['Preventief'],
            'eigenschappen' => ['Vertrouwelijkheid'],
            'concepten' => ['Beschermen'],
            'domeinen' => ['Bescherming'],
        ];
        $regel = Maatregel::factory()->metSoaRegel()->create(['kenmerken' => $uitgangspunt])->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->assertSet('kenmerken', $uitgangspunt)
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel->refresh();

        $this->assertTrue($regel->heeftEigenClassificatie());
        $this->assertFalse($regel->wijktAfVanUitgangspunt(), 'Bevestigen zonder wijziging is geen afwijking.');
    }

    /**
     * De badge zegt iets zodra de organisatie iets heeft vastgesteld. Vóór die
     * tijd niet: elke regel begint bij het uitgangspunt, en een badge die in elk
     * formulier staat draagt geen informatie. In de export staat de herkomst wél
     * bij elke maatregel — daar lees je de regels los van elkaar.
     */
    public function test_de_herkomstbadge_verschijnt_pas_na_een_eigen_vaststelling(): void
    {
        $uitgangspunt = [
            'type' => ['Preventief'],
            'eigenschappen' => ['Vertrouwelijkheid'],
            'concepten' => ['Beschermen'],
            'domeinen' => ['Bescherming'],
        ];
        $regel = Maatregel::factory()->metSoaRegel()->create(['kenmerken' => $uitgangspunt])->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->assertDontSee('Eigen vaststelling')
            ->assertDontSee('Afgeweken van uitgangspunt')
            ->set('kenmerken', ['type' => ['Detectief']] + $uitgangspunt)
            ->call('opslaan')
            ->assertHasNoErrors()
            ->call('bewerk', $regel->id)
            ->assertSee('Afgeweken van uitgangspunt');
    }

    public function test_een_gewijzigde_dimensie_levert_een_afwijking_op(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create(['kenmerken' => [
            'type' => ['Preventief'],
            'eigenschappen' => ['Vertrouwelijkheid'],
            'concepten' => ['Beschermen'],
            'domeinen' => ['Bescherming'],
        ]])->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->set('kenmerken.type', ['Detectief'])
            ->call('opslaan')
            ->assertHasNoErrors();

        $regel->refresh();

        $this->assertSame(['Detectief'], $regel->kenmerken()['type']);
        $this->assertTrue($regel->wijktAfVanUitgangspunt());
    }

    public function test_terug_naar_uitgangspunt_maakt_het_veld_weer_null(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create([
            'kenmerken' => ['type' => ['Preventief']],
        ])->soaRegel;
        $regel->update(['kenmerken_eigen' => ['type' => ['Detectief']]]);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->call('terugNaarUitgangspunt')
            ->assertHasNoErrors()
            ->assertSet('kenmerken.type', ['Preventief']);

        $regel->refresh();

        $this->assertNull($regel->kenmerken_eigen);
        $this->assertSame(['type' => ['Preventief']], $regel->kenmerken());
    }

    public function test_een_waarde_buiten_het_vocabulaire_wordt_geweigerd(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->set('kenmerken.type', ['Magisch'])
            ->call('opslaan')
            ->assertHasErrors('kenmerken.type.0');

        $this->assertNull($regel->fresh()->kenmerken_eigen);
    }

    /**
     * Alles of niets: wie één dimensie invult, vult ze allemaal in. Een half
     * ingevuld formulier is geen vaststelling.
     */
    public function test_een_half_ingevulde_classificatie_wordt_geweigerd(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->set('kenmerken.type', ['Preventief'])
            ->call('opslaan')
            ->assertHasErrors('kenmerken');

        $this->assertNull($regel->fresh()->kenmerken_eigen);
    }

    /**
     * De guard uit plan 04d §9: een uitgeschakelde dimensie mag ook niet via een
     * meegestuurd formulierveld binnenkomen, hoe plausibel de waarde ook is.
     */
    public function test_een_uitgeschakelde_dimensie_wordt_geweigerd(): void
    {
        $uit = array_key_first(array_diff_key(
            Maatregelkenmerken::alleDimensies(),
            Maatregelkenmerken::dimensies(),
        ));

        $this->assertNotNull($uit, 'Er is geen uitgeschakelde dimensie om op te toetsen.');

        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->call('bewerk', $regel->id)
            ->set("kenmerken.{$uit}", ['Governance'])
            ->call('opslaan')
            ->assertHasErrors('kenmerken');

        $this->assertNull($regel->fresh()->kenmerken_eigen);
    }

    public function test_de_auditor_mag_de_classificatie_niet_bewerken(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;
        $auditor = Gebruiker::factory()->metRol('Auditor')->create();

        Livewire::actingAs($auditor)
            ->test(SoaOverzicht::class)
            ->call('terugNaarUitgangspunt')
            ->assertForbidden();
    }

    /**
     * De regressietest die de hele plaatsingskeuze rechtvaardigt: `deploy.sh`
     * draait `db:seed --force` bij elke uitrol. Zou de seeder aan `soa_regels`
     * komen, dan verdween de vaststelling van de organisatie geruisloos.
     */
    public function test_deploy_seeder_raakt_de_eigen_classificatie_niet(): void
    {
        $this->seed([MaatregelSeeder::class, MaatregelKenmerkenSeeder::class]);

        $maatregel = Maatregel::where('annex_a_referentie', '5.1')->firstOrFail();
        $regel = $maatregel->soaRegel()->firstOrFail();
        $regel->update(['kenmerken_eigen' => ['type' => ['Corrigerend']]]);

        $this->seed(MaatregelKenmerkenSeeder::class);

        $regel->refresh();
        $this->assertSame(['type' => ['Corrigerend']], $regel->kenmerken_eigen);
        $this->assertSame(['type' => ['Corrigerend']], $regel->kenmerken());
        // Het uitgangspunt op de maatregel is wél bijgewerkt door de seeder.
        $this->assertSame(['Preventief'], $maatregel->fresh()->kenmerken['type']);
    }

    public function test_terug_naar_uitgangspunt_maakt_het_veld_weer_leeg(): void
    {
        $maatregel = Maatregel::factory()->metSoaRegel()->create([
            'kenmerken' => ['type' => ['Preventief']],
        ]);
        $regel = $maatregel->soaRegel;
        $regel->update(['kenmerken_eigen' => ['type' => ['Detectief']]]);

        $regel->update(['kenmerken_eigen' => null]);
        $regel->refresh();

        $this->assertFalse($regel->heeftEigenClassificatie());
        $this->assertFalse($regel->wijktAfVanUitgangspunt());
        $this->assertSame(['type' => ['Preventief']], $regel->kenmerken());
    }

    // --- Restrisico-rollup per control (plan 04c) --------------------------

    public function test_restrisico_rollup_neemt_de_max_en_telt_distinct_risicos(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        // Eén risico met twee behandelingen (6 en 12) aan deze control, plus een
        // tweede risico (4). Max = 12; distinct risico's = 2 (niet 3 behandelingen).
        $risicoA = Risico::factory()->beoordeeld(4, 5)->create();
        $b1 = $risicoA->behandelingen()->create(['behandeloptie' => 'mitigeren', 'restrisico_score' => 6]);
        $b2 = $risicoA->behandelingen()->create(['behandeloptie' => 'overdragen', 'restrisico_score' => 12]);

        $risicoB = Risico::factory()->beoordeeld(3, 3)->create();
        $b3 = $risicoB->behandelingen()->create(['behandeloptie' => 'mitigeren', 'restrisico_score' => 4]);

        $regel->risicobehandelingen()->attach([$b1->id, $b2->id, $b3->id]);
        $regel->load('risicobehandelingen');

        $this->assertSame(12, $regel->piekRestrisico());
        $this->assertSame(2, $regel->aantalGekoppeldeRisicos());
    }

    public function test_restrisico_zonder_ingevuld_getal_is_onbepaald_niet_nul(): void
    {
        $regel = Maatregel::factory()->metSoaRegel()->create()->soaRegel;

        $risico = Risico::factory()->beoordeeld(2, 2)->create();
        $behandeling = $risico->behandelingen()->create(['behandeloptie' => 'accepteren', 'restrisico_score' => null]);
        $regel->risicobehandelingen()->attach($behandeling->id);
        $regel->load('risicobehandelingen');

        // Wél een gekoppeld risico, maar geen restrisico ingevuld: null, geen 0.
        $this->assertNull($regel->piekRestrisico());
        $this->assertSame(1, $regel->aantalGekoppeldeRisicos());
    }

    public function test_soa_toont_de_restrisicokolom(): void
    {
        // Control mét restrisico -> getal + teller; control zonder koppeling -> —.
        $metRisico = Maatregel::factory()->metSoaRegel()->create();
        $zonderRisico = Maatregel::factory()->metSoaRegel()->create();

        $risico = Risico::factory()->beoordeeld(4, 5)->create();
        $behandeling = $risico->behandelingen()->create(['behandeloptie' => 'mitigeren', 'restrisico_score' => 12]);
        $metRisico->soaRegel->risicobehandelingen()->attach($behandeling->id);

        Livewire::actingAs($this->ciso)
            ->test(SoaOverzicht::class)
            ->assertSee('Restrisico')
            ->assertSee('(1)'); // de distinct-teller bij de control met een risico

        $this->assertSame(0, $zonderRisico->soaRegel->load('risicobehandelingen')->aantalGekoppeldeRisicos());
    }

    // --- Risicoregister ----------------------------------------------------

    public function test_risicoscore_wordt_afgeleid_uit_kans_en_impact(): void
    {
        $risico = Risico::factory()->create();
        $this->assertNull($risico->risicoscore);

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('kansNiveau', 4)
            ->set('impactNiveau', 5)
            ->call('opslaanBeoordeling')
            ->assertHasNoErrors();

        $risico->refresh();
        $this->assertSame(20, $risico->risicoscore);
        $this->assertSame('beoordeeld', $risico->status);
    }

    public function test_acceptatie_boven_de_drempel_wacht_op_de_directie(): void
    {
        // Score 20 > drempel 15. De CISO legt het plan vast, maar accepteren
        // kan hij niet: dat is `goedkeuren` (01c §4). Het risico blijft dus op
        // 'behandelplan_opgesteld' staan.
        $risico = Risico::factory()->beoordeeld(4, 5)->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('behandeloptie', 'accepteren')
            ->call('opslaanBehandeling')
            ->assertHasNoErrors();

        $this->assertSame('behandelplan_opgesteld', $risico->fresh()->status);
        $this->assertDatabaseHas('risicobehandelingen', [
            'risico_id' => $risico->id,
            'behandeloptie' => 'accepteren',
            'geaccepteerd_door' => null,
        ]);
    }

    public function test_ciso_kan_het_restrisico_niet_zelf_accepteren(): void
    {
        $risico = Risico::factory()->beoordeeld(4, 5)->create();
        $behandeling = $risico->behandelingen()->create(['behandeloptie' => 'accepteren']);

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('geaccepteerdDoor', 'Ikzelf')
            ->call('accepteerRestrisico', $behandeling->id)
            ->assertForbidden();

        $this->assertNull($behandeling->fresh()->geaccepteerd_door);
    }

    public function test_directie_accepteert_het_restrisico_boven_de_drempel(): void
    {
        $risico = Risico::factory()->beoordeeld(4, 5)->create();
        $behandeling = $risico->behandelingen()->create(['behandeloptie' => 'accepteren']);

        Livewire::actingAs($this->directeur)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('geaccepteerdDoor', 'Directie')
            ->set('geaccepteerdOp', now()->format('Y-m-d'))
            ->call('accepteerRestrisico', $behandeling->id)
            ->assertHasNoErrors();

        $this->assertSame('geaccepteerd', $risico->fresh()->status);
        $this->assertDatabaseHas('risicobehandelingen', [
            'id' => $behandeling->id,
            'geaccepteerd_door' => 'Directie',
        ]);
        $this->assertNotNull($behandeling->fresh()->geaccepteerd_op);
    }

    public function test_acceptatie_zonder_beslisser_wordt_geweigerd(): void
    {
        $risico = Risico::factory()->beoordeeld(4, 5)->create();
        $behandeling = $risico->behandelingen()->create(['behandeloptie' => 'accepteren']);

        Livewire::actingAs($this->directeur)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('geaccepteerdDoor', '')
            ->call('accepteerRestrisico', $behandeling->id)
            ->assertHasErrors('geaccepteerdDoor');

        $this->assertSame('beoordeeld', $risico->fresh()->status);
    }

    public function test_management_mag_het_behandelplan_niet_schrijven(): void
    {
        $risico = Risico::factory()->beoordeeld(4, 5)->create();

        Livewire::actingAs($this->directeur)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('behandeloptie', 'mitigeren')
            ->call('opslaanBehandeling')
            ->assertForbidden();

        $this->assertDatabaseCount('risicobehandelingen', 0);
    }

    public function test_acceptatie_onder_de_drempel_mag_zonder_beslisser(): void
    {
        // Score 4 < drempel 15.
        $risico = Risico::factory()->beoordeeld(2, 2)->create();

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('behandeloptie', 'accepteren')
            ->set('geaccepteerdDoor', '')
            ->call('opslaanBehandeling')
            ->assertHasNoErrors();

        $this->assertSame('geaccepteerd', $risico->fresh()->status);
    }

    public function test_mitigeren_koppelt_soa_regels_en_zet_de_status(): void
    {
        $risico = Risico::factory()->beoordeeld(3, 4)->create();
        $maatregel = Maatregel::factory()->metSoaRegel()->create();
        $maatregel->soaRegel->update(['van_toepassing' => true, 'motivatie' => 'Nodig.']);

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->set('behandeloptie', 'mitigeren')
            ->set('restrisicoScore', 6)
            ->set('geselecteerdeSoaRegels', [$maatregel->soaRegel->id])
            ->call('opslaanBehandeling')
            ->assertHasNoErrors();

        $risico->refresh();
        $this->assertSame('behandelplan_opgesteld', $risico->status);

        $behandeling = $risico->behandelingen()->firstOrFail();
        $this->assertSame(6, $behandeling->restrisico_score);
        $this->assertTrue($behandeling->soaRegels()->whereKey($maatregel->soaRegel->id)->exists());
        $this->assertSame(
            '1 gekoppeld: '.$maatregel->soaRegel->auditOmschrijving(),
            $this->laatsteKoppelregel('risicobehandeling', 'maatregelen'),
        );
    }

    public function test_risico_toont_een_korte_referentie(): void
    {
        // R-{id}: stabiel, uniek, afgeleid van het id (geen apart nummerveld).
        $risico = Risico::factory()->create();
        $this->assertSame('R-'.$risico->id, $risico->referentie());

        Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class)
            ->assertSee('R-'.$risico->id);
    }

    public function test_ciso_kan_een_risico_toevoegen_en_wordt_doorgestuurd(): void
    {
        Livewire::actingAs($this->ciso)
            ->test(RisicosOverzicht::class)
            ->set('titel', 'Uitval van de fileserver')
            ->call('opslaan')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('risicos', [
            'titel' => 'Uitval van de fileserver',
            'status' => 'geidentificeerd',
        ]);
    }

    public function test_verstreken_herbeoordeling_wordt_gesignaleerd(): void
    {
        $risico = Risico::factory()->beoordeeld()->create([
            'volgende_beoordeling_gepland' => now()->subMonth(),
        ]);

        $this->assertTrue($risico->herbeoordelingVerstreken());

        Livewire::actingAs($this->ciso)
            ->test(RisicoDetail::class, ['risico' => $risico])
            ->assertSee('Herbeoordeling verstreken');
    }
}
