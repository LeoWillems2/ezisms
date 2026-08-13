<?php

namespace App\Models;

use Database\Factories\AgendapuntFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén behandeld §9.3-onderwerp binnen een reviewsessie (implementatie/13 §3).
 * Niet Auditeerbaar: het is onderdeel van de sessie, die zelf het bewijs draagt.
 */
class Agendapunt extends Model
{
    /** @use HasFactory<AgendapuntFactory> */
    use HasFactory;

    protected $table = 'agendapunten';

    /** @var list<string> */
    protected $fillable = ['reviewsessie_id', 'categorie', 'samenvatting', 'gekoppeld_blok_naam'];

    public function reviewsessie(): BelongsTo
    {
        return $this->belongsTo(Reviewsessie::class);
    }
}
