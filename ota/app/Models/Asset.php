<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\AssetObserver;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AssetObserver::class])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'assets';

    /** @var list<string> */
    protected $fillable = [
        'naam', 'type', 'omschrijving', 'organisatie_eenheid_id',
        'accountable_id', 'responsible_id', 'status', 'binnen_scope',
        'vertrouwelijkheidsniveau', 'integriteitsniveau', 'beschikbaarheidsniveau',
        'laatst_geclassificeerd_op', 'persoonsgegevens', 'privacy_beoordeeld_op',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'binnen_scope' => 'boolean',
        'laatst_geclassificeerd_op' => 'date',
        'privacy_beoordeeld_op' => 'date',
    ];

    /** De soorten persoonsgegevens, in oplopende gevoeligheid (AVG art. 4/9/10). */
    public const PERSOONSGEGEVENSSOORTEN = ['geen', 'gewoon', 'bijzonder', 'strafrechtelijk'];

    /** De soorten waarvoor minstens 'vertrouwelijk' hoort te gelden. */
    private const GEVOELIGE_SOORTEN = ['bijzonder', 'strafrechtelijk'];

    public function organisatieEenheid(): BelongsTo
    {
        return $this->belongsTo(OrganisatieEenheid::class);
    }

    public function accountable(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'accountable_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'responsible_id');
    }

    public function systemen(): BelongsToMany
    {
        return $this->belongsToMany(Systeem::class, 'asset_systeem');
    }

    public function toewijzingen(): HasMany
    {
        return $this->hasMany(AssetToewijzing::class);
    }

    /**
     * Volledig geclassificeerd zodra alle drie de dimensies zijn ingevuld.
     *
     * `persoonsgegevens` telt hier bewust NIET in mee (implementatie/03b §0).
     * Deze methode stuurt via {@see AssetObserver::saving()} de
     * overgang naar 'actief'; zou privacy meetellen, dan zou elk bestaand asset
     * ineens onvolledig zijn en zou de observer niets meer activeren tot iemand
     * het hele register langsloopt. Classificatie (A.5.12) en de privacyvraag
     * (AVG) zijn bovendien verschillende beoordelingen.
     */
    public function isGeclassificeerd(): bool
    {
        return $this->vertrouwelijkheidsniveau && $this->integriteitsniveau && $this->beschikbaarheidsniveau;
    }

    /**
     * Bevat dit asset persoonsgegevens? `null` zolang het niet beoordeeld is.
     *
     * Let op de drieledige uitkomst. Wie hem als boolean gebruikt
     * (`if ($asset->bevatPersoonsgegevens())`) behandelt "nog niet beoordeeld"
     * stilzwijgend als "nee", en dat is precies het onderscheid dat dit veld
     * moet bewaken. Vergelijk dus met `=== true` of `=== null`.
     */
    public function bevatPersoonsgegevens(): ?bool
    {
        return $this->persoonsgegevens === null
            ? null
            : $this->persoonsgegevens !== 'geen';
    }

    /**
     * De waarschuwing dat de classificatie niet past bij de soort
     * persoonsgegevens, of `null` als er niets aan de hand is.
     *
     * Bewust een waarschuwing en geen validatiefout (implementatie/03b §0): het
     * ISMS moet kunnen vastleggen wat er *is*, ook als dat niet deugt — dat gat
     * is nu juist wat een CISO wil zien. Hard afdwingen levert of een leugen of
     * een leeg veld op.
     */
    public function privacywaarschuwing(): ?string
    {
        $gevoelig = in_array($this->persoonsgegevens, self::GEVOELIGE_SOORTEN, true);

        if (! $gevoelig || ! in_array($this->vertrouwelijkheidsniveau, ['openbaar', 'intern'], true)) {
            return null;
        }

        return 'Dit asset bevat '.($this->persoonsgegevens === 'bijzonder' ? 'bijzondere' : 'strafrechtelijke')
            .' persoonsgegevens maar is geclassificeerd als "'.$this->vertrouwelijkheidsniveau.'". '
            .'Die horen minstens op "vertrouwelijk" te staan.';
    }

    public function heeftOpenToewijzingen(): bool
    {
        return $this->toewijzingen()->whereNull('geretourneerd_op')->exists();
    }

    public function auditBlok(): string
    {
        return 'asset-classificatie';
    }
}
