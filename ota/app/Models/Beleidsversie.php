<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\BeleidsversieObserver;
use Database\Factories\BeleidsversieFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([BeleidsversieObserver::class])]
class Beleidsversie extends Model
{
    /** @use HasFactory<BeleidsversieFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'beleidsversies';

    /** @var list<string> */
    protected $fillable = [
        'beleidsdocument_id', 'versienummer', 'bewijsstuk_id', 'status',
        'wijzigingsreden', 'gepubliceerd_op', 'goedgekeurd_door_id',
        'goedgekeurd_op', 'volgende_herziening_gepland',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gepubliceerd_op' => 'date',
        'goedgekeurd_op' => 'datetime',
        'volgende_herziening_gepland' => 'date',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Beleidsdocument::class, 'beleidsdocument_id');
    }

    public function bewijsstuk(): BelongsTo
    {
        return $this->belongsTo(Bewijsstuk::class);
    }

    public function goedkeurder(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'goedgekeurd_door_id');
    }

    public function bevestigingen(): HasMany
    {
        return $this->hasMany(Leesbevestiging::class);
    }

    /** Inhoud is alleen te wijzigen zolang de versie nog niet gepubliceerd is. */
    public function isBewerkbaar(): bool
    {
        return in_array($this->status, ['concept', 'ter_goedkeuring'], true);
    }

    public function herzieningVerstreken(): bool
    {
        return $this->status === 'actief'
            && ($this->volgende_herziening_gepland?->isPast() ?? false);
    }

    public function isBevestigdDoor(?int $gebruikerId): bool
    {
        return $gebruikerId !== null
            && $this->bevestigingen()->where('gebruiker_id', $gebruikerId)->exists();
    }

    public function auditBlok(): string
    {
        return 'beleid-maatregelbeheer';
    }

    public function auditOmschrijving(): string
    {
        return ($this->document?->titel ?? 'Beleidsdocument').' v'.$this->versienummer;
    }
}
