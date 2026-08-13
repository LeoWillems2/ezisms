<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\IncidentMeldingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén externe meldverplichting bij een incident: welke grondslag, welke fase,
 * wanneer hij uiterlijk moet en of hij gedaan is (implementatie/08b §3).
 *
 * Eén rij per (incident, grondslag, fase), want de Cyberbeveiligingswet is een
 * gefaseerde meldplicht en niet één termijn: art. 26 lid 1 (24 uur), art. 27
 * lid 1 (72 uur) en art. 29 (één maand) zijn drie verplichtingen bij hetzelfde
 * incident. AVG en Cbw kunnen bovendien tegelijk gelden — een datalek bij een
 * Cbw-plichtige organisatie is het gewone geval, niet de uitzondering.
 *
 * `Auditeerbaar`, want wie wanneer heeft afgevinkt dat er aan een toezichthouder
 * is gemeld, is bij uitstek iets waarvan dat later moet vaststaan.
 */
class IncidentMelding extends Model
{
    /** @use HasFactory<IncidentMeldingFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'incident_meldingen';

    /** @var list<string> */
    protected $fillable = [
        'incident_id', 'grondslag', 'fase', 'meldtermijn_uren',
        'uiterlijk_op', 'gemeld_op', 'toelichting',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'uiterlijk_op' => 'datetime',
        'gemeld_op' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** Het label uit config/meldplicht.php, of de ruwe fase als die ontbreekt. */
    public function label(): string
    {
        return $this->instelling()['label'] ?? ucfirst($this->fase);
    }

    /** Het wetsartikel waar deze verplichting op steunt. */
    public function artikel(): ?string
    {
        return $this->instelling()['grondslag_artikel'] ?? null;
    }

    public function isGemeld(): bool
    {
        return $this->gemeld_op !== null;
    }

    /**
     * Is deze verplichting te laat? `false` zolang er geen deadline is.
     *
     * Een verplichting zonder termijn kan niet te laat zijn — AVG art. 34 kent
     * er geen, en het Cbw-eindverslag bij een voortdurend incident krijgt er pas
     * een bij afhandeling (art. 29 lid 2). Dat is geen randgeval om weg te
     * werken maar een normale toestand.
     */
    public function isTeLaat(): bool
    {
        if ($this->uiterlijk_op === null) {
            return false;
        }

        return $this->isGemeld()
            ? $this->gemeld_op->greaterThan($this->uiterlijk_op)
            : now()->greaterThan($this->uiterlijk_op);
    }

    /** @return array<string, mixed> */
    private function instelling(): array
    {
        return config("meldplicht.grondslagen.{$this->grondslag}.fasen.{$this->fase}", []);
    }

    public function auditBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    public function auditOmschrijving(): string
    {
        return strtoupper($this->grondslag).' — '.$this->label();
    }
}
