<?php

/*
 * Tweefactorauthenticatie (implementatie/01d §3).
 *
 * `afdwingen` staat in de demo-omgeving op `false`. Anders wordt de eerste
 * handeling van iedere bezoeker van het demo-ISMS het koppelen van een
 * authenticator-app aan een wegwerpaccount, en dat is niet wat de demo toont.
 */
return [

    'afdwingen' => env('ISMS_2FA_AFDWINGEN', true),

    /*
     * Respijt in dagen, per gebruiker geteld vanaf de eerste keer dat hij
     * zonder 2FA langskomt — niet vanaf een vaste datum. Een globale datum zou
     * iemand die volgende maand wordt uitgenodigd een deadline in het verleden
     * geven.
     */
    'respijt_dagen' => (int) env('ISMS_2FA_RESPIJT_DAGEN', 14),

    /*
     * De identiteitsprovider dwingt de tweede factor af (implementatie/01j §7).
     * Stelt accounts die via de IdP inloggen vrij van de TOTP-plicht hierboven;
     * wachtwoordaccounts op dezelfde installatie houden die plicht.
     *
     * Een verklaring van de organisatie over haar eigen IdP, geen controle: het
     * ISMS kan niet zien of de IdP dat werkelijk doet. Elke externe login legt
     * vast wat deze waarde toen was (`loginpogingen.mfa_bij_idp`), omdat een
     * wijziging in `.env` niet in de audit trail komt.
     */
    'idp_dwingt_af' => (bool) env('ISMS_IDP_DWINGT_MFA', false),

    /*
     * Eigen limiet op de challenge, losgekoppeld van de accountblokkade
     * (§1 beslissing 4): brute-force op zes cijfers is met vijf pogingen per
     * kwartier kansloos, terwijl blokkeren op typefouten en klokdrift alleen
     * zelf-DoS en CISO-werk oplevert.
     */
    'max_pogingen' => 5,
    'venster_minuten' => 15,

];
