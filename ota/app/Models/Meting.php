<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén onveranderlijk meetpunt (implementatie/12 §2c): nooit een update of
 * herberekening met terugwerkende kracht. Teller en noemer worden opgeslagen,
 * het percentage wordt afgeleid (§2a) — "61 van 90" is reconstrueerbaar, "68%"
 * niet, en de noemer beweegt mee.
 */
class Meting extends Model
{
    protected $table = 'metingen';

    /** @var list<string> */
    protected $fillable = [
        'kpi_definitie_id', 'gemeten_op', 'periode_van', 'periode_tot', 'teller', 'noemer',
        'definitie_versie', 'streefwaarde', 'signaalwaarde', 'toelichting',
        'ingevoerd_door_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gemeten_op' => 'date',
        'periode_van' => 'datetime',
        'periode_tot' => 'datetime',
        'teller' => 'integer',
        'noemer' => 'integer',
        'definitie_versie' => 'integer',
        'streefwaarde' => 'float',
        'signaalwaarde' => 'float',
    ];

    public function kpiDefinitie(): BelongsTo
    {
        return $this->belongsTo(KpiDefinitie::class);
    }

    /** Leeg bij een berekend meetpunt: dat legde `isms:meet-kpis` vast. */
    public function ingevoerdDoor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'ingevoerd_door_id');
    }

    /** Wie dit meetpunt vastlegde — of de maandelijkse meting, als niemand. */
    public function herkomst(): string
    {
        return $this->ingevoerd_door_id === null
            ? 'Automatisch'
            : ($this->ingevoerdDoor?->naam ?? 'Verwijderd account');
    }

    /** Meet dit punt gebeurtenissen in een periode in plaats van de toestand op één moment? */
    public function isGebeurtenis(): bool
    {
        return $this->periode_tot !== null;
    }

    /** Afgeleid percentage voor ratio-KPI's; `null` als er niets te meten viel. */
    public function percentage(): ?float
    {
        return $this->noemer > 0 ? round($this->teller / $this->noemer * 100, 1) : null;
    }

    /** Afgeleid gemiddelde (teller/noemer) voor 'dagen'-KPI's. */
    public function gemiddelde(): ?float
    {
        return $this->noemer > 0 ? round($this->teller / $this->noemer, 1) : null;
    }
}
