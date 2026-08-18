<?php

namespace App\Models;

use App\Support\Autorisatiegeheugen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolPermissie extends Model
{
    protected $table = 'rol_permissies';

    /** @var list<string> */
    protected $fillable = ['rol_id', 'blok_id', 'niveau'];

    protected static function booted(): void
    {
        // Een permissiewijziging maakt de onthouden `heeft-niveau`-antwoorden
        // ongeldig voor processen die langer leven dan één request.
        static::saved(fn () => app(Autorisatiegeheugen::class)->vergeet());
        static::deleted(fn () => app(Autorisatiegeheugen::class)->vergeet());
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }
}
