<?php

namespace App\Models;

use Database\Factories\ContractclausuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contractclausule extends Model
{
    /** @use HasFactory<ContractclausuleFactory> */
    use HasFactory;

    protected $table = 'contractclausules';

    /** @var list<string> */
    protected $fillable = ['leverancier_id', 'type', 'aanwezig'];

    /** @var array<string, string> */
    protected $casts = ['aanwezig' => 'boolean'];

    /**
     * De vaste, kleine set securityrelevante clausules (Annex A 5.19–5.23) —
     * bewust geen vrij contractmodel (§4). Label voor in het scherm.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'vertrouwelijkheid' => 'Vertrouwelijkheid (NDA)',
        'recht_op_audit' => 'Recht op audit',
        'sla' => 'SLA / beschikbaarheid',
        'incidentmeldplicht' => 'Incidentmeldplicht',
        'verwerkersovereenkomst' => 'Verwerkersovereenkomst (AVG)',
    ];

    public function leverancier(): BelongsTo
    {
        return $this->belongsTo(Leverancier::class);
    }
}
