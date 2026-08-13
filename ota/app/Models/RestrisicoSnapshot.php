<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén onveranderlijk restrisico-meetpunt per control per jaar (plan 04c §2). De
 * cijfers (max_restrisico, aantal_risicos) worden bij het vastleggen bevroren en
 * nooit herberekend — alleen `toelichting` mag achteraf gevuld worden met de
 * reden van beweging (gemitigeerd / herscoord / risico afgevoerd).
 */
class RestrisicoSnapshot extends Model
{
    use Auditeerbaar;

    protected $table = 'restrisico_snapshots';

    /** @var list<string> */
    protected $fillable = [
        'soa_regel_id', 'peiljaar', 'max_restrisico', 'aantal_risicos', 'definitie_versie',
        'risicocriteria_versie_id', 'toelichting',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'peiljaar' => 'integer',
        'max_restrisico' => 'integer',
        'aantal_risicos' => 'integer',
        'definitie_versie' => 'integer',
    ];

    public function soaRegel(): BelongsTo
    {
        return $this->belongsTo(SoaRegel::class);
    }

    /**
     * Het risicocriteriakader waaronder dit peiljaar tot stand kwam (04g §8.3).
     *
     * `max_restrisico` is een getal op de 1-25-schaal en betekent alleen iets
     * samen met de schaal eronder. Twee peiljaren onder verschillende versies
     * zijn dus niet zonder meer vergelijkbaar; de trendweergave markeert dat.
     */
    public function criteriaVersie(): BelongsTo
    {
        return $this->belongsTo(RisicocriteriaVersie::class, 'risicocriteria_versie_id');
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        $ref = $this->soaRegel?->maatregel?->annex_a_referentie;

        return 'Restrisico-snapshot '.$this->peiljaar.($ref ? ' (A.'.$ref.')' : '');
    }
}
