<?php

/*
 * Assistenttoegang via MCP (implementatie/14b §1.2).
 *
 * Standaard uit. Uit betekent: de routes worden niet geregistreerd — geen
 * /mcp, geen /oauth, geen discovery-document. Na het omzetten is een herstart
 * nodig, want de routes worden gecachet (14b §10.3).
 */
return [

    'aan' => (bool) env('ISMS_ASSISTENT', false),

    // De toegestane redirect-domeinen staan in config/mcp.php, want daar leest
    // het pakket ze (`ISMS_ASSISTENT_REDIRECTS`).

    // Map met oauth-private.key en oauth-public.key. Leeg = storage/, waar
    // `php artisan passport:keys` ze neerzet. De Docker-stack zet hier
    // storage/oauth op de bind mount (14b §10.3).
    'sleutelmap' => env('ISMS_OAUTH_SLEUTELS'),

    'toegangstoken_minuten' => 60,
    'verversingstoken_dagen' => 30,

    // Per gebruiker, per minuut. Een assistent die een KPI opbouwt, doet
    // tientallen aanroepen achter elkaar; dat is normaal gebruik.
    'aanroepen_per_minuut' => (int) env('ISMS_ASSISTENT_LIMIET', 120),

    // Het registratie-endpoint is anoniem (14b §3.2). Per IP, per uur.
    'registraties_per_uur' => 10,

    // Grootste bestand dat als resource wordt uitgeleverd (14b §6.2).
    'max_bestand_mb' => 10,

    // Bewaartermijn van de aanroeplog (14b §7.3).
    'log_bewaren_dagen' => 365,

    // Argumenten in de aanroeplog worden ingekort tot zoveel bytes (14b §7.1).
    'log_argumenten_bytes' => 2048,
];
