<?php

namespace Tests\Feature\Concerns;

use App\Support\ToetsBestanden;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Vult een demoscenario één keer per testklasse en laat elke test daarop
 * draaien (implementatie/00f §2).
 *
 * `RefreshDatabase` migreert één keer per proces en zet elke test in een
 * transactie die terugrolt. Vullen in `setUp()` betekende dus per test 23
 * maanden simulatie: 85 seconden voor één bestand. De haken die daarvoor bedoeld
 * lijken helpen niet — `afterRefreshingDatabase()` draait alsnog per test, en
 * `migrateDatabases()` vuurt alleen voor de eerste testklasse in het proces.
 *
 * Daarom: uit de transactie stappen, vullen, en er weer in. De vulling overleeft
 * daarmee de rollback na elke test; de test zelf draait in zijn eigen transactie
 * erbovenop.
 *
 * **De prijs staat in `Tests\TestCase`:** de gevulde tabellen blijven ook ná de
 * klasse staan. Die bewaking herkent de klassen met deze trait en faalt
 * luidruchtig als een andere klasse erna begint, in plaats van stilletjes op
 * demogegevens te toetsen. Twee klassen met deze trait na elkaar gaan wél goed:
 * de vulling begint met het legen van de database.
 */
trait VultDemoEenKeer
{
    /** Eén vulling per klasse: een static in een trait is per gebruikende klasse. */
    private static bool $gevuld = false;

    /**
     * Zet het normprofiel waarvoor de fixtures geschreven zijn.
     *
     * Letterlijk in de testklasse en niet als parameter hier: `SuiteDekkingTest`
     * herkent een profieltest aan de `config()->set` in het bestand, en eist dan
     * de groep van dat profiel.
     */
    abstract protected function zetDemoProfiel(): void;

    protected function vulDemoEenKeer(): void
    {
        // Geen databasestaat: dit hoort per test schoon te zijn. De bewijs- en
        // toetsenschijf omdat de vulling ze leegmaakt en vult, en `local` omdat
        // de inloggegevens daar landen — zonder nep-schijf overschrijft de suite
        // het bestand in de echte storage/ van wie hem draait.
        Storage::fake('bewijs');
        Storage::fake(ToetsBestanden::DISK);
        Storage::fake('local');

        // Elke test en niet alleen de eerste: `Tests\TestCase` zet het profiel
        // per test terug op `ISMS_NORM`, en de gevulde gegevens horen bij dit
        // profiel.
        $this->zetDemoProfiel();

        if (self::$gevuld) {
            return;
        }

        $this->app['env'] = 'local';

        // De vulling gebruikt `truncate()` en geen `migrate:fresh`; op sqlite
        // compileert dat naar DELETE FROM en blijft het binnen één verbinding.
        // Op MySQL zou TRUNCATE een impliciete commit geven — de suite draait op
        // sqlite, maar dat is geen vanzelfsprekendheid.
        DB::rollBack();

        // Zonder de opgevangen uitvoer meldt een mislukte vulling alleen "exit
        // code 1", en dan begint het zoeken pas. De motor zegt zelf precies bij
        // welke maand en welke gebeurtenis hij is gestopt.
        $exit = $this->withoutMockingConsoleOutput()
            ->artisan('isms:demo-vul', ['--stil' => true]);

        self::$gevuld = true;
        DB::beginTransaction();

        $this->assertSame(0, $exit, "Het vullen is mislukt:\n".Artisan::output());
    }
}
