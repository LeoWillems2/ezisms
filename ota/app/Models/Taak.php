<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\TaakObserver;
use App\Support\Koppelbaar;
use App\Support\Recordscope;
use Database\Factories\TaakFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TaakObserver::class])]
class Taak extends Model
{
    /** @use HasFactory<TaakFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'taken';

    /**
     * Statussen die nog aandacht vragen.
     *
     * `wachtend` staat hier bewust NIET bij (implementatie/07b §5). Deze
     * constante wordt op negen plekken gebruikt — planner, dashboard,
     * takenscherm, sweeps, demo — en door `wachtend` eruit te houden verdwijnt
     * een stap die op zijn beurt wacht daar overal vanzelf uit. Dat is de reden
     * voor de keuze, niet een bijwerking: zo'n stap vraagt niets van niemand.
     */
    public const OPENSTAAND = ['open', 'in_uitvoering', 'verlopen'];

    /** @var list<string> */
    protected $fillable = [
        'taaksjabloon_id', 'titel', 'omschrijving', 'eigenaar_id', 'deadline', 'status',
        'gekoppeld_blok_naam', 'gekoppeld_entiteit_type', 'gekoppeld_entiteit_id',
        'soort', 'escalatie_niveau', 'escalatie_op', 'voltooid_op',
        'volgorde', 'uitkomst', 'vraagt_uitkomst', 'sjabloonstap_id',
        'staptype', 'bewijs_verplicht', 'bij_afkeuren_terug_naar',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deadline' => 'date',
        'escalatie_op' => 'date',
        'voltooid_op' => 'date',
        'escalatie_niveau' => 'integer',
        'volgorde' => 'integer',
        'vraagt_uitkomst' => 'boolean',
        'bewijs_verplicht' => 'boolean',
        'bij_afkeuren_terug_naar' => 'integer',
    ];

    public function sjabloon(): BelongsTo
    {
        return $this->belongsTo(Taaksjabloon::class, 'taaksjabloon_id');
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'eigenaar_id');
    }

    public function entiteit(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'gekoppeld_entiteit_type', 'gekoppeld_entiteit_id');
    }

    /**
     * De toetsopdracht die deze taak uitvoert (blok 10), indien de taak een
     * uitgezette toets is. Via deze relatie bouwt het takenscherm de "Start
     * toets"-knop, zodat de token nergens in vrije tekst hoeft te staan.
     */
    public function toetsopdracht(): HasOne
    {
        return $this->hasOne(Toetsopdracht::class);
    }

    /**
     * Verlopen volgens de kalender, ongeacht of `isms:verloop-taken` al heeft
     * gedraaid. De opgeslagen status blijft leidend voor filteren en
     * rapporteren; dit is voor wat de gebruiker ziet, zodat een stilstaande
     * scheduler geen taak als "open" toont waarvan de deadline gisteren lag.
     */
    public function isFeitelijkVerlopen(): bool
    {
        // Een wachtende stap kan niet te laat zijn: hij is nog niet aan de beurt,
        // en `isms:verloop-taken` laat hem om dezelfde reden met rust.
        return ! in_array($this->status, ['voltooid', 'wachtend'], true)
            && $this->deadline->isPast();
    }

    /** Onderdeel van een stappenreeks (implementatie/07b §3). */
    public function isStap(): bool
    {
        return $this->volgorde !== null;
    }

    /**
     * De sjabloonstap waar deze stap uit voortkomt (blok 15). Nullable: de
     * engine kent geen sjablonen, dus een reeks kan ook zonder worden gestart.
     * De kolom komt uit de migratie van blok 15, niet uit die van 07b.
     *
     * Uitsluitend herkomst. Sinds migratie `000055` hangt er geen gedrag meer
     * aan: `staptype`, `bewijs_verplicht` en `bij_afkeuren_terug_naar` staan op
     * de taak zelf, zodat een aanpassing aan het sjabloon een lopend dossier
     * niet meer van gedrag laat veranderen (implementatie/15 §17).
     */
    public function sjabloonstap(): BelongsTo
    {
        return $this->belongsTo(Sjabloonstap::class);
    }

    /** Positief = te laat afgerond. Null zolang de taak niet voltooid is. */
    public function vertragingInDagen(): ?int
    {
        if (! $this->voltooid_op) {
            return null;
        }

        return (int) $this->deadline->diffInDays($this->voltooid_op, absolute: false);
    }

    /**
     * Leesbare weergave van de koppeling. Valt terug op het bronblok, want
     * taken uit een sjabloon hebben wel een blok maar geen entiteit — zonder
     * die terugval toont de kolom een streepje terwijl de informatie er is.
     */
    public function gekoppeldOmschrijving(): ?string
    {
        if ($this->gekoppeld_entiteit_type === null) {
            return $this->gekoppeld_blok_naam;
        }

        $label = Koppelbaar::TYPES[$this->gekoppeld_entiteit_type]['label']
            ?? ucfirst(str_replace('_', ' ', $this->gekoppeld_entiteit_type));

        $entiteit = $this->entiteit;

        return $entiteit
            ? $label.': '.$entiteit->auditOmschrijving()
            : $label.' #'.$this->gekoppeld_entiteit_id.' (verwijderd)';
    }

    /**
     * De deadline van een door TaakPlanner beheerde taak komt uit het bronblok
     * (bijv. `risicos.volgende_beoordeling_gepland`). Hem hier wijzigen heeft
     * geen zin: de eerstvolgende observer-run zet hem terug.
     *
     * Bij een stap geldt hetzelfde om een andere reden (implementatie/07b §8):
     * de datum volgt uit het dossier plus de offset uit het sjabloon. Dat dit
     * ook `isHeropenbaar()` op false zet is gewenst — een stap gaat alleen via
     * `Stappenreeks::heropenVanaf()` terug, zodat de reeks klopt blijft.
     */
    public function deadlineWordtBeheerd(): bool
    {
        return $this->soort !== null || $this->volgorde !== null;
    }

    public function isVanMij(): bool
    {
        return $this->eigenaar_id !== null && $this->eigenaar_id === Auth::id();
    }

    /**
     * Mag een voltooide taak terug op onvoltooid? Alleen bij een "gewone" taak
     * waarvan de status zelf de waarheid is. Bij een beheerde taak (soort ≠ null)
     * is voltooid een afgeleide van het bronblok en zet de observer hem toch
     * terug; bij een toetstaak komt voltooid uit de callback bij een geslaagde
     * toets en blijft die uitslag onherroepelijk staan. Heropenen zou daar de
     * indruk wekken iets ongedaan te maken wat feitelijk blijft bestaan.
     */
    public function isHeropenbaar(): bool
    {
        return ! $this->deadlineWordtBeheerd() && $this->toetsopdracht === null;
    }

    /** Record-scoping: zonder volledige inzage zie je alleen je eigen taken. */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->unless(
            Recordscope::magAllesZien('taken-workflow-engine'),
            fn (Builder $q) => $q->where('eigenaar_id', Auth::id())
        );
    }

    public function auditBlok(): string
    {
        return 'taken-workflow-engine';
    }

    public function auditOmschrijving(): string
    {
        return $this->titel;
    }
}
