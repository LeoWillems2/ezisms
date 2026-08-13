<?php

namespace App\Listeners;

use App\Models\Gebruiker;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

/**
 * Zet de 2FA-gebeurtenissen van Fortify om in leesbare audit-trailregels
 * (implementatie/01d §5).
 *
 * Waarom niet via de `Auditeerbaar`-trait: die zou "kolom two_factor_secret is
 * gewijzigd van null naar een onleesbare blob" opleveren — en die kolommen zijn
 * juist uitgesloten van de trail (01d §4). Wat een auditor wil lezen is *"deze
 * gebruiker heeft op 3 augustus 2FA bevestigd"*, en dat is precies wat een
 * gebeurtenis zegt.
 *
 * **Afwijking van 01d §5.** Dat plan wilde eigen acties (`tweefactor_bevestigd`
 * en zo). De `actie`-kolom is een enum met vier waarden, en dit is er geen
 * vijfde waard — dezelfde afweging als bij koppelingen (`06b` §5): het
 * onderscheid zit in de veldnaam en de waarde, niet in een nieuwe actie. Zou
 * elke feature er één toevoegen, dan is filteren op actie niets meer waard.
 */
class RegistreerTweefactorGebeurtenis
{
    /** @var array<class-string, string> */
    private const GEBEURTENISSEN = [
        TwoFactorAuthenticationEnabled::class => 'ingesteld, nog niet bevestigd',
        TwoFactorAuthenticationConfirmed::class => 'bevestigd en actief',
        TwoFactorAuthenticationDisabled::class => 'uitgezet',
        RecoveryCodesGenerated::class => 'herstelcodes vernieuwd',
    ];

    public function handle(object $gebeurtenis): void
    {
        $gebruiker = $gebeurtenis->user ?? null;
        $wat = self::GEBEURTENISSEN[$gebeurtenis::class] ?? null;

        if (! $gebruiker instanceof Gebruiker || $wat === null) {
            return;
        }

        $gebruiker->schrijfAuditregel('gewijzigd', oud: null, nieuw: ['tweefactor' => $wat]);
    }

    /** @return list<class-string> */
    public static function gebeurtenissen(): array
    {
        return array_keys(self::GEBEURTENISSEN);
    }
}
