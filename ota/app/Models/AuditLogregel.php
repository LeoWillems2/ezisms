<?php

namespace App\Models;

use App\Support\Audittrailketen;
use App\Support\Ketenhash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Append-only logboek (implementatie/06 §2 en §10).
 *
 * De guards hieronder zijn een vangnet tegen programmeerfouten, GEEN
 * beveiligingscontrole: wie databasetoegang heeft omzeilt ze met één UPDATE.
 * De echte controle is een grant op databaseniveau (INSERT/SELECT, geen
 * UPDATE/DELETE) — zie README.
 *
 * Elke regel draagt daarnaast de hash van zijn voorganger (implementatie/06c).
 * Dat verhindert een wijziging niet, maar maakt hem detecteerbaar: zonder keten
 * laat een verwijderde regel alleen een gat in de nummering achter, en die
 * ontstaan ook door teruggerolde transacties.
 */
class AuditLogregel extends Model
{
    protected $table = 'audit_logregels';

    /** Append-only: er is geen created_at/updated_at, alleen `tijdstip`. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'tijdstip', 'gebruiker_id', 'gebruiker_naam', 'blok_naam',
        'entiteit_type', 'entiteit_id', 'entiteit_omschrijving',
        'actie', 'oude_waarde', 'nieuwe_waarde',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tijdstip' => 'datetime',
        'oude_waarde' => 'array',
        'nieuwe_waarde' => 'array',
    ];

    /** Pogingen bij een gevorkte keten; zie save(). */
    private const MAX_POGINGEN = 3;

    /**
     * De toegestane waarden van `actie`, gelijk aan de enum in het schema.
     *
     * Deze lijst staat hier omdat de testsuite op sqlite draait en een enum daar
     * niets afdwingt: een nieuwe of verkeerd gespelde actie kwam er in de tests
     * doorheen en viel pas op MySQL om, met een QueryException op het scherm van
     * de gebruiker. Dat is één keer echt gebeurd (`geexporteerd`, 11-08-2026).
     * De controle hieronder maakt van dat productieprobleem een testprobleem.
     *
     * Een waarde toevoegen is dus twee dingen: deze lijst én een migratie die de
     * enum verruimt (`0001_01_01_000051`).
     */
    public const ACTIES = ['aangemaakt', 'gewijzigd', 'status_gewijzigd', 'verwijderd', 'geexporteerd'];

    protected static function booted(): void
    {
        // De hash wordt hier gelegd en niet bij de aanroepers: elke schrijver
        // (`Auditeerbaar`, `legVerzamelingVast`, `App\Support\Koppeling`) komt
        // hier langs. Een rauwe insert op de tabel gaat er wél omheen — die
        // regel mist dan zijn hash en de controle slaat aan. Dat is de juiste
        // faalrichting.
        static::creating(function (self $regel) {
            if (! in_array($regel->actie, self::ACTIES, true)) {
                throw new RuntimeException(
                    "Onbekende actie '{$regel->actie}' voor de audit trail. Toegestaan: "
                    .implode(', ', self::ACTIES).'. Een nieuwe waarde vraagt ook een migratie '
                    .'die de enum verruimt — op sqlite valt dat niet op, op MySQL wel.'
                );
            }

            $vorige = Audittrailketen::kop();

            $regel->vorige_hash = $vorige;
            $regel->hash = Ketenhash::van($regel->getAttributes(), $vorige);
        });

        static::updating(function () {
            throw new RuntimeException('Audit-logregels zijn append-only en kunnen niet worden gewijzigd.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit-logregels zijn append-only en kunnen niet worden verwijderd.');
        });
    }

    /**
     * Twee gelijktijdige schrijvers lezen dezelfde kop en hangen er allebei een
     * regel aan. De unieke index op `vorige_hash` maakt daar een botsing van in
     * plaats van een gevorkte keten (06c §4) — hier wordt hij opgevangen: de
     * `creating`-hook leest de kop opnieuw en rekent de hash opnieuw uit.
     *
     * Bewust een lus en geen lock. Een lock rond elke audit-insert betekent een
     * transactie plus `SELECT … FOR UPDATE` bij élke handeling in het systeem,
     * en bij het schrijfvolume van een single-tenant ISMS is een tweede poging
     * al zeldzaam.
     */
    public function save(array $options = []): bool
    {
        for ($poging = 1; ; $poging++) {
            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $botsing) {
                if ($this->exists || $poging >= self::MAX_POGINGEN) {
                    throw $botsing;
                }
            }
        }
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class);
    }

    /**
     * Een gebeurtenis die een verzameling raakt in plaats van één record —
     * bijvoorbeeld het handhaven van een bewaartermijn.
     *
     * `entiteit_id` blijft leeg: er ís geen rij om naar te wijzen. Dit gaat
     * bewust niet via de `Auditeerbaar`-trait; die logt per model, en juist bij
     * een opschoning is per-rij loggen verkeerd — dan verhuizen de gegevens naar
     * de audit trail in plaats van te verdwijnen (zie
     * `Raadpleging::verwijderOuderDan()`).
     *
     * @param  array<string, mixed>|null  $details
     */
    public static function legVerzamelingVast(
        string $blokNaam,
        string $entiteitType,
        string $actie,
        string $omschrijving,
        ?array $details = null,
    ): self {
        $gebruiker = auth()->user();

        return static::create([
            'tijdstip' => now(),
            'gebruiker_id' => $gebruiker?->getKey(),
            'gebruiker_naam' => $gebruiker?->naam ?? 'Systeem (geplande taak)',
            'blok_naam' => $blokNaam,
            'entiteit_type' => $entiteitType,
            'entiteit_id' => null,
            'entiteit_omschrijving' => $omschrijving,
            'actie' => $actie,
            // Bij 'verwijderd' hoort wat er wég is in `oude_waarde`, net als bij
            // de per-model variant in de trait.
            'oude_waarde' => $details,
            'nieuwe_waarde' => null,
        ]);
    }

    /** De velden die bij deze gebeurtenis wijzigden. */
    public function gewijzigdeVelden(): array
    {
        return array_keys($this->nieuwe_waarde ?? $this->oude_waarde ?? []);
    }
}
