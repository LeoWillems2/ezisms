<?php

namespace App\Support;

use App\Models\Risico;
use Illuminate\Support\Collection;

/**
 * Wat er met het risicoregister gebeurt als de drempels verschuiven
 * (implementatie/04g §4.4).
 *
 * Dit is de reden dat de hele versionering de moeite waard is. De vraag die een
 * auditor bij een aangescherpte acceptatiedrempel stelt is niet "waar staat
 * het", maar "wat heeft u toen gedaan met de risico's die daardoor
 * onaanvaardbaar werden". Die vraag is alleen te beantwoorden als je hem vóór
 * het activeren stelt.
 *
 * Twee gebruikers: het goedkeuringsblok op /risicos/criteria (voorvertoning voor
 * Management) en `ActiveerRisicocriteria` (die de herbeoordelingstaken plant).
 *
 * De bandindeling staat hier één keer, met meegegeven drempels — de enige plek
 * in de codebase waar de banden met andere dan de actieve waarden bepaald
 * worden. `Risico::scoreKleur()` krijgt daarom géén parameters: die wordt op zes
 * plekken kaal aangeroepen en moet altijd het huidige kader tonen.
 */
final class Bandverschuiving
{
    /** Oplopend: een hoger getal is een zwaarder oordeel over dezelfde score. */
    private const GROEN = 0;

    private const AMBER = 1;

    private const ROOD = 2;

    /**
     * @param  Collection<int, array{risico: Risico, oud: int, nieuw: int}>  $regels
     */
    private function __construct(private readonly Collection $regels) {}

    /**
     * Vergelijkt het hele beoordeelde register onder twee paren drempels.
     *
     * Niet-beoordeelde risico's blijven buiten beschouwing: die hebben geen
     * score en dus geen band, en ze zouden de tellingen vertroebelen.
     */
    public static function tussen(
        int $oudeDrempel,
        int $oudeWaarschuwing,
        int $nieuweDrempel,
        int $nieuweWaarschuwing,
    ): self {
        $regels = Risico::query()
            ->whereNotNull('risicoscore')
            ->orderByDesc('risicoscore')
            ->get()
            ->map(fn (Risico $risico) => [
                'risico' => $risico,
                'oud' => self::band($risico->risicoscore, $oudeDrempel, $oudeWaarschuwing),
                'nieuw' => self::band($risico->risicoscore, $nieuweDrempel, $nieuweWaarschuwing),
            ]);

        return new self($regels);
    }

    /** De risico's die zwaarder gaan wegen — waar het bestuurlijk om gaat. */
    public function omhoog(): Collection
    {
        return $this->regels
            ->filter(fn (array $regel) => $regel['nieuw'] > $regel['oud'])
            ->map(fn (array $regel) => $regel['risico'])
            ->values();
    }

    /**
     * De risico's die lichter gaan wegen. Wel tonen, niet opvolgen: een risico
     * dat door een verruimde drempel van rood naar amber zakt is een besluit dat
     * al genomen is, en een taak zou suggereren dat er nog iets moet gebeuren.
     */
    public function omlaag(): Collection
    {
        return $this->regels
            ->filter(fn (array $regel) => $regel['nieuw'] < $regel['oud'])
            ->map(fn (array $regel) => $regel['risico'])
            ->values();
    }

    /** Hoeveel risico's straks boven de acceptatiedrempel staan en nu nog niet. */
    public function aantalNieuwBovenDrempel(): int
    {
        return $this->regels
            ->filter(fn (array $regel) => $regel['nieuw'] === self::ROOD && $regel['oud'] !== self::ROOD)
            ->count();
    }

    public function heeftVerschuiving(): bool
    {
        return $this->omhoog()->isNotEmpty() || $this->omlaag()->isNotEmpty();
    }

    private static function band(int $score, int $drempel, int $waarschuwing): int
    {
        return match (true) {
            $score > $drempel => self::ROOD,
            $score >= $waarschuwing => self::AMBER,
            default => self::GROEN,
        };
    }
}
