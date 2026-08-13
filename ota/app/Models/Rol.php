<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'rollen';

    /** @var list<string> */
    protected $fillable = ['naam', 'omschrijving'];

    public function permissies(): HasMany
    {
        return $this->hasMany(RolPermissie::class, 'rol_id');
    }

    public function gebruikers(): BelongsToMany
    {
        return $this->belongsToMany(Gebruiker::class, 'rol_toewijzingen', 'rol_id', 'gebruiker_id');
    }
}
