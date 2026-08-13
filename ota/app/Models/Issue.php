<?php

namespace App\Models;

use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $table = 'issues';

    /** @var list<string> */
    protected $fillable = ['aard', 'categorie', 'omschrijving', 'laatst_beoordeeld_op'];

    /** @var array<string, string> */
    protected $casts = ['laatst_beoordeeld_op' => 'date'];

    public function scopeVerklaringen(): BelongsToMany
    {
        return $this->belongsToMany(ScopeVerklaring::class, 'scope_verklaring_issue');
    }

    /**
     * De risico's die uit deze context-kwestie zijn voortgekomen (plan 02b).
     *
     * Beheerd vanaf de risicokant; hier alleen om te tonen waar de kwestie landt.
     */
    public function risicos(): BelongsToMany
    {
        return $this->belongsToMany(Risico::class, 'issue_risico');
    }
}
