<?php

namespace App\Models;

use Database\Factories\NotificatieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De log van elke uitgaande verzendpoging (implementatie/14 §3). Bewust géén
 * `Auditeerbaar`: dit is een machinale operationele log, van nature append-only;
 * die via `audit_logregels` dubbelen voegt niets toe (zelfde lijn als
 * `synchronisatie_logs`).
 */
class Notificatie extends Model
{
    /** @use HasFactory<NotificatieFactory> */
    use HasFactory;

    protected $table = 'notificaties';

    /** @var list<string> */
    protected $fillable = [
        'notificatieregel_id', 'gebeurtenis_type', 'gebruiker_id',
        'gegenereerd_op', 'verzonden_op', 'resultaat', 'fout',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gegenereerd_op' => 'datetime',
        'verzonden_op' => 'datetime',
    ];

    public function regel(): BelongsTo
    {
        return $this->belongsTo(Notificatieregel::class, 'notificatieregel_id');
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }
}
