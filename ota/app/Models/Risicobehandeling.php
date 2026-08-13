<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Risicobehandeling extends Model
{
    use Auditeerbaar;

    protected $table = 'risicobehandelingen';

    /** @var list<string> */
    protected $fillable = [
        'risico_id', 'behandeloptie', 'restrisico_score', 'geaccepteerd_door', 'geaccepteerd_op',
    ];

    /** @var array<string, string> */
    protected $casts = ['geaccepteerd_op' => 'date'];

    public function risico(): BelongsTo
    {
        return $this->belongsTo(Risico::class);
    }

    public function soaRegels(): BelongsToMany
    {
        return $this->belongsToMany(SoaRegel::class, 'risicobehandeling_soa_regel');
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        return 'Behandeling ('.$this->behandeloptie.') van '.($this->risico?->titel ?? 'risico');
    }
}
