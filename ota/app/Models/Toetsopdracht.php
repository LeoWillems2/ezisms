<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\ToetsopdrachtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De machinerie achter een als taak uitgezette toets (implementatie/10 §4).
 * Staat bewust naast `taken` en niet erin: `taken` is auditeerbaar en heeft geen
 * plek voor score/pogingen, en de token zou anders leesbaar in de audit trail
 * belanden.
 */
class Toetsopdracht extends Model
{
    /** @use HasFactory<ToetsopdrachtFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'toetsopdrachten';

    /** @var list<string> */
    protected $fillable = [
        'taak_id', 'trainingsmodule_id', 'toets_bestand', 'toets_titel', 'token',
        'status', 'pogingen', 'laatste_score', 'laatste_totaal', 'laatste_poging_op', 'geslaagd_op',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'pogingen' => 'integer',
        'laatste_score' => 'integer',
        'laatste_totaal' => 'integer',
        'laatste_poging_op' => 'datetime',
        'geslaagd_op' => 'datetime',
    ];

    public function taak(): BelongsTo
    {
        return $this->belongsTo(Taak::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Trainingsmodule::class, 'trainingsmodule_id');
    }

    /**
     * De deelnemer-URL; de token komt nergens anders in vrije tekst (§8).
     *
     * De token staat er twee keer in, en dat is geen slordigheid: in het pad
     * omdat de route hem daar leest, en in `?callback=` omdat élk bestaand
     * toetsbestand `onQuizVoltooid` meedraagt en die de token daar zoekt. Zou de
     * querystring verdwijnen, dan registreert een al uitgeleverde toets
     * stilzwijgend niets meer — de deelnemer maakt hem, ziet geen fout, en de
     * taak blijft openstaan (implementatie/01e §1.3).
     */
    public function deelnemerUrl(): string
    {
        return route('toetsen.tonen', ['token' => $this->token, 'callback' => $this->token]);
    }

    /**
     * De token is het geheim dat toegang geeft tot de callback: nooit in de
     * audit trail, die de Auditor mag inzien én exporteren (§4).
     *
     * @return list<string>
     */
    public function auditUitgesloten(): array
    {
        return ['token'];
    }

    public function auditBlok(): string
    {
        return 'bewustzijn-training';
    }

    public function auditOmschrijving(): string
    {
        return 'Toets '.$this->toets_titel;
    }
}
