<?php

use Laravel\Fortify\Features;

/*
 * Fortify wordt hier uitsluitend gebruikt voor tweefactorauthenticatie
 * (implementatie/01d §2). Alles wat het pakket verder kan — registratie,
 * wachtwoordherstel, e-mailverificatie, de eigen views en routes — staat uit:
 * die functies bestaan in dit ISMS al, en een tweede route naar hetzelfde
 * scherm is een tweede plek waar de statuscontrole vergeten kan worden.
 *
 * Wat we hiermee níét zelf schrijven: versleutelde opslag van het secret en de
 * herstelcodes, de otpauth-URI, QR-generatie, drift-tolerantie bij verificatie,
 * het confirm-on-enable-patroon en het eenmalig verbruiken van een herstelcode.
 */
return [

    'guard' => 'web',

    /*
     * Geen Fortify-views: het instelscherm, de challenge en de login zijn van
     * onszelf (§6 en §7). `Fortify::ignoreRoutes()` in AppServiceProvider zorgt
     * dat het pakket ook geen routes registreert.
     */
    'views' => false,

    /*
     * `confirm => true` is niet optioneel. Zonder die vlag geldt een secret als
     * actief zodra het gegenereerd is, ook als de gebruiker nooit heeft
     * aangetoond dat zijn app het aankan — dan sluit je iemand buiten op een
     * QR-code die hij verkeerd gescand heeft.
     *
     * `confirmPassword` staat uit omdat Fortify's wachtwoordbevestiging via de
     * guard loopt en deze installatie een `wachtwoord`-kolom heeft in plaats van
     * `password`. Het instelscherm vraagt zelf om het wachtwoord; zo blijft
     * `getAuthPassword()` de enige plek waar die afwijking leeft.
     */
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]),
    ],

];
