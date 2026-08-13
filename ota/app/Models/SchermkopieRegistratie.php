<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * De vastlegging van één schermkopie die de deur uit is gegaan (implementatie/12h §9).
 *
 * Net als bij [Raadpleging] en [AuditLogregel] is de append-only guard een
 * vangnet tegen programmeerfouten, geen beveiligingscontrole.
 */
class SchermkopieRegistratie extends Model
{
    protected $table = 'schermkopieen';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'scherm', 'filters', 'aantal_rijen', 'totaal_rijen',
        'met_persoonsgegevens', 'gebruiker_id', 'gemaakt_op',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'filters' => 'array',
        'aantal_rijen' => 'integer',
        'totaal_rijen' => 'integer',
        'met_persoonsgegevens' => 'boolean',
        'gemaakt_op' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Vastgelegde schermkopieën kunnen niet worden gewijzigd.');
        });

        static::deleting(function () {
            throw new RuntimeException('Vastgelegde schermkopieën kunnen niet worden verwijderd.');
        });
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    /** Het volledige register, of een gefilterde selectie? */
    public function isVolledig(): bool
    {
        return $this->aantal_rijen === $this->totaal_rijen && blank($this->filters);
    }
}
