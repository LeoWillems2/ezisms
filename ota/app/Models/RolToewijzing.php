<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Rolregels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class RolToewijzing extends Model
{
    use Auditeerbaar;

    protected $table = 'rol_toewijzingen';

    /** @var list<string> */
    protected $fillable = ['gebruiker_id', 'rol_id', 'toegekend_door_id', 'toegekend_op'];

    protected static function booted(): void
    {
        // Het slot achter de validatie (implementatie/01e §2.4). Het scherm
        // weigert de combinatie al met een leesbare melding; dit vangt de paden
        // die niet door dat scherm lopen — een seeder, een artisan-commando, een
        // demoscenario, of een scherm dat later wordt gebouwd en waar iemand de
        // regel vergeet. Hier en niet in een validatieregel, omdat dit de enige
        // plek is waar élke toekenning langskomt.
        //
        // Een rauwe insert op de tabel gaat hier wél omheen. Dat is dezelfde
        // faalrichting als bij de audit trail: wie de database rechtstreeks
        // bewerkt, omzeilt de applicatie en weet dat.
        static::creating(function (self $toewijzing) {
            $gebruiker = $toewijzing->gebruiker ?? Gebruiker::find($toewijzing->gebruiker_id);
            $rol = $toewijzing->rol ?? Rol::find($toewijzing->rol_id);

            if ($gebruiker === null || $rol === null) {
                return;
            }

            if (Rolregels::onverenigbaar($gebruiker, $rol)) {
                throw new RuntimeException(Rolregels::melding());
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['toegekend_op' => 'datetime'];
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gebruiker_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function toegekendDoor(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'toegekend_door_id');
    }

    public function auditBlok(): string
    {
        return 'identity-access';
    }

    /** Kern van Annex A 5.15/5.18: wie kreeg wanneer welke rol. */
    public function auditOmschrijving(): string
    {
        return ($this->gebruiker?->naam ?? 'Onbekend').' - '.($this->rol?->naam ?? 'onbekende rol');
    }
}
