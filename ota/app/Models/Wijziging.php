<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Stapbelemmering;
use App\Support\Stappenreeks;
use Database\Factories\WijzigingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Een wijziging aan een informatieverwerkende faciliteit of informatiesysteem
 * (A.8.32). Dit is een register: de auditvraag is "toon alle wijzigingen van
 * het afgelopen jaar met hun goedkeuring", en dat is een query over deze tabel
 * — niet over een processtatus.
 *
 * De stappen staan in `taken` (implementatie/07b); dit model draagt alleen de
 * dossiervelden en de regels die het bronblok toebehoren.
 */
class Wijziging extends Model implements Stapbelemmering
{
    /** @use HasFactory<WijzigingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'wijzigingen';

    /** Statussen waarin het dossier nog loopt. */
    public const LOPEND = ['aangemeld', 'in_behandeling', 'uitgevoerd'];

    /** Eindstanden: read-only (§2a). */
    public const AFGEROND = ['gesloten', 'afgewezen', 'geannuleerd'];

    /** @var list<string> */
    protected $fillable = [
        'titel', 'wijzigingssjabloon_id', 'soort', 'zwaarte', 'leverancier_id',
        'aangemeld_door_id', 'externe_referentie', 'aangekondigd_op', 'gepland_op',
        'uitgevoerd_op', 'impact_toelichting', 'terugvalplan', 'status',
        'geslaagd', 'teruggedraaid', 'evaluatie',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'aangekondigd_op' => 'date',
        'gepland_op' => 'date',
        'uitgevoerd_op' => 'date',
        'geslaagd' => 'boolean',
        'teruggedraaid' => 'boolean',
    ];

    public function sjabloon(): BelongsTo
    {
        return $this->belongsTo(Wijzigingssjabloon::class, 'wijzigingssjabloon_id');
    }

    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class);
    }

    public function aangemeldDoor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'aangemeld_door_id');
    }

    public function systemen(): BelongsToMany
    {
        return $this->belongsToMany(Systeem::class, 'systeem_wijziging');
    }

    /** Incidenten die door deze wijziging zijn veroorzaakt. */
    public function incidenten(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * De stappenreeks van dit dossier. Geen Eloquent-relatie: wat een reeks
     * bij elkaar houdt is de polymorfe koppeling plus een gevulde volgorde, en
     * dat is precies wat `Stappenreeks` weet (07b §3).
     *
     * @return Collection<int, Taak>
     */
    public function stappen(): Collection
    {
        return Stappenreeks::voorEntiteit($this);
    }

    public function isAfgerond(): bool
    {
        return in_array($this->status, self::AFGEROND, true);
    }

    /**
     * De inhoudelijke eisen die aan een stap vastzitten (§6). Wordt vanuit
     * `TaakObserver::updating()` gevraagd, dus deze regels gelden ook wanneer
     * de stap vanaf `/taken` wordt afgevinkt.
     */
    public function belemmeringVoorStap(Taak $stap): ?string
    {
        // Van de taak zelf en niet van de sjabloonstap: de eisen liggen vast op
        // het moment dat de reeks start (§17). Een sjabloon dat later wordt
        // versoepeld mag een controle die al gold niet alsnog uitzetten.

        // A.8.32 f): nood- en voorzorgsoverwegingen, met inbegrip van
        // vangnetprocedures. De enige harde inhoudelijke eis van dit blok.
        if ($stap->staptype === 'uitvoeren' && blank($this->terugvalplan)) {
            return 'Leg eerst het terugvalplan vast; zonder vangnet mag deze wijziging niet worden uitgevoerd.';
        }

        // Het bewijs hangt aan het dossier en niet aan de losse stap: een
        // bewijsstuk is per wijziging gekoppeld (blok 5.1). Deze controle vraagt
        // dus of er überhaupt bewijs is, niet of het bij déze stap hoort.
        if ($stap->bewijs_verplicht && ! $this->heeftBewijs()) {
            return 'Koppel eerst een bewijsstuk aan deze wijziging; deze stap vraagt om onderbouwing.';
        }

        return null;
    }

    public function heeftBewijs(): bool
    {
        return BewijsKoppeling::query()
            ->where('entiteit_type', $this->getMorphClass())
            ->where('entiteit_id', $this->getKey())
            ->exists();
    }

    /**
     * Uitgevoerd zonder vastgelegd terugvalplan — hoort nul te zijn en is
     * daarom een gap-signaal en een KPI (§10).
     */
    public function scopeUitgevoerdZonderTerugvalplan(Builder $query): Builder
    {
        return $query->whereIn('status', ['uitgevoerd', 'gesloten'])
            ->where(fn (Builder $q) => $q->whereNull('terugvalplan')->orWhere('terugvalplan', ''));
    }

    public function auditBlok(): string
    {
        return 'wijzigingsbeheer';
    }

    public function auditOmschrijving(): string
    {
        return $this->titel;
    }
}
