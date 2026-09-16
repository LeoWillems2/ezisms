<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\ExterneIdentiteitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De koppeling tussen een account en een identiteit bij de externe
 * identiteitsprovider (implementatie/01j §2).
 *
 * De identiteit is (`issuer`, `subject`), nooit het e-mailadres: bij Google kan
 * iedereen een account met een willekeurig adres aanmaken, en bij Entra is
 * `email` niet gegarandeerd geverifieerd. `idp_gebruikersnaam` is weergave.
 *
 * Auditeerbaar, want koppelen en ontkoppelen bepalen wie via welke identiteit
 * toegang had (A.5.16).
 */
class ExterneIdentiteit extends Model
{
    /** @use HasFactory<ExterneIdentiteitFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'externe_identiteiten';

    /** @var list<string> */
    protected $fillable = ['gebruiker_id', 'issuer', 'subject', 'idp_gebruikersnaam', 'gekoppeld_op', 'laatst_gebruikt_op'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gekoppeld_op' => 'datetime',
            'laatst_gebruikt_op' => 'datetime',
        ];
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gebruiker_id');
    }

    public function auditBlok(): string
    {
        return 'identity-access';
    }

    public function auditOmschrijving(): string
    {
        return $this->idp_gebruikersnaam ?? $this->subject;
    }

    /**
     * Het gebruik bijwerken zonder auditregel: dit is de spiegel van
     * `gebruikers.laatst_ingelogd_op`, en de loginpoging legt het feit al vast.
     */
    public function markeerGebruikt(): void
    {
        $this->forceFill(['laatst_gebruikt_op' => now()])->saveQuietly();
    }
}
