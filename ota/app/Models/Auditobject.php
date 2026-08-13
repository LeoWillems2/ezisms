<?php

namespace App\Models;

use Database\Factories\AuditobjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eén auditeerbaar object in de audit-universe (plan 11b §3). Twee soorten:
 * een `clausule` uit de hoofdtekst (eigen korte titel, geen ISO-tekst) of een
 * `maatregel` die *verwijst* naar een Annex A-maatregel (blok 4) — bewust geen
 * kopie van de normtekst. Referentiedata: geseed (clausules) of gesynct
 * (maatregelen), niet Auditeerbaar.
 */
class Auditobject extends Model
{
    /** @use HasFactory<AuditobjectFactory> */
    use HasFactory;

    protected $table = 'auditobjecten';

    /** thema (Maatregel) => Bijlage-A-groep voor weergave/sortering. */
    public const THEMA_GROEP = [
        'organisatorisch' => 'A.5 Organisatorisch',
        'mensgericht' => 'A.6 Mensgericht',
        'fysiek' => 'A.7 Fysiek',
        'technologisch' => 'A.8 Technologisch',
    ];

    /** @var list<string> */
    protected $fillable = [
        'soort', 'clausule_nummer', 'titel', 'maatregel_id', 'groep', 'volgorde', 'actief',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'volgorde' => 'integer',
        'actief' => 'boolean',
    ];

    public function maatregel(): BelongsTo
    {
        return $this->belongsTo(Maatregel::class);
    }

    public function auditrondes(): BelongsToMany
    {
        return $this->belongsToMany(Auditronde::class, 'auditronde_auditobject');
    }

    public function dekkingen(): BelongsToMany
    {
        return $this->belongsToMany(Auditprogramma::class, 'auditprogramma_dekkingen')
            ->withPivot(['interval_jaren', 'gepland_start_programmajaar', 'toelichting']);
    }

    /** @param Builder<Auditobject> $query */
    public function scopeActief(Builder $query): void
    {
        $query->where('actief', true);
    }

    /**
     * De weergavecode: bij een clausule het nummer ("8.1"), bij een maatregel de
     * Annex A-referentie uit de relatie ("A.8.8") — canoniek op Maatregel, hier
     * niet gedupliceerd.
     */
    public function refCode(): string
    {
        if ($this->soort === 'clausule') {
            return (string) $this->clausule_nummer;
        }

        return 'A.'.($this->maatregel?->annex_a_referentie ?? '?');
    }

    /** De omschrijving: eigen titel bij een clausule, de maatregelnaam bij een maatregel. */
    public function omschrijving(): string
    {
        if ($this->soort === 'clausule') {
            return (string) $this->titel;
        }

        return (string) ($this->maatregel?->naam ?? '');
    }

    /**
     * Leesbare aanduiding voor de audit trail (06b). Het auditobject draagt de
     * Auditeerbaar-trait niet — het is referentiedata — maar het wordt wél
     * gekoppeld, en dan is "Auditobject #12" waardeloos: bij een maatregel zit
     * de titel niet eens in dit record maar in de maatregel.
     */
    public function auditOmschrijving(): string
    {
        return trim($this->refCode().' '.$this->omschrijving());
    }

    /** De Bijlage-A-groep bij een maatregelthema; onbekend thema → generieke bak. */
    public static function groepVoorThema(?string $thema): string
    {
        return self::THEMA_GROEP[$thema] ?? 'Bijlage A';
    }

    /** "5.9" → 5009, "8.12" → 8012 — stabiele sortering binnen het thema. */
    public static function volgordeVoorReferentie(?string $referentie): int
    {
        $delen = explode('.', (string) $referentie);

        return ((int) ($delen[0] ?? 0)) * 1000 + (int) ($delen[1] ?? 0);
    }

    /**
     * Houdt het maatregel-object in de pas met de SoA-scope van één control
     * (plan 11b §3). Van toepassing → actief object (aangemaakt indien nodig);
     * anders → een bestaand object inactief, maar nooit een inactief object
     * aangemaakt. Gedeeld door de SoaRegel-observer (live) en de sync-command
     * (bulk), zodat beide dezelfde groep/volgorde afleiden.
     */
    public static function synchroniseerMaatregel(Maatregel $maatregel, bool $vanToepassing): void
    {
        $object = static::firstOrNew([
            'soort' => 'maatregel',
            'maatregel_id' => $maatregel->id,
        ]);

        if (! $vanToepassing) {
            if ($object->exists && $object->actief) {
                $object->update(['actief' => false]);
            }

            return;
        }

        $object->fill([
            'groep' => static::groepVoorThema($maatregel->thema),
            'volgorde' => static::volgordeVoorReferentie($maatregel->annex_a_referentie),
            'actief' => true,
        ])->save();
    }
}
