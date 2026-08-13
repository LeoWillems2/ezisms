<?php

namespace App\Support\Demo;

use App\Models\Auditprogramma;
use App\Models\Gebruiker;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De lus: maand voor maand, gebeurtenis voor gebeurtenis.
 *
 * Zie `saasdemo/simulatiemotor.md` voor het ontwerp. De volgorde binnen een
 * maand is die van `tijdlijn.json`; dat bestand is met de afhankelijkheden
 * geschreven (in M4 staat de SoA-golf vóór de nulmeting, want de audit-universe
 * groeit mee met de SoA).
 */
final class Simulatie
{
    /** @var array<string, callable> gebeurtenistype => handler */
    private array $handlers = [];

    /** @var list<callable> handelingen aan het einde van elke maand */
    private array $maandafsluiters = [];

    /** @var array<string, string> gebruikerssleutel => gegenereerd wachtwoord */
    private array $wachtwoorden = [];

    private int $gebeurtenissen = 0;

    public function __construct(
        private readonly Fixtures $fixtures,
        private readonly Klok $klok,
        private readonly Bewijsgenerator $bewijs,
        private readonly Closure $melden,
    ) {}

    public function registreer(array $handlers): void
    {
        $this->handlers = [...$this->handlers, ...$handlers];
    }

    /**
     * Handelingen die elke maand plaatsvinden zonder dat de tijdlijn ze noemt —
     * het gewone werk. Zonder deze haak zou elke maandelijkse handeling als
     * gebeurtenis in `tijdlijn.json` moeten staan, en dat bestand beschrijft
     * juist wat er bijzonder is.
     *
     * @param  list<callable>  $afsluiters
     */
    public function naElkeMaand(array $afsluiters): void
    {
        $this->maandafsluiters = [...$this->maandafsluiters, ...$afsluiters];
    }

    public function fixtures(): Fixtures
    {
        return $this->fixtures;
    }

    public function klok(): Klok
    {
        return $this->klok;
    }

    public function bewijs(): Bewijsgenerator
    {
        return $this->bewijs;
    }

    public function meld(string $regel, bool $nieuweRegel = true): void
    {
        ($this->melden)($regel, $nieuweRegel);
    }

    /** Een gebruiker uit de fixtures; harde fout als hij nog niet bestaat. */
    public function gebruiker(string $sleutel): Gebruiker
    {
        $model = $this->fixtures->model($sleutel);

        return $model instanceof Gebruiker
            ? $model
            : throw DemoFixtureFout::bij('tijdlijn', "'{$sleutel}' is geen gebruiker");
    }

    public function onthoudWachtwoord(string $sleutel, string $wachtwoord): void
    {
        $this->wachtwoorden[$sleutel] = $wachtwoord;
    }

    /** @return array<string, string> */
    public function wachtwoorden(): array
    {
        return $this->wachtwoorden;
    }

    public function aantalGebeurtenissen(): int
    {
        return $this->gebeurtenissen;
    }

    // --- De run ------------------------------------------------------------

    public function voerUit(): void
    {
        try {
            $this->leegDatabase();

            foreach ($this->fixtures->bestand('tijdlijn')['maanden'] as $maand) {
                $this->verwerkMaand($maand);
            }
        } finally {
            // Verplicht: een proces dat met een verzette klok eindigt, schrijft
            // de rest van zijn werk in het verleden.
            Klok::herstel();
        }
    }

    private function verwerkMaand(array $maand): void
    {
        $nummer = $maand['maand'];
        $datum = $this->klok->beginMaand($nummer);
        $begin = microtime(true);

        $this->meld(sprintf(
            'M%-2d %s — %s',
            $nummer,
            Auditprogramma::maandJaar(Carbon::instance($datum)),
            $maand['kop'] ?? '',
        ));

        foreach ($maand['gebeurtenissen'] as $gebeurtenis) {
            $this->klok->volgendeGebeurtenis($nummer);
            $this->verwerkGebeurtenis($gebeurtenis, $nummer);
        }

        $this->terugkerend($nummer);

        $this->meld(sprintf('     %s in %s', 'maand afgesloten', $this->duur($begin)));
    }

    private function verwerkGebeurtenis(array $gebeurtenis, int $maand): void
    {
        $type = $gebeurtenis['type'] ?? throw DemoFixtureFout::bij("M{$maand}", 'gebeurtenis zonder type');

        // 'eindstand' is geen handeling maar een controlelijst voor de tests.
        if ($type === 'eindstand') {
            return;
        }

        $handler = $this->handlers[$type]
            ?? throw DemoFixtureFout::bij("M{$maand}", "geen handler voor gebeurtenistype '{$type}'");

        // Per gebeurtenis melden, niet alleen per maand: een vulling duurt
        // minuten, en zonder regelmatig teken van leven is niet te zien of het
        // commando werkt of vastloopt.
        $begin = microtime(true);
        $this->meld(sprintf('   · %-34s', $type), nieuweRegel: false);

        // Eén transactie per gebeurtenis. Niet om de consistentie — die is hier
        // niet in gevaar — maar om de snelheid: MySQL doet per commit een
        // schrijfbevestiging naar schijf, en een gebeurtenis als de SoA-golf zet
        // honderden losse rijen weg. Gebundeld scheelt dat een orde van grootte.
        DB::transaction(fn () => $handler($gebeurtenis, $maand, $this));
        $this->gebeurtenissen++;

        $this->meld($this->duur($begin));
    }

