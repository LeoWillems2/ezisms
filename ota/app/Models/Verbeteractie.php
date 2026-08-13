<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\VerbeteractieObserver;
use Database\Factories\VerbeteractieFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Een verbeteractie (§10.2), uitkomst van een besluit. Bewust een eigen entiteit
 * — geen `Taak` en niet het CAPA-model van blok 8 (implementatie/13 §5). De
 * deadline-bewaking loopt via de taken-engine (VerbeteractieObserver).
 */
#[ObservedBy([VerbeteractieObserver::class])]
class Verbeteractie extends Model
{
    /** @use HasFactory<VerbeteractieFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'verbeteracties';

    /** @var list<string> */
    protected $fillable = ['besluit_id', 'omschrijving', 'eigenaar_id', 'deadline', 'status', 'voltooid_op'];

    /** @var array<string, string> */
    protected $casts = [
        'deadline' => 'date',
        'voltooid_op' => 'date',
    ];

    public function besluit(): BelongsTo
    {
        return $this->belongsTo(Besluit::class);
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'eigenaar_id');
    }

    public function isVoltooid(): bool
    {
        return $this->status === 'voltooid';
    }

    public function auditBlok(): string
    {
        return 'management-review-verbetercyclus';
    }

    public function auditOmschrijving(): string
    {
        return Str::limit($this->omschrijving, 80);
    }
}
