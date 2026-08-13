<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Eis extends Model
{
    protected $table = 'eisen';

    /** @var list<string> */
    protected $fillable = ['belanghebbende_id', 'omschrijving', 'bron'];

    public function belanghebbende(): BelongsTo
    {
        return $this->belongsTo(Belanghebbende::class);
    }
}
