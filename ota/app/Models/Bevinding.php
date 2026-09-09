<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\BevindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Een auditbevinding (implementatie/11 §3). De afhandeling als non-conformiteit
 * loopt via blok 8 (Afwijking) — hier alleen de vaststelling en de doorstroom.
 */
class Bevinding extends Model
{
    /** @use HasFactory<BevindingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'bevindingen';

    /** Bevindingtypen die als non-conformiteit (major/minor) tellen. */
    public const NON_CONFORMITEITEN = ['non_conformiteit_major', 'non_conformiteit_minor'];

    /** @var list<string> */
    protected $fillable = [
        'auditronde_id', 'type', 'omschrijving', 'auditobject_id', 'gesproken_met_id',
        'eigen_waarneming', 'status', 'gesloten_op', 'gesloten_door_id', 'afhandelingsnotitie',
    ];

    /** @var array<string, string> */
    protected $casts = ['gesloten_op' => 'datetime', 'eigen_waarneming' => 'boolean'];

    public function auditronde(): BelongsTo
    {
        return $this->belongsTo(Auditronde::class);
    }

    /**
     * Waar de bevinding over gaat (plan 11d): een auditobject, dus óók een
     * clausule uit H4-H10. Vóór 11d wees dit naar een `Maatregel`, en daardoor
     * kon een bevinding over de hoofdtekst van de norm nergens heen. De maatregel
     * blijft bereikbaar via `$bevinding->auditobject->maatregel`.
     */
    public function auditobject(): BelongsTo
    {
        return $this->belongsTo(Auditobject::class);
    }

    /**
     * Met wie er voor déze bevinding is gesproken, als dat afwijkt van wat op het
     * object staat. Leeg betekent: het gesprek van het object zelf — zie
     * `gesprekspartner()`.
     */
    public function eigenGesprekspartner(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gesproken_met_id');
    }

    /** De non-conformiteit die uit deze bevinding is voortgekomen (blok 8). */
    public function afwijking(): HasOne
    {
        return $this->hasOne(Afwijking::class);
    }

    public function sluiter(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gesloten_door_id');
    }

    /**
     * Wie er bij deze bevinding hoort als gesprekspartner: de eigen, en anders
     * die van het object in de scope van deze ronde.
     *
     * Die terugval doet in de praktijk weinig — een object met een bevinding kan
     * geen "geen opmerkingen" dragen en dus ook geen bron op zijn koppeling — en
     * daarom is het veld op de bevinding zelf verplicht (naschrift 11d §12). Hij
     * blijft staan voor de bevindingen van vóór die regel en voor het geval dat
     * een object eerst groen was en later alsnog een bevinding kreeg.
     *
     * Geeft het id en niet de `Gebruiker`: dit wordt per regel in een lijst
     * gevraagd, en één query per bevinding is precies de N+1 die je in een
     * rondedossier met dertig bevindingen niet wilt. Het scherm zoekt de namen in
     * één keer op.
     */
    public function gesprekspartnerId(): ?int
    {
        if ($this->gesproken_met_id !== null) {
            return $this->gesproken_met_id;
        }

        return $this->auditronde?->auditobjecten
            ->firstWhere('id', $this->auditobject_id)
            ?->pivot->gesproken_met_id;
    }

    public function isNonConformiteit(): bool
    {
        return in_array($this->type, self::NON_CONFORMITEITEN, true);
    }

    public function isGesloten(): bool
    {
        return $this->status === 'gesloten';
    }

    /**
     * De reden waarom sluiten (nog) niet kan, of `null` wanneer het wel kan —
     * zelfde vorm als `Incident::belemmeringVoorSluiten()` (implementatie/11 §6).
     * Een non-conformiteit (major/minor) is pas te sluiten als de gekoppelde
     * afwijking gesloten is; een observatie/verbeterkans mag direct dicht.
     */
    public function belemmeringVoorSluiten(): ?string
    {
        if (! $this->isNonConformiteit()) {
            return null;
        }

        $afwijking = $this->afwijking;

        if ($afwijking === null) {
            return 'Start eerst een non-conformiteit (afwijking) voor deze bevinding.';
        }

        if (! $afwijking->isGesloten()) {
            return 'De gekoppelde afwijking is nog niet gesloten.';
        }

        return null;
    }

    public function auditBlok(): string
    {
        return 'auditmanagement';
    }

    public function auditOmschrijving(): string
    {
        return Str::limit($this->omschrijving, 80);
    }
}
