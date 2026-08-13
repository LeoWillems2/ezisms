<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\ReviewsessieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een managementreview (§9.3). Auditeerbaar: de sessie is het bewijs van
 * directiebetrokkenheid (implementatie/13 §3).
 */
class Reviewsessie extends Model
{
    /** @use HasFactory<ReviewsessieFactory> */
    use Auditeerbaar, HasFactory;

    /** De negen verplichte §9.3-inputs die een geldige review moet behandelen. */
    public const VERPLICHTE_CATEGORIEEN = [
        'status_vorige_acties', 'context_wijzigingen', 'belanghebbende_feedback',
        'kpi_resultaten', 'auditresultaten', 'non_conformiteiten',
        'monitoring_resultaten', 'verbeterkansen', 'risico_resultaten',
    ];

    /** @var list<string> */
    protected $fillable = ['datum', 'deelnemers', 'status'];

    /** @var array<string, string> */
    protected $casts = ['datum' => 'date'];

    public function agendapunten(): HasMany
    {
        return $this->hasMany(Agendapunt::class);
    }

    public function besluiten(): HasMany
    {
        return $this->hasMany(Besluit::class);
    }

    /**
     * De reden waarom de review nog niet 'gehouden' mag heten, of `null` als het
     * wel kan — zelfde vorm als `Incident::belemmeringVoorSluiten()` (§4). Een
     * review is pas geldig bewijs als élk verplicht §9.3-onderwerp is behandeld.
     */
    public function belemmeringVoorHouden(): ?string
    {
        $aanwezig = $this->agendapunten()->pluck('categorie')->all();
        $ontbreekt = array_diff(self::VERPLICHTE_CATEGORIEEN, $aanwezig);

        if ($ontbreekt === []) {
            return null;
        }

        $leesbaar = array_map(fn (string $c) => str_replace('_', ' ', $c), $ontbreekt);

        return 'Nog niet alle verplichte §9.3-onderwerpen zijn behandeld: '.implode(', ', $leesbaar)
            .'. "Niets te melden" mag, maar leg dat expliciet vast in het betreffende agendapunt.';
    }

    public function auditBlok(): string
    {
        return 'management-review-verbetercyclus';
    }

    public function auditOmschrijving(): string
    {
        return 'Managementreview '.$this->datum?->format('d-m-Y');
    }
}
