<?php

namespace App\Support\Demo;

use App\Models\Gebruiker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Voert een handeling uit namens een gebruiker.
 *
 * Dit is de kern van de motor. Twee dingen gebeuren hier die de demo pas
 * geloofwaardig maken:
 *
 *  1. **Inloggen.** De `Auditeerbaar`-trait leest `auth()->user()`. Zonder
 *     ingelogde gebruiker staat er "Systeem (geplande taak)" in de audit trail,
 *     en dan is precies het bewijsstuk waar het bij een ISMS om draait
 *     waardeloos.
 *  2. **De autorisatiecheck als assertie.** Vóór de handeling toetsen we of deze
 *     gebruiker hem daadwerkelijk mág doen. Zo niet: harde fout. Daarmee is het
 *     vullen meteen een proef op de som voor het rechtenmodel uit
 *     implementatie/01c, en valt een fixture die de verkeerde persoon aanwijst
 *     onmiddellijk op in plaats van een onmogelijke historie op te leveren.
 */
final class Handelt
{
    private ?string $ability = null;

    /** @var array<int, mixed> */
    private array $argumenten = [];

    private string $waar = 'onbekende gebeurtenis';

    private ?string $recordUitleg = null;

    /** @var callable(Gebruiker): bool|null */
    private $recordToets = null;

    private function __construct(private readonly Gebruiker $gebruiker) {}

    public static function als(Gebruiker $gebruiker): self
    {
        return new self($gebruiker);
    }

    /** De autorisatiecheck die moet slagen; zonder deze aanroep wordt niets getoetst. */
    public function mits(string $ability, array $argumenten = []): self
    {
        $this->ability = $ability;
        $this->argumenten = $argumenten;

        return $this;
    }

    /**
     * Een record-guard die moet slagen: `$toets` krijgt de gebruiker en geeft
     * true als hij mag.
     *
     * Nodig voor blok 11: de onafhankelijkheid van de interne auditor zit niet
     * in de blok-autorisatiecheck maar in `Auditronde::magBevindingBewerkenDoor()`
     * en `magUitvoerenDoor()`. Zou de motor daar `mits()` gebruiken, dan zou hij
     * de Auditor `auditmanagement/muteren` toedichten — precies het blanket
     * schrijfrecht dat implementatie/11 §4 bewust níet geeft.
     */
    public function mitsRecord(string $uitleg, callable $toets): self
    {
        $this->recordUitleg = $uitleg;
        $this->recordToets = $toets;

        return $this;
    }

    /** Context voor de foutmelding: welke gebeurtenis, welke maand. */
    public function bij(string $waar): self
    {
        $this->waar = $waar;

        return $this;
    }

    public function doe(callable $handeling): mixed
    {
        if ($this->ability !== null
            && ! Gate::forUser($this->gebruiker)->allows($this->ability, $this->argumenten)) {
            $argumenten = implode(', ', array_map(
                fn ($a) => is_scalar($a) ? (string) $a : gettype($a),
                $this->argumenten
            ));

            throw DemoFixtureFout::bij($this->waar, sprintf(
                '%s (%s) mag "%s(%s)" niet. Wijst de tijdlijn de juiste persoon aan?',
                $this->gebruiker->naam,
                $this->gebruiker->rollen->pluck('naam')->implode('/') ?: 'zonder rol',
                $this->ability,
                $argumenten,
            ));
        }

        if ($this->recordToets !== null && ! ($this->recordToets)($this->gebruiker)) {
            throw DemoFixtureFout::bij($this->waar, sprintf(
                '%s mag dit record niet bewerken: %s',
                $this->gebruiker->naam,
                $this->recordUitleg,
            ));
        }

        Auth::login($this->gebruiker);

        try {
            return $handeling();
        } finally {
            Auth::logout();
        }
    }
}
