<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Uitsluiting extends Model
{
    use Auditeerbaar;

    protected $table = 'uitsluitingen';

    /** @var list<string> */
    protected $fillable = ['scope_verklaring_id', 'omschrijving', 'motivatie'];

    public function scopeVerklaring(): BelongsTo
    {
        return $this->belongsTo(ScopeVerklaring::class);
    }

    public function auditBlok(): string
    {
        return 'context-scope';
    }

    public function auditOmschrijving(): string
    {
        return 'Uitsluiting: '.Str::limit($this->omschrijving, 60);
    }
}
