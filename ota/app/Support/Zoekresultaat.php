<?php

namespace App\Support;

/**
 * Eén treffer uit de kennisbank (implementatie/00g §3).
 *
 * `passage` is bewust HTML en geen platte tekst: de treffer staat er al in
 * gemarkeerd met `<mark>`. De omliggende tekst is ge-escaped door
 * {@see Kennisbankzoeker}, dus de view mag hem ongefilterd uitschrijven — en
 * moet dat ook, anders staan de markeringen als letterlijke tags op het scherm.
 */
final readonly class Zoekresultaat
{
    public function __construct(
        public string $slug,
        public string $titel,
        public string $categorie,
        public float $score,
        public string $passage,
        /** Het kopanker waaronder de treffer staat; null bij een treffer in de titel. */
        public ?string $anker = null,
    ) {}

    /** De link naar het artikel, zo diep als de treffer toelaat. */
    public function url(): string
    {
        return route('kennisbank', $this->slug).($this->anker !== null ? '#'.$this->anker : '');
    }
}
