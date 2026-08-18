<?php

namespace Tests\Feature;

use App\Models\Auditobject;
use App\Models\Beleidsdocument;
use App\Models\Concerns\Auditeerbaar;
use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use App\Support\Kennisartikelen;
use App\Support\Pandoc;
use Database\Seeders\AuditobjectClausuleSeeder;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class KennisbankTest extends TestCase
{
    use RefreshDatabase;

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->get('/kennisbank')->assertRedirect(route('login'));
    }

    public function test_elke_ingelogde_gebruiker_ziet_het_eerste_artikel(): void
    {
        // Geen rol nodig: de kennisbank is niet blok-gated.
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank')
            ->assertOk()
            ->assertSee('Incidenten')
            ->assertSee('De sluitpoort van een incident'); // markdown → gerenderde h2
    }

    public function test_specifiek_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/incidenten-en-afwijkingen')
            ->assertOk()
            ->assertSee('De corrigerende-actie-cyclus');
    }

    public function test_hr_saas_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/hr-saas-leverancier-opvoeren')
            ->assertOk()
            ->assertSee('teruggave van data')
            ->assertSee('incidentmeldplicht');
    }

    public function test_sbom_artikel_rendert_uit_de_projectroot(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        // De SBOM staat canoniek in de projectroot (gegenereerd), niet in
        // resources/kennisbank — de kennisbank leest 'm daar rechtstreeks.
        $this->actingAs($gebruiker)->get('/kennisbank/software-bill-of-materials')
            ->assertOk()
            ->assertSee('Software Bill of Materials')      // titel uit het register
            ->assertSee('Licentie-samenvatting');          // markdown → gerenderde h2
    }

    public function test_besluit_opslag_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/besluit-opslag-bewijzen-beleidsdocumenten')
            ->assertOk()
            ->assertSee('Besluit: opslag van bewijzen en beleidsdocumenten') // titel uit register
            ->assertSee('slaat documenten lokaal op');
    }

    public function test_issues_en_risicos_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/issues-en-risicos')
            ->assertOk()
            ->assertSee('De twee manieren waarop het misgaat') // markdown → gerenderde h2
            ->assertSee('er zit een gat in je risicobeoordeling');
    }

    public function test_soa_onderbouwing_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/soa-onderbouwen-en-restrisico')
            ->assertOk()
            ->assertSee('De vier bouwstenen van een goede') // markdown → gerenderde h2
            ->assertSee('max netto-restrisico');
    }

    public function test_maatregelclassificatie_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/maatregelclassificatie')
            ->assertOk()
            ->assertSee('Waarom vier dimensies en niet vijf')
            ->assertSee('isms:capaciteiten');
    }

    /**
     * Het artikel voor wie de norm heeft gekocht. Geen variant per profiel: het
     * bestand, het commando en de valkuilen zijn in beide profielen gelijk, en
     * wat NEN 7510 erbij heeft staat in `wat-nen-7510-toevoegt`.
     */
    public function test_normteksten_invoeren_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/normteksten-invoeren')
            ->assertOk()
            ->assertSee('De normteksten invoeren')          // titel uit register
            // Beide bestandsnamen: het artikel is profielloos en moet de lezer
            // van elke installatie naar het juiste bestand wijzen.
            ->assertSee('maatregelen-iso27001.json')
            ->assertSee('maatregelen-nen7510.json')
            ->assertSee('php artisan isms:maatregelen')
            // Het licentievoorbehoud hoort er hoe dan ook bij te staan.
            ->assertSee('mag deze installatie niet verlaten');
    }

    public function test_integraties_normeis_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/integraties-en-normeis')
            ->assertOk()
            ->assertSee('Integraties: welke norm-eis onderbouw je ermee?') // titel uit register
            ->assertSee('geen zelfstandige norm-eis'); // markdown → gerenderde tekst
    }

    public function test_interne_audit_opzetten_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/interne-audit-opzetten')
            ->assertOk()
            ->assertSee('Een interne audit opzetten (§9.2)') // titel uit register
            ->assertSee('isms:bereid-auditcyclus-voor')      // beheercommando
            ->assertSee('isms:verwijder-auditdata');         // reset-commando
    }

    public function test_externe_certificeringsaudit_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/externe-certificeringsaudit')
            ->assertOk()
            ->assertSee('De externe certificeringsaudit in het ISMS') // titel uit register
            ->assertSee('rapport blijft de bron van waarheid');       // markdown → gerenderde tekst
    }

    public function test_gebruikers_rollen_en_rechten_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/gebruikers-rollen-en-rechten')
            ->assertOk()
            ->assertSee('Gebruikers, rollen en rechten') // titel uit het register
            ->assertSee('Drie lagen toegang')            // markdown → gerenderde h2
            ->assertSee('isms:eerste-ciso');             // bootstrapcommando
    }

    public function test_communicatie_en_overleg_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/communicatie-en-overleg')
            ->assertOk()
            ->assertSee('Communicatie en overleg vastleggen (§7.4)') // titel uit het register
            ->assertSee('Het ritme: terugkerende overleggen als taaksjabloon') // markdown → gerenderde h2
            ->assertSee('Geen agendabeheer of notuleneditor');       // de afbakening
    }

    /**
     * Het artikel doet twee toetsbare uitspraken over het systeem: dat je een
     * interne audit op clausule 7.4 kunt plannen, en dat de bevestigingsplicht
     * standaard aan staat bij beleid en niet bij een procedure. Allebei zijn ze
     * elders te wijzigen zonder dat iemand aan dit artikel denkt.
     */
    public function test_communicatie_artikel_klopt_met_het_systeem(): void
    {
        $this->seed(AuditobjectClausuleSeeder::class);

        $this->assertTrue(
            Auditobject::where('soort', 'clausule')->where('clausule_nummer', '7.4')->exists(),
            'Het artikel belooft een interne audit op clausule 7.4, maar dat auditobject bestaat niet.',
        );

        $this->assertTrue(
            Beleidsdocument::standaardLeesbevestiging('beleid'),
            'Het artikel zegt dat de bevestigingsplicht bij type beleid standaard aan staat.',
        );

        $this->assertFalse(
            Beleidsdocument::standaardLeesbevestiging('procedure'),
            'Het artikel zegt dat je de bevestigingsplicht bij een procedure zelf omzet.',
        );
    }

    public function test_cyberbeveiligingswet_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/de-cyberbeveiligingswet-in-het-isms')
            ->assertOk()
            ->assertSee('De Cyberbeveiligingswet: waar hij dit systeem raakt') // titel uit het register
            ->assertSee('ISMS_CBW_PLICHTIG')                                   // de instelling
            ->assertSee('Waar de bewaking ophoudt');                           // markdown → gerenderde h2
    }

    /**
     * De termijnentabel in het artikel, rij voor rij, tegen
     * `config/meldplicht.php`. Uit de config en niet uit een lijst hier: die is
     * de enige plek waar de wet staat, dus een wetswijziging hoort dit artikel
     * te laten falen in plaats van het stilzwijgend te laten verouderen.
     *
     * Per tabelrij en niet "staat ergens in de tekst": beide getallen komen
     * verderop in het artikel nog een keer voor (24 uur in de paragraaf over de
     * nachtelijke sweeps, art. 29 in de uitleg over het eindverslag), en dan
     * bewijst een zoektocht door het hele bestand niets over de tabel.
     */
    public function test_cyberbeveiligingswet_artikel_noemt_de_termijnen_uit_de_config(): void
    {
        $inhoud = Kennisartikelen::inhoud('de-cyberbeveiligingswet-in-het-isms') ?? '';

        foreach (config('meldplicht.grondslagen.cbw.fasen') as $fase => $regel) {
            $gevonden = preg_match(
                '/^\|\s*'.preg_quote($regel['label'], '/').'\s*\|(.*)$/mu',
                $inhoud,
                $rij,
            );

            $this->assertSame(1, $gevonden, "Fase {$fase} ({$regel['label']}) heeft geen rij in de termijnentabel.");

            $this->assertStringContainsString(
                str_replace('Cbw ', '', $regel['grondslag_artikel']),
                $rij[1],
                "Fase {$fase}: de tabelrij noemt een ander artikel dan config/meldplicht.php.",
            );

            // Het eindverslag staat als "één maand" in de tabel en niet als 720
            // uur; die vertaling is bewust, dus daar toetsen we alleen het
            // artikelnummer hierboven.
            if ($regel['uren'] !== null && $regel['uren'] < 100) {
                $this->assertStringContainsString(
                    "{$regel['uren']} uur",
                    $rij[1],
                    "Fase {$fase}: de tabelrij noemt een andere termijn dan config/meldplicht.php.",
                );
            }
        }
    }

    public function test_audit_trail_artikel_rendert_de_volledige_entiteitentabel(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $antwoord = $this->actingAs($gebruiker)->get('/kennisbank/de-audit-trail')
            ->assertOk()
            ->assertSee('De audit trail: wat er in staat, en wat niet')
            ->assertSee('status_gewijzigd')
            ->assertSee('Wat er bewust níét in staat');

        // De auditor wil de volledige lijst zien: elke auditeerbare entiteit
        // hoort in de tabel te staan, niet een selectie ervan.
        $aliassen = $this->auditeerbareAliassen();

        foreach ($aliassen as $alias) {
            $antwoord->assertSee('<code>'.$alias.'</code>', false);
        }

        // En andersom: geen rij te veel. Een entiteit die uit de code verdwijnt
        // blijft anders in de tabel staan, en dan belooft het artikel de auditor
        // een regel die er nooit komt.
        preg_match_all('/^\|[^|]+\|\s*`([a-z_]+)`\s*\|/m', $markdown = Kennisartikelen::inhoud('de-audit-trail') ?? '', $rijen);

        sort($aliassen);
        $inTabel = $rijen[1];
        sort($inTabel);

        $this->assertSame($aliassen, $inTabel);

        // Het getal in de inleidende zin telt mee: de tabel eronder werd bij
        // elke uitbreiding netjes bijgewerkt, maar het woord ervoor bleef staan
        // — precies zo liep het tot augustus 2026 achter op de werkelijkheid.
        $this->assertStringContainsString(
            $this->inWoorden(count($aliassen)).' entiteiten schrijven naar de trail',
            mb_strtolower($markdown),
        );
    }

    /**
     * Het telwoord waarmee de kennisbank een aantal uitschrijft. Uit `intl` en
     * niet uit een tabel hier, zodat er geen tweede lijst is die zelf weer
     * achter kan lopen. De zachte afbreekstreepjes die de formatter erin zet
     * ("een\u{00AD}en\u{00AD}veertig") horen niet in de tekst thuis.
     */
    private function inWoorden(int $aantal): string
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('intl ontbreekt; het telwoord is niet te controleren.');
        }

        return str_replace(
            "\u{00AD}",
            '',
            (new \NumberFormatter('nl', \NumberFormatter::SPELLOUT))->format($aantal),
        );
    }

    /**
     * Het aantal KPI's staat als getal in de tekst en is dus niet af te leiden
     * uit de tabel eronder. Uit de seeder en niet uit een lijst hier: een
     * nieuwe KPI hoort dit te laten falen, want de lezer telt dat getal na.
     */
    public function test_kpi_artikel_noemt_het_juiste_aantal_kpis(): void
    {
        $this->seed(KpiDefinitieSeeder::class);

        $inhoud = Kennisartikelen::inhoud('kpis-en-meetwaarden') ?? '';

        $this->assertMatchesRegularExpression('/\*\*(\d+) KPI\'s\*\* in de catalogus/', $inhoud);
        preg_match('/\*\*(\d+) KPI\'s\*\* in de catalogus/', $inhoud, $treffer);

        $this->assertSame(
            KpiDefinitie::count(),
            (int) $treffer[1],
            'Het aantal KPI\'s in het kennisartikel loopt achter op KpiDefinitieSeeder.',
        );
    }

    /**
     * De morph-aliassen van alle modellen met de Auditeerbaar-trait. Uit de code
     * en niet uit een lijst in de test: een nieuw auditeerbaar model hoort het
     * kennisartikel te laten falen, want anders belooft het artikel de auditor
     * een volledigheid die het niet heeft.
     *
     * @return list<string>
     */
    private function auditeerbareAliassen(): array
    {
        $map = Relation::morphMap();
        $aliassen = [];

        foreach (glob(app_path('Models/*.php')) as $bestand) {
            $klasse = 'App\\Models\\'.basename($bestand, '.php');

            if (! class_exists($klasse)
                || ! in_array(Auditeerbaar::class, class_uses_recursive($klasse), true)) {
                continue;
            }

            $alias = array_search($klasse, $map, true);
            $this->assertNotFalse($alias, "{$klasse} is auditeerbaar maar staat niet in de morph map.");
            $aliassen[] = $alias;
        }

        return $aliassen;
    }

    /**
     * Eén test voor het hele beheerartikel: elk `isms:`-commando dat de
     * applicatie kent, hoort erin te staan. Uit de commandoregistratie en niet
     * uit een lijst hier, zodat een nieuw commando het artikel laat falen —
     * anders belooft de pagina een volledigheid die ze niet heeft.
     */
    public function test_beheerartikel_beschrijft_elk_artisan_commando(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $antwoord = $this->actingAs($gebruiker)->get('/kennisbank/beheer')
            ->assertOk()
            ->assertSee("Beheer: de artisan-commando's");

        $commandos = array_filter(
            array_keys(Artisan::all()),
            fn (string $naam) => str_starts_with($naam, 'isms:'),
        );

        $this->assertNotEmpty($commandos);

        foreach ($commandos as $naam) {
            $antwoord->assertSee($naam);
        }
    }

    /**
     * De twee oriëntatiestukken voor lezers die het systeem nog niet kennen. Ze
     * zijn profielloos, en dat mag alleen zolang er geen maatregelaantal in
     * staat: 93 (ISO) tegen 101 (NEN) is precies het getal dat een lezer natelt.
     */
    public function test_orientatieartikelen_renderen_en_noemen_geen_maatregelaantal(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/ezisms-voor-de-ciso')
            ->assertOk()
            ->assertSee('EzISMS voor de CISO: past dit bij je?') // titel uit het register
            ->assertSee('Dit past bij je als')                   // markdown → gerenderde h2
            ->assertSee('Wat dit systeem uitdrukkelijk niet is')
            // De afbakening tegen CMDB en servicedesk: die verwachting kost bij
            // een demo altijd tijd, dus ze hoort in beide oriëntatiestukken.
            ->assertSee('geen assetmanagementsysteem')
            ->assertSee('geen ticketsysteem');

        $this->actingAs($gebruiker)->get('/kennisbank/ezisms-voor-de-auditor')
            ->assertOk()
            ->assertSee('EzISMS voor de externe auditor: een rondleiding')
            ->assertSee('Waar wat te vinden is')
            ->assertSee('Wat er van dit systeem níét te verwachten valt')
            ->assertSee('geen assetmanagementsysteem')
            ->assertSee('geen ticketsysteem');

        foreach (['ezisms-voor-de-ciso', 'ezisms-voor-de-auditor'] as $slug) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(93|101)\b/',
                Kennisartikelen::inhoud($slug) ?? '',
                "Het artikel {$slug} noemt een maatregelaantal, en dat verschilt per normprofiel.",
            );
        }
    }

    /**
     * Beide artikelen zijn één en al doorverwijzing naar de rest van de
     * kennisbank — dezelfde eis als bij `open-punten`: een dode link bij een
     * eerste kennismaking kost het vertrouwen in de hele lijst.
     */
    public function test_orientatieartikelen_verwijzen_alleen_naar_bestaande_artikelen(): void
    {
        foreach (['ezisms-voor-de-ciso', 'ezisms-voor-de-auditor'] as $slug) {
            preg_match_all('#/kennisbank/([a-z0-9-]+)#', Kennisartikelen::inhoud($slug) ?? '', $treffers);

            $this->assertNotEmpty($treffers[1], "Het artikel {$slug} verwijst nergens heen.");

            foreach (array_unique($treffers[1]) as $doel) {
                $this->assertTrue(
                    Kennisartikelen::bestaat($doel),
                    "{$slug} verwijst naar /kennisbank/{$doel}, en dat artikel bestaat niet.",
                );
            }
        }
    }

    public function test_sitestructuur_artikel_rendert_de_svg(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        // De SVG-sitemap moet ongewijzigd (niet ge-escaped) doorkomen.
        $this->actingAs($gebruiker)->get('/kennisbank/sitestructuur')
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Restrisico-trend')
            ->assertSee('Statement of Applicability');
    }

    public function test_verantwoording_artikel_rendert(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/verantwoording-en-disclaimer')
            ->assertOk()
            ->assertSee('Verantwoording en disclaimer')       // titel uit het register
            ->assertSee('Waar dit systeem geen vervanging voor is')
            ->assertSee('Auteursrecht en verspreiding');
    }

    public function test_open_punten_artikel_rendert_en_verwijst_alleen_naar_bestaande_artikelen(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $antwoord = $this->actingAs($gebruiker)->get('/kennisbank/open-punten')
            ->assertOk()
            ->assertSee('Open punten, bedenkingen en ideeën')   // titel uit het register
            ->assertSee('Beslissingen die nog aan u zijn')      // markdown → gerenderde h2
            ->assertSee('Ideeën die klaarliggen')
            ->assertSee('Wat hier níét op hoort');

        // Deze pagina is één en al doorverwijzing. Een dode link is hier erger
        // dan elders: wie een open punt natrekt en op een 404 stuit, gelooft de
        // rest van de lijst ook niet meer.
        preg_match_all('#/kennisbank/([a-z0-9-]+)#', Kennisartikelen::inhoud('open-punten') ?? '', $treffers);

        $this->assertNotEmpty($treffers[1]);

        foreach (array_unique($treffers[1]) as $slug) {
            $this->assertTrue(Kennisartikelen::bestaat($slug), "De pagina verwijst naar /kennisbank/{$slug}, en dat artikel bestaat niet.");
        }
    }

    public function test_lijst_linkt_naar_de_echte_slugs_niet_naar_id_nul(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $response = $this->actingAs($gebruiker)->get('/kennisbank')->assertOk();

        // De regressie: groupBy zonder preserveKeys maakte er /kennisbank/0 van.
        $response->assertDontSee(route('kennisbank', 0));
        $response->assertSee(route('kennisbank', 'incidenten-en-afwijkingen'));
        $response->assertSee(route('kennisbank', 'hr-saas-leverancier-opvoeren'));
    }

    public function test_onbekende_slug_geeft_404(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker)->get('/kennisbank/bestaat-niet')->assertNotFound();
    }

    // --- Downloaden: alleen de CISO ----------------------------------------

    /**
     * Een gebruiker met een rol. Alleen `RolSeeder`, geen blokken of permissies:
     * de kennisbank staat buiten het blokkenmodel en `kennisartikel-downloaden`
     * kijkt puur naar de rolnaam. Lokaal en niet in `setUp()`, zodat de
     * leestests hierboven hun lege database houden — daar is "geen enkele rol"
     * juist het uitgangspunt.
     */
    private function gebruikerMetRol(string $rol): Gebruiker
    {
        $this->seed(RolSeeder::class);

        return Gebruiker::factory()->metRol($rol)->create();
    }

    /**
     * Pandoc wegmocken, zoals de previewtests doen: de conversie zelf is niet
     * wat hier op het spel staat, en een test die op de binary leunt faalt op
     * een machine zonder pandoc om de verkeerde reden. De markdown die erin gaat
     * leggen we vast in `$gezien`, want dát is wat dit onderdeel bepaalt.
     */
    private function mockPandoc(?string &$gezien = null): void
    {
        $this->mock(Pandoc::class, function ($mock) use (&$gezien) {
            $mock->shouldReceive('beschikbaar')->andReturnTrue();
            $mock->shouldReceive('naarDocx')->andReturnUsing(function (string $markdown) use (&$gezien) {
                $gezien = $markdown;

                return 'PK-docx';
            });
        });
    }

    public function test_ciso_downloadt_het_artikel_als_word_document(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');
        $this->mockPandoc($aangeleverd);

        $this->actingAs($ciso)
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertOk()
            ->assertDownload('incidenten-en-afwijkingen.docx')
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );

        // De ruwe markdown gaat de conversie in, niet de gerenderde HTML:
        // '##' overleeft die conversie niet, dus dit onderscheidt de twee.
        $this->assertStringContainsString('## De sluitpoort van een incident', $aangeleverd);
    }

    /**
     * Het document moet zijn eigen titel dragen. `Kennisartikelen::inhoud()`
     * haalt een H1 juist weg — de pagina toont de titel apart — en twee
     * artikelen hebben er sowieso nooit een gehad. Zonder deze stap begint het
     * Word-document dus middenin.
     */
    public function test_het_document_begint_met_de_titel_uit_het_register(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');
        $this->mockPandoc($aangeleverd);

        // Een artikel dat zelf geen H1 in het bestand heeft staan.
        $this->actingAs($ciso)
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertOk();

        $this->assertStringStartsWith(
            '# Incidenten & afwijkingen: statussen en normkoppeling',
            $aangeleverd,
        );

        // En een artikel dat er wél een had: die mag niet gedubbeld worden.
        $this->mockPandoc($tweede);
        $this->actingAs($ciso)->get('/kennisbank/de-audit-trail/download')->assertOk();

        $this->assertSame(1, substr_count($tweede, "\n# ") + str_starts_with($tweede, '# '));
    }

    /**
     * Een download die stil een leeg of kapot bestand oplevert is erger dan een
     * foutmelding — dezelfde regel als bij de schermkopie (12h §7).
     */
    public function test_zonder_pandoc_volgt_een_leesbare_foutmelding(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');

        $this->mock(Pandoc::class, fn ($mock) => $mock->shouldReceive('beschikbaar')->andReturnFalse());

        $this->actingAs($ciso)
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertStatus(503);
    }

    public function test_een_mislukte_conversie_geeft_geen_half_document(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');

        $this->mock(Pandoc::class, function ($mock) {
            $mock->shouldReceive('beschikbaar')->andReturnTrue();
            $mock->shouldReceive('naarDocx')->andThrow(new RuntimeException('pandoc klapte'));
        });

        $this->actingAs($ciso)
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertStatus(503);
    }

    /**
     * Eén keer de echte binary, zodat we weten dat de markdown in de kennisbank
     * ook daadwerkelijk door pandoc heen komt. Overgeslagen zonder pandoc, net
     * als bij de schermkopie.
     */
    public function test_de_echte_conversie_levert_een_docx_op(): void
    {
        if (! (new Pandoc)->beschikbaar()) {
            $this->markTestSkipped('pandoc staat niet op deze machine.');
        }

        config(['app.organisatie' => 'Fruit BV']);

        $ciso = $this->gebruikerMetRol('CISO');

        $antwoord = $this->actingAs($ciso)
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertOk()
            ->assertDownload('incidenten-en-afwijkingen.docx');

        // Een .docx is een zip; 'PK' is de handtekening ervan.
        $this->assertStringStartsWith('PK', $antwoord->streamedContent());

        $voettekst = $this->voettekstUit($antwoord->streamedContent());

        // Wél een voettekst, maar níet die van de schermkopie: een kennisartikel
        // is meegeleverde documentatie uit de repo. De organisatienaam eronder
        // zou suggereren dat zij het geschreven heeft.
        $this->assertStringContainsString('EzISMS', $voettekst);
        $this->assertStringNotContainsString('Fruit BV', $voettekst);
    }

    /** Haalt `word/footer1.xml` uit een docx die alleen als string bestaat. */
    private function voettekstUit(string $docx): string
    {
        $pad = tempnam(sys_get_temp_dir(), 'ezisms-toets-');
        file_put_contents($pad, $docx);

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($pad) === true);

            $voettekst = $zip->getFromName('word/footer1.xml');
            $zip->close();

            $this->assertIsString($voettekst, 'Het document draagt geen voettekst.');

            return $voettekst;
        } finally {
            @unlink($pad);
        }
    }

    public function test_de_downloadknop_staat_alleen_bij_de_ciso_op_het_scherm(): void
    {
        $url = route('kennisbank.download', 'incidenten-en-afwijkingen');

        $ciso = $this->gebruikerMetRol('CISO');
        $this->actingAs($ciso)->get('/kennisbank/incidenten-en-afwijkingen')
            ->assertOk()
            ->assertSee($url, false);

        foreach (['Medewerker', 'Auditor', 'Management'] as $rol) {
            $ander = $this->gebruikerMetRol($rol);

            $this->actingAs($ander)->get('/kennisbank/incidenten-en-afwijkingen')
                ->assertOk()
                ->assertDontSee($url, false);
        }
    }

    /**
     * De knop weglaten is geen beveiliging: de route moet zelf weigeren. Alle
     * andere rollen mogen het artikel wél lézen — dat is precies het verschil
     * dat deze test bewaakt.
     */
    public function test_andere_rollen_krijgen_403_op_de_download(): void
    {
        foreach (['Medewerker', 'Auditor', 'Management', 'Administrator'] as $rol) {
            $gebruiker = $this->gebruikerMetRol($rol);

            $this->actingAs($gebruiker)
                ->get('/kennisbank/incidenten-en-afwijkingen/download')
                ->assertForbidden();
        }

        // En een account zonder enige rol ook niet.
        $this->actingAs(Gebruiker::factory()->create())
            ->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertForbidden();
    }

    public function test_gast_wordt_bij_de_download_naar_login_gestuurd(): void
    {
        $this->get('/kennisbank/incidenten-en-afwijkingen/download')
            ->assertRedirect(route('login'));
    }

    /**
     * De SBOM staat in de projectroot en niet in `resources/kennisbank/`. De
     * download moet dat pad meelopen — anders is dit het ene artikel dat een
     * 404 geeft terwijl de pagina gewoon rendert.
     */
    public function test_download_werkt_ook_voor_het_artikel_uit_de_projectroot(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');
        $this->mockPandoc();

        $this->actingAs($ciso)
            ->get('/kennisbank/software-bill-of-materials/download')
            ->assertOk()
            ->assertDownload('software-bill-of-materials.docx');
    }

    public function test_download_van_een_onbekende_slug_geeft_404(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');

        $this->actingAs($ciso)->get('/kennisbank/bestaat-niet/download')->assertNotFound();
    }

    /**
     * Elk artikel dat de lijst toont én downloadbaar heet, moet ook echt te
     * downloaden zijn. Uit het register en niet uit een lijst hier: een nieuw
     * artikel met een ontbrekend bestand hoort hier te stranden en niet pas bij
     * een gebruiker.
     */
    public function test_elk_downloadbaar_artikel_is_te_downloaden(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');
        $this->mockPandoc();

        $gezien = 0;

        foreach (array_keys(Kennisartikelen::alles()) as $slug) {
            if (! Kennisartikelen::isDownloadbaar($slug)) {
                continue;
            }

            $this->actingAs($ciso)->get("/kennisbank/{$slug}/download")->assertOk();
            $gezien++;
        }

        // Zodat een fout in `isDownloadbaar()` deze test niet leegdraait.
        $this->assertGreaterThan(10, $gezien);
    }

    /**
     * De sitestructuur is één inline SVG met de menuboom erin, en die valt bij
     * de conversie naar docx weg. Een download die het onderwerp van de pagina
     * stilzwijgend weglaat is erger dan geen download — dus geen knop, en de
     * route weigert ook.
     */
    public function test_de_sitestructuur_heeft_geen_downloadknop(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');

        $this->actingAs($ciso)->get('/kennisbank/sitestructuur')
            ->assertOk()
            ->assertSee('<svg', false) // de pagina zelf blijft gewoon werken
            ->assertDontSee(route('kennisbank.download', 'sitestructuur'), false);

        $this->actingAs($ciso)
            ->get('/kennisbank/sitestructuur/download')
            ->assertNotFound();
    }

    /**
     * En alleen daar: het weglaten van de knop is een uitzondering voor één
     * artikel, geen sluipende regel die ook andere pagina's raakt.
     */
    public function test_de_overige_artikelen_houden_hun_downloadknop(): void
    {
        $ciso = $this->gebruikerMetRol('CISO');

        foreach (array_keys(Kennisartikelen::alles()) as $slug) {
            $verwacht = $slug !== 'sitestructuur';

            $this->assertSame($verwacht, Kennisartikelen::isDownloadbaar($slug), $slug);

            $antwoord = $this->actingAs($ciso)->get("/kennisbank/{$slug}")->assertOk();
            $url = route('kennisbank.download', $slug);

            $verwacht ? $antwoord->assertSee($url, false) : $antwoord->assertDontSee($url, false);
        }
    }
}
