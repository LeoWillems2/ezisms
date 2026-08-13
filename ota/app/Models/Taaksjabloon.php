<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\TaaksjabloonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Taaksjabloon extends Model
{
    /** @use HasFactory<TaaksjabloonFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'taaksjablonen';

    /** @var list<string> */
    protected $fillable = [
        'naam', 'omschrijving', 'herhaling', 'interval_dagen', 'bron_blok',
        'standaard_eigenaar_id', 'aanmaken_dagen_vooraf', 'actief',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'actief' => 'boolean',
        'interval_dagen' => 'integer',
        'aanmaken_dagen_vooraf' => 'integer',
    ];

    public function taken(): HasMany
    {
        return $this->hasMany(Taak::class);
    }

    public function standaardEigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'standaard_eigenaar_id');
    }

    /** De volgende deadline na een gegeven datum, volgens de herhaling. */
    public function volgendeDeadlineNa(Carbon $datum): ?Carbon
    {
        return match ($this->herhaling) {
            'maandelijks' => $datum->copy()->addMonth(),
            'per_kwartaal' => $datum->copy()->addMonths(3),
            'jaarlijks' => $datum->copy()->addYear(),
            'aangepast' => $this->interval_dagen ? $datum->copy()->addDays($this->interval_dagen) : null,
            // Eenmalig: na de eerste taak volgt er geen tweede.
            default => null,
        };
    }

    public function auditBlok(): string
    {
        return 'taken-workflow-engine';
    }
}
