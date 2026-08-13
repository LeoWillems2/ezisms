<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\LeverancierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Leverancier extends Model
{
    /** @use HasFactory<LeverancierFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'leveranciers';

    /** @var list<string> */
    protected $fillable = [
        'naam', 'status', 'risiconiveau', 'eigen_certificering_geldig_tot',
        'beeindigd_op', 'data_teruggave_bevestigd_op', 'data_teruggave_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'eigen_certificering_geldig_tot' => 'date',
        'beeindigd_op' => 'date',
        'data_teruggave_bevestigd_op' => 'datetime',
    ];

    public function diensten(): HasMany
    {
        return $this->hasMany(Dienst::class);
    }

    public function beoordelingen(): HasMany
    {
        return $this->hasMany(Leveranciersbeoordeling::class);
    }

    /** De nieuwste beoordeling bepaalt de herbeoordelingsdatum (§6). */
    public function nieuwsteBeoordeling(): HasOne
    {
        return $this->hasOne(Leveranciersbeoordeling::class)->latestOfMany('uitgevoerd_op');
    }

    public function contractclausules(): HasMany
    {
        return $this->hasMany(Contractclausule::class);
    }

    public function systemen(): HasMany
    {
        return $this->hasMany(Systeem::class, 'leverancier_id');
    }

    public function risicos(): HasMany
    {
        return $this->hasMany(Risico::class, 'gekoppeld_leverancier_id');
    }

    public function teruggaveDoor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'data_teruggave_door_id');
    }

    /** De geplande herbeoordeling ligt op de nieuwste beoordeling. */
    public function volgendeBeoordelingGepland(): mixed
    {
        return $this->nieuwsteBeoordeling?->volgende_beoordeling_gepland;
    }

    public function herbeoordelingVerstreken(): bool
    {
        return $this->volgendeBeoordelingGepland()?->isPast() ?? false;
    }

    /**
     * "Recht op audit" mag op twee manieren blijken (§3, deelproducten/09 §7):
     * een aanwezige `recht_op_audit`-clausule óf een nog geldig eigen ISO
     * 27001-certificaat van de leverancier.
     */
    public function heeftRechtOpAudit(): bool
    {
        $clausuleAanwezig = $this->contractclausules
            ->firstWhere('type', 'recht_op_audit')?->aanwezig ?? false;

        $certificaatGeldig = $this->eigen_certificering_geldig_tot !== null
            && ! $this->eigen_certificering_geldig_tot->isPast();

        return $clausuleAanwezig || $certificaatGeldig;
    }

    /** Het rapportagesignaal uit §11.2: hoog risico zonder aantoonbaar recht op audit. */
    public function isHoogRisicoZonderAuditrecht(): bool
    {
        return $this->risiconiveau === 'hoog' && ! $this->heeftRechtOpAudit();
    }

    /**
     * De reden waarom beëindigen (nog) niet kan, of `null` wanneer het wel kan
     * (§5). Zelfde vorm als `Incident::belemmeringVoorSluiten()`, zodat het
     * scherm de reden kan tonen in plaats van een grijze knop.
     *
     * `$teruggaveBevestigd` wordt meegegeven omdat de gebruiker die bevestiging
     * in hetzelfde formulier zet als waarin hij beëindigt.
     */
    public function belemmeringVoorBeeindigen(?bool $teruggaveBevestigd = null): ?string
    {
        $bevestigd = $teruggaveBevestigd ?? ($this->data_teruggave_bevestigd_op !== null);

        if (! $bevestigd) {
            return 'Bevestig eerst dat data en toegang zijn teruggegeven of vernietigd; beëindigen is de afronding, niet het startsein.';
        }

        return null;
    }

    public function auditBlok(): string
    {
        return 'leveranciers-derdenrisico';
    }
}
