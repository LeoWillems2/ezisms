<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Wijzigingsroutes;
use Database\Factories\WijzigingssjabloonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De reeks stappen die een wijziging van een bepaalde soort en zwaarte
 * doorloopt (implementatie/15 §3).
 *
 * Auditeerbaar, net als de risicocriteria in 04g: dit is configuratie die de
 * compliance-uitkomst bepaalt, en dan hoort herleidbaar te zijn wie hem wanneer
 * heeft aangepast.
 */
class Wijzigingssjabloon extends Model
{
    /** @use HasFactory<WijzigingssjabloonFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'wijzigingssjablonen';

    /**
     * Staptypen die een route moet hebben om A.8.32 te dekken, met de reden
     * erbij (implementatie/15 §19).
     *
     * Bewust een waarschuwing en geen verbod: een organisatie mag afwijken, ze
     * hoort alleen te zien dát ze het doet. `analyse` en `informeren` staan er
     * niet bij — niet elke wijziging vraagt een impactanalyse vooraf of raakt
     * iemand, en de spoedroute zou anders uit zichzelf al klagen.
     *
     * @var array<string, string>
     */
    public const VEREISTE_STAPTYPEN = [
        'goedkeuring' => 'autorisatie van de wijziging (A.8.32 b)',
        'uitvoeren' => 'de implementatie zelf (A.8.32 e)',
        'evaluatie' => 'het vastleggen van de uitkomst (A.8.32 g)',
    ];

    /** @var list<string> */
    protected $fillable = ['naam', 'omschrijving', 'soort', 'zwaarte', 'actief', 'geleverd'];

    /** @var array<string, string> */
    protected $casts = ['actief' => 'boolean', 'geleverd' => 'boolean'];

    public function stappen(): HasMany
    {
        return $this->hasMany(Sjabloonstap::class)->orderBy('volgorde')->orderBy('id');
    }

    public function wijzigingen(): HasMany
    {
        return $this->hasMany(Wijziging::class);
    }

    /**
     * De vereiste staptypen die deze route mist, met de reden.
     *
     * Dit is het signaal dat het echte risico dekt: een route raakt niet alleen
     * kapot door een stap te verwijderen, maar net zo goed door een staptype te
     * veranderen. Beide komen hier uit.
     *
     * @return array<string, string>
     */
    public function ontbrekendeStaptypen(): array
    {
        $aanwezig = $this->stappen->pluck('staptype')->unique();

        return array_filter(
            self::VEREISTE_STAPTYPEN,
            fn (string $staptype) => ! $aanwezig->contains($staptype),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Een geleverde route die de organisatie heeft bijgesteld. */
    public function isAangepast(): bool
    {
        return $this->geleverd && Wijzigingsroutes::wijktAf($this);
    }

    public function auditBlok(): string
    {
        return 'wijzigingsbeheer';
    }
}
