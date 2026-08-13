<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\AfwijkingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Een afwijking in de zin van §10.2 — het deelproduct noemt dit een
 * non-conformiteit (implementatie/08 §2).
 */
class Afwijking extends Model
{
    /** @use HasFactory<AfwijkingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'afwijkingen';

    /** @var list<string> */
    protected $fillable = [
        'bron', 'omschrijving', 'status', 'incident_id', 'bevinding_id', 'eigenaar_id',
        'gesloten_door_id', 'gesloten_op',
    ];

    /** @var array<string, string> */
    protected $casts = ['gesloten_op' => 'datetime'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** De auditbevinding waaruit deze afwijking voortkwam (blok 11), indien van toepassing. */
    public function bevinding(): BelongsTo
    {
        return $this->belongsTo(Bevinding::class);
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'eigenaar_id');
    }

    public function sluiter(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gesloten_door_id');
    }

    public function grondoorzaken(): HasMany
    {
        return $this->hasMany(Grondoorzaak::class);
    }

    public function maatregelen(): HasMany
    {
        return $this->hasMany(CorrigerendeMaatregel::class);
    }

    public function isGesloten(): bool
    {
        return $this->gesloten_op !== null;
    }

    /**
     * De afleidingsregel uit implementatie/08 §5. `gesloten` staat er bewust
     * niet in: dat is een besluit, geen gevolg — zie Afwijkingafsluiting.
     */
    public function afgeleideStatus(): string
    {
        return match (true) {
            $this->isGesloten() => 'gesloten',
            $this->maatregelen()->exists() => 'actie_lopend',
            $this->grondoorzaken()->exists() => 'analyse',
            default => 'open',
        };
    }

    public function auditBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    public function auditOmschrijving(): string
    {
        return Str::limit($this->omschrijving, 80);
    }

    /**
     * Voltooide corrigerende maatregelen zonder effectieve toets: precies het
     * gat waar §10.2 naar vraagt. Zolang dit niet nul is, is de afwijking niet
     * aantoonbaar verholpen.
     *
     * `null` betekent "n.v.t.": er zijn helemaal geen corrigerende maatregelen
     * vastgelegd, en dan is de vraag naar de toetsstand niet aan de orde. Dat
     * is nadrukkelijk niet hetzelfde als nul — een nul zegt dat de behandeling
     * rond is, terwijl null zegt dat er nog niets is ingericht. Dat verschil
     * moet zichtbaar blijven, ook in de kopie voor de auditor.
     *
     * Op het model en niet in de view, omdat de schermkopie voor de auditor
     * hetzelfde getal nodig heeft (12h §5). Twee kopieën van deze telling lopen
     * uit elkaar, en dan zegt het document iets anders dan het scherm.
     *
     * Rekent op de al geladen relatie; laad `maatregelen.laatsteToets` mee.
     */
    public function nogTeToetsen(): ?int
    {
        if ($this->maatregelen->isEmpty()) {
            return null;
        }

        return $this->maatregelen
            ->where('status', 'voltooid')
            ->reject->isEffectiefBevonden()
            ->count();
    }

    /**
     * Dezelfde stand als tekst, voor plekken zonder badges — de schermkopie.
     * Zonder maatregelen "n.v.t.", zodat een leeg vakje daar niet als nul leest.
     */
    public function nogTeToetsenLabel(): string
    {
        return $this->nogTeToetsen() === null ? 'n.v.t.' : (string) $this->nogTeToetsen();
    }
}
