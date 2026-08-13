<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\EffectiviteitstoetsObserver;
use Database\Factories\EffectiviteitstoetsFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([EffectiviteitstoetsObserver::class])]
class Effectiviteitstoets extends Model
{
    /** @use HasFactory<EffectiviteitstoetsFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'effectiviteitstoetsen';

    /** @var list<string> */
    protected $fillable = [
        'corrigerende_maatregel_id', 'uitgevoerd_op', 'resultaat',
        'toelichting', 'uitgevoerd_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['uitgevoerd_op' => 'date'];

    public function maatregel(): BelongsTo
    {
        return $this->belongsTo(CorrigerendeMaatregel::class, 'corrigerende_maatregel_id');
    }

    public function uitvoerder(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'uitgevoerd_door_id');
    }

    public function auditBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    public function auditOmschrijving(): string
    {
        return 'Toets '.$this->uitgevoerd_op?->format('d-m-Y').': '.$this->resultaat;
    }
}
