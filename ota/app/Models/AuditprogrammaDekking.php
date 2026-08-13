<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De dekkings*planning*: per auditobject binnen een programma de risicogebaseerde
 * frequentie (plan 11b §4). `interval_jaren` = 1 (jaarlijks) t/m de cycluslengte
 * (eenmaal per cyclus). De concrete peiljaren volgen uit start + interval; de
 * *feitelijke* dekking komt uit de auditronde ↔ auditobject-koppeling.
 */
class AuditprogrammaDekking extends Model
{
    protected $table = 'auditprogramma_dekkingen';

    /** @var list<string> */
    protected $fillable = [
        'auditprogramma_id', 'auditobject_id', 'interval_jaren', 'gepland_start_programmajaar', 'toelichting',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'interval_jaren' => 'integer',
        'gepland_start_programmajaar' => 'integer',
    ];

    public function auditprogramma(): BelongsTo
    {
        return $this->belongsTo(Auditprogramma::class);
    }

    public function auditobject(): BelongsTo
    {
        return $this->belongsTo(Auditobject::class);
    }

    /**
     * De geplande programmajaren (1..N): vanaf het startjaar van deze regel met
     * stappen van `interval_jaren`. Bij interval 1 dus elk jaar; bij interval =
     * cycluslengte precies één keer.
     *
     * Rekent bewust in cyclusnummers en niet in kalenderjaren (plan 11c): een
     * dekkingsregel plant "eenmaal per cyclus vanaf jaar 2", en wat dat in
     * kalendertermen betekent hangt van de startdatum af.
     *
     * @return list<int>
     */
    public function geplandeProgrammajaren(int $aantalJaren): array
    {
        $start = max(1, $this->gepland_start_programmajaar ?: 1);
        $interval = max(1, $this->interval_jaren);

        $jaren = [];
        for ($nummer = $start; $nummer <= $aantalJaren; $nummer += $interval) {
            $jaren[] = $nummer;
        }

        return $jaren;
    }
}
