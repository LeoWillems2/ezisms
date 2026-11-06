<?php

namespace App\Models;

use App\Models\Concerns\Waardenbewaking;
use App\Support\Assistent\Aanroepcontext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Eén aanroep van een tool of resource door een gekoppelde assistent
 * (implementatie/14b §2.3, §7).
 *
 * Leesgedrag, dus niet in de audit trail. Net als bij [Raadpleging] is de
 * append-only guard een vangnet tegen programmeerfouten, geen
 * beveiligingscontrole.
 */
class AssistentAanroep extends Model
{
    use Waardenbewaking;

    /** @var list<string> */
    public const SOORTEN = ['tool', 'resource'];

    /** @var list<string> */
    public const UITKOMSTEN = [Aanroepcontext::OK, Aanroepcontext::GEWEIGERD, Aanroepcontext::FOUT];

    protected $table = 'assistent_aanroepen';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'gebruiker_id', 'client_id', 'client_naam', 'soort', 'naam', 'argumenten',
        'aantal_resultaten', 'bewijsstuk_id', 'uitkomst', 'duur_ms', 'aangeroepen_op',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'argumenten' => 'array',
            'aangeroepen_op' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Assistentaanroepen zijn append-only en kunnen niet worden gewijzigd.');
        });

        static::deleting(function () {
            throw new RuntimeException('Assistentaanroepen zijn append-only en kunnen niet worden verwijderd.');
        });
    }

    /**
     * Verwijdert regels ouder dan de bewaartermijn (14b §7.3). Een massa-delete
     * op de builder, zoals bij [Raadpleging::verwijderOuderDan]: de guard
     * hierboven houdt die bewust niet tegen, en `deleteGeaudit()` zou het
     * leesgedrag naar de audit trail verhuizen.
     */
    public static function verwijderOuderDan(CarbonInterface $grens): int
    {
        return static::query()->where('aangeroepen_op', '<', $grens)->delete();
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gebruiker_id');
    }
}
