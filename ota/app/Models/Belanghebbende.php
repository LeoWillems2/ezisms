<?php

namespace App\Models;

use Database\Factories\BelanghebbendeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Belanghebbende extends Model
{
    /** @use HasFactory<BelanghebbendeFactory> */
    use HasFactory;

    protected $table = 'belanghebbenden';

    /** @var list<string> */
    protected $fillable = ['naam', 'aard', 'relevantie_voor_isms'];

    public function eisen(): HasMany
    {
        return $this->hasMany(Eis::class);
    }

    public function scopeVerklaringen(): BelongsToMany
    {
        return $this->belongsToMany(ScopeVerklaring::class, 'scope_verklaring_belanghebbende');
    }
}
