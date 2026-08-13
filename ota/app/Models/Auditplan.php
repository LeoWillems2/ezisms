<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\AuditplanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jaarlijks auditplan (§9.2). Eén per jaar; vaststellen is auditrelevant en
 * daarom Auditeerbaar (implementatie/11 §3).
 */
class Auditplan extends Model
{
    /** @use HasFactory<AuditplanFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'auditplannen';

    /** @var list<string> */
    protected $fillable = [
        'jaar', 'status', 'auditprogramma_id', 'programmajaar', 'periode_start', 'periode_eind',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'jaar' => 'integer',
        'programmajaar' => 'integer',
        'periode_start' => 'date',
        'periode_eind' => 'date',
    ];

    /**
     * Valt een uitvoerdatum binnen het venster van dit programmajaar? Zonder
     * periode (een los plan buiten een cyclus) is het antwoord nee — dan is er
     * geen venster om binnen te vallen.
     */
    public function dektDatum(\DateTimeInterface $datum): bool
    {
        return $this->periode_start !== null
            && $this->periode_eind !== null
            && $datum >= $this->periode_start->startOfDay()
            && $datum <= $this->periode_eind->endOfDay();
    }

    public function rondes(): HasMany
    {
        return $this->hasMany(Auditronde::class);
    }

    /** De meerjarige cyclus waar dit jaarplan onder valt (plan 11b), optioneel. */
    public function auditprogramma(): BelongsTo
    {
        return $this->belongsTo(Auditprogramma::class);
    }

    public function auditBlok(): string
    {
        return 'auditmanagement';
    }

    public function auditOmschrijving(): string
    {
        return 'Auditplan '.$this->jaar;
    }
}
