<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\SjabloonstapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén stap in een wijzigingssjabloon (implementatie/15 §1).
 *
 * De vijf staptypen zijn code en geen configuratie: elk type heeft eigen
 * gedrag. Slechts één ervan raakt de taken-engine — een `goedkeuring` wordt een
 * stap met `vraagt_uitkomst`.
 */
class Sjabloonstap extends Model
{
    /** @use HasFactory<SjabloonstapFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'sjabloonstappen';

    /** @var list<string> */
    public const STAPTYPEN = ['analyse', 'goedkeuring', 'informeren', 'uitvoeren', 'evaluatie'];

    /** @var list<string> */
    protected $fillable = [
        'wijzigingssjabloon_id', 'volgorde', 'titel', 'omschrijving', 'staptype',
        'standaard_eigenaar_id', 'deadline_offset_dagen', 'bewijs_verplicht',
        'doelgroep_id', 'bij_afkeuren_terug_naar',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'volgorde' => 'integer',
        'deadline_offset_dagen' => 'integer',
        'bewijs_verplicht' => 'boolean',
        'bij_afkeuren_terug_naar' => 'integer',
    ];

    public function sjabloon(): BelongsTo
    {
        return $this->belongsTo(Wijzigingssjabloon::class, 'wijzigingssjabloon_id');
    }

    public function standaardEigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'standaard_eigenaar_id');
    }

    public function doelgroep(): BelongsTo
    {
        return $this->belongsTo(Doelgroep::class);
    }

    public function auditBlok(): string
    {
        return 'wijzigingsbeheer';
    }
}
