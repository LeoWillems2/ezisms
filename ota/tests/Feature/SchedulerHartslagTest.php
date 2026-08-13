<?php

namespace Tests\Feature;

use App\Models\KpiDefinitie;
use App\Models\Systeemhartslag;
use App\Models\Taak;
use App\Support\Meetbronnen;
use Database\Seeders\KpiDefinitieSeeder;
use Database\Seeders\SysteemhartslagSeeder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as GeplandeTaak;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * De schedulerhartslag (implementatie/00m §12).
 *
 * De zwaarste gevolgen van een gat zitten bij de KPI's, vandaar de suite
 * `meten-review`.
 *
 * Deze tests draaien tegen de échte planning uit `routes/console.php` en niet
 * tegen een verzonnen schema. Dat is met opzet: het hele plan draait om de regel
 * dat er geen tweede lijst van geplande commando's bestaat (00m §0.1), en een
 * test met zijn eigen schema zou precies die eigenschap niet toetsen.
 */
class SchedulerHartslagTest extends TestCase
{
    use RefreshDatabase;

    /** Een dagelijks commando met klasse `inhaalbaar`. */
    private const DAGELIJKS = 'isms:genereer-taken';

    /** Het jaarlijkse commando met klasse `onherstelbaar`. */
    private const JAARLIJKS = 'isms:leg-restrisico-vast';

    private const MAANDELIJKS = 'isms:meet-kpis';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Hulpjes ──────────────────────────────────────────────────────────────

    /** De geplande taak achter een artisan-commando. */
    private function geplandeTaak(string $sleutel): GeplandeTaak
    {
        foreach (app(Schedule::class)->events() as $taak) {
            if (Systeemhartslag::sleutelVoor($taak) === $sleutel) {
                return $taak;
            }
        }

        $this->fail("Geen geplande taak gevonden voor {$sleutel}.");
    }

    /**
     * Alle commando's op "zojuist gedraaid" zetten, en daarna één ervan
     * terugzetten. Zo staat er precies één gat in het rapport en gaan de
     * assertions niet over de ruis van de andere acht.
     */
    private function alleenGatVoor(string $sleutel, Carbon $laatsteRun): void
    {
        $this->seed(SysteemhartslagSeeder::class);

        Systeemhartslag::where('taak_sleutel', $sleutel)->update(['gedraaid_op' => $laatsteRun]);
    }

    private function bewakingstaken(): int
    {
        return Taak::whereIn('soort', ['kpi-meetpunt-gemist', 'bewaking-onderbroken'])->count();
    }

    // ── 1/2. De listener ─────────────────────────────────────────────────────

    public function test_de_listener_schrijft_een_regel_per_gebeurtenis(): void
    {
        $taak = $this->geplandeTaak(self::DAGELIJKS);

        event(new ScheduledTaskFinished($taak, 1.25));
        event(new ScheduledTaskFailed($taak, new RuntimeException('database weg')));
        event(new ScheduledTaskSkipped($taak));

        $regels = Systeemhartslag::where('taak_sleutel', self::DAGELIJKS)->orderBy('id')->get();

        $this->assertSame(['gelukt', 'fout', 'overgeslagen'], $regels->pluck('resultaat')->all());
        $this->assertSame(1250, $regels[0]->duur_ms);
        $this->assertNull($regels[0]->melding);
        $this->assertSame('database weg', $regels[1]->melding);
        $this->assertNotNull($regels[2]->melding);
        $this->assertNotNull($regels[0]->weergavenaam);
    }

    public function test_een_mislukte_registratie_laat_de_geplande_taak_niet_omvallen(): void
    {
        // De harde manier om de listener te laten struikelen: de tabel weghalen.
        // Wat er ook misgaat bij het wegschrijven, de geplande taak eromheen moet
        // gewoon doorlopen — anders is de bewaking zelf de storing (00m §2).
        Schema::drop('systeemhartslag');

        event(new ScheduledTaskFinished($this->geplandeTaak(self::DAGELIJKS), 1.0));

        // Geen exception tot hier is de assertie; dit maakt hem expliciet.
        $this->assertFalse(Schema::hasTable('systeemhartslag'));
    }

    // ── 3. Het nulpunt ───────────────────────────────────────────────────────

    public function test_het_nulpunt_komt_er_een_keer_per_gepland_commando(): void
    {
        $verwacht = collect(app(Schedule::class)->events())
            ->map(fn (GeplandeTaak $taak) => Systeemhartslag::sleutelVoor($taak))
            ->filter()
            ->unique()
            ->count();

        $this->seed(SysteemhartslagSeeder::class);

        $this->assertSame($verwacht, Systeemhartslag::count());
        $this->assertSame($verwacht, Systeemhartslag::where('resultaat', 'nulpunt')->count());

        // Een tweede db:seed verdubbelt niets — de uitrol draait hem bij élke
        // start (00m §3).
        $this->seed(SysteemhartslagSeeder::class);

        $this->assertSame($verwacht, Systeemhartslag::count());
    }

