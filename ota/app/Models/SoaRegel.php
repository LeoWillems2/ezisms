<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Observers\SoaRegelObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(SoaRegelObserver::class)]
class SoaRegel extends Model
{
    use Auditeerbaar;

    protected $table = 'soa_regels';

    /** @var list<string> */
    protected $fillable = [
        'maatregel_id', 'van_toepassing', 'motivatie', 'beleidreferentie', 'procesreferentie',
        'kenmerken_eigen', 'implementatiestatus', 'laatst_beoordeeld_op',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'van_toepassing' => 'boolean',
        'laatst_beoordeeld_op' => 'date',
        'kenmerken_eigen' => 'array',
    ];

    public function maatregel(): BelongsTo
    {
        return $this->belongsTo(Maatregel::class);
    }

    public function risicobehandelingen(): BelongsToMany
    {
        return $this->belongsToMany(Risicobehandeling::class, 'risicobehandeling_soa_regel');
    }

    /**
     * De jaarlijkse restrisico-meetpunten (plan 04c §2), oplopend op peiljaar.
     *
     * Tot nu toe werd hier altijd rechtstreeks op `RestrisicoSnapshot` gequeryd
     * (`RestrisicoTrend`, `LegRestrisicoVast`); de export leest ze per control en
     * heeft daar een relatie voor nodig om N+1 te vermijden.
     */
    public function restrisicoSnapshots(): HasMany
    {
        return $this->hasMany(RestrisicoSnapshot::class)->orderBy('peiljaar');
    }

    /**
     * De BIO-verplichtingen onder deze beheersmaatregel, met wat de organisatie
     * erover heeft vastgesteld (deelproducten/04b §2).
     *
     * Leeg buiten een BIO-installatie: daar staan geen overheidsmaatregelen in de
     * tabel. Gesorteerd op volgnummer, want 5.24.03 hoort tussen .02 en .04 en niet
     * op de plek waar de seeder hem toevallig aanmaakte.
     */
    public function overheidsmaatregelBeoordelingen(): HasMany
    {
        return $this->hasMany(OverheidsmaatregelBeoordeling::class)
            ->join('overheidsmaatregelen', 'overheidsmaatregelen.id', '=', 'overheidsmaatregel_beoordelingen.overheidsmaatregel_id')
            ->orderBy('overheidsmaatregelen.volgnummer')
            ->select('overheidsmaatregel_beoordelingen.*');
    }

    /** Blok 5: het koppelvlak dat hier bewust openbleef tot beleid bestond. */
    public function beleidsdocumenten(): BelongsToMany
    {
        return $this->belongsToMany(Beleidsdocument::class, 'beleidsdocument_soa_regel');
    }

    /**
     * Van toepassing verklaard, maar zonder onderbouwend actief beleid — het
     * gap-signaal uit deelproducten/05 §6. Verwacht een relatie die al op
     * `status = actief` gefilterd is ingeladen.
     */
    public function mistBeleid(): bool
    {
        return $this->van_toepassing === true && $this->beleidsdocumenten->isEmpty();
    }

    /** Nog niet beoordeeld: `van_toepassing` is null, niet false. */
    public function isOnbeslist(): bool
    {
        return $this->van_toepassing === null;
    }

    /**
     * Het hoogste netto-restrisico over de behandelingen die aan deze control
     * hangen (plan 04c §1). Bewust de *max*: je bent zo sterk als het zwakst
     * behandelde risico onder deze control. `null` als geen enkele gekoppelde
     * behandeling een restrisico heeft ingevuld — dat is "onbepaald", niet 0.
     * Verwacht `risicobehandelingen` eager-geladen.
     */
    public function piekRestrisico(): ?int
    {
        $scores = $this->risicobehandelingen
            ->pluck('restrisico_score')
            ->filter(fn ($score) => $score !== null);

        return $scores->isEmpty() ? null : (int) $scores->max();
    }

    /**
     * Aantal *distinct* risico's dat via een behandeling aan deze control hangt
     * (plan 04c §1). Distinct op risico, niet op behandeling: één risico kan
     * meerdere behandelingen aan dezelfde control hangen, anders dubbeltel je.
     */
    public function aantalGekoppeldeRisicos(): int
    {
        return $this->risicobehandelingen->pluck('risico_id')->unique()->count();
    }

    // --- Classificatie (plan 04d) ------------------------------------------

    /**
     * De classificatie die geldt: de eigen vaststelling van de organisatie,
     * anders de meegeleverde uitgangsclassificatie op de maatregel.
     *
     * Alles-of-niets: `kenmerken_eigen` is `null` (volg het uitgangspunt) of een
     * complete set. Geen mengvorm per dimensie — dan zou je bij elke lezing per
     * dimensie moeten terugvallen, en is "wat heeft deze organisatie nu eigenlijk
     * vastgesteld" niet meer te beantwoorden.
     *
     * Let op bij filteren of rapporteren op kenmerken (nu buiten scope): dat moet
     * op déze methode werken en niet op de baseline-kolom, anders rapporteer je
     * het uitgangspunt in plaats van wat de organisatie heeft bepaald.
     *
     * @return array<string, list<string>>
     */
    public function kenmerken(): array
    {
        return $this->kenmerken_eigen ?? $this->maatregel?->kenmerken ?? [];
    }

    /** Heeft de organisatie hier zelf naar gekeken en het vastgelegd? */
    public function heeftEigenClassificatie(): bool
    {
        return $this->kenmerken_eigen !== null;
    }

    /**
     * Een eigen vaststelling die inhoudelijk van het uitgangspunt afwijkt.
     *
     * Genormaliseerd vergelijken: een bevestiging zonder wijziging is géén
     * afwijking, en de volgorde binnen een dimensie doet er niet toe — die hangt
     * van de invoervolgorde in het formulier af en zegt niets.
     */
    public function wijktAfVanUitgangspunt(): bool
    {
        if (! $this->heeftEigenClassificatie()) {
            return false;
        }

        return $this->normaliseer($this->kenmerken_eigen)
            !== $this->normaliseer($this->maatregel?->kenmerken ?? []);
    }

    /**
     * @param  array<string, list<string>>  $kenmerken
     * @return array<string, list<string>>
     */
    private function normaliseer(array $kenmerken): array
    {
        $genormaliseerd = [];

        foreach ($kenmerken as $dimensie => $waarden) {
            $waarden = array_values(array_unique((array) $waarden));
            sort($waarden);

            // Een lege dimensie is hetzelfde als een ontbrekende: in beide
            // gevallen heeft de organisatie er niets over gezegd.
            if ($waarden !== []) {
                $genormaliseerd[$dimensie] = $waarden;
            }
        }

        ksort($genormaliseerd);

        return $genormaliseerd;
    }

    public function auditBlok(): string
    {
        return 'risico-soa';
    }

    public function auditOmschrijving(): string
    {
        return 'A.'.($this->maatregel?->annex_a_referentie ?? '?').' '.($this->maatregel?->naam ?? '');
    }
}
