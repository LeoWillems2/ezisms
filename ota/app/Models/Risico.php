<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\RisicoObserver;
use App\Support\Bandverschuiving;
use Database\Factories\RisicoFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([RisicoObserver::class])]
class Risico extends Model
{
    /** @use HasFactory<RisicoFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'risicos';

    /** @var list<string> */
    protected $fillable = [
        'titel', 'dreiging', 'kwetsbaarheid', 'gekoppeld_asset_id', 'gekoppeld_leverancier_id',
        'kans_niveau', 'impact_niveau', 'risicoscore', 'status', 'risico_eigenaar_id',
        'volgende_beoordeling_gepland', 'risicocriteria_versie_id',
    ];

    /** @var array<string, string> */
    protected $casts = ['volgende_beoordeling_gepland' => 'date'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'gekoppeld_asset_id');
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'risico_eigenaar_id');
    }

    /** Leveranciersrisico loopt via de gewone risicomotor (blok 9 §4). */
    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class, 'gekoppeld_leverancier_id');
    }

    public function behandelingen(): HasMany
    {
        return $this->hasMany(Risicobehandeling::class);
    }

    /**
     * De §4.1-kwesties waaruit dit risico is geïdentificeerd (plan 02b).
     *
     * Heet bewust `aanleidingen` en niet `issues`: het onderscheid met de
     * SoA-koppeling is dat die aan de *behandeling* hangt (een maatregel is een
     * antwoord), terwijl dit aan het risico zelf hangt (een kwestie is een
     * aanleiding). Twee koppelingen op twee niveaus — dat is de PDCA-volgorde,
     * geen toeval.
     *
     * Leeg is legitiem: risico's komen ook uit assets, incidenten, leveranciers
     * en audits. Het gat dat er wél toe doet loopt de andere kant op — een issue
     * zonder risico's; zie `IssuesOverzicht`.
     */
    public function aanleidingen(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_risico');
    }

    /**
     * De criteriaversie waaronder dit risico beoordeeld is (04g §2.6a). Leeg bij
     * een nog niet beoordeeld risico, en bij risico's van vóór die versionering.
     */
    public function criteriaVersie(): BelongsTo
    {
        return $this->belongsTo(RisicocriteriaVersie::class, 'risicocriteria_versie_id');
    }

    /** Is dit risico beoordeeld onder een inmiddels vervangen kader? */
    public function beoordeeldOnderOudCriterium(): bool
    {
        return $this->risicocriteria_versie_id !== null
            && $this->risicocriteria_versie_id !== RisicocriteriaVersie::actief()?->id;
    }

    /**
     * Fallback-standaarden voor als er (nog) geen actieve `RisicocriteriaVersie`
     * in de database staat. Beide grenzen worden door de organisatie vastgesteld,
     * dus dit zijn slechts vangnetten, geen vaste waarden — een half geseede
     * installatie hoort geen 500 te geven op het risicoregister.
     */
    public const DREMPEL_STANDAARD = 15;

    public const WAARSCHUWINGSDREMPEL_STANDAARD = 10;

    /**
     * De rode acceptatiedrempel: score > deze waarde is boven de risk appetite.
     *
     * Leest de gememoiseerde actieve versie: de matrix vraagt dit per cel, en
     * dat waren met de oude opzet net zoveel queries.
     */
    public static function drempelwaarde(): int
    {
        return RisicocriteriaVersie::actief()?->drempelwaarde_score
            ?? static::DREMPEL_STANDAARD;
    }

    /** De amber-waarschuwingsgrens: score >= deze waarde vraagt aandacht. */
    public static function waarschuwingsdrempel(): int
    {
        return RisicocriteriaVersie::actief()?->waarschuwingsdrempel_score
            ?? static::WAARSCHUWINGSDREMPEL_STANDAARD;
    }

    public function boventDrempel(): bool
    {
        return ($this->risicoscore ?? 0) > static::drempelwaarde();
    }

    /**
     * De semafoorkleur voor een risicoscore, als één bron van waarheid voor de
     * lijst, het detail én de tolerantiematrix (implementatie/04b §4). Beide
     * grenzen komen uit de actieve `RisicocriteriaVersie` — vastgesteld, niet
     * ingesteld — zodat de kleur niet losloopt van de "boven de drempel"-filter
     * of van de vastgestelde risk appetite.
     *
     * Zonder argumenten en dus altijd tegen het *huidige* kader. Wie de banden
     * onder andere drempels wil weten, gebruikt {@see Bandverschuiving};
     * deze methode wordt op zes plekken kaal aangeroepen en dat moet zo blijven.
     */
    public static function scoreKleur(?int $score): string
    {
        return match (true) {
            $score === null => 'zinc',                             // niet beoordeeld
            $score > static::drempelwaarde() => 'red',             // boven de acceptatiedrempel
            $score >= static::waarschuwingsdrempel() => 'amber',   // aandacht
            default => 'green',                                    // aanvaardbaar
        };
    }

    public function herbeoordelingVerstreken(): bool
    {
        return $this->volgende_beoordeling_gepland?->isPast() ?? false;
    }

    /**
     * Korte, stabiele verwijzing om een risico te citeren in een SoA-motivatie of
     * koppeling: "R-7", afgeleid van het id. Bewust het bestaande id — geen apart
     * gecureerd nummerveld — dus de reeks kan gaten hebben (R-7, R-8, R-12); wél
     * uniek en onveranderlijk. Eén bron van waarheid voor lijst én detail.
     */
    public function referentie(): string
    {
        return 'R-'.$this->id;
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }
}
