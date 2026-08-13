<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Recordscope;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'incidenten';

    /** @var list<string> */
    protected $fillable = [
        'titel', 'omschrijving', 'gemeld_door_id', 'gemeld_op', 'ernst',
        'status', 'gekoppeld_asset_id', 'gekoppeld_risico_id',
        'gesloten_op', 'gesloten_door_id', 'geen_afwijking_reden',
        'kennisname_op', 'extern_meldingsplichtig', 'meldplicht_beoordeeld_op',
        'meldplicht_motivatie', 'raakt_persoonsgegevens', 'is_netwerk_informatie_incident',
        'wijziging_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gemeld_op' => 'datetime',
        'gesloten_op' => 'datetime',
        'kennisname_op' => 'datetime',
        'meldplicht_beoordeeld_op' => 'datetime',
        'extern_meldingsplichtig' => 'boolean',
        'raakt_persoonsgegevens' => 'boolean',
        'is_netwerk_informatie_incident' => 'boolean',
    ];

    /**
     * Zonder volledige inzage alleen de eigen meldingen — hetzelfde patroon als
     * bij bewijsstukken en taken. Melden is een `uitvoeren`-recht, en dat
     * impliceert lezen; zonder deze scope zou een Medewerker ieders
     * incidentmeldingen kunnen inzien (implementatie/08 §9).
     */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->unless(
            Recordscope::magAllesZien('incident-afwijkingenbeheer'),
            fn (Builder $q) => $q->where('gemeld_door_id', Auth::id())
        );
    }

    public function melder(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gemeld_door_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'gekoppeld_asset_id');
    }

    public function risico(): BelongsTo
    {
        return $this->belongsTo(Risico::class, 'gekoppeld_risico_id');
    }

    public function afwijkingen(): HasMany
    {
        return $this->hasMany(Afwijking::class);
    }

    public function meldingen(): HasMany
    {
        return $this->hasMany(IncidentMelding::class);
    }

    public function sluiter(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gesloten_door_id');
    }

    /**
     * De reden waarom sluiten (nog) niet kan, of `null` wanneer het wel kan
     * (implementatie/08 §6). Zelfde vorm als `Afwijkingafsluiting::belemmering()`,
     * zodat het scherm ook hier de reden kan tonen in plaats van een grijze knop.
     *
     * `$reden` is de vastgelegde motivatie voor het geval er géén afwijking uit
     * dit incident is voortgekomen; hij wordt meegegeven omdat de gebruiker hem
     * in hetzelfde formulier invult als waarin hij sluit.
     */
    public function belemmeringVoorSluiten(?string $reden = null): ?string
    {
        // Sluiten is de afronding van het dossier, niet van het probleem. Wat
        // operationeel nog loopt, is niet af te ronden.
        if (! in_array($this->status, ['opgelost', 'gesloten'], true)) {
            return 'Zet het incident eerst op "opgelost": sluiten is de afronding van het dossier, niet van het probleem zelf.';
        }

        if ($this->afwijkingen()->where('status', '!=', 'gesloten')->exists()) {
            return 'Er hangt nog een niet-gesloten afwijking aan dit incident.';
        }

        // Geen afwijking? Dan hoort er een besluit te liggen dát er geen nodig
        // is. Zonder deze eis kan een incident langs de hele CAPA-cyclus heen
        // glippen zonder dat iemand de vraag ooit heeft gesteld — en juist het
        // stellen van die vraag is wat §10.1 verlangt.
        $heeftAfwijking = $this->afwijkingen()->exists();
        $motivatie = $reden ?? $this->geen_afwijking_reden;

        if (! $heeftAfwijking && blank($motivatie)) {
            return 'Leg vast waarom dit incident geen corrigerende maatregel vergt, of open een afwijking.';
        }

        // Zonder deze haak kan een incident langs de hele cyclus glippen zonder
        // dat iemand de meldplichtvraag ooit heeft gesteld — hetzelfde argument
        // als hierboven bij de afwijkingsvraag (implementatie/08b §4).
        return $this->belemmeringVoorMeldplicht();
    }

    /**
     * Valt dit incident onder een documentatieplicht?
     *
     * Alleen als het raakvlak heeft met een van de twee wetten. Een incident dat
     * geen persoonsgegevens raakt en (bij een Cbw-plichtige organisatie) geen
     * netwerk- of informatiesysteem, valt buiten beide en hoeft niet
     * gemotiveerd te worden — AVG art. 33 lid 5 gaat over inbreuken *in verband
     * met persoonsgegevens*, niet over elk beveiligingsincident.
     *
     * Leest de opgeslagen antwoorden en niet de huidige instelling: een
     * organisatie die niet langer Cbw-plichtig is, mag haar eerdere oordelen
     * niet met terugwerkende kracht zien verdwijnen.
     */
    public function heeftDocumentatieplicht(): bool
    {
        return $this->raakt_persoonsgegevens === true
            || $this->is_netwerk_informatie_incident === true;
    }

    /**
     * De grondslagen die uit de twee raakvlakvragen volgen.
     *
     * De gebruiker kiest dus geen grondslag; die volgt uit wát er geraakt is.
     * Dat scheelt een keuzelijst die niets toevoegt en niet uit de pas kan lopen
     * met de antwoorden erboven.
     *
     * @return list<string>
     */
    public function meldgrondslagen(): array
    {
        return array_values(array_filter([
            $this->raakt_persoonsgegevens === true ? 'avg' : null,
            $this->is_netwerk_informatie_incident === true ? 'cbw' : null,
        ]));
    }

    /**
     * De reden waarom de beoordeling van de externe meldplicht nog niet rond is,
     * of `null` als hij dat wel is (implementatie/08b §4).
     *
     * Een verstreken termijn staat hier bewust NIET in. Een gemiste melding is
     * een feit dat vastgelegd moet worden, niet iets wat je kunt wegnemen door
     * het dossier open te houden; het incident blijft dus sluitbaar.
     */
    public function belemmeringVoorMeldplicht(?string $motivatie = null): ?string
    {
        // De vráág hoort bij elk incident gesteld te worden — dat is de hele
        // reden voor deze poort. Alleen het antwoord mag goedkoop zijn.
        if ($this->raakt_persoonsgegevens === null) {
            return 'Beoordeel of dit incident persoonsgegevens raakt.';
        }

        if (config('meldplicht.cbw_plichtig') && $this->is_netwerk_informatie_incident === null) {
            return 'Beoordeel of dit een incident in netwerk- of informatiesystemen is (Cyberbeveiligingswet).';
        }

        // Geen raakvlak met een van beide wetten: geen documentatieplicht, dus
        // ook geen motivatie. Twee keer "nee" is een volledig antwoord.
        if (! $this->heeftDocumentatieplicht()) {
            return null;
        }

        if ($this->extern_meldingsplichtig === null) {
            return 'Beoordeel of dit incident extern gemeld moet worden.';
        }

        // Ook bij "nee" — hier bijt de documentatieplicht wél. Het oordeel dat
        // een risico onwaarschijnlijk is (AVG art. 33 lid 1) of dat een incident
        // niet significant is, heeft criteria en hoort navolgbaar te zijn.
        if (blank($motivatie ?? $this->meldplicht_motivatie)) {
            return 'Leg vast waarom dit incident wel of niet extern gemeld moet worden.';
        }

        return null;
    }

    /**
     * Wat het gekoppelde asset zegt over de AVG-vraag, of `null`.
     *
     * Twee vormen: een hint zolang de vraag openstaat, en een tegenspraak zodra
     * er "nee" staat terwijl het asset persoonsgegevens bevat. Bewust een
     * signaal en geen automatisch antwoord — of een incident écht
     * persoonsgegevens raakt, hangt af van wat er gebeurd is en niet alleen van
     * wat er in het systeem zit. Zelfde keuze als bij
     * {@see Asset::privacywaarschuwing()}: signaleren, niet blokkeren.
     *
     * Het antwoord komt als parameter binnen en wordt niet van het model
     * gelezen, zodat het scherm de tegenspraak toont zodra iemand "nee" kiest en
     * niet pas na opslaan. `null` betekent hier "nog niet beoordeeld" — de
     * parameter is daarom verplicht en heeft geen standaardwaarde, anders zou
     * weglaten stilzwijgend hetzelfde betekenen als onbeoordeeld.
     */
    public function meldplichtsignaalUitAsset(?bool $raaktPersoonsgegevens): ?string
    {
        if ($this->asset?->bevatPersoonsgegevens() !== true) {
            return null;
        }

        $soort = $this->asset->persoonsgegevens === 'gewoon'
            ? 'persoonsgegevens'
            : $this->asset->persoonsgegevens.' persoonsgegevens';

        return match ($raaktPersoonsgegevens) {
            null => "Let op: het gekoppelde asset \"{$this->asset->naam}\" bevat {$soort}.",
            false => 'Dit incident staat op "raakt geen persoonsgegevens", terwijl het gekoppelde asset '
                ."\"{$this->asset->naam}\" {$soort} bevat. Klopt dat?",
            default => null,
        };
    }

    /** Openstaande meldverplichtingen waarvan de termijn al verstreken is. */
    public function teLateMeldingen(): Collection
    {
        return $this->meldingen->filter(fn (IncidentMelding $m) => $m->isTeLaat());
    }

    /** Van melding tot afgerond dossier — de doorlooptijd uit deelproducten/08 §6. */
    public function doorlooptijdInDagen(): ?int
    {
        return $this->gesloten_op === null
            ? null
            : (int) $this->gemeld_op->diffInDays($this->gesloten_op);
    }

    public function auditBlok(): string
    {
        return 'incident-afwijkingenbeheer';
    }

    public function auditOmschrijving(): string
    {
        return $this->titel;
    }
}
