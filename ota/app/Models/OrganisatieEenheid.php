<?php

namespace App\Models;

use Database\Factories\OrganisatieEenheidFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganisatieEenheid extends Model
{
    /** @use HasFactory<OrganisatieEenheidFactory> */
    use HasFactory;

    /** Het enige type dat als doelgroep voor een leesbevestiging kan dienen. */
    public const TYPE_AFDELING = 'afdeling';

    protected $table = 'organisatie_eenheden';

    /** @var list<string> */
    protected $fillable = ['naam', 'type', 'bovenliggende_eenheid_id'];

    /** Alleen de eenheden van type 'afdeling' — de rest (locatie, proces) is
     *  geen doelgroep voor een leesbevestiging. */
    public function scopeAfdelingen(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_AFDELING);
    }

    public function gebruikers(): HasMany
    {
        return $this->hasMany(Gebruiker::class, 'organisatie_eenheid_id');
    }

    public function bovenliggendeEenheid(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bovenliggende_eenheid_id');
    }

    public function subEenheden(): HasMany
    {
        return $this->hasMany(self::class, 'bovenliggende_eenheid_id');
    }

    public function scopeVerklaringen(): BelongsToMany
    {
        return $this->belongsToMany(ScopeVerklaring::class, 'scope_verklaring_organisatie_eenheid');
    }
}
