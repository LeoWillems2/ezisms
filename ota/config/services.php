<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Pad naar de pandoc-binary voor de RTF->HTML-preview (blok 5/6). Vereist
    // pandoc >= 3.1.7 (dat is de eerste versie met een RTF-lezer); een oudere
    // distropackage kent `-f rtf` niet en de preview valt dan terug op "niet
    // beschikbaar". Ontbreekt pandoc, dan blijft alleen downloaden over.
    'pandoc' => [
        'bin' => env('PANDOC_BIN', 'pandoc'),
    ],

];
