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
        'auditronde_id', 'type', 'omschrijving', 'maatregel_id',
        'status', 'gesloten_op', 'gesloten_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['gesloten_op' => 'datetime'];

    public function auditronde(): BelongsTo
    {
        return $this->belongsTo(Auditronde::class);
    }

    public function maatregel(): BelongsTo
    {
        return $this->belongsTo(Maatregel::class);
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
