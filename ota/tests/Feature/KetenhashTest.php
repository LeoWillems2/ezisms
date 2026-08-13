<?php

namespace Tests\Feature;

use App\Livewire\AuditLogOverzicht;
use App\Models\AuditLogregel;
use App\Models\Gebruiker;
use App\Models\Ketencontrole;
use App\Models\Risico;
use App\Support\Audittrailketen;
use App\Support\Ketenhash;
use Database\Seeders\BlokSeeder;
use Database\Seeders\RolPermissieSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Keten-hashing van de audit trail (implementatie/06c).
 *
 * De logregels worden hier met `legVerzamelingVast()` geschreven: dat is een
 * echte schrijver uit de applicatie en het levert precies één regel per aanroep.
 * Via een model zou een observer er een taak bij kunnen maken, en dan toetst een
 * telling iets anders dan ze belooft.
 */
class KetenhashTest extends TestCase
{
    use RefreshDatabase;

    private function schrijfRegels(int $aantal): void
    {
        foreach (range(1, $aantal) as $nummer) {
            AuditLogregel::legVerzamelingVast(
                blokNaam: 'risico-soa',
                entiteitType: 'risico',
                actie: 'verwijderd',
                omschrijving: 'Handeling '.$nummer,
                details: ['aantal' => $nummer],
            );
        }
    }

    /**
     * De teststeen uit 06c §2. Deze hash staat er letterlijk, en dat is de
     * bedoeling: hij mag alleen veranderen als iemand de canonieke vorm bewust
     * wijzigt. Gebeurt dat, dan moet élke bestaande installatie opnieuw verzegeld
     * worden — dat hoort een besluit te zijn en geen bijvangst van een refactor
     * of een PHP-upgrade.
     */
    public function test_de_canonieke_vorm_ligt_vast(): void
    {
        $regel = [
            'tijdstip' => '2026-08-03 14:30:00',
            'gebruiker_id' => 7,
            'gebruiker_naam' => 'Aurelius Aardappel',
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => 42,
            'entiteit_omschrijving' => 'Uitval koelinstallatie',
            'actie' => 'gewijzigd',
            'oude_waarde' => ['kans_niveau' => 4, 'impact_niveau' => 5],
            'nieuwe_waarde' => ['kans_niveau' => 3, 'impact_niveau' => 5],
        ];

        $this->assertSame(
            '{"tijdstip":"2026-08-03 14:30:00","gebruiker_id":7,"gebruiker_naam":"Aurelius Aardappel",'
            .'"blok_naam":"risico-soa","entiteit_type":"risico","entiteit_id":42,'
            .'"entiteit_omschrijving":"Uitval koelinstallatie","actie":"gewijzigd",'
            .'"oude_waarde":{"impact_niveau":5,"kans_niveau":4},'
            .'"nieuwe_waarde":{"impact_niveau":5,"kans_niveau":3},"vorige_hash":null}',
            Ketenhash::canoniek($regel, vorigeHash: null),
        );

        $this->assertSame(
            'b4ec31e0e7eb357cc9970d184197acb3eddbb809c2066dc7ef9bb0ef692f4ede',
            Ketenhash::van($regel, vorigeHash: null),
        );
    }

    /**
     * De MySQL-val uit 06c §2: een `json`-kolom herordent sleutels. Zonder deze
     * eigenschap zou de keten op productie breken bij een dump-en-restore, en
     * geen enkele test zou dat vangen.
     */
    public function test_een_andere_sleutelvolgorde_geeft_dezelfde_hash(): void
    {
        $basis = [
            'tijdstip' => '2026-08-03 14:30:00',
            'gebruiker_naam' => 'Systeem (geplande taak)',
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => 1,
            'entiteit_omschrijving' => 'Uitval koelinstallatie',
            'actie' => 'gewijzigd',
        ];

        $eerst = Ketenhash::van([...$basis, 'nieuwe_waarde' => ['a' => 1, 'b' => ['x' => 1, 'y' => 2]]], null);
        $anders = Ketenhash::van([...$basis, 'nieuwe_waarde' => ['b' => ['y' => 2, 'x' => 1], 'a' => 1]], null);

        $this->assertSame($eerst, $anders);

        // Maar een andere wáárde levert wél een andere hash op — anders toetst
        // de assertie hierboven niets.
        $this->assertNotSame(
            $eerst,
            Ketenhash::van([...$basis, 'nieuwe_waarde' => ['a' => 2, 'b' => ['x' => 1, 'y' => 2]]], null),
        );
    }

