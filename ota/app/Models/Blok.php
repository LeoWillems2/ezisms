<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blok extends Model
{
    protected $table = 'blokken';

    /** @var list<string> */
    protected $fillable = ['code', 'naam'];

    public function permissies(): HasMany
    {
        return $this->hasMany(RolPermissie::class, 'blok_id');
    }
}
