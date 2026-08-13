<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\SysteemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Systeem extends Model
{
    /** @use HasFactory<SysteemFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'systemen';

    /** @var list<string> */
    protected $fillable = [
        'naam', 'hostingtype', 'leverancier_id', 'status', 'afgevoerd_op',
        'beschikbaarheidseis', 'redundant', 'redundantie_toelichting',
    ];

    /** @var array<string, string> */
    protected $casts = ['afgevoerd_op' => 'date', 'redundant' => 'boolean'];

    /** De beschikbaarheidseis oplopend; null = nog niet bepaald (A.8.14). */
    public const BESCHIKBAARHEIDSEISEN = ['niet_kritiek', 'normaal', 'hoog', 'bedrijfskritiek'];

    /** Eisen waarbij ontbrekende redundantie een A.8.14-gap is. */
    public const KRITIEKE_EISEN = ['hoog', 'bedrijfskritiek'];

    /** Terugkijkperiode van het afvoersignaal, in maanden. Zie scopeAfgevoerdZonderDossier(). */
    public const SIGNAALPERIODE_MAANDEN = 12;

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_systeem');
    }

    /** De primaire leverancierskoppeling — de FK is in blok 9 ingevuld. */
    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class);
    }

    public function diensten(): BelongsToMany
    {
        return $this->belongsToMany(Dienst::class, 'dienst_systeem');
    }

    /** De wijzigingen die dit systeem raakten (blok 15). */
    public function wijzigingen(): BelongsToMany
    {
        return $this->belongsToMany(Wijziging::class, 'systeem_wijziging');
    }

    /**
     * Afgevoerd zonder dat er een afgerond afvoerdossier tegenover staat
     * (implementatie/15 §16).
     *
     * Beperkt tot de laatste twaalf maanden. Een signaal dat tot het begin der
     * tijden terugkijkt kan bij een bestaande installatie nooit op nul komen —
     * en dan is het geen signaal meer maar een vaste rode balk. Twaalf maanden
     * sluit aan op de auditperiode.
     *
     * Systemen zonder `afgevoerd_op` vallen erbuiten: het scherm vult die datum
     * altijd, dus een lege waarde is ingelezen historie en geen nalatigheid van
     * vandaag.
     */
    public function scopeAfgevoerdZonderDossier(Builder $query): Builder
    {
        return $query->where('status', 'afgevoerd')
            ->whereNotNull('afgevoerd_op')
            ->whereDate('afgevoerd_op', '>=', now()->subMonths(self::SIGNAALPERIODE_MAANDEN))
            ->whereDoesntHave('wijzigingen', fn (Builder $q) => $q
                ->where('soort', 'afvoer')
                ->where('status', 'gesloten'));
    }

    /** Alleen systemen die nog in gebruik zijn — voor nieuwe koppelingen/keuzelijsten. */
    public function scopeInGebruik(Builder $query): Builder
    {
        return $query->where('status', 'in_gebruik');
    }

    public function isAfgevoerd(): bool
    {
        return $this->status === 'afgevoerd';
    }

    /**
     * Kritieke beschikbaarheidseis zonder aantoonbare redundantie — het
     * A.8.14-signaal. `redundant !== true` vat zowel "nee" als "nog onbekend"
     * als gap op: bij een bedrijfskritiek systeem is niet-vastgestelde
     * redundantie net zo goed een openstaand punt. Een afgevoerd systeem telt
     * niet mee — dat draait niet meer.
     */
    public function heeftRedundantieGap(): bool
    {
        return ! $this->isAfgevoerd()
            && in_array($this->beschikbaarheidseis, self::KRITIEKE_EISEN, true)
            && $this->redundant !== true;
    }

    public function auditBlok(): string
    {
        return 'asset-classificatie';
    }
}
