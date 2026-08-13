<?php

namespace App\Models;

use Database\Factories\DienstFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dienst extends Model
{
    /** @use HasFactory<DienstFactory> */
    use HasFactory;

    protected $table = 'diensten';

    /** @var list<string> */
    protected $fillable = ['leverancier_id', 'omschrijving'];

    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class);
    }

    /** De concrete invulling van `systemen.leverancier_id`: welke dienst hoort bij welk systeem (§7). */
    public function systemen(): BelongsToMany
    {
        return $this->belongsToMany(Systeem::class, 'dienst_systeem');
    }
}
