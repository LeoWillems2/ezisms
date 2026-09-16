<?php

namespace App\Models;

use Database\Factories\LoginpogingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loginpoging extends Model
{
    /** @use HasFactory<LoginpogingFactory> */
    use HasFactory;

    protected $table = 'loginpogingen';

    /** @var list<string> */
    /**
     * `reden` onderscheidt een mislukt wachtwoord van een mislukte tweede
     * factor (implementatie/01d §7c). Waarden: wachtwoord, totp, herstelcode,
     * status. Bij rijen van vóór 03-08-2026 is hij leeg — invullen zou een
     * bewering doen die niet uit de data volgt.
     *
     * Sinds 01j ook `extern_onbekend` en `extern_geweigerd` (§5.3). `methode`
     * zegt langs welke weg er is ingelogd, en `mfa_bij_idp` legt bij een externe
     * login vast wat `ISMS_IDP_DWINGT_MFA` op dat moment was (§7.3): een
     * wijziging in `.env` komt niet in de audit trail.
     */
    protected $fillable = ['gebruiker_id', 'email_ingevoerd', 'tijdstip', 'succesvol', 'methode', 'mfa_bij_idp', 'reden', 'ip_adres'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tijdstip' => 'datetime',
            'succesvol' => 'boolean',
            'mfa_bij_idp' => 'boolean',
        ];
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gebruiker_id');
    }
}
