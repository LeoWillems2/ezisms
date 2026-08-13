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
     * Eigen limiet op de challenge, losgekoppeld van de accountblokkade
     * (§1 beslissing 4): brute-force op zes cijfers is met vijf pogingen per
     * kwartier kansloos, terwijl blokkeren op typefouten en klokdrift alleen
     * zelf-DoS en CISO-werk oplevert.
     */
    'max_pogingen' => 5,
    'venster_minuten' => 15,

];
