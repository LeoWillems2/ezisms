<?php

namespace App\Support\Oidc;

/**
 * Staat de koppeling met een externe identiteitsprovider aan, en klopt ze
 * (implementatie/01j §1.1)?
 *
 * Elk scherm en elke route uit 01j vraagt eerst `isIngesteld()`. Een installatie
 * zonder IdP — de demo, saasdemo, elke bestaande installatie — ziet dan niets
 * nieuws.
 */
final class Configuratie
{
    private const GOOGLE = 'https://accounts.google.com';

    public static function isIngesteld(): bool
    {
        return filled(config('extern_inloggen.issuer'))
            && filled(config('extern_inloggen.client_id'))
            && filled(config('extern_inloggen.client_secret'));
    }

    /** De issuer zonder afsluitende slash, zoals hij in tokens en in de database staat. */
    public static function issuer(): string
    {
        return rtrim((string) config('extern_inloggen.issuer'), '/');
    }

    public static function isGoogle(): bool
    {
        return self::issuer() === self::GOOGLE;
    }

    public static function weergavenaam(): string
    {
        return (string) config('extern_inloggen.weergavenaam');
    }

    /**
     * Wat er aan de ingestelde koppeling niet deugt, in mensentaal. Leeg als
     * alles klopt of als er niets is ingesteld — dat laatste is geen fout maar
     * de uitgeschakelde toestand.
     *
     * @return list<string>
     */
    public static function fouten(): array
    {
        if (! self::isIngesteld()) {
            return [];
        }

        $fouten = [];
        $issuer = self::issuer();

        if (! str_starts_with($issuer, 'https://')) {
            $fouten[] = 'De issuer moet een https-adres zijn.';
        }

        // Met een van deze drie kan elk Microsoft-account inloggen dat de
        // app-registratie toelaat. Het tenant hoort in de issuer, want dan is de
        // exacte vergelijking in de tokencontrole meteen de tenantcontrole.
        if (preg_match('#login\.microsoftonline\.com/(common|organizations|consumers)(/|$)#i', $issuer)) {
            $fouten[] = 'Gebruik het tenant-specifieke adres van Entra ID; met common, organizations of consumers kan elk Microsoft-account inloggen.';
        }

        if (self::isGoogle() && blank(config('extern_inloggen.domein'))) {
            $fouten[] = 'Stel OIDC_DOMEIN in; zonder die controle kan ook een persoonlijk Google-account worden gekoppeld.';
        }

        return $fouten;
    }

    /** Ingesteld én zonder fouten: alleen dan gaat er iemand naar de IdP. */
    public static function isBruikbaar(): bool
    {
        return self::isIngesteld() && self::fouten() === [];
    }

    /**
     * Is `$iss` uit een token dezelfde issuer als de ingestelde?
     *
     * Exact, met één uitzondering: Google zet `iss` soms zonder schema. Die
     * ruimte geldt alleen voor Google — elders is een afwijkende issuer een
     * andere uitgever.
     */
    public static function issuerKlopt(mixed $iss): bool
    {
        if (! is_string($iss)) {
            return false;
        }

        $iss = rtrim($iss, '/');

        if ($iss === self::issuer()) {
            return true;
        }

        return self::isGoogle() && $iss === 'accounts.google.com';
    }
}
