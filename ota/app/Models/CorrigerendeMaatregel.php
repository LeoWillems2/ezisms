<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\CorrigerendeMaatregelObserver;
use Database\Factories\CorrigerendeMaatregelFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * De CAPA uit `deelproducten/08` — hier met de term uit de Nederlandse norm
 * (implementatie/08 §2).
 */
#[ObservedBy([CorrigerendeMaatregelObserver::class])]
class CorrigerendeMaatregel extends Model
{
    /** @use HasFactory<CorrigerendeMaatregelFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'corrigerende_maatregelen';

    /** @var list<string> */
    protected $fillable = [
        'afwijking_id', 'omschrijving', 'eigenaar_id', 'deadline', 'status', 'voltooid_op',
    ];

    /** @var array<string, string> */
    protected $casts = ['deadline' => 'date', 'voltooid_op' => 'date'];

    public function afwijking(): BelongsTo
    {
        return $this->belongsTo(Afwijking::class);
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'eigenaar_id');
    }

    public function toetsen(): HasMany
    {
        return $this->hasMany(Effectiviteitstoets::class);
    }

    /**
     * De toets die telt. Een maatregel die eerst niet en later wél effectief
     * bleek, is effectief — en andersom. Alleen de laatste uitspraak geldt.
     */
    public function laatsteToets(): HasOne
    {
        return $this->hasOne(Effectiviteitstoets::class)->latestOfMany('uitgevoerd_op');
    }

    public function isEffectiefBevonden(): bool
    {
        return $this->laatsteToets?->resultaat === 'effectief';
    }

    public function auditBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    public function auditOmschrijving(): string
    {
        return Str::limit($this->omschrijving, 80);
    }
}
