<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Waar `isms:exporteer` naartoe schrijft vanuit het beheerscherm
    |--------------------------------------------------------------------------
    |
    | Eén vast pad, geen keuzeveld in het scherm en geen env-sleutel. Dat is
    | opzet: dit is de uitgang van de installatie, geen instelling van een
    | gebruiker. Zou de beheerder het doel zelf mogen typen, dan is het scherm
    | een schrijfprimitief naar elk pad waar www-data bij kan.
    |
    | In de Docker-route is dit pad een bind mount naar `data/isms_export` op de
    | host (implementatie/01e §3.1, 00l §15); op bare metal is het een gewone map
    | in /var/tmp die de applicatie zelf aanmaakt.
    |
    | Het commando `isms:exporteer` heeft zijn eigen standaard
    | (`storage/app/exports`) en die blijft ongemoeid: wie het van de
    | opdrachtregel draait, kiest zelf met `--doel`.
    |
    | Deze waarde staat hier en niet als constante in het component, zodat de
    | testsuite hem naar een tijdelijke map kan wijzen. Anders schrijft elke
    | testrun een volledige ISMS-export naar /var/tmp van wie hem draait.
    |
    */

    'map' => '/var/tmp/isms_export',

];