    /** JSON als tekst of als array: hetzelfde. Zo komt het uit de twee databases. */
    public function test_json_als_tekst_geeft_dezelfde_hash_als_json_als_array(): void
    {
        $basis = ['tijdstip' => '2026-08-03 14:30:00', 'actie' => 'gewijzigd'];

        $this->assertSame(
            Ketenhash::van([...$basis, 'nieuwe_waarde' => ['titel' => 'Aa/Bb']], null),
            Ketenhash::van([...$basis, 'nieuwe_waarde' => '{"titel":"Aa\/Bb"}'], null),
        );
    }

    /** Een lijst houdt zijn volgorde: daar ís de volgorde de betekenis. */
    public function test_een_andere_lijstvolgorde_geeft_een_andere_hash(): void
    {
        $basis = ['tijdstip' => '2026-08-03 14:30:00', 'actie' => 'gewijzigd'];

        $this->assertNotSame(
            Ketenhash::van([...$basis, 'nieuwe_waarde' => ['doelgroepen' => ['A', 'B']]], null),
            Ketenhash::van([...$basis, 'nieuwe_waarde' => ['doelgroepen' => ['B', 'A']]], null),
        );
    }

    public function test_logregels_vormen_een_keten(): void
    {
        $this->schrijfRegels(3);

        $regels = DB::table('audit_logregels')->orderBy('id')->get();

        $this->assertNull($regels[0]->vorige_hash, 'De eerste regel heeft geen voorganger.');

        foreach ($regels as $index => $regel) {
            $this->assertSame(64, strlen((string) $regel->hash));

            if ($index > 0) {
                $this->assertSame($regels[$index - 1]->hash, $regel->vorige_hash);
            }
        }

        $this->assertSame($regels->last()->hash, Audittrailketen::kop());
    }

    /** Ook de weg via een model — dat is hoe verreweg de meeste regels ontstaan. */
    public function test_een_regel_uit_de_auditeerbaar_trait_krijgt_ook_een_hash(): void
    {
        Risico::factory()->create();

        $this->assertGreaterThan(0, DB::table('audit_logregels')->count());
        $this->assertSame(0, DB::table('audit_logregels')->whereNull('hash')->count());
        $this->assertTrue(Audittrailketen::controleer()->intact);
    }

    public function test_de_controle_meldt_een_ongeschonden_keten(): void
    {
        $this->schrijfRegels(3);

        $uitkomst = Audittrailketen::controleer();

        $this->assertTrue($uitkomst->intact);
        $this->assertSame(3, $uitkomst->regels);
        $this->assertNull($uitkomst->kapotte_id);
        $this->assertSame(Audittrailketen::kop(), $uitkomst->kophash);
    }

    public function test_een_lege_trail_is_een_intacte_keten(): void
    {
        $uitkomst = Audittrailketen::controleer();

        $this->assertTrue($uitkomst->intact);
        $this->assertSame(0, $uitkomst->regels);
        $this->assertNull($uitkomst->kophash);
    }

