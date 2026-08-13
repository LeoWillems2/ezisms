<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\RisicocriteriaVersieObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De risicocriteria uit ISO 27001 §6.1.2 a) als vastgesteld kader met eigen
 * versies (implementatie/04g).
 *
 * Eén versie draagt het hele kader: de risk-appetite-verklaring, de rode
 * acceptatiedrempel, de amber-waarschuwingsgrens, de leidraad per as en de tien
 * niveaudefinities. De CISO stelt op en dient in, Management activeert — zie
 * `RisicoCriteria` voor waarom dat uit de bestaande rechtenladder valt.
 *
 * Statusgang `concept → ter_goedkeuring → actief → vervangen`, gelijk aan
 * `ScopeVerklaring`. Een vervangen versie blijft staan: elk risico verwijst naar
 * de versie waaronder het beoordeeld is.
 */
#[ObservedBy([RisicocriteriaVersieObserver::class])]
class RisicocriteriaVersie extends Model
{
    use Auditeerbaar;

    protected $table = 'risicocriteria_versies';

    /** @var list<string> */
    protected $fillable = [
        'versienummer', 'status', 'omschrijving', 'drempelwaarde_score',
        'waarschuwingsdrempel_score', 'leidraad_kans', 'leidraad_impact',
        'geldig_vanaf', 'goedgekeurd_door', 'beleidsdocument_id', 'besluit_id',
        'volgende_herziening_gepland', 'wijzigingsreden',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'drempelwaarde_score' => 'integer',
        'waarschuwingsdrempel_score' => 'integer',
        'geldig_vanaf' => 'date',
        'volgende_herziening_gepland' => 'date',
    ];

    /**
     * De actieve versie, onthouden binnen het verzoek.
     *
     * Memoisatie is hier geen optimalisatie maar een voorwaarde. De matrixblade
     * vraagt per cel de drempels op en per as-label een niveaunaam; met een
     * tabel erachter zijn dat tientallen queries per render, waar het er met de
     * config nul waren. Zie `Beoordelingsschaal`.
     */
    private static ?self $memo = null;

    private static bool $memoGeladen = false;

    public static function actief(): ?self
    {
        if (! self::$memoGeladen) {
            self::$memo = self::query()->where('status', 'actief')->first();
            self::$memoGeladen = true;
        }

        return self::$memo;
    }

    /**
     * Vergeet de gememoiseerde actieve versie.
     *
     * Aangeroepen door `ActiveerRisicocriteria` en door de observer, en door
     * `Tests\TestCase::setUp()` — een statische memo overleeft de testtransactie
     * en zonder die reset krijgt de tweede test in een klasse de criteria van de
     * eerste te zien.
     */
    public static function vergeet(): void
    {
        self::$memo = null;
        self::$memoGeladen = false;
    }

    public function niveaus(): HasMany
    {
        return $this->hasMany(Beoordelingsniveau::class);
    }

    public function risicos(): HasMany
    {
        return $this->hasMany(Risico::class);
    }

    public function beleidsdocument(): BelongsTo
    {
        return $this->belongsTo(Beleidsdocument::class);
    }

    public function besluit(): BelongsTo
    {
        return $this->belongsTo(Besluit::class);
    }

    /** De vijf niveaus van één as, gesleuteld op 1 t/m 5. */
    public function niveausVan(string $as): Collection
    {
        return $this->niveaus
            ->where('as', $as)
            ->sortBy('niveau')
            ->keyBy('niveau');
    }

    /** Inhoud is alleen te wijzigen zolang de versie nog concept is. */
    public function isBewerkbaar(): bool
    {
        return $this->status === 'concept';
    }

    public function herzieningVerstreken(): bool
    {
        return $this->status === 'actief'
            && ($this->volgende_herziening_gepland?->isPast() ?? false);
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        return 'Risicocriteria v'.$this->versienummer
            .' (acceptatiedrempel '.$this->drempelwaarde_score.')';
    }
}
