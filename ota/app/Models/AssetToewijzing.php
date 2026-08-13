<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetToewijzing extends Model
{
    use Auditeerbaar;

    protected $table = 'asset_toewijzingen';

    /** @var list<string> */
    protected $fillable = ['asset_id', 'gebruiker_id', 'toegewezen_op', 'geretourneerd_op'];

    /** @var array<string, string> */
    protected $casts = [
        'toegewezen_op' => 'date',
        'geretourneerd_op' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    public function auditBlok(): string
    {
        return 'asset-classificatie';
    }

    /** Annex A 5.11: uitreiken en retourneren van bedrijfsmiddelen. */
    public function auditOmschrijving(): string
    {
        return ($this->asset?->naam ?? 'Asset').' aan '.($this->gebruiker?->naam ?? 'onbekend');
    }
}
