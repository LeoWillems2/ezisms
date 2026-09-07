<?php

namespace Tests\Feature;

use App\Support\Enumwaarden;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\VangendeBlueprint;
use Tests\TestCase;

/**
 * Elke enum-kolom heeft een waardenlijst in de code (implementatie/00s §10).
 *
 * Op MySQL bewaakt de database zelf wat er in zo'n kolom mag; op SQLite levert
 * dezelfde migratie een kale `varchar` op. Deze toets bewaakt dat de codekant de
 * verzameling kent, zodat de garantie op beide drivers geldt en een nieuwe
 * enum-kolom niet stilzwijgend zonder controle blijft.
 */
class EnumdekkingTest extends TestCase
{
    /** @return array<string, list<string>> */
    private function declaratiesUitDeMigraties(): array
    {
        VangendeBlueprint::$enums = [];

        // Herbinden en niet `Schema::blueprintResolver()`: `db.schema` is een
        // `bind` en de migrator laat de facade onderweg opnieuw oplossen. Een
        // resolver die op het eerste exemplaar staat, is daarna weg — en dan
        // vangt deze toets nul kolommen en slaagt hij ten onrechte.
        $this->app->bind('db.schema', function ($app) {
            $bouwer = $app['db']->connection()->getSchemaBuilder();
            $bouwer->blueprintResolver(fn ($verbinding, $tabel, $terugroep) => new VangendeBlueprint($verbinding, $tabel, $terugroep));

            return $bouwer;
        });

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertNotEmpty(
            VangendeBlueprint::$enums,
            'Er is geen enkele enum-kolom gevangen; de recorder hangt er niet meer tussen.'
        );

        return VangendeBlueprint::$enums;
    }

    public function test_elke_enumkolom_uit_de_migraties_staat_in_het_register(): void
    {
        $register = Enumwaarden::alle();
        $ontbreekt = array_diff(array_keys($this->declaratiesUitDeMigraties()), array_keys($register));

        $this->assertSame([], array_values($ontbreekt), sprintf(
            "Deze enum-kolommen hebben geen waardenlijst in App\\Support\\Enumwaarden:\n  %s\n"
            .'Op SQLite is die kolom daarmee een tekstveld zonder enige controle (00s §10).',
            implode("\n  ", $ontbreekt)
        ));
    }

    public function test_het_register_bevat_geen_kolommen_die_niet_bestaan(): void
    {
        $overbodig = array_diff(array_keys(Enumwaarden::alle()), array_keys($this->declaratiesUitDeMigraties()));

        $this->assertSame([], array_values($overbodig), sprintf(
            "Deze regels in App\\Support\\Enumwaarden horen bij geen enkele enum-kolom:\n  %s",
            implode("\n  ", $overbodig)
        ));
    }

    public function test_de_waardenlijsten_komen_overeen(): void
    {
        $register = Enumwaarden::alle();

        foreach ($this->declaratiesUitDeMigraties() as $sleutel => $gedeclareerd) {
            if (! isset($register[$sleutel])) {
                continue; // gemeld door de toets hierboven
            }

            $toegestaan = $register[$sleutel];

            // Kolommen die een latere migratie met ruwe SQL verbreedde ziet de
            // recorder niet; daar hoort het register méér te kennen, niet minder.
            if (isset(Enumwaarden::RUWE_SQL_UITBREIDING[$sleutel])) {
                $this->assertSame([], array_values(array_diff($gedeclareerd, $toegestaan)), sprintf(
                    '%s: de migratie declareert waarden die het register niet kent (%s).',
                    $sleutel,
                    Enumwaarden::RUWE_SQL_UITBREIDING[$sleutel]
                ));

                continue;
            }

            sort($gedeclareerd);
            sort($toegestaan);

            $this->assertSame($toegestaan, $gedeclareerd, sprintf(
                "%s: de waardenlijst in Enumwaarden wijkt af van de migratie.\n"
                .'Wijzigt u een enum, wijzig dan allebei — anders bewaakt MySQL iets anders dan SQLite.',
                $sleutel
            ));
        }
    }
}
