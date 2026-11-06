<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Autorisatiegeheugen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén rij in de rechtenmatrix. Auditeerbaar sinds implementatie/14b §8.1: de
 * CISO zet daar per rol de assistenttoegang aan of uit, en een wijziging in wie
 * wat mag is precies wat een auditor in de trail wil terugvinden (A.5.18).
 * Seeders die niets veranderen, schrijven niets: `updateOrCreate` op een
 * bestaande, gelijke rij vuurt geen `updated`.
 */
class RolPermissie extends Model
{
    use Auditeerbaar;

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

    public function auditBlok(): string
    {
        return 'identity-access';
    }

    public function auditOmschrijving(): string
    {
        return sprintf('%s · %s · %s', $this->rol?->naam ?? 'rol #'.$this->rol_id, $this->blok?->code ?? 'blok #'.$this->blok_id, $this->niveau);
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
