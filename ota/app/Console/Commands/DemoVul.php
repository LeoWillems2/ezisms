<?php

namespace App\Console\Commands;

use App\Models\Maatregel;
use App\Models\Overheidsmaatregel;
use App\Support\Demo\Bewijsgenerator;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Fixtures;
use App\Support\Demo\Handlers;
use App\Support\Demo\Klok;
use App\Support\Demo\Simulatie;
use App\Support\Normprofiel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Vult het ISMS met het demoscenario dat bij het normprofiel hoort: FruitBV
 * voor ISO 27001 (`saasdemo/scenario.md`), ZorgZeker voor NEN 7510
 * (`zorgdemo/scenario.md`).
 *
 * **Begint met het volledig legen van de database.** De enige beveiliging is een
 * omgevingsblokkade op `local` en `demo`: geen bevestigingsvraag en geen
 * `--force`, zodat herhaald vullen tijdens het ontwikkelen niet in de weg zit.
 * Dat is een bewuste keuze uit `saasdemo/scenario.md` §11.6 — draai dit nooit op
 * een omgeving met echte data.
 *
 * **Eén scenario per profiel.** Op een profiel zonder scenario (de BIO) weigert
 * dit commando, en fixtures van het ene profiel vullen het andere niet; zie de
 * toelichting bij die controles.
 *
 * Het commando is dun: alles wat een beslissing neemt staat in
 * `App\Support\Demo`, waar het te testen is.
 */
class DemoVul extends Command
{
    protected $signature = 'isms:demo-vul
        {--fixtures= : Map met de fixtures (standaard het scenario van het normprofiel)}
        {--stil : Toon alleen de samenvatting}
        {--ontgrendel : Hef een blijven hangen vergrendeling van een afgebroken vulling op}';

    /** Verhindert dat twee vullingen elkaars tabellen leegmaken. */
    private const SLOT = 'isms:demo-vul';

    /** Waar de gegenereerde inloggegevens terechtkomen; zie schrijfInloggegevens(). */
    private const SCHIJF = 'local';

    private const BESTAND = 'demo-inloggegevens.txt';

    /**
     * Het scenario per normprofiel, met de standaardmap van de fixtures
     * (relatief aan de applicatie). Een profiel dat hier niet staat, heeft geen
     * demo.
     *
     * @var array<string, array{naam: string, map: string}>
     */
    private const SCENARIO = [
        'iso27001' => ['naam' => 'FruitBV', 'map' => '../saasdemo/data'],
        'nen7510' => ['naam' => 'ZorgZeker', 'map' => '../zorgdemo/data'],
    ];

    protected $description = 'Vult het ISMS met het demoscenario van het normprofiel (WIST EERST DE HELE DATABASE; alleen local/demo)';

    public function handle(): int
    {
        if (! app()->environment('local', 'demo')) {
            $this->error('isms:demo-vul draait alleen in local of demo — dit commando wist de hele database.');

            return self::FAILURE;
        }

        // Welk scenario bij welk profiel hoort. Een profiel zonder scenario
        // weigert expliciet en neemt niet stilzwijgend een ander aan: een
        // scenario beoordeelt de maatregelen van één norm, en wat een ander
        // profiel daarnaast kent blijft dan leeg. Een demonstratie die de
        // verkeerde norm laat zien is erger dan geen demonstratie.
        //
        // **Op het profiel en niet op een capaciteit** (00k §1 schreef het
        // laatste voor). De BIO heeft dezelfde 93 beheersmaatregelen als
        // ISO 27001, met 118 overheidsmaatregelen eronder; een controle op
        // capaciteiten had het FruitBV-scenario daar doorgelaten. Voor een
        // commando dat begint met het wissen van de hele database is de veilige
        // kant: alleen de profielen met een eigen scenario.
        $scenario = self::SCENARIO[Normprofiel::actief()] ?? null;

        if ($scenario === null) {
            $this->weigerProfiel();

            return self::FAILURE;
        }

        $map = $this->option('fixtures') ?: base_path($scenario['map']);

        if (! is_dir($map)) {
            $this->error("Fixtures-map niet gevonden: {$map}");

            return self::FAILURE;
        }

        // Ingelezen vóór het legen: fixtures die niet kloppen, of die voor een
        // ander profiel geschreven zijn, laten de database zoals hij was.
        try {
            $fixtures = Fixtures::uit($map);
        } catch (DemoFixtureFout $e) {
            $this->error('Fixtures ongeldig: '.$e->getMessage());

            return self::FAILURE;
        }

        // `--fixtures` kan naar een scenario van een ander profiel wijzen; de
        // standaardmap hierboven kan dat niet, maar de fixtures zeggen zelf
        // waarvoor ze geschreven zijn en dat is de controle die telt.
        if (! Normprofiel::is($fixtures->normprofiel())) {
            $this->error("Deze fixtures zijn geschreven voor het profiel '{$fixtures->normprofiel()}'; "
                ."deze installatie draait op '".Normprofiel::actief()."'.");
            $this->line('Een scenario beoordeelt de maatregelen van één norm. Kies de fixtures die bij dit profiel horen.');

            return self::FAILURE;
        }

        // Twee vullingen tegelijk op dezelfde database leveren een halve demo op
        // die er heel plausibel uitziet: de een leegt de tabellen waar de ander
        // net in schreef. Dat kost meer tijd om te ontrafelen dan deze
        // vergrendeling waard is.
        $slot = Cache::lock(self::SLOT, 3600);

        if ($this->option('ontgrendel')) {
            $slot->forceRelease();
            $this->info('Vergrendeling opgeheven.');
        }

        if (! $slot->get()) {
            $this->error('Er draait al een isms:demo-vul op deze installatie.');
            $this->line('Wacht tot die klaar is. Is het proces afgebroken en blijft de vergrendeling hangen,');
            $this->line('draai dan: php artisan isms:demo-vul --ontgrendel');

            return self::FAILURE;
        }

        // Geen echte mail. De motor doorloopt 23 maanden aan escalaties en
        // incidentmeldingen, en `NotificatieDispatcher` verstuurt synchroon: dat
        // levert tientallen SMTP-verbindingen op naar adressen die van een echt
        // domein zijn maar niet van echte mensen. Bovendien blokkeert elke
        // verbinding de vulling — een onbereikbare of trage mailserver laat het
        // commando minutenlang stilstaan zonder dat er iets te zien is.
        config(['mail.default' => 'array']);

        $this->toonMigratiestand();

        $start = microtime(true);

        try {
            $simulatie = new Simulatie(
                $fixtures,
                new Klok,
                new Bewijsgenerator,
                function (string $regel, bool $nieuweRegel = true) {
                    if ($this->option('stil')) {
                        return;
                    }

                    // Zonder nieuwe regel groeit de regel aan met de duur, zodat
                    // je ziet wáár het commando staat te wachten in plaats van
                    // alleen dat het nog leeft.
                    $nieuweRegel ? $this->line($regel) : $this->output->write($regel);
                },
            );

            $handlers = new Handlers;
            $simulatie->registreer($handlers->perType());
            $simulatie->naElkeMaand($handlers->maandafsluiters());
            $simulatie->voerUit();
        } catch (DemoFixtureFout $e) {
            $this->newLine(2);
            $this->error('Vullen afgebroken: '.$e->getMessage());
            $this->line('De database staat nu halverwege. Draai het commando opnieuw zodra de fixtures kloppen.');

            return self::FAILURE;
        } finally {
            $slot->release();
        }

        $this->samenvatting($simulatie, microtime(true) - $start);

        return self::SUCCESS;
    }

