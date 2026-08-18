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
 * Vult het ISMS met het demo-scenario van FruitBV (`saasdemo/scenario.md`).
 *
 * **Begint met het volledig legen van de database.** De enige beveiliging is een
 * omgevingsblokkade op `local` en `demo`: geen bevestigingsvraag en geen
 * `--force`, zodat herhaald vullen tijdens het ontwikkelen niet in de weg zit.
 * Dat is een bewuste keuze uit `saasdemo/scenario.md` §11.6 — draai dit nooit op
 * een omgeving met echte data.
 *
 * **De simulatiemotor is ISO-only** (nen7510-opzet.md §4.8). Op elk ander
 * normprofiel weigert dit commando; zie de toelichting bij die controle.
 *
 * Het commando is dun: alles wat een beslissing neemt staat in
 * `App\Support\Demo`, waar het te testen is.
 */
class DemoVul extends Command
{
    protected $signature = 'isms:demo-vul
        {--fixtures= : Map met de fixtures (standaard ../saasdemo/data)}
        {--stil : Toon alleen de samenvatting}
        {--ontgrendel : Hef een blijven hangen vergrendeling van een afgebroken vulling op}';

    /** Verhindert dat twee vullingen elkaars tabellen leegmaken. */
    private const SLOT = 'isms:demo-vul';

    /** Waar de gegenereerde inloggegevens terechtkomen; zie schrijfInloggegevens(). */
    private const SCHIJF = 'local';

    private const BESTAND = 'demo-inloggegevens.txt';

    protected $description = 'Vult het ISMS met het FruitBV-demoscenario (WIST EERST DE HELE DATABASE; alleen local/demo)';

    public function handle(): int
    {
        if (! app()->environment('local', 'demo')) {
            $this->error('isms:demo-vul draait alleen in local of demo — dit commando wist de hele database.');

            return self::FAILURE;
        }

        // Expliciet weigeren en niet stilzwijgend aannemen (nen7510-opzet.md
        // §4.8). Het scenario is geschreven voor de controlset van ISO 27001 en
        // vult alleen die; wat een ander profiel daarnaast kent blijft leeg. Een
        // demonstratie die de verkeerde norm laat zien is erger dan geen
        // demonstratie.
        //
        // **`is()` en niet `heeft()`, anders dan 00k §1 voorschreef.** Dat
        // voorschrift ging ervan uit dat een derde profiel een eigen controlset
        // zou meebrengen; de BIO doet dat juist niet — dezelfde 93
        // beheersmaatregelen, met 118 overheidsmaatregelen eronder. Een controle
        // op capaciteiten had die dus doorgelaten. Voor een commando dat begint
        // met het wissen van de hele database is de veilige kant: alles weigeren
        // wat niet het profiel is waarvoor het scenario geschreven is. Dan valt
        // een vierde profiel er vanzelf ook buiten. Gelijk aan wat de
        // Docker-entrypoint al deed bij `ISMS_DEMO=ja` (00n §0.2).
        if (! Normprofiel::is('iso27001')) {
            // Alleen de geldende verplichtingen tellen mee: een vervallen of
            // verplaatst nummer blijft staan als referentie en is niets wat de
            // demo had moeten beoordelen.
            $telling = Maatregel::count().' beheersmaatregelen';

            if (Normprofiel::heeft('overheidsmaatregelen')) {
                $telling .= ' plus '.Overheidsmaatregel::where('status', 'geldend')->count()
                    .' overheidsmaatregelen';
            }

            $this->error('isms:demo-vul draait niet op een '.Normprofiel::label('naam_kort').'-installatie.');
            $this->line('Het FruitBV-scenario is geschreven voor de 93 maatregelen van ISO 27001 en vult');
            $this->line('alleen die. Hier telt de norm '.$telling.';');
            $this->line('wat het scenario niet aanraakt blijft onbeoordeeld — de demo zou een compleet');
            $this->line('ogend ISMS met de verkeerde norm tonen.');
            $this->line('Zet ISMS_NORM=iso27001 op een aparte demo-installatie.');

            return self::FAILURE;
        }

        $map = $this->option('fixtures') ?: base_path('../saasdemo/data');

        if (! is_dir($map)) {
            $this->error("Fixtures-map niet gevonden: {$map}");

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
                Fixtures::uit($map),
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
