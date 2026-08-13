<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\IntegratieAdapterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Het register van externe koppelingen (implementatie/14 §3/§6). Houdt bij *dát*
 * een koppeling bestaat, van welk type, en of de laatste sync lukte — niet hoe
 * die technisch werkt. Registreren of aan-/uitzetten is een bestuurlijke daad en
 * dus Auditeerbaar.
 */
class IntegratieAdapter extends Model
{
    /** @use HasFactory<IntegratieAdapterFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'integratie_adapters';

    /** @var list<string> */
    protected $fillable = ['naam', 'type', 'status', 'laatste_synchronisatie_op'];

    /** @var array<string, string> */
    protected $casts = ['laatste_synchronisatie_op' => 'datetime'];

    public function synchronisatieLogs(): HasMany
    {
        return $this->hasMany(SynchronisatieLog::class);
    }

    public function auditBlok(): string
    {
        return 'notificatie-integratielaag';
    }
}
