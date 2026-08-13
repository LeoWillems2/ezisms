<?php

namespace App\Models;

use Database\Factories\SynchronisatieLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het resultaat van één (buiten het systeem uitgevoerde) synchronisatie
 * (implementatie/14 §3/§6). Machinale log, dus géén `Auditeerbaar`. Het
 * vastleggen ervan werkt `status` en `laatste_synchronisatie_op` op de adapter
 * bij — die logica staat in het component (§6), niet hier.
 */
class SynchronisatieLog extends Model
{
    /** @use HasFactory<SynchronisatieLogFactory> */
    use HasFactory;

    protected $table = 'synchronisatie_logs';

    /** @var list<string> */
    protected $fillable = [
        'integratie_adapter_id', 'tijdstip', 'resultaat', 'aantal_verwerkte_records',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tijdstip' => 'datetime',
        'aantal_verwerkte_records' => 'integer',
    ];

    public function adapter(): BelongsTo
    {
        return $this->belongsTo(IntegratieAdapter::class, 'integratie_adapter_id');
    }
}