    /**
     * De aanval waar dit hele plan om draait: iemand met databasetoegang past
     * een regel aan. Het model verbiedt dat, dus de test doet het zoals een
     * aanvaller het zou doen — rechtstreeks met de query builder.
     */
    public function test_een_gewijzigde_regel_wordt_gevonden(): void
    {
        $this->schrijfRegels(3);

        $tweede = DB::table('audit_logregels')->orderBy('id')->skip(1)->first();
        DB::table('audit_logregels')->where('id', $tweede->id)
            ->update(['gebruiker_naam' => 'Iemand anders']);

        $uitkomst = Audittrailketen::controleer();

        $this->assertFalse($uitkomst->intact);
        $this->assertSame($tweede->id, $uitkomst->kapotte_id);
        $this->assertStringContainsString('gebroken', $uitkomst->samenvatting());
    }

    public function test_een_verwijderde_regel_wordt_gevonden(): void
    {
        $this->schrijfRegels(3);

        $ids = DB::table('audit_logregels')->orderBy('id')->pluck('id');
        DB::table('audit_logregels')->where('id', $ids[1])->delete();

        // De derde regel wijst nu naar een voorganger die er niet meer is.
        $uitkomst = Audittrailketen::controleer();

        $this->assertFalse($uitkomst->intact);
        $this->assertSame($ids[2], $uitkomst->kapotte_id);
    }

    /** De vork uit §4: één hash mag hoogstens één opvolger hebben. */
    public function test_twee_regels_kunnen_niet_dezelfde_voorganger_krijgen(): void
    {
        // Twee regels, want de tweede is de eerste met een gevulde
        // `vorige_hash` — en null botst nergens mee.
        $this->schrijfRegels(2);

        $bestaande = DB::table('audit_logregels')->orderBy('id')->skip(1)->first();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('audit_logregels')->insert([
            'tijdstip' => now(),
            'gebruiker_naam' => 'Vork',
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => 1,
            'entiteit_omschrijving' => 'Tweede tak',
            'actie' => 'aangemaakt',
            'hash' => str_repeat('f', 64),
            'vorige_hash' => $bestaande->vorige_hash,
        ]);
    }

    public function test_verzegelen_levert_een_intacte_keten_en_een_vastlegging(): void
    {
        $this->schrijfRegels(2);

        // Een regel die buiten het model om is toegevoegd, heeft geen hash.
        DB::table('audit_logregels')->insert([
            'tijdstip' => now(),
            'gebruiker_naam' => 'Rauwe insert',
            'blok_naam' => 'risico-soa',
            'entiteit_type' => 'risico',
            'entiteit_id' => 1,
            'entiteit_omschrijving' => 'Buiten het model om',
            'actie' => 'aangemaakt',
        ]);

        $this->assertFalse(Audittrailketen::controleer()->intact);

        $verzegeling = Audittrailketen::verzegel('Test');

        $this->assertTrue(Audittrailketen::controleer()->intact);
        $this->assertTrue($verzegeling->isVerzegeling());
        $this->assertSame(3, $verzegeling->regels);
        $this->assertDatabaseHas('audit_ketencontroles', ['soort' => 'verzegeld', 'reden' => 'Test']);
    }

    /** De migratie verzegelt zelf; dat is waar de bewijskracht begint (§7). */
    public function test_de_migratie_laat_een_verzegeling_achter(): void
    {
        $this->assertDatabaseHas('audit_ketencontroles', ['soort' => 'verzegeld']);
    }

    /**
     * `gebruiker_id` is `nullOnDelete`: verdwijnt een account uit de database,
     * dan wijzigen de logregels buiten het model om en breekt de keten. Dat is
     * geen fout maar de bedoeling — het ís een wijziging van de trail. De weg
     * terug is een verzegeling met een reden, precies zoals bij
     * `isms:verwijder-auditdata --met-trail`.
     */
    public function test_een_verdwenen_account_breekt_de_keten_en_vraagt_een_verzegeling(): void
    {
        $gebruiker = Gebruiker::factory()->create();

        $this->actingAs($gebruiker);
        $this->schrijfRegels(2);
        auth()->logout();

        $this->assertTrue(Audittrailketen::controleer()->intact);

        DB::table('audit_logregels')->update(['gebruiker_id' => null]);

        $this->assertFalse(Audittrailketen::controleer()->intact);

        Audittrailketen::verzegel('Account verwijderd op verzoek van de betrokkene.');

        $this->assertTrue(Audittrailketen::controleer()->intact);
        $this->assertDatabaseHas('audit_ketencontroles', [
            'reden' => 'Account verwijderd op verzoek van de betrokkene.',
        ]);
    }

