<?php

/*
 * Inloggen via een externe identiteitsprovider (implementatie/01j §1).
 *
 * Leeg = uit. Zonder issuer, client-id en secret verandert er nergens iets
 * zichtbaars: geen knop op het loginscherm, geen keuze bij het uitnodigen.
 */
return [

    'issuer' => env('OIDC_ISSUER'),
    'client_id' => env('OIDC_CLIENT_ID'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),

    // Wat er op de knop staat: "Inloggen met {weergavenaam}".
    'weergavenaam' => env('OIDC_WEERGAVENAAM', 'uw organisatieaccount'),

    /*
     * Welke claim de stabiele identiteit draagt. `sub` is de OIDC-standaard.
     * Voor Entra ID is `oid` beter: `sub` verschilt daar per app-registratie,
     * zodat een opnieuw aangemaakte registratie alle koppelingen zou breken.
     */
    'subject_claim' => env('OIDC_SUBJECT_CLAIM', 'sub'),

    /*
     * Vergeleken met de claim `hd`. Verplicht voor Google: een persoonlijk
     * Gmail-account heeft geen `hd`, en zonder deze controle is dat te koppelen.
     */
    'domein' => env('OIDC_DOMEIN'),

    // Alleen voor de waarschuwing in isms:extern-inloggen-controleren (Y-m-d).
    'secret_verloopt_op' => env('OIDC_CLIENT_SECRET_VERLOOPT_OP'),

    // Hoe recent een aanmelding bij de IdP mag zijn om het wachtwoord te
    // vervangen op het tweefactorscherm (§7.5).
    'herbevestiging_minuten' => 15,

    'klokspeling_seconden' => 60,

    // Hoe lang een doorverwijzing naar de IdP geldig blijft (§3.3).
    'aanvraag_minuten' => 10,

];
