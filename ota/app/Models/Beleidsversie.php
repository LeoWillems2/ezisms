<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Models\Concerns\Waardenbewaking;
use App\Observers\BeleidsversieObserver;
use App\Support\Beleidsgoedkeuring;
use App\Support\Taakbelemmering;
use Database\Factories\BeleidsversieFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[ObservedBy([BeleidsversieObserver::class])]
class Beleidsversie extends Model implements Taakbelemmering
{
    /** @use HasFactory<BeleidsversieFactory> */
    use Auditeerbaar, HasFactory, Waardenbewaking;

    protected $table = 'beleidsversies';

    /** @var list<string> */
    protected $fillable = [
        'beleidsdocument_id', 'versienummer', 'bewijsstuk_id', 'status',
        'aangeboden_op', 'wijzigingsreden', 'gepubliceerd_op',
        'goedgekeurd_door_id', 'goedgekeurd_op', 'volgende_herziening_gepland',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'aangeboden_op' => 'datetime',
        'gepubliceerd_op' => 'date',
        'goedgekeurd_op' => 'datetime',
        'volgende_herziening_gepland' => 'date',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Beleidsdocument::class, 'beleidsdocument_id');
    }

    public function bewijsstuk(): BelongsTo
    {
        return $this->belongsTo(Bewijsstuk::class);
    }

    public function goedkeurder(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'goedgekeurd_door_id');
    }

    public function bevestigingen(): HasMany
    {
        return $this->hasMany(Leesbevestiging::class);
    }

    /** Inhoud is alleen te wijzigen zolang de versie nog niet gepubliceerd is. */
    public function isBewerkbaar(): bool
    {
        return in_array($this->status, ['concept', 'ter_goedkeuring'], true);
    }

    public function herzieningVerstreken(): bool
    {
        return $this->status === 'actief'
            && ($this->volgende_herziening_gepland?->isPast() ?? false);
    }

    /**
     * Termijn waarbinnen een gepubliceerd beleid gelezen en bevestigd hoort te
     * zijn. Staat NIET in de norm — gekozen omdat een leestermijn zonder einde
     * geen termijn is (implementatie/05 §8).
     *
     * Stond tot 12i in `GenereerTaken`. Verhuisd omdat er sindsdien twee lezers
     * zijn: de takengeneratie die de deadline zet, en het dashboard dat toont
     * wie er overheen is. Twee kopieën van dit getal lopen uit elkaar zonder
     * dat één van beide schermen er verkeerd uitziet.
     */
    public const LEESTERMIJN_DAGEN = 30;

    /**
     * De uiterste bevestigdatum. Loopt vanaf publicatie en niet vanaf vandaag:
     * een taak die elke nacht opnieuw dertig dagen zou krijgen, verloopt nooit.
     *
     * Gevolg, en dat is een bekend gat: wie later tot de doelgroep toetreedt,
     * krijgt een termijn die al loopt en soms al voorbij is. Zie de kennisbank,
     * "Een nieuwe medewerker komt er niet vanzelf in" — het is een open keuze,
     * niet een omissie die je hier stilletjes moet repareren.
     */
    public function leesdeadline(): Carbon
    {
        return ($this->gepubliceerd_op ?? Carbon::today())
            ->copy()
            ->addDays(self::LEESTERMIJN_DAGEN);
    }

    public function leestermijnVerstreken(): bool
    {
        return $this->status === 'actief' && $this->leesdeadline()->isPast();
    }

    /**
     * Termijn waarbinnen een aangeboden versie vastgesteld hoort te zijn.
     * Staat NIET in de norm — gekozen gelijk aan de `REACTIETERMIJN_DAGEN` uit
     * blok 7, want dit is dezelfde soort termijn: de tijd die iemand krijgt om
     * op een signaal te reageren (implementatie/05b §3).
     *
     * Twee weken is bewust ruim genoeg om een MT-vergadering af te wachten en
     * krap genoeg om een vergeten aanbieding zichtbaar te maken.
     */
    public const GOEDKEURTERMIJN_DAGEN = 14;

    /**
     * De uiterste vaststeldatum. Loopt vanaf de aanbieding en niet vanaf
     * vandaag, om dezelfde reden als bij `leesdeadline()`: `TaakPlanner` verzet
     * de deadline bij elke aanroep, dus een deadline van "vandaag + 14" zou bij
     * elke opslag opnieuw beginnen en nooit verlopen.
     *
     * Terugval op `updated_at` voor versies van vóór migratie `000062`, waar de
     * kolom nog leeg kan zijn.
     */
    public function goedkeurdeadline(): Carbon
    {
        return ($this->aangeboden_op ?? $this->updated_at ?? Carbon::now())
            ->copy()
            ->addDays(self::GOEDKEURTERMIJN_DAGEN);
    }

    /**
     * Uitsluitend de eigen toestand van de versie, net als
     * `leestermijnVerstreken()`. Of er ook nog iemand iets moet doen, is een
     * bredere vraag (een ingetrokken document telt niet meer mee) en hoort bij
     * de taak, niet bij de versie: zie `Beleidsgoedkeuring`.
     */
    public function wachtOpGoedkeuring(): bool
    {
        return $this->status === 'ter_goedkeuring';
    }

    public function goedkeurtermijnVerstreken(): bool
    {
        return $this->wachtOpGoedkeuring() && $this->goedkeurdeadline()->isPast();
    }

    public function isBevestigdDoor(?int $gebruikerId): bool
    {
        return $gebruikerId !== null
            && $this->bevestigingen()->where('gebruiker_id', $gebruikerId)->exists();
    }

    /**
     * Houdt de knop *Voltooien* op `/taken` tegen zolang de handeling waar de
     * taak over gaat niet verricht is (implementatie/05b §6).
     *
     * Beide taaksoorten van dit blok sluiten zichzelf: de goedkeuringstaak bij
     * het publiceren, de leesbevestigingstaak zodra de bevestiging binnen is.
     * Zonder deze rem is de taak met de hand af te vinken zonder dat er iets
     * gebeurt — de versie blijft ter goedkeuring liggen, de bevestiging blijft
     * uit, en de nachtelijke ronde zet de taak gewoon terug. Een knop die niets
     * doet en een taak die terugkomt, leren mensen samen dat de takenlijst
     * onbetrouwbaar is.
     *
     * De tekst zegt daarom waar de echte handeling zit. Merk op dat dit géén
     * autorisatiecheck is: die staat op de knop *Publiceren* zelf (05 §9).
     */
    public function belemmeringVoorTaak(Taak $taak): ?string
    {
        return match ($taak->soort) {
            Beleidsgoedkeuring::SOORT => Beleidsgoedkeuring::vraagtNogVaststelling($this)
                ? 'Deze taak sluit vanzelf zodra de versie is vastgesteld. Ga naar Beleid & procedures en gebruik daar Publiceren.'
                : null,
            'beleid-leesbevestiging' => $this->isBevestigdDoor($taak->eigenaar_id)
                ? null
                : 'Deze taak sluit vanzelf zodra u de leesbevestiging geeft. Open het document bij Beleid & procedures en bevestig daar dat u het gelezen hebt.',
            default => null,
        };
    }

    public function auditBlok(): string
    {
        return 'beleid-maatregelbeheer';
    }

    public function auditOmschrijving(): string
    {
        return ($this->document?->titel ?? 'Beleidsdocument').' v'.$this->versienummer;
    }
}
