<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * De uitslag van één ketencontrole op de audit trail (implementatie/06c §5).
 *
 * Twee soorten:
 *
 * - `controle` — de nachtelijke `isms:controleer-audittrail`.
 * - `verzegeld` — de keten is (opnieuw) aangelegd. Dat gebeurt bij de migratie
 *   en na `isms:verwijder-auditdata --met-trail`, en het is het moment waarop de
 *   bewijskracht van de trail opnieuw begint.
 *
 * Deze tabel is zelf niet auditeerbaar en heeft geen keten. Hij bewijst niet
 * zichzelf — hij is de geschiedenis van het toezicht, en die geschiedenis is
 * pas iets waard náást de trail, niet erin.
 */
class Ketencontrole extends Model
{
    protected $table = 'audit_ketencontroles';

    /** Geen created_at/updated_at: `tijdstip` is het moment dat telt. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'tijdstip', 'soort', 'intact', 'regels', 'tot_id', 'kapotte_id', 'kophash', 'reden',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tijdstip' => 'datetime',
        'intact' => 'boolean',
        'regels' => 'integer',
        'tot_id' => 'integer',
        'kapotte_id' => 'integer',
    ];

    /** De laatste uitslag, ongeacht soort — dat is wat het scherm toont. */
    public static function laatste(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }

    public function isVerzegeling(): bool
    {
        return $this->soort === 'verzegeld';
    }

    /** Eén regel voor op het scherm en in de kopie voor de auditor. */
    public function samenvatting(): string
    {
        if (! $this->intact) {
            return 'Keten gebroken bij regel '.$this->kapotte_id;
        }

        return ($this->isVerzegeling() ? 'Keten verzegeld' : 'Keten intact')
            .($this->tot_id === null ? ' (geen regels)' : ' t/m regel '.$this->tot_id);
    }
}