    public function test_het_commando_legt_zijn_uitkomst_vast(): void
    {
        $this->schrijfRegels(1);

        $this->artisan('isms:controleer-audittrail')
            ->expectsOutputToContain('Keten intact')
            ->assertSuccessful();

        $laatste = Ketencontrole::laatste();
        $this->assertSame('controle', $laatste->soort);
        $this->assertTrue($laatste->intact);
        $this->assertSame(1, $laatste->regels);
    }

    public function test_het_commando_faalt_op_een_gebroken_keten(): void
    {
        $this->schrijfRegels(2);

        DB::table('audit_logregels')->orderBy('id')->limit(1)->update(['blok_naam' => 'iets-anders']);

        $this->artisan('isms:controleer-audittrail')->assertFailed();

        $this->assertFalse(Ketencontrole::laatste()->intact);
    }

    // --- Op het scherm en in de kopie voor de auditor (06c §6 en §8) --------

    private function ciso(): Gebruiker
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);

        return Gebruiker::factory()->metRol('CISO')->create();
    }

    public function test_de_audit_trail_toont_de_ketenstatus(): void
    {
        $ciso = $this->ciso();
        $this->schrijfRegels(2);
        $this->artisan('isms:controleer-audittrail --stil')->assertSuccessful();

        $this->actingAs($ciso)->get('/audit-log')
            ->assertOk()
            ->assertSee('Keten intact')
            ->assertSee(substr(Audittrailketen::kop(), 0, 12));
    }

    public function test_een_gebroken_keten_staat_op_het_scherm(): void
    {
        $ciso = $this->ciso();
        $this->schrijfRegels(2);
        DB::table('audit_logregels')->orderBy('id')->limit(1)->update(['blok_naam' => 'iets-anders']);
        $this->artisan('isms:controleer-audittrail --stil')->assertFailed();

        $this->actingAs($ciso)->get('/audit-log')
            ->assertOk()
            ->assertSee('Keten gebroken bij regel');
    }

    /**
     * Het anker uit §8: zonder de kophash in het document is de kopie een
     * momentopname zonder ijkpunt, en dan blijft de keten een belofte die het
     * systeem over zichzelf doet.
     */
    public function test_de_kopie_voor_de_auditor_draagt_de_kophash(): void
    {
        $ciso = $this->ciso();
        $this->schrijfRegels(2);
        $this->artisan('isms:controleer-audittrail --stil')->assertSuccessful();

        $component = Livewire::actingAs($ciso)->test(AuditLogOverzicht::class);
        $markdown = (fn () => $this->schermkopie())->call($component->instance())->markdown();

        $this->assertStringContainsString('# Audit trail', $markdown);
        $this->assertStringContainsString(Audittrailketen::kop(), $markdown);
        $this->assertStringContainsString('Keten intact t/m regel', $markdown);
        $this->assertStringContainsString('Handeling 2', $markdown);
    }

    /**
     * De kopie gaat het pand uit; het scherm niet. Wie wat deed hoort op het
     * scherm te staan — daar kijkt de CISO met een reden naar — maar het
     * document hoeft die namen niet te herhalen om te tonen dát er toezicht is.
     */
    public function test_de_kopie_noemt_personen_alleen_met_initialen(): void
    {
        $this->seed([RolSeeder::class, BlokSeeder::class, RolPermissieSeeder::class]);
        $ciso = Gebruiker::factory()->metRol('CISO')->create(['naam' => 'Aurelius Aardappel']);

        $this->actingAs($ciso);
        $this->schrijfRegels(1);
        AuditLogregel::legVerzamelingVast('risico-soa', 'risico', 'verwijderd', 'Nachtelijke opschoning');
        // Een omschrijving die zelf een naam bevat — zo heet een
        // leesbevestiging of een trainingsvoltooiing in de trail.
        AuditLogregel::legVerzamelingVast(
            'bewustzijn-training', 'trainingsvoltooiing', 'aangemaakt',
            'Trainingsvoltooiing Basis informatiebeveiliging door Aurelius Aardappel',
        );
        auth()->logout();

        $component = Livewire::actingAs($ciso)->test(AuditLogOverzicht::class);
        $kopie = (fn () => $this->schermkopie())->call($component->instance());
        $markdown = $kopie->markdown();

        // Nergens meer: niet als handelende persoon, niet in de omschrijving van
        // een handeling, en niet als naam van het account zelf.
        $this->assertStringNotContainsString('Aurelius Aardappel', implode("\n", array_map(
            fn (array $rij) => implode(' ', $rij), $kopie->rijen,
        )));
        $this->assertStringContainsString('| AA |', $markdown);
        $this->assertStringContainsString('door AA', $markdown);
        $this->assertStringContainsString('AA (gebruiker', $markdown);

        // Zonder gebruiker is het geen persoon maar het systeem; daar valt niets
        // te anonimiseren, en "S(g" zou alleen maar verwarren.
        $this->assertStringContainsString('Systeem (geplande taak)', $markdown);

        // Op het scherm staat de naam gewoon.
        $this->actingAs($ciso)->get('/audit-log')->assertOk()->assertSee('Aurelius Aardappel');
    }

    /**
     * Wie een periode kiest, vraagt om die periode — niet om de vijftig regels
     * die toevallig op de eerste pagina staan.
     */
    public function test_met_een_datumfilter_gaat_de_hele_periode_mee(): void
    {
        $ciso = $this->ciso();
        $this->schrijfRegels(60);

        $component = Livewire::actingAs($ciso)->test(AuditLogOverzicht::class);

        // Zonder datumfilter: de zichtbare pagina.
        $zonder = (fn () => $this->schermkopie())->call($component->instance());
        $this->assertCount(50, $zonder->rijen);

        $component->set('vanaf', now()->subDay()->toDateString());
        $met = (fn () => $this->schermkopie())->call($component->instance());

        $this->assertSame(AuditLogregel::count(), count($met->rijen));
        $this->assertGreaterThan(60, count($met->rijen));
        $this->assertStringContainsString('Alle regels uit de gekozen periode', (string) $met->toelichting);
    }

    public function test_de_kopie_meldt_hoeveel_regels_erin_staan(): void
    {
        $ciso = $this->ciso();
        $this->schrijfRegels(3);

        $component = Livewire::actingAs($ciso)->test(AuditLogOverzicht::class)
            ->set('filterActie', 'verwijderd');
        $kopie = (fn () => $this->schermkopie())->call($component->instance());

        // Het aanmaken van de CISO levert zelf ook trailregels op; die vallen
        // buiten het filter maar tellen wél mee in het totaal. Precies dat
        // verschil hoort de kop van het document te noemen (12h §4).
        $this->assertCount(3, $kopie->rijen);
        $this->assertSame(AuditLogregel::count(), $kopie->totaalRijen);
        $this->assertStringContainsString('3 van '.AuditLogregel::count().' regels', $kopie->omvangregel());
        $this->assertStringContainsString('filter: actie verwijderd', $kopie->omvangregel());
    }

    public function test_het_commando_toont_de_kophash(): void
    {
        $this->schrijfRegels(1);

        $this->artisan('isms:controleer-audittrail --kop')
            ->expectsOutput(Audittrailketen::kop())
            ->assertSuccessful();
    }
}
