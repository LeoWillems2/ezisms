<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interface naar een buiten-scope onderdeel (bijv. IT-beheer door een externe
 * partij). Class- en tabelnaam zijn bewust 'ScopeInterface'/'scope_interfaces':
 * 'interface' is een gereserveerd PHP-woord.
 */
class ScopeInterface extends Model
{
    protected $table = 'scope_interfaces';

    /** @var list<string> */
    protected $fillable = ['scope_verklaring_id', 'omschrijving', 'risico_implicatie'];

    public function scopeVerklaring(): BelongsTo
    {
        return $this->belongsTo(ScopeVerklaring::class);
    }
}
