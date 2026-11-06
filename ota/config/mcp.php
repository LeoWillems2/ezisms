<?php

/*
 * laravel/mcp (implementatie/14b). Alleen wat het ISMS gebruikt; de
 * clientkant van het pakket (verbinden met andere MCP-servers) staat hier
 * bewust niet in.
 */
return [

    // Waar een dynamisch geregistreerde client naar mag terugsturen, als
    // schema + host (`ISMS_ASSISTENT_REDIRECTS`, kommagescheiden). Het
    // pakket laat loopback alleen toe als `http://localhost` in de lijst staat;
    // dat staat er altijd, voor Claude Code en andere lokale clients. Nooit
    // '*': dan kan elke site zich als client registreren en een gebruiker
    // via een nagemaakt toestemmingsverzoek laten koppelen (14b §3.2).
    'redirect_domains' => [
        'http://localhost',
        ...array_values(array_filter(array_map('trim', explode(',', (string) env(
            'ISMS_ASSISTENT_REDIRECTS',
            'https://claude.ai',
        ))))),
    ],

    // Geen eigen URI-schema's (cursor://, vscode://): niet nodig voor de
    // clients die we ondersteunen, en elk schema is een app op de machine
    // van de gebruiker die zich als client kan voordoen.
    'custom_schemes' => [],

    // null = url('/'), afgeleid van APP_URL.
    'authorization_server' => null,

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    'clients' => [],
];
