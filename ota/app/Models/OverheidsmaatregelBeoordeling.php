<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Wat de organisatie over één BIO-overheidsmaatregel heeft vastgesteld
 * (deelproducten/04b §2 en §3.1).
 *
 * In het onderzoek (00q §5.3) heette dit nog `soa_overheidsmaatregel`, als
 * koppeltabel. Dat was te klein gedacht: dit ding heeft een eigen status, eigen
 * motivatie, eigen historie en eigen bewijs. Een pivot met vijf attributen en een
 * audit trail is een entiteit met een misleidende naam.
 *
 * De sleutel loopt via `SoaRegel` en niet rechtstreeks naar `Maatregel`. Technisch
 * is dat gelijk (er is precies één SoA-regel per maatregel), maar langs de
 * SoA-regel is de cascade uitdrukbaar: verklaart de organisatie de
 * beheersmaatregel niet van toepassing, dan doet wat eronder hangt niet meer mee.
 */
class OverheidsmaatregelBeoordeling extends Model
{
    use Auditeerbaar;

    protected $table = 'overheidsmaatregel_beoordelingen';

    /**
     * De standen, met hun schermlabel. Eén plek, zodat scherm, schermkopie en
     * export niet uiteenlopen.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'niet_beoordeeld' => 'Niet beoordeeld',
        'belegd' => 'Belegd',
        'deels_belegd' => 'Deels belegd',
        'niet_belegd' => 'Niet belegd',
        'niet_van_toepassing' => 'Niet van toepassing',
    ];

    /**
     * Standen die een onderbouwing eisen.
     *
     * Bij `belegd` is het bewijsstuk de onderbouwing en is een motivatie
     * overbodig; bij deze twee is er niets anders dan de tekst van de CISO.
     *
     * @var list<string>
     */
    public const MOTIVATIE_VERPLICHT = ['niet_belegd', 'niet_van_toepassing'];

    /** @var list<string> */
    protected $fillable = [
        'soa_regel_id', 'overheidsmaatregel_id', 'status', 'motivatie',
        'beleidreferentie', 'procesreferentie',
        'risicobehandeling_id', 'laatst_beoordeeld_op', 'beoordeeld_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['laatst_beoordeeld_op' => 'date'];

    /**
     * De begintoestand ook in het geheugen, niet alleen als database-default.
     *
     * De seeder maakt deze rijen aan met `firstOrCreate` op de twee sleutels, dus
     * zonder `status`. Eloquent weet dan niets van de kolomdefault, en de
     * `created`-gebeurtenis van {@see Auditeerbaar} vraagt onmiddellijk om
     * `auditOmschrijving()` — die viel om op een lege status. Dit is dezelfde
     * waarde als in migratie 000060; ze horen samen te blijven.
     *
     * @var array<string, string>
     */
    protected $attributes = ['status' => 'niet_beoordeeld'];

    public function soaRegel(): BelongsTo
    {
        return $this->belongsTo(SoaRegel::class);
    }

    public function overheidsmaatregel(): BelongsTo
    {
        return $this->belongsTo(Overheidsmaatregel::class);
    }

    public function risicobehandeling(): BelongsTo
    {
        return $this->belongsTo(Risicobehandeling::class);
    }

    public function beoordeeldDoor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'beoordeeld_door_id');
    }

    /**
     * Het bewijs onder déze verplichting (deelproducten/04c §4.2).
     *
     * Dit is waar het extra detailniveau zich uitbetaalt: bewijs bij 5.24.03 in
     * plaats van bij 5.24. De koppeling bestond al via {@see Koppelbaar}, maar
     * zonder relatie was het aantal niet te tonen zonder een query per rij.
     */
    public function bewijsKoppelingen(): MorphMany
    {
        return $this->morphMany(BewijsKoppeling::class, 'entiteit', 'entiteit_type', 'entiteit_id');
    }

    /**
     * Belegd verklaard zonder bewijsstuk — het gap-signaal uit 04b §6.
     *
     * Deel 1 §4 van de BIO vraagt om opzet, bestaan en werking met verwijzingen;
     * een bewering zonder bewijs is geen "bestaan". Geen blokkade bij het opslaan,
     * net als bij {@see mistRisicoanalyse()}. Verwacht `bewijsKoppelingen`
     * eager-geladen.
     */
    public function mistBewijs(): bool
    {
        return $this->status === 'belegd' && $this->bewijsKoppelingen->isEmpty();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    /** Heeft de CISO hier al naar gekeken? */
    public function isBeoordeeld(): bool
    {
        return $this->status !== 'niet_beoordeeld';
    }

    /** Telt deze verplichting mee als "belegd" in de dekkingsgraad? */
    public function isBelegd(): bool
    {
        return $this->status === 'belegd';
    }

    /**
     * Een uitzondering zonder verwijzing naar de onderbouwende risicoanalyse.
     *
     * Deel 1 §7 van de BIO vraagt die verwijzing in de bijlage Uitzonderingen bij
     * de VvT. Het ISMS eist hem niet hard bij het opslaan, en dat is een bewuste
     * keuze: de koppeling tussen een risicobehandeling en een control wordt vanuit
     * de risicokant gelegd, dus een harde eis hier zou de CISO klemzetten in een
     * formulier waarin hij het gat niet kán dichten. Daarom hetzelfde patroon als
     * {@see SoaRegel::mistBeleid()}: geen blokkade, maar een signaal dat op /soa en
     * in de export zichtbaar is en dat naar nul hoort te gaan.
     */
    public function mistRisicoanalyse(): bool
    {
        return $this->status === 'niet_van_toepassing' && $this->risicobehandeling_id === null;
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        return 'BIO '.($this->overheidsmaatregel?->nummer ?? '?').' — '.$this->statusLabel();
    }
}
