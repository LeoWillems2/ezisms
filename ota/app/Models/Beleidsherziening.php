<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\BeleidsherzieningObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vastgelegde beoordeling van de geldende versie waaruit blijkt dat het
 * beleid ongewijzigd kan blijven (A.5.1). Moet het beleid wél veranderen, dan
 * is dit niet de weg: dan komt er een nieuwe versie met goedkeuring, en vervalt
 * de herzieningstaak zodra die gepubliceerd is.
 *
 * Net als een leesbevestiging onherroepelijk: aanmaken en laten staan.
 */
#[ObservedBy([BeleidsherzieningObserver::class])]
class Beleidsherziening extends Model
{
    use Auditeerbaar;

    protected $table = 'beleidsherzieningen';

    /** Onder deze lengte is een toelichting geen conclusie met reden. */
    public const MINIMALE_TOELICHTING = 20;

    /** @var list<string> */
    protected $fillable = [
        'beleidsversie_id', 'beoordeeld_door_id', 'beoordeeld_op',
        'toelichting', 'volgende_herziening_gepland',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'beoordeeld_op' => 'datetime',
        'volgende_herziening_gepland' => 'date',
    ];

    public function versie(): BelongsTo
    {
        return $this->belongsTo(Beleidsversie::class, 'beleidsversie_id');
    }

    public function beoordelaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'beoordeeld_door_id');
    }

    public function auditBlok(): string
    {
        return 'beleid-maatregelbeheer';
    }

    public function auditOmschrijving(): string
    {
        return 'Herziening '.($this->versie?->auditOmschrijving() ?? '')
            .' door '.($this->beoordelaar?->naam ?? '?');
    }
}
