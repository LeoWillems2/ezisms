<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wat één niveau van kans of impact betekent, binnen één criteriaversie
 * (implementatie/04g §3.2).
 *
 * `Auditeerbaar`, en dat is geen automatisme: bijstellen wat impact 4 betekent
 * verandert de betekenis van elke score van 4 en hoort daarom net zo goed in de
 * trail als het verzetten van de drempel zelf.
 */
class Beoordelingsniveau extends Model
{
    use Auditeerbaar;

    protected $table = 'beoordelingsniveaus';

    /** @var list<string> */
    protected $fillable = [
        'risicocriteria_versie_id', 'as', 'niveau', 'naam', 'omschrijving', 'kwantitatieve_band',
    ];

    /** @var array<string, string> */
    protected $casts = ['niveau' => 'integer'];

    public function versie(): BelongsTo
    {
        return $this->belongsTo(RisicocriteriaVersie::class, 'risicocriteria_versie_id');
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        return ucfirst($this->as).' '.$this->niveau
            .' (criteria v'.($this->versie?->versienummer ?? '?').')';
    }
}
