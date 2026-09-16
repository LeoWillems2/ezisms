<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bewaakt dat elke instelbare sleutel in een `env.voorbeeld` ook echt in de
 * container terechtkomt, en omgekeerd (implementatie/01j §17, 16-09-2026).
 *
 * De `environment:`-lijst in de compose-bestanden is uitputtend: er is geen
 * `env_file`, dus een sleutel die daar niet staat, bereikt de applicatie nooit.
 * Dat gaat stil mis. `SESSION_SECURE_COOKIE` stond vanaf de eerste versie in
 * `env.voorbeeld`, kwam nooit langs compose, en was daardoor in het geheel niet
 * instelbaar — zie het commentaar bij die sleutel in `compose.yml` en `tls.md`.
 * Bij het toevoegen van de OIDC-sleutels ging het de andere kant op: compose gaf
 * ze door, maar `env.voorbeeld-image` noemde ze niet, dus wist een beheerder van
 * de image-variant niet dat ze bestonden.
 *
 * Bewust een Unit-test (geen database, geen app-bootstrap): het vergelijkt twee
 * bestanden. En bewust hier en niet in het handmatige dockerprotocol — dit is
 * een eigenschap van de repo, niet van de machine waarop de stack draait.
 */
class ComposeDekkingTest extends TestCase
{
    /**
     * Welk voorbeeldbestand bij welk compose-bestand hoort.
     *
     * @var array<string, string>
     */
    private const PAREN = [
        'env.voorbeeld' => 'compose.yml',
        'env.voorbeeld-image' => 'compose-image.yml',
        'env.voorbeeld-sqlite' => 'compose-sqlite.yml',
    ];

    /**
     * Sleutels die bewust in compose staan maar niet in het voorbeeldbestand.
     *
     * `APP_KEY` wordt bij de eerste start gegenereerd en bewaard in
     * `installatie/app_key`. Hij staat tóch in compose, zodat de entrypoint kan
     * zien dát iemand er een heeft ingevuld en kan weigeren als die afwijkt van
     * de bewaarde; en hij staat bewust níet in het voorbeeldbestand, omdat een
     * nieuwe sleutel iedereen permanent buitensluit (00n §0.5).
     *
     * @var list<string>
     */
    private const ALLEEN_IN_COMPOSE = ['APP_KEY'];

    private function map(): string
    {
        return dirname(__DIR__, 3).'/docker/ezisms';
    }

    /** @return list<string> de sleutels uit een env-bestand, ook de uitgecommentarieerde */
    private function sleutelsUitEnv(string $bestand): array
    {
        preg_match_all('/^#?([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents($bestand), $treffers);

        return $this->uniek($treffers[1]);
    }

    /** @return list<string> de sleutels waarop een compose-bestand interpoleert */
    private function sleutelsUitCompose(string $bestand): array
    {
        preg_match_all('/\$\{([A-Z][A-Z0-9_]*)/', (string) file_get_contents($bestand), $treffers);

        return $this->uniek($treffers[1]);
    }

    /**
     * @param  list<string>  $waarden
     * @return list<string>
     */
    private function uniek(array $waarden): array
    {
        $waarden = array_values(array_unique($waarden));
        sort($waarden);

        return $waarden;
    }

    public function test_elke_sleutel_uit_een_voorbeeldbestand_komt_in_de_container(): void
    {
        foreach (self::PAREN as $env => $compose) {
            $ontbreekt = array_diff(
                $this->sleutelsUitEnv($this->map().'/'.$env),
                $this->sleutelsUitCompose($this->map().'/'.$compose),
            );

            $this->assertSame([], array_values($ontbreekt),
                "Deze sleutels staan in {$env} maar worden door {$compose} niet doorgegeven; "
                .'invullen doet dan niets: '.implode(', ', $ontbreekt));
        }
    }

    public function test_elke_sleutel_uit_compose_staat_in_het_voorbeeldbestand(): void
    {
        foreach (self::PAREN as $env => $compose) {
            $ongenoemd = array_diff(
                $this->sleutelsUitCompose($this->map().'/'.$compose),
                $this->sleutelsUitEnv($this->map().'/'.$env),
                self::ALLEEN_IN_COMPOSE,
            );

            $this->assertSame([], array_values($ongenoemd),
                "Deze sleutels geeft {$compose} door, maar {$env} noemt ze niet; "
                .'een beheerder weet dan niet dat ze bestaan: '.implode(', ', $ongenoemd));
        }
    }

    /**
     * De drie varianten delen bijna alles. Loopt de ene sleutelverzameling uit de
     * pas met de andere, dan is dat meestal een vergeten regel bij het toevoegen
     * van een instelling — precies wat er bij de OIDC-sleutels gebeurde.
     */
    public function test_de_varianten_verschillen_alleen_waar_dat_hoort(): void
    {
        // Wat per variant hoort te verschillen: de MySQL-instellingen en
        // `ISMS_BOOM` (alleen de variant die zelf uit een uitgepakte boom bouwt,
        // kent dat pad), en `EZISMS_VERSIE` (alleen de image-variant, die een
        // tag kiest in plaats van te bouwen).
        $verwachteVerschillen = [
            'MYSQL_DATABASE', 'MYSQL_GEBRUIKER', 'MYSQL_ROOT_PASSWORD', 'MYSQL_WACHTWOORD',
            'ISMS_BOOM', 'EZISMS_VERSIE',
        ];

        $perVariant = [];
        foreach (self::PAREN as $env => $compose) {
            // `array_values`, want `array_diff` houdt de oorspronkelijke sleutels
            // en dan vergelijkt assertSame die mee.
            $perVariant[$env] = array_values(array_diff($this->sleutelsUitEnv($this->map().'/'.$env), $verwachteVerschillen));
        }

        $eerste = array_key_first($perVariant);

        foreach ($perVariant as $env => $sleutels) {
            $this->assertSame($perVariant[$eerste], $sleutels,
                "De sleutels van {$env} lopen uit de pas met {$eerste}.");
        }
    }
}
