<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Wát er gemeten wordt, hoe en met welke definitieversie (§9.1, implementatie/12
 * §5). Verandert de berekening, dan gaat `definitie_versie` omhoog zodat de
 * breuk in de reeks zichtbaar blijft.
 *
 * Sinds 12e is dit geen pure referentiedata meer: de CISO beheert de catalogus
 * in de applicatie. Vandaar de audit trail en de drie regels hieronder, die de
 * vergelijkbaarheid van een meetreeks bewaken tegen een goedbedoelde wijziging
 * in het scherm (12e §7).
 */
class KpiDefinitie extends Model
{
    use Auditeerbaar;

    protected $table = 'kpi_definities';

    /**
     * Velden waarvan een wijziging de betekenis van de reeks verandert. Ze
     * hogen `definitie_versie` op: `meetbron` verandert de berekening,
     * `richting` verandert of een daling verbetering of verslechtering was — en
     * dus welke kleur historische punten krijgen.
     *
     * @var list<string>
     */
    private const VERSIEBEPALEND = ['meetbron', 'richting'];

    /** @var list<string> */
    protected $fillable = [
        'sleutel', 'meetbron', 'naam', 'fase', 'eenheid', 'richting',
        'berekeningswijze', 'streefwaarde', 'signaalwaarde', 'streefwaarde_vastgesteld_op',
        'definitie_versie', 'actief',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'streefwaarde' => 'float',
        'signaalwaarde' => 'float',
        'streefwaarde_vastgesteld_op' => 'date',
        'definitie_versie' => 'integer',
        'actief' => 'boolean',
    ];

    public function auditBlok(): string
    {
        return 'management-review-verbetercyclus';
    }

    protected static function booted(): void
    {
        static::updating(function (self $definitie) {
            $definitie->bewaakOnveranderlijkeVelden();
            $definitie->hoogVersieOpBijBetekeniswijziging();
        });
    }

    public function metingen(): HasMany
    {
        return $this->hasMany(Meting::class);
    }

    /**
     * Rekent de applicatie deze KPI zelf uit, of voert de CISO teller en noemer
     * per periode in (implementatie/12e §2)?
     */
    public function isHandmatig(): bool
    {
        return $this->meetbron === null;
    }

    /**
     * Is de streefwaarde een vastgesteld besluit, of nog het voorstel dat met
     * het product is meegeleverd (implementatie/12e §9)?
     */
    public function streefwaardeIsVastgesteld(): bool
    {
        return $this->streefwaarde !== null && $this->streefwaarde_vastgesteld_op !== null;
    }

    /**
     * De streefwaarde zoals ze op een meetrij hoort te landen — en dus zoals hij een
     * meetpunt mag kleuren. Een voorstel telt niet mee: afwezigheid van een
     * vastgestelde streefwaarde hoort te lezen als "geen streefwaarde", niet
     * als "gehaald".
     */
    public function vastgesteldeStreefwaarde(): ?float
    {
        return $this->streefwaardeIsVastgesteld() ? $this->streefwaarde : null;
    }

    public function vastgesteldeSignaalwaarde(): ?float
    {
        return $this->streefwaardeIsVastgesteld() ? $this->signaalwaarde : null;
    }

    /**
     * De eenheid ligt vast zodra er één meetpunt is: hij bepaalt wát teller en
     * noemer betekenen (percentage of gemiddelde), en een reeks waarin dat
     * halverwege omslaat is onherstelbaar gemengd. Dan hoort het een nieuwe KPI
     * te zijn.
     */
    public function eenheidLigtVast(): bool
    {
        return $this->exists && $this->metingen()->exists();
    }

    /** Een KPI met historie mag niet weg; `actief = false` doet wat de gebruiker bedoelt. */
    public function magVerwijderdWorden(): bool
    {
        return ! $this->metingen()->exists();
    }

    /**
     * Een unieke, machinaal afgeleide sleutel. Hij is de identiteit van de reeks
     * en staat in de export en de audit trail, dus hij verandert nooit meer —
     * ook niet als de naam later wordt bijgesteld.
     */
    public static function sleutelVoor(string $naam): string
    {
        $basis = Str::slug($naam, '_') ?: 'kpi';
        $sleutel = $basis;
        $volgnummer = 1;

        while (static::where('sleutel', $sleutel)->exists()) {
            $sleutel = $basis.'_'.++$volgnummer;
        }

        return $sleutel;
    }

    /**
     * De twee regels die niet aan het scherm zijn overgelaten. Een validatieregel
     * in één component is geen regel: hij geldt niet voor het volgende component,
     * een commando of een seeder, en juist die kanten komen er later bij.
     */
    private function bewaakOnveranderlijkeVelden(): void
    {
        if ($this->isDirty('sleutel')) {
            throw new RuntimeException(
                'De sleutel van een KPI-definitie ligt vast: hij is de identiteit van de meetreeks.'
            );
        }

        if ($this->isDirty('eenheid') && $this->metingen()->exists()) {
            throw new RuntimeException(
                'De eenheid ligt vast zodra er een meetpunt bestaat; maak anders een nieuwe KPI aan.'
            );
        }
    }

    /**
     * Automatisch en niet door de gebruiker in te stellen (12e §7). Niemand denkt
     * bij het bijstellen van een berekening aan de vergelijkbaarheid van zijn
     * reeks — dat is precies waarvoor dit veld bestaat. Een handmatig bedienbaar
     * versienummer is een versienummer dat vergeten wordt.
     *
     * **`berekeningswijze` staat bewust niet in `VERSIEBEPALEND`, en dat is geen
     * omissie** (12f §2). Bij een berekende KPI is die tekst proza *over* code:
     * herformuleren verandert de berekening niet, en een bump zou een breuk
     * melden die er niet is. Bij een **handmatige** KPI is er geen code en ís die
     * tekst de meetmethode — daar kan een wijziging wél een breuk zijn, maar of
     * dat zo is kan alleen de schrijver beoordelen. Die vraag stelt
     * `MeetaanpakOverzicht` bij het opslaan; komt het antwoord "ja", dan zet het
     * `definitie_versie` expliciet en slaat de hook hieronder zichzelf over.
     */
    private function hoogVersieOpBijBetekeniswijziging(): void
    {
        // Wie de versie expliciet meegeeft (de seeder zet hem op 1) weet wat hij
        // doet; er dan nóg eens overheen tellen maakt het getal onvoorspelbaar.
        if ($this->isDirty('definitie_versie') || ! $this->isDirty(self::VERSIEBEPALEND)) {
            return;
        }

        $this->definitie_versie = (int) $this->getOriginal('definitie_versie') + 1;
    }
}
