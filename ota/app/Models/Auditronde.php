<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\AuditrondeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * Een auditronde binnen een plan (intern of extern). De record-guards hieronder
 * zijn de kern van dit blok (implementatie/11 §4): de onafhankelijkheid wordt
 * afgedwongen door het CISO-schrijfrecht op dít record weg te nemen, niet door
 * de Auditor-rol een blanket schrijfrecht te geven.
 */
class Auditronde extends Model
{
    /** @use HasFactory<AuditrondeFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'auditrondes';

    /** @var list<string> */
    protected $fillable = [
        'auditplan_id', 'type', 'telt_mee_voor_dekking', 'gepland_op', 'uitgevoerd_op',
        'auditor_gebruiker_id', 'extern_auditor_naam', 'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gepland_op' => 'date',
        'uitgevoerd_op' => 'date',
        'telt_mee_voor_dekking' => 'boolean',
    ];

    /** De interne typen: een nulmeting ís een interne audit (plan 11c fase 1). */
    public const INTERNE_TYPEN = ['intern', 'intern_nulmeting'];

    protected static function booted(): void
    {
        // Een nulmeting dekt per definitie alles in één keer en hoort daarom niet
        // in de dekkingsmatrix. Dat is de regel, geen automatisme dat je niet kunt
        // overrulen: geeft de aanroeper de vlag expliciet mee, dan wint die.
        //
        // De vlag wordt hier altijd gezet, ook op `true`, en niet aan het
        // kolomdefault overgelaten. Anders staat hij ná Auditronde::create() in
        // het geheugen op null — falsy — terwijl de database `true` bevat, en
        // gedraagt hetzelfde object zich anders vóór en ná een refresh.
        static::creating(function (self $ronde) {
            if (! array_key_exists('telt_mee_voor_dekking', $ronde->getAttributes())) {
                $ronde->telt_mee_voor_dekking = $ronde->type !== 'intern_nulmeting';
            }
        });
    }

    /** @param  Builder<self>  $query */
    public function scopeDekkend($query): void
    {
        $query->where('telt_mee_voor_dekking', true);
    }

    public function auditplan(): BelongsTo
    {
        return $this->belongsTo(Auditplan::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'auditor_gebruiker_id');
    }

    public function organisatieEenheden(): BelongsToMany
    {
        return $this->belongsToMany(OrganisatieEenheid::class, 'auditronde_organisatie_eenheid');
    }

    /** De normatieve scope-as (plan 11b): welke clausules/controls deze ronde dekt. */
    public function auditobjecten(): BelongsToMany
    {
        return $this->belongsToMany(Auditobject::class, 'auditronde_auditobject');
    }

    public function bevindingen(): HasMany
    {
        return $this->hasMany(Bevinding::class);
    }

    /**
     * Een nulmeting telt hier mee: hij wordt door dezelfde interne auditor
     * uitgevoerd en valt dus onder dezelfde record-guards uit blok 11 §4. Alleen
     * de dekkingstelling behandelt hem anders, en dat loopt via de vlag.
     */
    public function isIntern(): bool
    {
        return in_array($this->type, self::INTERNE_TYPEN, true);
    }

    public function isNulmeting(): bool
    {
        return $this->type === 'intern_nulmeting';
    }

    /** "Intern nulmeting" leest niet; alleen dat type krijgt een eigen naam. */
    public static function labelVoorType(string $type): string
    {
        return $type === 'intern_nulmeting'
            ? 'Interne nulmeting'
            : ucfirst(str_replace('_', ' ', $type));
    }

    public function typeLabel(): string
    {
        return self::labelVoorType($this->type);
    }

    public function isAfgerond(): bool
    {
        return $this->status === 'afgerond';
    }

    /**
     * Wie de ronde-status mag doorzetten (gepland → in_uitvoering → afgerond):
     * bij een interne ronde de toegewezen auditor, bij een externe de CISO.
     */
    public function magUitvoerenDoor(Gebruiker $gebruiker): bool
    {
        if ($this->isIntern()) {
            return $this->auditor_gebruiker_id !== null
                && $gebruiker->id === $this->auditor_gebruiker_id;
        }

        return Gate::forUser($gebruiker)->allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    /**
     * Wie de bevinding-inhoud (type/omschrijving/maatregel) mag vastleggen — §4a.
     * Ná 'afgerond' niemand, ook de CISO niet: dat is wat de onafhankelijkheid
     * afdwingt in plaats van documenteert.
     */
    public function magBevindingBewerkenDoor(Gebruiker $gebruiker): bool
    {
        if ($this->isAfgerond()) {
            return false;
        }

        if ($this->isIntern()) {
            return $this->status === 'in_uitvoering'
                && $this->auditor_gebruiker_id !== null
                && $gebruiker->id === $this->auditor_gebruiker_id;
        }

        // extern_*: de CISO transcribeert een reeds geautoriseerd extern rapport
        // (kleiner transcriptierisico), zolang de ronde niet is afgerond.
        return Gate::forUser($gebruiker)->allows('heeft-niveau', ['auditmanagement', 'muteren']);
    }

    public function auditBlok(): string
    {
        return 'auditmanagement';
    }

    public function auditOmschrijving(): string
    {
        return $this->typeLabel().' — plan '.($this->auditplan?->jaar ?? '?');
    }
}
