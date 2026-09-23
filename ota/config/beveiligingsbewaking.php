<?php

/*
 * Bewaking van misbruik van inloggegevens (implementatie/01l, BIO2 5.17.01).
 *
 * De vijf use cases staan in `App\Models\Beveiligingssignaal::SOORTEN`; hier
 * staan hun drempels. Die staan bewust niet in `.env`, om dezelfde reden als in
 * `config/hartslag.php`: een beheerder moet ze kunnen lezen, maar hoort ze niet
 * per installatie te verschuiven zonder dat er een commit van is (01l §1,
 * beslissing 3).
 */
return [

    // Uit = geen detectie en geen signalen. Voor de demo, waar iedereen met
    // dezelfde wegwerpaccounts van overal inlogt.
    'aan' => (bool) env('ISMS_BEWAKING_AAN', true),

    /*
     * Waar de signalen heen gaan (§6.1). Host ingesteld: syslog, en alleen
     * syslog. Leeg: mail aan de actieve CISO's. Geen mailkanaal: alleen de
     * audit trail en het applicatielog.
     */
    'syslog' => [
        'host' => env('ISMS_SYSLOG_HOST'),
        'poort' => (int) env('ISMS_SYSLOG_POORT', 514),
        // udp of tcp. Geen TLS (§15).
        'protocol' => strtolower((string) env('ISMS_SYSLOG_PROTOCOL', 'udp')),
        // Hoe lang TCP mag wachten op een verbinding. Kort: het versturen
        // gebeurt na het antwoord aan de browser, maar wel in een php-fpm-worker.
        'wachttijd_seconden' => 3,
    ],

    // A: pieken in mislukte pogingen, over alle accounts samen. Twintig en niet
    // vijf: vijf is al het blokkeerniveau van één account, en dat meldt B.
    'piek' => ['aantal' => 20, 'venster_minuten' => 10, 'onderdrukken_minuten' => 60],

    // B: onderdrukt over het blokkeervenster zelf.
    'blokkade' => ['onderdrukken_minuten' => 15],

    // C: ruimte voor een typefout en een klok die een halve minuut afwijkt.
    'tweede_factor' => ['aantal' => 3, 'venster_minuten' => 15, 'onderdrukken_minuten' => 60],

    // D: een netwerk is een IPv4-/24 of IPv6-/48.
    'nieuw_netwerk' => ['terugkijken_dagen' => 90, 'prefix_v4' => 24, 'prefix_v6' => 48, 'onderdrukken_uren' => 24],

    // E
    'succes_na' => ['aantal' => 3, 'venster_minuten' => 15, 'onderdrukken_minuten' => 60],

];
