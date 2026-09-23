<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use App\Models\Concerns\Waardenbewaking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een waarneming van mogelijk misbruik van inloggegevens
 * (implementatie/01l, BIO2 5.17.01).
 *
 * `Auditeerbaar`, zodat elk signaal als "aangemaakt" in de audit trail staat —
 * dat is het bewijs dat er gemonitord is, los van het kanaal waarlangs het
 * signaal verder ging. Toegeschreven aan het systeem en niet aan de ingelogde
 * gebruiker (§7): use case D en E vuren in het verzoek waarin iemand net is
 * ingelogd, en bij een overgenomen account is dat de aanvaller.
 */
class Beveiligingssignaal extends Model
{
    use Auditeerbaar, Waardenbewaking;

    protected $table = 'beveiligingssignalen';

    /**
     * De use cases, met hun definitie. Deze lijst ís de definitie die de norm
     * vraagt ("Use Cases worden gedefinieerd"); de kennisbank neemt hem over.
     *
     * @var array<string, string>
     */
    public const SOORTEN = [
        'piek_mislukt' => 'Piek in mislukte inlogpogingen over de hele installatie',
        'account_geblokkeerd' => 'Account door het systeem geblokkeerd na te veel mislukte pogingen',
        'tweede_factor_mislukt' => 'Wachtwoord goed, tweede factor herhaald fout',
        'nieuw_netwerk' => 'Geslaagde login vanaf een netwerk dat nieuw is voor dit account',
        'succes_na_mislukkingen' => 'Geslaagde login na meerdere mislukte pogingen',
    ];

    /**
     * Syslogernst per soort (RFC 5424). C is het sterkste signaal: het
     * wachtwoord was goed, dus waarschijnlijk gelekt (01d §7c). D is het
     * lawaaiigst: thuiswerkers wisselen van adres.
     *
     * @var array<string, string>
     */
    public const ERNST = [
        'piek_mislukt' => 'warning',
        'account_geblokkeerd' => 'warning',
        'tweede_factor_mislukt' => 'alert',
        'nieuw_netwerk' => 'notice',
        'succes_na_mislukkingen' => 'warning',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tijdstip', 'soort', 'gebruiker_id', 'ip_adres', 'sleutel', 'details', 'kanaal', 'afleverfout',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tijdstip' => 'datetime',
            'details' => 'array',
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

    /**
     * `afleverfout` wordt ná het aanmaken bijgewerkt. Een tweede trailregel
     * voor een geweigerde SMTP-verbinding voegt niets toe aan het bewijs; de
     * fout staat in de rij en in het log (§3).
     */
    public function auditUitgesloten(): array
    {
        return ['afleverfout'];
    }

    public function auditSysteemactor(): ?string
    {
        return 'Systeem (beveiligingsbewaking)';
    }

    public function auditOmschrijving(): string
    {
        $wie = $this->gebruiker?->email ?? $this->details['account'] ?? null;

        return self::SOORTEN[$this->soort]
            .($wie !== null ? ' — '.$wie : '')
            .($this->ip_adres !== null ? ' ('.$this->ip_adres.')' : '');
    }

    public function ernst(): string
    {
        return self::ERNST[$this->soort] ?? 'warning';
    }
}
