<?php

namespace App\Models;

use Database\Factories\BesluitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een besluit uit een reviewsessie (implementatie/13 §3). Kan tot verbeteracties
 * leiden; de terugkoppeling naar scope/criteria is informatief (§6).
 */
class Besluit extends Model
{
    /** @use HasFactory<BesluitFactory> */
    use HasFactory;

    protected $table = 'besluiten';

    /** @var list<string> */
    protected $fillable = ['reviewsessie_id', 'omschrijving'];

    public function reviewsessie(): BelongsTo
    {
        return $this->belongsTo(Reviewsessie::class);
    }

    public function verbeteracties(): HasMany
    {
        return $this->hasMany(Verbeteractie::class);
    }
}
