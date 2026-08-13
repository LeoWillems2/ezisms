<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\NotificatieregelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De configuratie van de notificatielaag: welke gebeurtenis mailt wie, aan/uit
 * (implementatie/14 §3). Een regel uitzetten is een bestuurlijke daad en dus
 * Auditeerbaar.
 */
class Notificatieregel extends Model
{
    /** @use HasFactory<NotificatieregelFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'notificatieregels';

    /** @var list<string> */
    protected $fillable = ['gebeurtenis_type', 'ontvanger_rol', 'actief'];

    /** @var array<string, string> */
    protected $casts = ['actief' => 'boolean'];

    public function notificaties(): HasMany
    {
        return $this->hasMany(Notificatie::class);
    }

    public function auditBlok(): string
    {
        return 'notificatie-integratielaag';
    }

    public function auditOmschrijving(): string
    {
        return $this->gebeurtenis_type.' → '.($this->ontvanger_rol ?? 'betrokkene');
    }
}