    public function test_een_commando_zonder_hartslag_meldt_geen_gat(): void
    {
        // Geen nulpunt geseed: dit commando is "nieuw" en er wordt niets van
        // verwacht (00m §9.1). Met terugwerkende kracht een gat melden dat nooit
        // bestond is erger dan één ronde niets zeggen.
        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('geen gaten')
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    // ── 4/5/6. Geen gat, kort gat, inhaalbaar gat ────────────────────────────

    public function test_zonder_gat_gebeurt_er_niets(): void
    {
        $this->seed(SysteemhartslagSeeder::class);

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('geen gaten')
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    public function test_een_gat_onder_de_drempel_levert_geen_taak_en_geen_melding(): void
    {
        // Twee uur, met het moment van 02:00 er middenin: er ís een moment
        // gemist, maar dit is een herstart en geen stilstand (00m §7).
        Carbon::setTestNow(Carbon::parse('2026-06-15 03:00:00'));
        $this->alleenGatVoor(self::DAGELIJKS, Carbon::parse('2026-06-15 01:00:00'));

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('geen gaten')
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    public function test_een_inhaalbaar_gat_wordt_gemeld_maar_levert_geen_taak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $this->alleenGatVoor(self::DAGELIJKS, Carbon::parse('2026-06-10 12:00:00'));

        // Eén verwachting, en dat is geen slordigheid: `expectsOutputToContain`
        // legt per verwachting een Mockery-expectation op `doWrite`, en één
        // schrijfactie wordt maar door de éérste passende opgegeten. Twee
        // verwachtingen die op dezelfde regel slaan laat de tweede dus altijd
        // falen. Dat het om deze regel gaat, borgt `alleenGatVoor()`: er is maar
        // één commando met een gat.
        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain(self::DAGELIJKS.' — 5 momenten gemist')
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    public function test_de_let_op_tekst_komt_mee_bij_vervallen_accounts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $this->alleenGatVoor('isms:verval-gebruikersaccounts', Carbon::parse('2026-05-01 12:00:00'));

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('accounts bleven actief ná hun vervaldatum')
            ->assertSuccessful();
    }

    // ── 7. Onherstelbaar ─────────────────────────────────────────────────────

    public function test_een_gemist_jaarmoment_levert_een_taak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00'));
        $this->alleenGatVoor(self::JAARLIJKS, Carbon::parse('2025-12-20 12:00:00'));

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('onherstelbaar')
            ->assertSuccessful();

        $taken = Taak::where('soort', 'bewaking-onderbroken')->get();

        $this->assertCount(1, $taken);
        $this->assertStringContainsString(self::JAARLIJKS, $taken->first()->titel);
        $this->assertSame('management-review-verbetercyclus', $taken->first()->gekoppeld_blok_naam);
    }

    // ── 8. De KPI-nuance ─────────────────────────────────────────────────────

    public function test_een_gemiste_maandmeting_treft_alleen_de_toestand_kpis(): void
    {
        $this->seed(KpiDefinitieSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $this->alleenGatVoor(self::MAANDELIJKS, Carbon::parse('2026-08-15 12:00:00'));

        $this->artisan('isms:controleer-hartslag')->assertSuccessful();

        [$toestand, $gebeurtenis] = $this->kpisNaarSoort();

        $this->assertNotEmpty($toestand, 'Zonder toestand-KPI\'s toetst deze test niets.');
        $this->assertNotEmpty($gebeurtenis, 'Zonder gebeurtenis-KPI\'s toetst deze test niets.');

        foreach ($toestand as $definitie) {
            $this->assertTrue(
                $this->heeftMeetpuntTaak($definitie),
                "Toestand-KPI {$definitie->sleutel} hoort een taak te krijgen: de stand van die maand is weg.",
            );
        }

        foreach ($gebeurtenis as $definitie) {
            $this->assertFalse(
                $this->heeftMeetpuntTaak($definitie),
                "Gebeurtenis-KPI {$definitie->sleutel} wordt opgevangen door een langer venster (12g §3).",
            );
        }
    }

    public function test_twee_keer_draaien_over_hetzelfde_gat_levert_een_taak(): void
    {
        $this->seed(KpiDefinitieSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $this->alleenGatVoor(self::MAANDELIJKS, Carbon::parse('2026-08-15 12:00:00'));

        $this->artisan('isms:controleer-hartslag')->assertSuccessful();
        $eerste = $this->bewakingstaken();

        $this->artisan('isms:controleer-hartslag')->assertSuccessful();

        $this->assertGreaterThan(0, $eerste);
        $this->assertSame($eerste, $this->bewakingstaken());
    }

    // ── 10/11/12. Vlaggen en randgevallen ────────────────────────────────────

    public function test_geen_taken_rapporteert_wel_maar_maakt_niets_aan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00'));
        $this->alleenGatVoor(self::JAARLIJKS, Carbon::parse('2025-12-20 12:00:00'));

        $this->artisan('isms:controleer-hartslag --geen-taken')
            ->expectsOutputToContain(self::JAARLIJKS)
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    public function test_een_commando_buiten_de_configuratie_geldt_als_onherstelbaar(): void
    {
        // De veilige kant: wie een gepland commando toevoegt zonder de klasse te
        // bepalen, hoort ruis te krijgen en geen stilte (00m §5).
        config(['hartslag.commandos' => []]);

        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $this->alleenGatVoor(self::DAGELIJKS, Carbon::parse('2026-06-10 12:00:00'));

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('onherstelbaar')
            ->assertSuccessful();

        $this->assertSame(1, Taak::where('soort', 'bewaking-onderbroken')->count());
    }

    public function test_een_hartslag_in_de_toekomst_levert_een_waarschuwing_en_geen_taak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $this->alleenGatVoor(self::JAARLIJKS, Carbon::parse('2027-06-15 12:00:00'));

        // De bewaking heeft gedraaid; alleen de klok liegt (00m §9.3). Dus geen
        // gat, geen taak — en zeker geen taak op een negatief tijdsverschil.
        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('geen gaten')
            ->assertSuccessful();

        $this->assertSame(0, $this->bewakingstaken());
    }

    public function test_een_heel_lang_gat_wordt_afgekapt(): void
    {
        config(['hartslag.maximum_momenten' => 5]);

        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $this->alleenGatVoor(self::DAGELIJKS, Carbon::parse('2025-06-15 12:00:00'));

        $this->artisan('isms:controleer-hartslag')
            ->expectsOutputToContain('meer dan 5 momenten')
            ->assertSuccessful();
    }

    // ── 13. Opruimen ─────────────────────────────────────────────────────────

    public function test_opruimen_spaart_altijd_de_laatste_regel_per_sleutel(): void
    {
        // Dit is het belangrijkste punt van de dertien: gaat dit mis, dan wist
        // het opruimen het ankerpunt waar de detectie op leunt — en dan
        // verdwijnt het gat samen met het bewijs ervan (00m §1.1).
        $this->seed(SysteemhartslagSeeder::class);

        $oud = Carbon::now()->subDays(500);

        // Sleutel A: een oude én een recente regel. De oude mag weg.
        Systeemhartslag::create([
            'taak_sleutel' => 'isms:toets-a', 'gedraaid_op' => $oud, 'resultaat' => 'gelukt',
        ]);
        Systeemhartslag::create([
            'taak_sleutel' => 'isms:toets-a', 'gedraaid_op' => Carbon::now()->subDays(10), 'resultaat' => 'gelukt',
        ]);

        // Sleutel B: alléén een oude regel. Die is het ankerpunt en blijft.
        $anker = Systeemhartslag::create([
            'taak_sleutel' => 'isms:toets-b', 'gedraaid_op' => $oud, 'resultaat' => 'gelukt',
        ]);

        $this->artisan('isms:controleer-hartslag')->assertSuccessful();

        $this->assertSame(1, Systeemhartslag::where('taak_sleutel', 'isms:toets-a')->count());
        $this->assertDatabaseHas('systeemhartslag', ['id' => $anker->id]);
    }

    // ── Hulpjes voor de KPI-test ─────────────────────────────────────────────

    /** @return array{0: list<KpiDefinitie>, 1: list<KpiDefinitie>} */
    private function kpisNaarSoort(): array
    {
        $berekend = KpiDefinitie::where('actief', true)->get()
            ->filter(fn (KpiDefinitie $d) => $d->meetbron !== null && Meetbronnen::bestaat($d->meetbron));

        return [
            $berekend->reject(fn (KpiDefinitie $d) => Meetbronnen::isGebeurtenis($d->meetbron))->values()->all(),
            $berekend->filter(fn (KpiDefinitie $d) => Meetbronnen::isGebeurtenis($d->meetbron))->values()->all(),
        ];
    }

    private function heeftMeetpuntTaak(KpiDefinitie $definitie): bool
    {
        return Taak::where('soort', 'kpi-meetpunt-gemist')
            ->where('gekoppeld_entiteit_type', $definitie->getMorphClass())
            ->where('gekoppeld_entiteit_id', $definitie->id)
            ->exists();
    }
}
