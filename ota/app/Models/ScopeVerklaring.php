<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\ScopeVerklaringObserver;
use Database\Factories\ScopeVerklaringFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ScopeVerklaringObserver::class])]
class ScopeVerklaring extends Model
{
    /** @use HasFactory<ScopeVerklaringFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'scope_verklaringen';

    /** @var list<string> */
    protected $fillable = ['versienummer', 'scopetekst', 'status', 'geldig_vanaf', 'goedgekeurd_door', 'volgende_herziening_gepland'];

    /** @var array<string, string> */
    protected $casts = [
        'geldig_vanaf' => 'date',
        'volgende_herziening_gepland' => 'date',
    ];

    public function organisatieEenheden(): BelongsToMany
    {
        return $this->belongsToMany(OrganisatieEenheid::class, 'scope_verklaring_organisatie_eenheid');
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'scope_verklaring_issue');
    }

    public function belanghebbenden(): BelongsToMany
    {
        return $this->belongsToMany(Belanghebbende::class, 'scope_verklaring_belanghebbende');
    }

    public function uitsluitingen(): HasMany
    {
        return $this->hasMany(Uitsluiting::class);
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(ScopeInterface::class);
    }

    /** Inhoud is alleen te wijzigen zolang de versie nog concept is. */
    public function isBewerkbaar(): bool
    {
        return $this->status === 'concept';
    }

    public function herzieningVerstreken(): bool
    {
        return $this->volgende_herziening_gepland?->isPast() ?? false;
    }

    public function auditBlok(): string
    {
        return 'context-scope';
    }

    public function auditOmschrijving(): string
    {
        return 'Scope-verklaring v'.$this->versienummer;
    }
}
