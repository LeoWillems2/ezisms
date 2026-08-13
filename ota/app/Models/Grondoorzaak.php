<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\GrondoorzaakObserver;
use Database\Factories\GrondoorzaakFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[ObservedBy([GrondoorzaakObserver::class])]
class Grondoorzaak extends Model
{
    /** @use HasFactory<GrondoorzaakFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'grondoorzaken';

    /** @var list<string> */
    protected $fillable = ['afwijking_id', 'omschrijving', 'methodiek'];

    public function afwijking(): BelongsTo
    {
        return $this->belongsTo(Afwijking::class);
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
