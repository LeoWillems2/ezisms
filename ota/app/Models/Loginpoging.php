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
     */
    protected $fillable = ['gebruiker_id', 'email_ingevoerd', 'tijdstip', 'succesvol', 'reden', 'ip_adres'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tijdstip' => 'datetime',
            'succesvol' => 'boolean',
        ];
    }

    public function gebruiker(): BelongsTo
    {
        return $this->belongsTo(Gebruiker::class, 'gebruiker_id');
    }
}
