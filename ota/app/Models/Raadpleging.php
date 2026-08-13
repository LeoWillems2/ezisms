<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Eén keer een bewijsstuk opgehaald, door één gebruiker (implementatie/05 §14).
 *
 * Net als bij [AuditLogregel] is de append-only guard een vangnet tegen
 * programmeerfouten, geen beveiligingscontrole.
 */
class Raadpleging extends Model
{
    protected $table = 'raadplegingen';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['bewijsstuk_id', 'gebruiker_id', 'geraadpleegd_op'];

    /** @var array<string, string> */
    protected $casts = ['geraadpleegd_op' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Raadplegingen zijn append-only en kunnen niet worden gewijzigd.');
        });

        static::deleting(function () {
            throw new RuntimeException('Raadplegingen zijn append-only en kunnen niet worden verwijderd.');
        });
    }

    /**
     * Verwijdert raadplegingen ouder dan de bewaartermijn (implementatie/05 §14).
     *
     * Dit is de enige plek die raadplegingen mag verwijderen, en het is bewust
     * een aparte methode in plaats van een `delete()` verspreid door de
     * codebase. Twee dingen die hier makkelijk misgaan:
     *
     * 1. Een massa-delete op de query builder vuurt géén model-events, dus de
     *    append-only guard hierboven houdt hem niet tegen. Die guard beschermt
     *    tegen het sleutelen aan losse regels, niet tegen een beleidsmatige
     *    opschoning — dat zijn twee verschillende dingen, en de guard kan het
     *    verschil niet zien.
     * 2. Niet `deleteGeaudit()` gebruiken. Die macro schrijft per rij een
     *    logregel, en dan verhuist het leesgedrag naar de audit trail in plaats
     *    van te verdwijnen. Dat holt zowel de bewaartermijn als het doel van
     *    deze opschoning uit.
     */
    public static function verwijderOuderDan(CarbonInterface $grens): int
    {
        return static::query()->where('geraadpleegd_op', '<', $grens)->delete();
    }

    public function bewijsstuk(): BelongsTo
    {
        return $this->belongsTo(Bewijsstuk::class);
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    /**
     * Per gebruiker het moment waarop deze het bewijsstuk voor het eerst
     * ophaalde. De *eerste* keer en niet de laatste: de vraag is of iemand het
     * document al had toen hij bevestigde, niet of hij het later nog eens
     * opzocht.
     *
     * @return Collection<int, Carbon>
     */
    public static function eersteRaadplegingPerGebruiker(?int $bewijsstukId): Collection
    {
        if ($bewijsstukId === null) {
            return collect();
        }

        return static::query()
            ->where('bewijsstuk_id', $bewijsstukId)
            ->groupBy('gebruiker_id')
            ->selectRaw('gebruiker_id, MIN(geraadpleegd_op) as eerste')
            ->pluck('eerste', 'gebruiker_id')
            ->map(fn ($tijdstip) => Carbon::parse($tijdstip));
    }
}
