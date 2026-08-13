<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Support\Recordscope;
use Database\Factories\BewijsstukFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Bewijsstuk extends Model
{
    /** @use HasFactory<BewijsstukFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'bewijsstukken';

    /** De disk waarop bewijsstukken staan — nooit 'public', zie §7. */
    public const DISK = 'bewijs';

    /** @var list<string> */
    protected $fillable = [
        'naam', 'omschrijving', 'bestandsnaam', 'bestandstype', 'bestandsgrootte',
        'opslaglocatie_referentie', 'bestandshash', 'geupload_door', 'geupload_op',
        'status', 'bewaren_tot',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'geupload_op' => 'datetime',
        'bewaren_tot' => 'date',
        'bestandsgrootte' => 'integer',
    ];

    /** Record-scoping: zonder volledige inzage zie je alleen je eigen uploads. */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->unless(
            Recordscope::magAllesZien('bewijsrepository-audit-trail'),
            fn (Builder $q) => $q->where('geupload_door', auth()->id())
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'geupload_door');
    }

    public function koppelingen(): HasMany
    {
        return $this->hasMany(BewijsKoppeling::class);
    }

    /** Een bewijsstuk zonder koppeling is een bestand zonder bewijswaarde. */
    public function isOngekoppeld(): bool
    {
        return $this->koppelingen()->doesntExist();
    }

    /**
     * Controleert of het bestand op schijf nog overeenkomt met de hash die bij
     * upload is vastgelegd. Dit is wat "onveranderlijke opslag" aantoonbaar
     * maakt (§3a).
     */
    public function integriteitIsIntact(): bool
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($this->opslaglocatie_referentie)) {
            return false;
        }

        return hash('sha256', $disk->get($this->opslaglocatie_referentie)) === $this->bestandshash;
    }

    /**
     * Bestandstypen waarvan pandoc een HTML-preview kan maken (blok 5/6):
     * extensie => pandoc-bronformaat. De waarde gaat rechtstreeks naar
     * `pandoc --from=...`, dus dit is bewust een vaste lijst en geen
     * gebruikersinvoer.
     *
     * @var array<string, string>
     */
    public const PREVIEW_FORMATEN = [
        'rtf' => 'rtf',
        'docx' => 'docx',
        'odt' => 'odt',
        // Markdown is geen upload-formaat, maar `isms:demo-vul` schrijft zijn
        // bewijsstukken zo weg. Zonder deze regel heeft de demo geen enkele
        // previewbare bijlage, en dat is precies wat een demo moet laten zien.
        'md' => 'markdown',
    ];

    /**
     * Bestandstypen die de browser zelf toont: extensie => content-type. Deze
     * gaan niet door pandoc heen maar worden inline geserveerd. PDF staat hier
     * en niet hierboven omdat een PDF-naar-HTML-conversie de opmaak weggooit —
     * en juist bij beleid is de opmaak (paginanummers, kopjes, handtekening)
     * onderdeel van het document.
     *
     * @var array<string, string>
     */
    public const INLINE_FORMATEN = [
        'pdf' => 'application/pdf',
    ];

    /**
     * Het pandoc-bronformaat voor een preview, of `null` als dit bestandstype
     * niet via pandoc gaat. Op de extensie: die bepaalt ook wat pandoc moet
     * lezen, en de upload-validatie dwingt de extensie af.
     */
    public function previewFormaat(): ?string
    {
        return self::PREVIEW_FORMATEN[$this->extensie()] ?? null;
    }

    /** Het content-type voor een inline preview, of `null`. */
    public function inlineType(): ?string
    {
        return self::INLINE_FORMATEN[$this->extensie()] ?? null;
    }

    public function isPreviewbaar(): bool
    {
        return $this->previewFormaat() !== null || $this->inlineType() !== null;
    }

    private function extensie(): string
    {
        return strtolower(pathinfo((string) $this->bestandsnaam, PATHINFO_EXTENSION));
    }

    public function leesbareGrootte(): string
    {
        $eenheden = ['B', 'KB', 'MB', 'GB'];
        $grootte = (float) $this->bestandsgrootte;
        $i = 0;

        while ($grootte >= 1024 && $i < count($eenheden) - 1) {
            $grootte /= 1024;
            $i++;
        }

        return round($grootte, $i === 0 ? 0 : 1).' '.$eenheden[$i];
    }

    public function auditBlok(): string
    {
        return 'bewijsrepository-audit-trail';
    }

    /**
     * Niets uitgesloten, en `bestandshash` juist bewust niet: die hash in de
     * audit trail maakt achteraf aantoonbaar wélk bestand er op dat moment is
     * geüpload, ook als het bewijsstuk daarna is vervangen.
     *
     * @return list<string>
     */
    public function auditUitgesloten(): array
    {
        return [];
    }
}
