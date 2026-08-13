<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\TrainingsmoduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Trainingsmodule extends Model
{
    /** @use HasFactory<TrainingsmoduleFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'trainingsmodules';

    /** @var list<string> */
    protected $fillable = ['titel', 'toets_bestand', 'geldigheidsduur_maanden', 'actief'];

    /** @var array<string, string> */
    protected $casts = [
        'geldigheidsduur_maanden' => 'integer',
        'actief' => 'boolean',
    ];

    public function doelgroepen(): BelongsToMany
    {
        return $this->belongsToMany(Doelgroep::class, 'doelgroep_trainingsmodule');
    }

    public function beleidsdocumenten(): BelongsToMany
    {
        return $this->belongsToMany(Beleidsdocument::class, 'beleidsdocument_trainingsmodule');
    }

    public function voltooiingen(): HasMany
    {
        return $this->hasMany(Trainingsvoltooiing::class);
    }

    public function toetsopdrachten(): HasMany
    {
        return $this->hasMany(Toetsopdracht::class);
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    /**
     * Hangt aan deze module een toets? Dan loopt voltooiing via de toets en niet
     * via zelfregistratie (§6).
     */
    public function heeftToets(): bool
    {
        return filled($this->toets_bestand);
    }

    /**
     * De enige bron voor "wie is de doelgroep": actieve gebruikers in álle aan
     * de module gekoppelde doelgroepen (union, gededupliceerd). Zowel de status,
     * de trainingsgraad als de herinneringstaken rekenen hiermee, zodat ze niet
     * uit elkaar kunnen lopen (§5, gemodelleerd naar
     * Beleidsdocument::doelgroepGebruikerIds()).
     *
     * @return list<int>
     */
    public function doelgroepGebruikerIds(): array
    {
        $doelgroepIds = $this->doelgroepen()->pluck('doelgroepen.id');

        if ($doelgroepIds->isEmpty()) {
            return [];
        }

        $lidIds = DB::table('doelgroep_gebruiker')
            ->whereIn('doelgroep_id', $doelgroepIds)
            ->pluck('gebruiker_id');

        return Gebruiker::where('status', 'actief')
            ->whereIn('id', $lidIds)
            ->pluck('id')
            ->all();
    }

    /**
     * De afgeleide status voor één gebruiker (§5): geen opgeslagen statusveld.
     *
     * @return 'voltooid'|'verlopen'|'te_doen'
     */
    public function statusVoor(Gebruiker $gebruiker): string
    {
        $nieuwste = $this->voltooiingen()
            ->where('gebruiker_id', $gebruiker->id)
            ->orderByDesc('voltooid_op')
            ->orderByDesc('id')
            ->first();

        if ($nieuwste === null) {
            return 'te_doen';
        }

        if ($nieuwste->verloopt_op !== null && $nieuwste->verloopt_op->isPast()) {
            return 'verlopen';
        }

        return 'voltooid';
    }

    /**
     * Percentage van de actieve doelgroep met een geldige voltooiing, of `null`
     * als de doelgroep leeg is (n.v.t. — nadrukkelijk niet 0%, zoals de
     * bevestigingsgraad bij beleid).
     */
    public function trainingsgraad(): ?int
    {
        $doelgroepIds = $this->doelgroepGebruikerIds();

        if ($doelgroepIds === []) {
            return null;
        }

        $geldig = $this->voltooiingen()
            ->geldig()
            ->whereIn('gebruiker_id', $doelgroepIds)
            ->distinct()
            ->count('gebruiker_id');

        return (int) round($geldig / count($doelgroepIds) * 100);
    }

    /**
     * Het enige punt dat een voltooiing aanmaakt (§6). Berekent `verloopt_op`
     * uit de geldigheidsduur op dít moment en zet de bron.
     *
     * Zelfregistratie is niet toegestaan voor een module mét toets: die
     * voltooiing moet via de toets lopen. De aanroepende component controleert
     * dit vooraf; deze guard is het vangnet.
     */
    public function registreerVoltooiing(Gebruiker $gebruiker, string $bron): Trainingsvoltooiing
    {
        if ($bron === 'zelfregistratie' && $this->heeftToets()) {
            throw new \RuntimeException('Zelfregistratie is niet toegestaan voor een module met een toets.');
        }

        $verlooptOp = $this->geldigheidsduur_maanden
            ? Carbon::today()->addMonths($this->geldigheidsduur_maanden)
            : null;

        return $this->voltooiingen()->create([
            'gebruiker_id' => $gebruiker->id,
            'voltooid_op' => Carbon::today(),
            'verloopt_op' => $verlooptOp,
            'bron' => $bron,
        ]);
    }

    public function auditBlok(): string
    {
        return 'bewustzijn-training';
    }

    public function auditOmschrijving(): string
    {
        return $this->titel;
    }
}
