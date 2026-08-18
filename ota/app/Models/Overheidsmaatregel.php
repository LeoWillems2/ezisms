<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén BIO-overheidsmaatregel: de verplichte minimale invulling van een
 * beheersmaatregel (deelproducten/04b §2).
 *
 * Referentiedata, net als {@see Maatregel}: aangemaakt door
 * {@see \Database\Seeders\OverheidsmaatregelSeeder} uit het bronbestand, niet via
 * de applicatie. Geen `Auditeerbaar` — er valt hier niets te wijzigen dat van de
 * organisatie is, en een nieuwe BIO-uitgave hoort geen 127 auditregels op te
 * leveren. Wat de organisatie er wél over vindt, staat in
 * {@see OverheidsmaatregelBeoordeling}.
 *
 * Bestaat alleen in het normprofiel `bio2`. In de andere profielen is de tabel
 * leeg; er is geen code die op het profiel vergelijkt, alles hangt aan de
 * capaciteit `overheidsmaatregelen`.
 */
class Overheidsmaatregel extends Model
{
    protected $table = 'overheidsmaatregelen';

    /**
     * Wat er staat waar de norm een verplichting heeft en dit ISMS de tekst niet
     * meelevert.
     *
     * Zelfde patroon als {@see Maatregel::ZORGAANVULLING_NIET_MEEGELEVERD}: een
     * markering in de data, geen schermtekst. De reden is hier wél een
     * licentiekwestie en geen keuze — de BIO staat onder CC BY-NC-SA 4.0
     * (niet-commercieel), en of het Cyberbeveiligingsbesluit die beperking
     * opheft is een open juridische vraag (implementatie/00q §8).
     *
     * Wat er zonder tekst overblijft is precies wat een auditor nodig heeft om te
     * volgen waar hij is: het nummer, de beheersmaatregel eronder, de status en de
     * reikwijdte. Zeggen dát er een verplichting is en dat wij de tekst niet
     * leveren, is iets anders dan zwijgen.
     *
     * De literal staat ook in `scripts/genereer_overheidsmaatregelen_seed.py`;
     * BioOverheidsmaatregelenTest bewaakt dat de twee niet uiteenlopen.
     */
    public const TEKST_NIET_MEEGELEVERD = 'Dit ISMS levert bij deze overheidsmaatregel geen tekst mee.';

    /** Wat het scherm zegt in plaats van de markering. */
    public const GEEN_TEKST_AANHEF = 'De tekst van deze verplichting levert dit ISMS niet mee; '
        .'u ziet het nummer. Waarom:';

    /** @var list<string> */
    protected $fillable = [
        'nummer', 'maatregel_id', 'volgnummer', 'tekst',
        'cbw_reikwijdte', 'status', 'verwezen_naar',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'cbw_reikwijdte' => 'boolean',
        'volgnummer' => 'integer',
    ];

    public function maatregel(): BelongsTo
    {
        return $this->belongsTo(Maatregel::class);
    }

    public function beoordelingen(): HasMany
    {
        return $this->hasMany(OverheidsmaatregelBeoordeling::class);
    }

    /** Geldt deze verplichting nu nog? */
    public function isGeldend(): bool
    {
        return $this->status === 'geldend';
    }

    /**
     * Heeft deze rij een ingevoerde normtekst?
     *
     * Drie schrijfwijzen voor "nee", net als bij
     * {@see Maatregel::toontOmschrijving()}: `null` bij een vervallen of
     * verplaatst nummer, `''` een leeggemaakt veld, en de markering uit het
     * seedbestand.
     */
    public function toontTekst(): bool
    {
        return ! in_array($this->tekst, [null, '', self::TEKST_NIET_MEEGELEVERD], true);
    }

    /**
     * Wat er bij een vervallen of verplaatst nummer op het scherm hoort.
     *
     * Geen normtekst maar een mededeling van dit ISMS, en dus geen
     * licentiekwestie. Een auditor met de vorige BIO-uitgave in de hand zoekt
     * 5.25.01 op, en dit is het antwoord dat hij nodig heeft.
     */
    public function statusMededeling(): ?string
    {
        return match ($this->status) {
            'vervallen' => 'Vervallen in een latere uitgave van de BIO.',
            'verplaatst' => $this->verwezen_naar === null
                ? 'Verplaatst naar een ander nummer.'
                : "Verplaatst naar {$this->verwezen_naar}.",
            default => null,
        };
    }
}
