<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\LeesbevestigingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bevestigen is onherroepelijk: de rij wordt aangemaakt en blijft staan, er is
 * geen intrekken en geen bewerken (implementatie/05 §6). Juist dat maakt hem
 * bruikbaar als bewijs bij A.5.1.
 */
#[ObservedBy([LeesbevestigingObserver::class])]
class Leesbevestiging extends Model
{
    use Auditeerbaar;

    protected $table = 'leesbevestigingen';

    /** @var list<string> */
    protected $fillable = ['beleidsversie_id', 'gebruiker_id', 'bevestigd_op'];

    /** @var array<string, string> */
    protected $casts = ['bevestigd_op' => 'datetime'];

    public function versie(): BelongsTo
    {
        return $this->belongsTo(Beleidsversie::class, 'beleidsversie_id');
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    public function auditBlok(): string
    {
        return 'beleid-maatregelbeheer';
    }

    public function auditOmschrijving(): string
    {
        return 'Leesbevestiging '.($this->versie?->auditOmschrijving() ?? '')
            .' door '.($this->gebruiker?->naam ?? '?');
    }
}
