<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Recordscope;
use Database\Factories\TrainingsvoltooiingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Onherroepelijk, net als Leesbevestiging: aanmaken, niet bewerken/wissen. Juist
 * dat maakt de voltooiingshistorie bruikbaar als bewijs bij Annex A 6.3
 * (implementatie/10 §4). De bron (zelfregistratie of toets) legt vast hoe de
 * voltooiing ontstond.
 */
class Trainingsvoltooiing extends Model
{
    /** @use HasFactory<TrainingsvoltooiingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'trainingsvoltooiingen';

    /** @var list<string> */
    protected $fillable = ['trainingsmodule_id', 'gebruiker_id', 'voltooid_op', 'verloopt_op', 'bron'];

    /** @var array<string, string> */
    protected $casts = [
        'voltooid_op' => 'date',
        'verloopt_op' => 'date',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Trainingsmodule::class, 'trainingsmodule_id');
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    /** Nog geldig: geen verloopdatum, of die ligt niet in het verleden. */
    public function scopeGeldig(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('verloopt_op')
            ->orWhereDate('verloopt_op', '>=', now()->toDateString()));
    }

    /** Record-scoping: zonder volledige inzage zie je alleen je eigen rijen. */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->unless(
            Recordscope::magAllesZien('bewustzijn-training'),
            fn (Builder $q) => $q->where('gebruiker_id', Auth::id())
        );
    }

    public function auditBlok(): string
    {
        return 'bewustzijn-training';
    }

    public function auditOmschrijving(): string
    {
        return 'Trainingsvoltooiing '.($this->module?->titel ?? '')
            .' door '.($this->gebruiker?->naam ?? '?');
    }
}
