<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\AuditprogrammaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * De meerjarige interne-auditcyclus als eigen entiteit (plan 11b §0/§4), bóven
 * het jaarlijkse Auditplan — niet afgeleid uit een reeks jaarplannen. Het venster
 * (start_datum + aantal_jaren) is doorgaans gelijk aan de externe
 * certificeringscyclus (certificatie + 2× surveillance). Vaststellen is
 * auditrelevant, dus Auditeerbaar.
 *
 * De startdatum is een datum en geen jaartal (plan 11c): de certificeringscyclus
 * begint op de certificaatdatum, en een auditcyclus die daar niet op kan
 * aansluiten loopt er structureel naast. Het certificaat zelf blijft een
 * bewijsstuk — het anker is de datum, niet een foreign key.
 */
class Auditprogramma extends Model
{
    /** @use HasFactory<AuditprogrammaFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'auditprogrammas';

    /** @var list<string> */
    protected $fillable = ['naam', 'start_datum', 'aantal_jaren', 'aard', 'status'];

    /** @var array<string, string> */
    protected $casts = [
        'start_datum' => 'date',
        'aantal_jaren' => 'integer',
    ];

    public function auditplannen(): HasMany
    {
        return $this->hasMany(Auditplan::class);
    }

    public function dekkingen(): HasMany
    {
        return $this->hasMany(AuditprogrammaDekking::class);
    }

    /** De laatste dag van de cyclus: start + aantal jaren − 1 dag. */
    public function eindDatum(): Carbon
    {
        return $this->start_datum->copy()->addYears($this->aantal_jaren)->subDay();
    }

    /**
     * De programmajaren van de cyclus: nummer 1..N met hun feitelijke venster.
     * Een cyclus die op 14 mei begint, heeft programmajaren die op 14 mei
     * beginnen — het kalenderjaar is hooguit een label.
     *
     * Carbon overal, nooit `date()`: de simulatiemotor van saasdemo leunt daarop.
     *
     * @return list<array{nummer: int, start: Carbon, eind: Carbon}>
     */
    public function programmajaren(): array
    {
        $jaren = [];

        for ($nummer = 1; $nummer <= $this->aantal_jaren; $nummer++) {
            $start = $this->start_datum->copy()->addYears($nummer - 1);

            $jaren[] = [
                'nummer' => $nummer,
                'start' => $start,
                'eind' => $start->copy()->addYear()->subDay(),
            ];
        }

        return $jaren;
    }

    /** @return list<int> de nummers 1..N, voor waar alleen de as nodig is. */
    public function programmajaarNummers(): array
    {
        return range(1, $this->aantal_jaren);
    }

    /**
     * "mei 2027" — bewust een eigen maandtabel en geen `translatedFormat()`:
     * `app.locale` staat op 'en', en die omzetten zou de validatiemeldingen van
     * het hele platform meenemen. Eén tabel hier is goedkoper dan die bijwerking.
     */
    public static function maandJaar(Carbon $datum): string
    {
        $maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

        return $maanden[$datum->month - 1].' '.$datum->year;
    }

    /** Het venster als leesbare regel: "mei 2027 – apr 2030". */
    public function venster(): string
    {
        return self::maandJaar($this->start_datum).' – '.self::maandJaar($this->eindDatum());
    }

    public function isVoorbereiding(): bool
    {
        return $this->aard === 'voorbereiding';
    }

    public function auditBlok(): string
    {
        return 'auditmanagement';
    }

    public function auditOmschrijving(): string
    {
        return 'Auditprogramma '.$this->naam;
    }
}