    private function duur(float $begin): string
    {
        return sprintf('%.1fs', microtime(true) - $begin);
    }

    /**
     * De terugkerende commando's aan het einde van de maand. Volgorde is
     * betekenisvol: verlopen ná genereren (anders verloopt een taak die net is
     * aangemaakt niet), meten als laatste (zodat de meting de stand ná de maand
     * vastlegt).
     */
    private function terugkerend(int $maand): void
    {
        foreach ($this->klok->jaargrenzenIn($maand) as $oudjaar) {
            $this->klok->zet($oudjaar);
            $this->meld('   · jaargrens: restrisico vastleggen         ', nieuweRegel: false);
            $begin = microtime(true);
            Artisan::call('isms:leg-restrisico-vast');
            $this->meld($this->duur($begin));
        }

        $this->klok->eindeMaand($maand);

        $begin = microtime(true);
        $this->meld('   · maandafsluiting                             ', nieuweRegel: false);

        DB::transaction(function () use ($maand) {
            Artisan::call('isms:genereer-taken');

            // Tussen genereren en verlopen: het gewone werk van de maand. Draait
            // het afwerken ná `verloop-taken`, dan is alles wat deze maand werd
            // afgerond eerst nog even verlopen geweest.
            foreach ($this->maandafsluiters as $afsluiter) {
                $afsluiter($maand, $this);
            }

            Artisan::call('isms:verloop-taken');
            Artisan::call('isms:meet-kpis');
        });

        $this->meld($this->duur($begin));
    }

    // --- De wipe -----------------------------------------------------------

    /**
     * Leegt de database en zet de referentiedata terug.
     *
     * Bewust géén `migrate:fresh`: dat kost op een MySQL-ontwikkelmachine
     * minuten aan DDL en levert niets op, want het schema verandert niet tussen
     * twee vullingen. Schemawijzigingen blijven de verantwoordelijkheid van
     * `migrate` — een demovulling is geen migratiegereedschap.
     */
    private function leegDatabase(): void
    {
        $begin = microtime(true);
        $this->meld('Database legen en referentiedata terugzetten…');

        $this->bewijs->ruimOp();

        $verbinding = Schema::getConnection();
        // Uitdrukkelijk op het eigen schema begrensd. Een databasegebruiker mag
        // meer schema's zien dan de database waarop hij verbonden is — een
        // ontwikkelmachine met een tweede ISMS-database naast deze, bijvoorbeeld
        // — en dan geeft een ongebonden `getTableListing()` ook díe tabellen
        // terug. Onvoorvoegd getruncate't worden dat stille misgrepen: namen die
        // in beide schema's staan gaan twee keer langs dezelfde tabel, en een
        // naam die alleen elders bestaat laat de vulling halverwege klappen op
        // een lege database.
        $tabellen = collect(Schema::getTableListing(
            schema: Schema::getCurrentSchemaName(),
            schemaQualified: false,
        ))
            // `cache`/`cache_locks` zijn geen demodata maar infrastructuur — en
            // in `cache_locks` staat de vergrendeling van dit commando zelf.
            ->reject(fn (string $t) => in_array($t, ['migrations', 'cache', 'cache_locks'], true));

        foreach ($this->refertiechecksUit($verbinding->getDriverName()) as $statement) {
            $verbinding->statement($statement);
        }

        try {
            foreach ($tabellen as $tabel) {
                DB::table($tabel)->truncate();
            }
        } finally {
            $verbinding->statement(match ($verbinding->getDriverName()) {
                'sqlite' => 'PRAGMA foreign_keys = ON',
                default => 'SET FOREIGN_KEY_CHECKS=1',
            });
        }

        // Ook hier één commit in plaats van duizenden: de referentiedata is met
        // afstand het duurste onderdeel van een vulling.
        DB::transaction(fn () => Artisan::call('db:seed', ['--force' => true]));

        $this->meld(sprintf(
            'Database geleegd (%d tabellen); referentiedata opnieuw geseed in %s.',
            $tabellen->count(),
            $this->duur($begin),
        ));
        $this->meld('');
    }

    /**
     * De statements die de referentiechecks tijdelijk opzijzetten, zodat de
     * tabellen in willekeurige volgorde geleegd kunnen worden.
     *
     * SQLite krijgt er twee. `PRAGMA foreign_keys` is namelijk een no-op zodra er
     * een transactie loopt — en in de testsuite loopt die altijd, want
     * `RefreshDatabase` zet er een om elke test heen. `defer_foreign_keys` werkt
     * daar wél: het stelt de controle uit tot het einde van de transactie, en
     * tegen die tijd is alles leeg en klopt alles weer. Buiten een transactie
     * doet de eerste het werk, binnen een transactie de tweede.
     *
     * @return list<string>
     */
    private function refertiechecksUit(string $driver): array
    {
        return $driver === 'sqlite'
            ? ['PRAGMA foreign_keys = OFF', 'PRAGMA defer_foreign_keys = ON']
            : ['SET FOREIGN_KEY_CHECKS=0'];
    }
}
