<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Koppelbaar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generieke koppeling van een bewijsstuk aan een entiteit in een willekeurig
 * blok. `entiteit_type` bevat een morph-alias ('soa_regel'), geen classnaam —
 * zie implementatie/06 §4 en de morph map in AppServiceProvider.
 */
class BewijsKoppeling extends Model
{
    use Auditeerbaar;

    protected $table = 'bewijs_koppelingen';

    /** @var list<string> */
    protected $fillable = ['bewijsstuk_id', 'blok_naam', 'entiteit_type', 'entiteit_id'];

    public function bewijsstuk(): BelongsTo
    {
        return $this->belongsTo(Bewijsstuk::class);
    }

    public function entiteit(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entiteit_type', 'entiteit_id');
    }

    /**
     * Leesbare weergave voor in een overzicht. Zonder dit toont de kolom
     * "asset #3" — voldoende voor een ontwikkelaar, waardeloos voor een
     * auditor. Valt terug op type + id als de entiteit intussen weg is.
     */
    public function omschrijving(): string
    {
        $label = Koppelbaar::TYPES[$this->entiteit_type]['label']
            ?? ucfirst(str_replace('_', ' ', $this->entiteit_type));

        $entiteit = $this->entiteit;

        return $entiteit
            ? $label.': '.$entiteit->auditOmschrijving()
            : $label.' #'.$this->entiteit_id.' (verwijderd)';
    }

    /**
     * Bewust het blok van de gekoppelde entiteit, niet blok 6. Een auditor die
     * filtert op "risico-soa" wil zien dat daar bewijs bij is gehangen; onder
     * "bewijsrepository" zou die gebeurtenis juist onvindbaar zijn. Het volgt
     * bovendien de autorisatieregel: koppelen vereist muteerrecht op dít blok.
     */
    public function auditBlok(): string
    {
        return $this->blok_naam;
    }

    public function auditOmschrijving(): string
    {
        return ($this->bewijsstuk?->naam ?? 'Bewijsstuk').' aan '.$this->omschrijving();
    }
}
