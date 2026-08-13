<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\LeveranciersbeoordelingObserver;
use Database\Factories\LeveranciersbeoordelingFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([LeveranciersbeoordelingObserver::class])]
class Leveranciersbeoordeling extends Model
{
    /** @use HasFactory<LeveranciersbeoordelingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'leveranciersbeoordelingen';

    /** @var list<string> */
    protected $fillable = [
        'leverancier_id', 'uitgevoerd_op', 'bevindingen',
        'volgende_beoordeling_gepland', 'uitgevoerd_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'uitgevoerd_op' => 'date',
        'volgende_beoordeling_gepland' => 'date',
    ];

    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class);
    }

    public function uitvoerder(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'uitgevoerd_door_id');
    }

    public function auditBlok(): string
    {
        return 'leveranciers-derdenrisico';
    }

    public function auditOmschrijving(): string
    {
        return 'Beoordeling '.($this->leverancier?->naam ?? '?').' ('.$this->uitgevoerd_op?->format('d-m-Y').')';
    }
}
