<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Recordscope;
use Database\Factories\BeleidsdocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Beleidsdocument extends Model
{
    /** @use HasFactory<BeleidsdocumentFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'beleidsdocumenten';

    /** @var list<string> */
    protected $fillable = [
        'titel', 'type', 'omschrijving', 'status', 'ingetrokken_op',
        'eigenaar_id', 'leesbevestiging_vereist',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'ingetrokken_op' => 'date',
        'leesbevestiging_vereist' => 'boolean',
    ];

    /**
     * Default voor de bevestigingsplicht, afgeleid uit het type
     * (implementatie/05 §6). A.5.1 vraagt om erkenning door "relevant
     * personeel"; ISO 27002 onderscheidt daarbij het organisatiebrede
     * informatiebeveiligingsbeleid van onderwerpspecifieke beleidsregels over
     * onderwerpen als toegangsbeveiliging en veilig ontwikkelen.
     *
     * Nadrukkelijk alleen een default: de CISO overrulet hem per document.
     */
    public static function standaardLeesbevestiging(string $type): bool
    {
        return $type === 'beleid';
    }

    /**
     * Zonder volledige inzage zie je alleen vastgestelde documenten. Een
     * concept of een versie die ter goedkeuring ligt is nog geen beleid: dat
     * hoort niemand te lezen en al helemaal niet te bevestigen (§9).
     */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->unless(
            Recordscope::magAllesZien('beleid-maatregelbeheer'),
            fn (Builder $q) => $q->where('status', 'actief')
        );
    }

    public function versies(): HasMany
    {
        return $this->hasMany(Beleidsversie::class);
    }

    public function actieveVersie(): HasOne
    {
        return $this->hasOne(Beleidsversie::class)->where('status', 'actief');
    }

    public function eigenaar(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'eigenaar_id');
    }

    public function soaRegels(): BelongsToMany
    {
        return $this->belongsToMany(SoaRegel::class, 'beleidsdocument_soa_regel');
    }

    /**
     * De afdelingen waarop de bevestigingsplicht gericht is. Alleen zinvol als
     * `leesbevestiging_vereist` aan staat; de doelgroep is de gebruikers ván
     * deze afdelingen (§6).
     */
    public function afdelingen(): BelongsToMany
    {
        return $this->belongsToMany(OrganisatieEenheid::class, 'beleidsdocument_organisatie_eenheid');
    }

    /**
     * De id's van de gebruikers die dit document moeten bevestigen: actieve
     * gebruikers wier afdeling is aangevinkt. Leeg wanneer er geen afdeling is
     * gekoppeld — dan is er geen doelgroep en is de bevestigingsgraad n.v.t.
     *
     * De enige bron voor "wie is de doelgroep": zowel de taakgeneratie als de
     * bevestigingsgraad en de waarschuwingen rekenen hiermee, zodat ze niet uit
     * elkaar kunnen lopen.
     *
     * @return list<int>
     */
    public function doelgroepGebruikerIds(): array
    {
        $afdelingIds = $this->afdelingen()->pluck('organisatie_eenheden.id');

        if ($afdelingIds->isEmpty()) {
            return [];
        }

        return Gebruiker::where('status', 'actief')
            ->whereIn('organisatie_eenheid_id', $afdelingIds)
            ->pluck('id')
            ->all();
    }

    /**
     * Hoort deze gebruiker tot de doelgroep? Een actieve gebruiker wiens
     * afdeling het document heeft aangevinkt. Gebruikt om te bepalen wie een
     * bevestiging te zien krijgt en wie er eentje mag vastleggen.
     */
    public function isInDoelgroep(?Gebruiker $gebruiker): bool
    {
        if ($gebruiker === null
            || $gebruiker->status !== 'actief'
            || $gebruiker->organisatie_eenheid_id === null) {
            return false;
        }

        return $this->afdelingen()
            ->where('organisatie_eenheden.id', $gebruiker->organisatie_eenheid_id)
            ->exists();
    }

    public function isIngetrokken(): bool
    {
        return $this->ingetrokken_op !== null;
    }

    /**
     * Percentage van de actieve gebruikers dat de actieve versie heeft
     * bevestigd, of `null` wanneer de vraag niet van toepassing is: geen
     * bevestigingsplicht, of (nog) geen actieve versie.
     *
     * `null` betekent in de UI "n.v.t." en nadrukkelijk niet 0% — een nul op
     * de bevestigingsgraad leest als een falende beheersmaatregel (§6).
     */
    public function bevestigingsgraad(): ?int
    {
        $versie = $this->actieveVersie;

        if (! $this->leesbevestiging_vereist || $versie === null) {
            return null;
        }

        $doelgroepIds = $this->doelgroepGebruikerIds();

        if ($doelgroepIds === []) {
            return null;
        }

        // Teller alléén de doelgroep: iemand die bevestigde en daarna van
        // afdeling wisselde, telt niet meer mee — anders kan de graad boven
        // 100% uitkomen.
        $bevestigd = $versie->bevestigingen()->whereIn('gebruiker_id', $doelgroepIds)->count();

        return (int) round($bevestigd / count($doelgroepIds) * 100);
    }

    public function auditBlok(): string
    {
        return 'beleid-maatregelbeheer';
    }

    /**
     * `leesbevestiging_vereist` staat er bewust NIET tussen: "wanneer is de
     * leesbevestigingsplicht uitgezet, en door wie" is precies wat een auditor
     * bij A.5.1 wil kunnen zien.
     *
     * @return list<string>
     */
    public function auditUitgesloten(): array
    {
        return [];
    }

    public function auditOmschrijving(): string
    {
        return $this->titel;
    }
}