    /** Geen scenario voor dit profiel: zeg waarom, en wat er wél kan. */
    private function weigerProfiel(): void
    {
        // Alleen de geldende verplichtingen tellen mee: een vervallen of
        // verplaatst nummer blijft staan als referentie en is niets wat de demo
        // had moeten beoordelen.
        $telling = Maatregel::count().' beheersmaatregelen';

        if (Normprofiel::heeft('overheidsmaatregelen')) {
            $telling .= ' plus '.Overheidsmaatregel::where('status', 'geldend')->count()
                .' overheidsmaatregelen';
        }

        $this->error('isms:demo-vul heeft geen scenario voor een '.Normprofiel::label('naam_kort').'-installatie.');
        $this->line('Een scenario is geschreven voor de maatregelen van één norm en vult alleen die.');
        $this->line('Hier telt de norm '.$telling.'; wat geen scenario aanraakt blijft');
        $this->line('onbeoordeeld — de demo zou een compleet ogend ISMS met de verkeerde norm tonen.');
        $this->line('Scenario\'s zijn er voor: '.collect(self::SCENARIO)
            ->map(fn (array $s, string $profiel) => "{$s['naam']} (ISMS_NORM={$profiel})")->implode(', ').'.');
    }

    /**
     * Een verouderd schema levert halverwege een onbegrijpelijke fout op. Beter
     * hier melden welke stand is aangetroffen dan daar raden.
     */
    private function toonMigratiestand(): void
    {
        $laatste = DB::table('migrations')->orderByDesc('id')->value('migration');
        $this->line("Migratiestand: {$laatste}");
    }

    private function samenvatting(Simulatie $simulatie, float $seconden): void
    {
        $this->newLine();
        $this->info(sprintf(
            'Demo gevuld: %d gebeurtenissen over %d maanden, %d bewijsstukken, in %.1f seconden.',
            $simulatie->aantalGebeurtenissen(),
            Klok::AANTAL_MAANDEN + 1,
            $simulatie->bewijs()->aantal(),
            $seconden,
        ));
        $this->line('Notificaties zijn wel vastgelegd maar niet verstuurd: de mailer stond tijdens het vullen op "array".');

        $wachtwoorden = $simulatie->wachtwoorden();

        if ($wachtwoorden === []) {
            return;
        }

        $pad = $this->schrijfInloggegevens($wachtwoorden);

        $this->newLine();
        $this->line(sprintf('Inloggegevens (%d accounts): %s', count($wachtwoorden), $pad));
        $this->line('Alleen leesbaar voor de eigenaar van het bestand.');
    }

    /**
     * De gegenereerde wachtwoorden gaan naar een bestand en niet naar het
     * scherm. Dit commando wordt ook onbeheerd gedraaid — `deploy.sh` neemt het
     * mee in een uitrol — en dan belandt alles wat het afdrukt in het uitrollog,
     * dat bewaard blijft in `shared/installatie/`. Verzonnen mensen of niet:
     * werkende inloggegevens horen niet in een logbestand dat rondgaat.
     *
     * Het bestand wordt elke vulling overschreven; de wachtwoorden van een
     * vorige vulling gelden toch niet meer.
     */
    private function schrijfInloggegevens(array $wachtwoorden): string
    {
        $regels = ['# Demo-accounts, gegenereerd op '.now()->toDateTimeString()];

        foreach ($wachtwoorden as $email => $wachtwoord) {
            $regels[] = "{$email}\t{$wachtwoord}";
        }

        // De schijf `local` wortelt in storage/app/private en staat dus niet
        // onder public/. `private` als zichtbaarheid levert 0600 op — alleen de
        // gebruiker die de applicatie draait komt erbij.
        Storage::disk(self::SCHIJF)->put(self::BESTAND, implode("\n", $regels)."\n", 'private');

        return Storage::disk(self::SCHIJF)->path(self::BESTAND);
    }
}
